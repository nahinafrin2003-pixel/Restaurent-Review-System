<?php

namespace App\Http\Controllers;

use App\Models\Complaint;
use App\Models\Restaurant;
use App\Models\Review;
use App\Services\AuditLogger;
use App\Support\ComplaintStatus;
use App\Support\Districts;
use Illuminate\Http\Request;

class RestaurantController extends Controller
{
    public function index(Request $request)
    {
        $query = Restaurant::query();
        if ($district = $request->query('district')) {
            $query->where('district', $district);
        }
        if ($q = trim((string) $request->query('q'))) {
            $query->where('name', 'like', '%' . $q . '%');
        }
        $sort = $request->query('sort', 'trust');
        $sort === 'name' ? $query->orderBy('name') : $query->orderByDesc('trust_score')->orderBy('name');

        return view('restaurants.index', [
            'restaurants' => $query->paginate(12)->withQueryString(),
            'districts' => Districts::all(),
        ]);
    }

    public function show(Restaurant $restaurant)
    {
        $reviews = Review::where('restaurant_id', $restaurant->id)->where('status', 'approved')->latest('created_at')->limit(20)->get();
        $complaints = Complaint::where('restaurant_id', $restaurant->id)->whereNotNull('verified_at')->where('status', '!=', ComplaintStatus::REJECTED)->latest('verified_at')->get();

        return view('restaurants.show', [
            'restaurant' => $restaurant,
            'reviews' => $reviews,
            'verifiedComplaints' => $complaints,
            'avgReview' => $reviews->avg('review_score'),
            'mapsKey' => config('services.google_maps.key'),
        ]);
    }

    public function create(Request $request)
    {
        return view('restaurants.create', [
            'districts' => Districts::all(),
            'centres' => Districts::CENTRES,
            'mapsKey' => config('services.google_maps.key'),
            'returnTo' => $request->query('return', '/complaints/create'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|min:2|max:200',
            'address' => 'nullable|string|max:255',
            'district' => 'required|in:' . implode(',', Districts::all()),
            'latitude' => 'nullable|numeric|between:20,27',
            'longitude' => 'nullable|numeric|between:87,93',
            'return' => 'nullable|string|max:100',
        ], [
            'latitude.between' => 'Please pick a location inside Bangladesh.',
            'longitude.between' => 'Please pick a location inside Bangladesh.',
        ]);

        if ($existing = Restaurant::findByName($data['name'], $data['district'])) {
            return redirect($this->target($data['return'] ?? null, $existing))
                ->with('status', 'That restaurant is already listed — we selected it for you.');
        }

        $restaurant = Restaurant::create([
            'name' => $data['name'],
            'address' => $data['address'] ?? null,
            'district' => $data['district'],
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'trust_score' => 5.0,
            'created_by' => $request->attributes->get('auth_user')->user_id,
        ]);
        AuditLogger::log('restaurant.created', $restaurant, ['name' => $restaurant->name]);

        return redirect($this->target($data['return'] ?? null, $restaurant))
            ->with('status', 'Restaurant added.');
    }

    /** Only allow returning to our own forms (no open redirects). */
    private function target(?string $path, Restaurant $restaurant): string
    {
        return in_array($path, ['/complaints/create', '/reviews/create'], true)
            ? $path . '?restaurant_id=' . $restaurant->id
            : '/restaurants/' . $restaurant->id;
    }
}
