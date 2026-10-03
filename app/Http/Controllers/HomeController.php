<?php

namespace App\Http\Controllers;

use App\Models\Complaint;
use App\Models\Restaurant;
use App\Models\Review;
use App\Models\UserActivity;
use App\Models\UserBadge;
use App\Services\RewardService;
use App\Support\ComplaintStatus;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function home(Request $request)
    {
        return view('home', [
            'stats' => [
                'restaurants' => Restaurant::count(),
                'reviews' => Review::where('status', 'approved')->count(),
                'complaints' => Complaint::count(),
                'resolved' => Complaint::whereIn('status', [ComplaintStatus::RESOLVED, ComplaintStatus::CLOSED])->whereNotNull('verified_at')->count(),
            ],
            'topRestaurants' => Restaurant::orderByDesc('trust_score')->orderBy('name')->limit(6)->get(),
            'watchList' => Restaurant::where('verified_complaints', '>', 0)->orderBy('trust_score')->limit(5)->get(),
        ]);
    }

    public function redirectHome(Request $request)
    {
        return redirect($request->attributes->get('auth_user')->homePath());
    }

    public function customerDashboard(Request $request)
    {
        $user = $request->attributes->get('auth_user');

        $complaints = Complaint::with('restaurant')->where('user_id', $user->user_id)->latest()->limit(5)->get();
        $reviews = Review::where('user_id', $user->user_id)->latest('created_at')->limit(5)->get();
        $badges = UserBadge::where('user_id', $user->user_id)->pluck('badge')->all();

        return view('customer.dashboard', [
            'user' => $user,
            'complaints' => $complaints,
            'reviews' => $reviews,
            'activity' => UserActivity::firstOrCreate(['user_id' => $user->user_id], ['remaining_reviews' => 5]),
            'badges' => $badges,
            'badgeCatalog' => RewardService::BADGES,
            'counts' => [
                'open' => Complaint::where('user_id', $user->user_id)->whereIn('status', ComplaintStatus::OPEN_STATES)->count(),
                'verified' => Complaint::where('user_id', $user->user_id)->whereNotNull('verified_at')->count(),
                'evidence_requested' => Complaint::where('user_id', $user->user_id)->where('status', ComplaintStatus::EVIDENCE_REQUESTED)->count(),
            ],
        ]);
    }
}
