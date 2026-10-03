<?php

namespace App\Http\Controllers;

use App\Models\Restaurant;
use App\Models\Review;
use App\Models\UserActivity;
use App\Services\AuditLogger;
use App\Support\Districts;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public const MAX_PENDING = 10;      // moderation queue cap; overflow is waitlisted
    public const REVIEWS_PER_WINDOW = 5; // then a 24h cooldown
    public const MAX_EDITS = 3;

    public function index(Request $request)
    {
        $user = $request->attributes->get('auth_user');
        $reviews = Review::where('user_id', $user->user_id)->orderByDesc('review_id')->get();

        $integrity = [];
        foreach ($reviews as $review) {
            $integrity[$review->review_id] = $review->verifyIntegrity();
            if (! $integrity[$review->review_id]) {
                AuditLogger::log('review.integrity_failed', $review);
            }
        }

        return view('reviews.index', [
            'reviews' => $reviews,
            'integrity' => $integrity,
            'activity' => UserActivity::firstOrCreate(['user_id' => $user->user_id], ['remaining_reviews' => self::REVIEWS_PER_WINDOW]),
        ]);
    }

    public function create(Request $request)
    {
        $user = $request->attributes->get('auth_user');

        return view('reviews.create', [
            'restaurants' => Restaurant::orderBy('name')->get(['id', 'name', 'district']),
            'selectedRestaurant' => $request->query('restaurant_id'),
            'activity' => UserActivity::firstOrCreate(['user_id' => $user->user_id], ['remaining_reviews' => self::REVIEWS_PER_WINDOW]),
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->attributes->get('auth_user');

        $data = $request->validate([
            'restaurant_id' => 'required|exists:restaurants,id',
            'review_text' => 'required|string|min:10|max:1000',
            'review_score' => 'required|integer|min:1|max:10',
            'location' => 'nullable|string|max:200',
        ]);

        $activity = UserActivity::firstOrCreate(['user_id' => $user->user_id], ['remaining_reviews' => self::REVIEWS_PER_WINDOW]);

        // Cooldown elapsed → reset the allowance.
        if ($activity->cooldown_end_time && now()->greaterThanOrEqualTo($activity->cooldown_end_time)) {
            $activity->update(['cooldown_end_time' => null, 'remaining_reviews' => self::REVIEWS_PER_WINDOW]);
        }
        if (! $activity->canPostReview()) {
            return back()->withInput()->withErrors(['general' => 'You have used your ' . self::REVIEWS_PER_WINDOW . ' reviews. You can post again ' . $activity->cooldown_end_time->diffForHumans() . '.']);
        }

        $restaurant = Restaurant::findOrFail($data['restaurant_id']);
        $queueStatus = Review::where('status', 'pending')->count() >= self::MAX_PENDING ? 'wait' : 'pending';

        $review = Review::create([
            'user_id' => $user->user_id,
            'restaurant_id' => $restaurant->id,
            'restaurant_name' => $restaurant->name,
            'review_text' => $data['review_text'],
            'review_score' => (int) $data['review_score'],
            'location' => $data['location'] ?: $restaurant->address,
            'city' => $restaurant->district,
            'status' => $queueStatus,
            'edit_count' => 0,
            'max_edit_limit' => self::MAX_EDITS,
        ]);
        $review->generateMac();
        $review->generateSignature();
        $review->save();

        $activity->decrement('remaining_reviews');
        $activity->update(['last_post_time' => now()]);
        if ($activity->fresh()->remaining_reviews <= 0) {
            $activity->update(['cooldown_end_time' => now()->addHours(24), 'remaining_reviews' => 0]);
        }

        AuditLogger::log('review.submitted', $review, ['status' => $queueStatus]);

        return redirect('/reviews')->with('status', $queueStatus === 'wait'
            ? 'The moderation queue is full (' . self::MAX_PENDING . ' pending). Your review is waitlisted and will move up automatically.'
            : 'Review submitted and waiting for moderator approval.');
    }

    public function edit(Request $request, $id)
    {
        $review = $this->ownedReview($request, (int) $id);

        if ($guard = $this->editGuard($review)) {
            return redirect('/reviews')->withErrors(['general' => $guard]);
        }

        return view('reviews.edit', ['review' => $review]);
    }

    public function update(Request $request, $id)
    {
        $review = $this->ownedReview($request, (int) $id);
        if ($guard = $this->editGuard($review)) {
            return redirect('/reviews')->withErrors(['general' => $guard]);
        }

        $data = $request->validate([
            'review_text' => 'required|string|min:10|max:1000',
            'review_score' => 'required|integer|min:1|max:10',
            'location' => 'nullable|string|max:200',
        ]);

        $queueStatus = Review::where('status', 'pending')->where('review_id', '!=', $review->review_id)->count() >= self::MAX_PENDING ? 'wait' : 'pending';

        $review->fill([
            'review_text' => $data['review_text'],
            'review_score' => (int) $data['review_score'],
            'location' => $data['location'],
            'edit_count' => $review->edit_count + 1,
            'status' => $queueStatus,     // edited reviews go back through moderation
        ]);
        $review->updated_at = now();
        $review->generateMac();
        $review->generateSignature();
        $review->save();

        \App\Models\PostedReview::where('review_id', $review->review_id)->delete();
        if ($review->restaurant) {
            \App\Services\TrustScoreService::recalculate($review->restaurant);
        }
        AuditLogger::log('review.edited', $review, ['edit_count' => $review->edit_count]);

        return redirect('/reviews')->with('status', 'Review updated and sent back for moderation (' . ($review->max_edit_limit - $review->edit_count) . ' edit(s) left).');
    }

    public function show(Request $request, $id)
    {
        $user = $request->attributes->get('auth_user');
        $review = Review::findOrFail($id);
        abort_unless($review->status === 'approved' || $review->user_id === $user->user_id || $user->isAdmin(), 404);

        return view('reviews.show', ['review' => $review, 'integrity' => $review->verifyIntegrity()]);
    }

    public function allApproved(Request $request)
    {
        $reviews = Review::with('restaurant')->where('status', 'approved')->orderByDesc('review_id')->paginate(15);
        $integrity = [];
        foreach ($reviews as $review) {
            $integrity[$review->review_id] = $review->verifyIntegrity();
        }

        return view('reviews.all', compact('reviews', 'integrity'));
    }

    private function ownedReview(Request $request, int $id): Review
    {
        $user = $request->attributes->get('auth_user');

        return Review::where('review_id', $id)->where('user_id', $user->user_id)->firstOrFail();
    }

    private function editGuard(Review $review): ?string
    {
        if (! $review->verifyIntegrity()) {
            return 'This review failed its integrity check and cannot be edited.';
        }
        if ($review->edit_count >= ($review->max_edit_limit ?: self::MAX_EDITS)) {
            return 'You have reached the maximum of ' . self::MAX_EDITS . ' edits for this review.';
        }
        if ($review->status === 'rejected') {
            return 'Rejected reviews cannot be edited.';
        }

        return null;
    }
}
