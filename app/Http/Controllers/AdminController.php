<?php

namespace App\Http\Controllers;

use App\Models\AdminAction;
use App\Models\AuditLog;
use App\Models\Complaint;
use App\Models\PostedReview;
use App\Models\Restaurant;
use App\Models\Review;
use App\Models\SessionTable;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Crypto\KeyManager;
use App\Services\TrustScoreService;
use App\Support\ComplaintStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class AdminController extends Controller
{
    public const MAX_PENDING = 10;

    public function dashboard(Request $request)
    {
        return view('admin.dashboard', [
            'pendingReviews' => Review::where('status', 'pending')->orderBy('created_at')->limit(10)->get(),
            'counts' => [
                'pending' => Review::where('status', 'pending')->count(),
                'wait' => Review::where('status', 'wait')->count(),
                'approved' => Review::where('status', 'approved')->count(),
                'rejected' => Review::where('status', 'rejected')->count(),
                'customers' => User::whereIn('role', ['customer', 'user'])->count(),
                'officers' => User::where('role', 'officer')->count(),
                'active_officers' => User::where('role', 'officer')->where('is_active', true)->count(),
                'open_complaints' => Complaint::whereIn('status', ComplaintStatus::OPEN_STATES)->count(),
                'restaurants' => Restaurant::count(),
                'sessions' => SessionTable::where('expiry_time', '>', now())->count(),
            ],
            'recentAudit' => AuditLog::with('user')->latest('created_at')->limit(8)->get(),
        ]);
    }

    public function pendingRequests(Request $request)
    {
        return view('admin.pending_requests', [
            'pendingRequests' => Review::with('user')->where('status', 'pending')->orderBy('created_at')->get(),
            'waiting' => Review::with('user')->where('status', 'wait')->orderBy('created_at')->get(),
            'max' => self::MAX_PENDING,
        ]);
    }

    public function approveReview(Request $request, $reviewId)
    {
        return $this->moderate($request, (int) $reviewId, 'approved');
    }

    public function rejectReview(Request $request, $reviewId)
    {
        return $this->moderate($request, (int) $reviewId, 'rejected');
    }

    private function moderate(Request $request, int $reviewId, string $decision)
    {
        $admin = $request->attributes->get('auth_user');
        $review = Review::findOrFail($reviewId);

        if (! in_array($review->status, ['pending', 'wait'], true)) {
            return back()->withErrors(['review' => 'This review has already been moderated.']);
        }

        if (! $review->verifyIntegrity()) {
            AuditLogger::log('review.integrity_failed', $review);

            return back()->withErrors(['review' => 'Review #' . $review->review_id . ' failed its integrity check (HMAC/ECC) and cannot be approved.']);
        }

        DB::transaction(function () use ($review, $admin, $decision) {
            $review->update(['status' => $decision]);

            if ($decision === 'approved') {
                PostedReview::createFromReview($review);
            } else {
                PostedReview::where('review_id', $review->review_id)->delete();
            }

            AdminAction::create([
                'admin_id' => $admin->user_id,
                'review_id' => $review->review_id,
                'status' => $decision,
                'action_time' => now(),
            ]);
        });

        AuditLogger::log('review.' . $decision, $review, ['review_id' => $review->review_id]);

        if ($review->restaurant_id && ($restaurant = Restaurant::find($review->restaurant_id))) {
            TrustScoreService::recalculate($restaurant);
        }

        try {
            if (($email = $review->user?->email) && ! str_ends_with($email, '.test')) {
                Mail::raw(
                    "Your review of {$review->restaurant_name} has been {$decision} by the moderator.",
                    fn ($m) => $m->to($email)->subject('Review ' . ucfirst($decision))
                );
            }
        } catch (\Throwable $e) {
            \Log::warning('Review moderation mail failed: ' . $e->getMessage());
        }

        $this->promoteWaitlist();

        return back()->with('status', 'Review ' . $decision . '.');
    }

    /** Moves waitlisted reviews into the pending queue while there is room (cap = 10). */
    private function promoteWaitlist(): void
    {
        $room = self::MAX_PENDING - Review::where('status', 'pending')->count();
        if ($room > 0) {
            Review::where('status', 'wait')->orderBy('created_at')->limit($room)->get()
                ->each(fn ($r) => $r->update(['status' => 'pending']));
        }
    }

    public function reviewActions(Request $request)
    {
        return view('admin.review_actions', [
            'actions' => AdminAction::with(['admin', 'review'])->latest('action_time')->paginate(25),
        ]);
    }

    public function audit(Request $request)
    {
        $query = AuditLog::with('user')->latest('created_at');
        if ($action = $request->query('action')) {
            $query->where('action', 'like', $action . '%');
        }

        return view('admin.audit', [
            'logs' => $query->paginate(40)->withQueryString(),
            'groups' => ['auth', 'complaint', 'evidence', 'report', 'review', 'admin', 'restaurant', 'keys'],
        ]);
    }

    public function keyManagement(Request $request)
    {
        return view('admin.keys', ['keys' => app(KeyManager::class)->activeKeySummary()]);
    }

    public function rotateKeys(Request $request)
    {
        @set_time_limit(600);
        app(KeyManager::class)->rotateKeys();
        AuditLogger::log('keys.rotated');

        return back()->with('status', 'New RSA-2048 and secp256k1 key pairs generated. Existing data remains readable with the rotated keys.');
    }
}
