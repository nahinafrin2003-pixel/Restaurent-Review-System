<?php

namespace App\Http\Controllers;

use App\Models\Review;
use App\Models\UserActivity;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    private $districts = ['Dhaka', 'Chittagong', 'Khulna', 'Rajshahi', 'Sylhet', 'Barisal', 'Rangpur', 'Mymensingh'];

    public function index(Request $request)
    {
        $user = $request->attributes->get('auth_user');
        $reviews = Review::where('user_id', $user->user_id)
            ->whereIn('status', ['pending', 'approved'])
            ->get();
        // Verify integrity of each review before displaying
        foreach ($reviews as $review) {
            if (!$review->verifyIntegrity()) {
                \Log::warning('Review integrity check failed for review ' . $review->review_id);
            }
        }

        return view('reviews.index', compact('reviews'));
    }

    public function dashboard(Request $request)
    {
        $user = $request->attributes->get('auth_user');
        // Admins see all pending, wait, and approved reviews; regular users see only their approved reviews and activity
        if ($user->isAdmin()) {
            $pendingReviews = Review::where('status', 'pending')->latest()->limit(10)->get();
            $waitReviews = Review::where('status', 'wait')->latest()->limit(10)->get();
            $approvedReviews = Review::where('status', 'approved')->latest()->limit(10)->get();

            return view('dashboard', [
                'user' => $user,
                'pendingReviews' => $pendingReviews,
                'waitReviews' => $waitReviews,
                'approvedReviews' => $approvedReviews,
                'districts' => $this->districts,
            ]);
        } else {
            $userActivity = UserActivity::where('user_id', $user->user_id)->first();
            $approvedReviews = Review::where('user_id', $user->user_id)
                ->where('status', 'approved')
                ->latest()
                ->limit(10) // Only show the latest 10 approved reviews for regular users
                ->get();

            return view('dashboard', [
                'user' => $user,
                'approvedReviews' => $approvedReviews,
                'userActivity' => $userActivity,
                'districts' => $this->districts,
            ]);
        }
    }

    public function create(Request $request)
    {
        return view('reviews.create', ['districts' => $this->districts]);
    }

    public function store(Request $request)
    {
        $user = $request->attributes->get('auth_user');

        $request->validate([
            'restaurant_name' => 'required|string|max:200',
            'review_text' => 'required|string|max:1000',
            'review_score' => 'required|integer|min:1|max:10',
            'location' => 'required|string|max:200',
            'city' => 'required|string|in:' . implode(',', $this->districts),
        ]);

        // Determine review status based on current pending queue size
        $pendingCount = Review::where('status', 'pending')->count();
        $queueStatus = $pendingCount >= 10 ? 'wait' : 'pending';

        // Check user activity and rate limiting
        $userActivity = UserActivity::where('user_id', $user->user_id)->first();

        if (!$userActivity->canPostReview()) {
            $timeUntil = $userActivity->getTimeUntilNextReview();
            return back()->withErrors(['general' => 'You have reached the daily limit. Try again after ' . $timeUntil]);
        }

        // Create review
        $review = Review::create([
            'user_id' => $user->user_id,
            'restaurant_name' => $request->input('restaurant_name'),
            'review_text' => $request->input('review_text'),
            'review_score' => (int) $request->input('review_score'),
            'location' => $request->input('location'),
            'city' => $request->input('city'),
            'status' => $queueStatus,
            'edit_count' => 0,
            'max_edit_limit' => 3,
        ]);

        // Generate MAC for integrity
        $review->generateMac();

        // Generate ECC signature
        $review->generateSignature();

        // Persist MAC/signature metadata
        $review->save();

        // Update user activity
        $userActivity->decrement('remaining_reviews');

        // If last review, set cooldown
        if ($userActivity->remaining_reviews === 0) {
            $userActivity->update([
                'cooldown_end_time' => now()->addHours(24),
                'remaining_reviews' => 5,
            ]);
        }

        $message = $queueStatus === 'wait'
            ? 'The pending review queue is full. Your review has been queued and will be considered once space is available.'
            : 'Review submitted and pending approval.';

        return redirect('/reviews')->with('status', $message);
    }

    public function edit(Request $request, $id)
    {
        $user = $request->attributes->get('auth_user');
        $review = Review::where('review_id', $id)
            ->where('user_id', $user->user_id)
            ->firstOrFail();

        if (!$review->verifyIntegrity()) {
            abort(403, 'Review integrity verification failed.');
        }

        if ($review->edit_count >= $review->max_edit_limit) {
            abort(403, 'Maximum edit limit reached for this review.');
        }

        if ($review->status !== 'approved') {
            abort(403, 'Only approved reviews can be edited.');
        }

        return view('reviews.edit', ['review' => $review, 'districts' => $this->districts]);
    }

    public function update(Request $request, $id)
    {
        $user = $request->attributes->get('auth_user');
        $review = Review::where('review_id', $id)
            ->where('user_id', $user->user_id)
            ->firstOrFail();

        if ($review->edit_count >= $review->max_edit_limit) {
            abort(403, 'Maximum edit limit reached for this review.');
        }

        if ($review->status !== 'approved') {
            abort(403, 'Only approved reviews can be edited.');
        }

        $request->validate([
            'restaurant_name' => 'required|string|max:200',
            'review_text' => 'required|string|max:1000',
            'review_score' => 'required|integer|min:1|max:10',
            'location' => 'required|string|max:200',
            'city' => 'required|string|in:' . implode(',', $this->districts),
        ]);

        $review->update([
            'restaurant_name' => $request->input('restaurant_name'),
            'review_text' => $request->input('review_text'),
            'review_score' => (int) $request->input('review_score'),
            'location' => $request->input('location'),
            'city' => $request->input('city'),
            'edit_count' => $review->edit_count + 1,
            'status' => 'pending', // Reset to pending on edit
            'max_edit_limit' => $review->max_edit_limit ?: 3,
        ]);

        // Regenerate MAC
        $review->generateMac();

        // Regenerate ECC signature
        $review->generateSignature();

        // Persist regenerated integrity metadata
        $review->save();

        return redirect('/dashboard')->with('status', 'Review updated.');
    }

    public function show($id)
    {
        $review = Review::findOrFail($id);

        if ($review->status !== 'approved' && !auth()->check()) {
            abort(403);
        }

        return view('reviews.show', ['review' => $review]);
    }
    // Admin functions
    /**
     * Returns the list of pending reviews for admin review.
     * Only administrators can access this route.
     */
    public function pendingRequests(Request $request)
    {
        $admin = $request->attributes->get('auth_user');

        if (!$admin->isAdmin()) {
            abort(403, 'Admin access required.');
        }

        $pendingRequests = Review::with('user')
            ->where('status', 'pending')
            ->latest()
            ->limit(20)
            ->get();

        return view('admin.pending_requests', compact('pendingRequests'));
    }

    public function allApproved(Request $request)
    {
        $reviews = Review::where('status', 'approved')->latest()->get();

        return view('user.reviews', compact('reviews'));
    }
}
