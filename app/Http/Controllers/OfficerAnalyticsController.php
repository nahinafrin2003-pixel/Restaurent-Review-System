<?php

namespace App\Http\Controllers;

use App\Models\Complaint;
use App\Models\Restaurant;
use App\Support\ComplaintCategory;
use App\Support\ComplaintStatus;
use App\Support\Districts;
use Illuminate\Http\Request;

class OfficerAnalyticsController extends Controller
{
    public function index(Request $request)
    {
        $months = (int) $request->query('months', 12);
        $months = in_array($months, [3, 6, 12, 24], true) ? $months : 12;
        $since = now()->startOfMonth()->subMonths($months - 1);

        // Only non-sensitive columns are loaded: no decryption needed for analytics.
        $complaints = Complaint::where('created_at', '>=', $since)
            ->get(['id', 'restaurant_id', 'category', 'district', 'status', 'created_at', 'verified_at', 'resolved_at']);

        $byDistrict = [];
        foreach (Districts::all() as $district) {
            $byDistrict[$district] = $complaints->where('district', $district)->count();
        }

        $byCategory = [];
        foreach (ComplaintCategory::LABELS as $key => $label) {
            $byCategory[$label] = $complaints->where('category', $key)->count();
        }

        $byStatus = [];
        foreach (ComplaintStatus::LABELS as $key => $label) {
            $byStatus[$label] = $complaints->where('status', $key)->count();
        }

        $monthly = ['labels' => [], 'submitted' => [], 'verified' => [], 'resolved' => []];
        for ($i = 0; $i < $months; $i++) {
            $start = $since->copy()->addMonths($i);
            $end = $start->copy()->endOfMonth();
            $monthly['labels'][] = $start->format('M Y');
            $monthly['submitted'][] = $complaints->filter(fn ($c) => $c->created_at->between($start, $end))->count();
            $monthly['verified'][] = $complaints->filter(fn ($c) => $c->verified_at && $c->verified_at->between($start, $end))->count();
            $monthly['resolved'][] = $complaints->filter(fn ($c) => $c->resolved_at && $c->status === ComplaintStatus::RESOLVED && $c->resolved_at->between($start, $end))->count();
        }

        $finished = $complaints->filter(fn ($c) => $c->resolved_at && in_array($c->status, [ComplaintStatus::RESOLVED, ComplaintStatus::CLOSED], true));
        $avgResolutionDays = $finished->count()
            ? round($finished->avg(fn ($c) => $c->created_at->diffInMinutes($c->resolved_at) / 1440), 1)
            : null;
        $verifiedSet = $complaints->whereNotNull('verified_at');
        $avgVerificationDays = $verifiedSet->count()
            ? round($verifiedSet->avg(fn ($c) => $c->created_at->diffInMinutes($c->verified_at) / 1440), 1)
            : null;

        $offenderIds = $complaints->whereNotNull('verified_at')->where('status', '!=', ComplaintStatus::REJECTED)
            ->groupBy('restaurant_id')->map->count()->sortDesc()->take(10);
        $restaurants = Restaurant::whereIn('id', $offenderIds->keys())->get()->keyBy('id');
        $topOffenders = $offenderIds->map(fn ($count, $id) => ['restaurant' => $restaurants[$id] ?? null, 'count' => $count])->filter(fn ($row) => $row['restaurant'])->values();

        return view('officer.analytics', [
            'months' => $months,
            'kpis' => [
                'total' => $complaints->count(),
                'open' => $complaints->whereIn('status', ComplaintStatus::OPEN_STATES)->count(),
                'verified' => $verifiedSet->count(),
                'rejected' => $complaints->where('status', ComplaintStatus::REJECTED)->count(),
                'avg_resolution_days' => $avgResolutionDays,
                'avg_verification_days' => $avgVerificationDays,
                'verification_rate' => $complaints->count() ? round(100 * $verifiedSet->count() / $complaints->count()) : 0,
            ],
            'byDistrict' => $byDistrict,
            'byCategory' => $byCategory,
            'byStatus' => $byStatus,
            'monthly' => $monthly,
            'topOffenders' => $topOffenders,
        ]);
    }

    public function heatmap(Request $request)
    {
        $query = Complaint::with('restaurant:id,name,district,latitude,longitude');
        if ($category = $request->query('category')) {
            $query->where('category', $category);
        }
        if ($request->query('only') === 'verified') {
            $query->whereNotNull('verified_at');
        }
        $months = (int) $request->query('months', 12);
        $query->where('created_at', '>=', now()->subMonths(max(1, min(36, $months))));

        $points = [];
        $byRestaurant = [];
        foreach ($query->get(['id', 'restaurant_id', 'category', 'status', 'verified_at']) as $complaint) {
            if (! $complaint->restaurant) {
                continue;
            }
            [$lat, $lng] = $complaint->restaurant->coordinates();
            $points[] = ['lat' => $lat, 'lng' => $lng, 'weight' => $complaint->verified_at ? 3 : 1];
            $rid = $complaint->restaurant_id;
            $byRestaurant[$rid] ??= ['name' => $complaint->restaurant->name, 'district' => $complaint->restaurant->district, 'lat' => $lat, 'lng' => $lng, 'count' => 0, 'verified' => 0, 'id' => $rid];
            $byRestaurant[$rid]['count']++;
            $byRestaurant[$rid]['verified'] += $complaint->verified_at ? 1 : 0;
        }

        return view('officer.heatmap', [
            'points' => $points,
            'markers' => array_values($byRestaurant),
            'mapsKey' => config('services.google_maps.key'),
            'categories' => ComplaintCategory::LABELS,
            'months' => $months,
        ]);
    }
}
