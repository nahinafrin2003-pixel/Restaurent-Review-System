<?php

namespace App\Http\Controllers;

use App\Models\Complaint;
use App\Services\Crypto\HMAC;
use App\Services\QrService;
use App\Support\ComplaintCategory;
use App\Support\ComplaintStatus;
use App\Support\Districts;
use Illuminate\Http\Request;

class PublicComplaintController extends Controller
{
    /** Public QR verification page — no login, no personal data. */
    public function verify(Request $request, string $reference)
    {
        $complaint = $this->resolve($request, $reference);
        $complaint?->load(['restaurant', 'evidence']);

        return view('public.verify', [
            'complaint' => $complaint,
            'reference' => strtoupper($reference),
            'integrity' => $complaint?->integrity(),
        ]);
    }

    public function qr(Request $request, string $reference)
    {
        $complaint = $this->resolve($request, $reference);
        abort_unless($complaint, 404);

        return response(QrService::svg($complaint->publicUrl(), 220), 200, ['Content-Type' => 'image/svg+xml', 'Cache-Control' => 'public, max-age=86400']);
    }

    /** Anonymised list of resolved complaints. */
    public function transparency(Request $request)
    {
        $query = Complaint::with('restaurant:id,name,district,trust_score')
            ->whereNotNull('verified_at')
            ->whereIn('status', [ComplaintStatus::RESOLVED, ComplaintStatus::CLOSED]);

        if ($district = $request->query('district')) {
            $query->where('district', $district);
        }
        if ($category = $request->query('category')) {
            $query->where('category', $category);
        }

        $base = Complaint::whereNotNull('verified_at')->whereIn('status', [ComplaintStatus::RESOLVED, ComplaintStatus::CLOSED]);

        return view('public.transparency', [
            'complaints' => $query->latest('resolved_at')->paginate(20, ['id', 'reference', 'restaurant_id', 'category', 'district', 'status', 'outcome', 'verified_at', 'resolved_at'])->withQueryString(),
            'districts' => Districts::all(),
            'categories' => ComplaintCategory::LABELS,
            'totals' => [
                'resolved' => (clone $base)->count(),
                'restaurants' => (clone $base)->distinct('restaurant_id')->count('restaurant_id'),
                'this_year' => (clone $base)->where('resolved_at', '>=', now()->startOfYear())->count(),
            ],
        ]);
    }

    private function resolve(Request $request, string $reference): ?Complaint
    {
        $reference = strtoupper(trim($reference));
        $token = (string) $request->query('t', '');
        if (! preg_match('/^CMP-\d{4}-\d{6}$/', $reference) || ! HMAC::equals(Complaint::makePublicToken($reference), $token)) {
            return null;
        }

        return Complaint::where('reference', $reference)->first();
    }
}
