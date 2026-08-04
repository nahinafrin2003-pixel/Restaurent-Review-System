<?php

namespace App\Http\Controllers;

use App\Models\AdminAction;
use App\Models\PostedReview;
use App\Models\Review;
use Illuminate\Database\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class AdminController extends Controller
{
    public function approvReview(Request $request, $reviewId)
    {
        $admin = $request->attributes->get('auth_user');

        if (!$admin->isAdmin()) {
            abort(403, 'Admin access required.');
        }

        $review = Review::findOrFail($reviewId);

        // Check admin limit
        DB::transaction(function () use ($review, $admin) {
            if ($review->status !== 'approved') {
                $review->update(['status' => 'approved']);
            }

            // Create posted review only once for approved reviews
            PostedReview::createFromReview($review);

            // Log admin action
            AdminAction::create([
                'admin_id' => $admin->user_id,
                'review_id' => $review->review_id,
                'status' => 'approved',
                'action_time' => now(),
            ]);
        });

        // Fill any freed pending slots from the wait queue
        $pendingCount = Review::where('status', 'pending')->count();
        if ($pendingCount < 10) {
            Review::where('status', 'wait')
                ->orderBy('created_at')
                ->limit(10 - $pendingCount)
                ->update(['status' => 'pending']);
        }

        return back()->with('status', 'Review approved.');
    }

    /**
     * Handles admin rejection of a review.
     * Updates review status to rejected, removes any posted review copy,
     * logs the admin action, notifies the user by email, and refills the pending queue.
     */
    public function rejectReview(Request $request, $reviewId)
    {
        $admin = $request->attributes->get('auth_user');

        if (!$admin->isAdmin()) {
            abort(403, 'Admin access required.');
        }

        $review = Review::findOrFail($reviewId);

        DB::transaction(function () use ($review, $admin) {
            // Reject review
            $review->update(['status' => 'rejected']);

            // Remove any previously posted version when the review is rejected
            PostedReview::where('review_id', $review->review_id)->delete();

            // Log admin action
            AdminAction::create([
                'admin_id' => $admin->user_id,
                'review_id' => $review->review_id,
                'status' => 'rejected',
                'action_time' => now(),
            ]);
        });

        // Send notification email to user
        $user = $review->user;
        if ($user) {
            $email = $user->email; // This will decrypt
            Mail::raw('Your review has been rejected by the admin.', function ($message) use ($email) {
                $message->to($email)->subject('Review Rejected Notification');
            });
        }

        // Fill any freed pending slots from the wait queue
        $pendingCount = Review::where('status', 'pending')->count();
        if ($pendingCount < 10) {
            Review::where('status', 'wait')
                ->orderBy('created_at')
                ->limit(10 - $pendingCount)
                ->update(['status' => 'pending']);
        }

        return back()->with('status', 'Review rejected.');
    }

    public function keyManagement(Request $request)
    {
        $admin = $request->attributes->get('auth_user');

        if (!$admin->isAdmin()) {
            abort(403, 'Admin access required.');
        }

        return view('admin.keys');
    }
}
