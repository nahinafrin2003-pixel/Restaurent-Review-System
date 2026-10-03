<?php

namespace App\Http\Controllers;

use App\Models\Complaint;
use App\Models\ComplaintMessage;
use App\Models\Restaurant;
use App\Models\RewardEvent;
use App\Models\UserBadge;
use App\Services\AuditLogger;
use App\Services\ComplaintWorkflow;
use App\Services\ReportService;
use App\Services\RewardService;
use App\Support\ComplaintCategory;
use App\Support\ComplaintStatus;
use App\Support\Districts;
use App\Support\EvidenceRules;
use Illuminate\Http\Request;

class ComplaintController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->attributes->get('auth_user');
        $complaints = Complaint::with('restaurant')->where('user_id', $user->user_id)->latest()->paginate(10);

        return view('complaints.index', compact('complaints'));
    }

    public function create(Request $request)
    {
        return view('complaints.create', [
            'restaurants' => Restaurant::orderBy('name')->get(['id', 'name', 'district']),
            'selectedRestaurant' => $request->query('restaurant_id'),
            'categories' => ComplaintCategory::LABELS,
            'districts' => Districts::all(),
            'evidenceTypes' => EvidenceRules::TYPES,
        ]);
    }

    public function store(Request $request)
    {
        @set_time_limit(300);
        $user = $request->attributes->get('auth_user');

        $data = $request->validate([
            'restaurant_id' => 'required|exists:restaurants,id',
            'category' => 'required|in:auto,' . implode(',', ComplaintCategory::keys()),
            'title' => 'required|string|min:5|max:150',
            'description' => 'required|string|min:20|max:4000',
            'incident_date' => 'required|date|before_or_equal:today|after:' . now()->subYears(2)->toDateString(),
        ] + EvidenceRules::rules(), EvidenceRules::messages());

        $complaint = ComplaintWorkflow::submit($user, Restaurant::findOrFail($data['restaurant_id']), $data, EvidenceRules::collect($request));

        $message = 'Complaint ' . $complaint->reference . ' submitted.';
        if ($data['category'] === 'auto') {
            $message .= ' We categorised it as "' . $complaint->categoryLabel() . '".';
        }

        return redirect('/complaints/' . $complaint->id)->with('status', $message);
    }

    public function show(Request $request, Complaint $complaint)
    {
        $user = $request->attributes->get('auth_user');
        abort_unless($complaint->user_id === $user->user_id, 404);

        $complaint->load(['restaurant', 'history.actor', 'evidence', 'messages.sender']);
        $integrity = $complaint->integrity();
        if (! $integrity['ok']) {
            AuditLogger::log('complaint.integrity_failed', $complaint, $integrity);
        }

        // Mark staff messages as read
        ComplaintMessage::where('complaint_id', $complaint->id)->where('sender_id', '!=', $user->user_id)->whereNull('read_at')->update(['read_at' => now()]);

        return view('complaints.show', [
            'complaint' => $complaint,
            'integrity' => $integrity,
            'evidenceTypes' => EvidenceRules::TYPES,
        ]);
    }

    public function addEvidence(Request $request, Complaint $complaint)
    {
        @set_time_limit(300);
        $user = $request->attributes->get('auth_user');
        abort_unless($complaint->user_id === $user->user_id, 404);

        if (! $complaint->acceptsEvidence()) {
            return back()->withErrors(['evidence' => 'This complaint no longer accepts new evidence.']);
        }

        $request->validate(EvidenceRules::rules(), EvidenceRules::messages());
        $count = 0;
        foreach (EvidenceRules::collect($request) as $type => $files) {
            foreach ($files as $file) {
                ComplaintWorkflow::storeEvidence($complaint, $file, $type, $user);
                $count++;
            }
        }

        if ($count === 0) {
            return back()->withErrors(['evidence' => 'Please choose at least one file.']);
        }

        // Evidence requested → back into the verification queue automatically.
        if ($complaint->status === ComplaintStatus::EVIDENCE_REQUESTED) {
            ComplaintWorkflow::transition($complaint, ComplaintStatus::UNDER_VERIFICATION, $user, "Customer uploaded {$count} new evidence file(s).");
        }

        return back()->with('status', "{$count} evidence file(s) uploaded.");
    }

    /** Customer ↔ officer messaging (owner or staff). */
    public function message(Request $request, Complaint $complaint)
    {
        $user = $request->attributes->get('auth_user');
        abort_unless($complaint->user_id === $user->user_id || $user->isStaff(), 404);

        $data = $request->validate(['body' => 'required|string|max:2000']);

        if (in_array($complaint->status, [ComplaintStatus::CLOSED, ComplaintStatus::REJECTED], true)) {
            return back()->withErrors(['body' => 'This complaint is closed; messaging is disabled.']);
        }

        ComplaintMessage::create([
            'complaint_id' => $complaint->id,
            'sender_id' => $user->user_id,
            'body' => $data['body'],
            'created_at' => now(),
        ]);
        AuditLogger::log('complaint.message_sent', $complaint, ['reference' => $complaint->reference]);

        if ($user->isStaff()) {
            ComplaintWorkflow::notify($complaint, $complaint->status, $complaint->status, 'You have a new message from the verification officer.');
        }

        return back()->with('status', 'Message sent.');
    }

    /** Downloadable verification report (PDF) — owner or staff, verified complaints only. */
    public function report(Request $request, Complaint $complaint)
    {
        @set_time_limit(300);
        $user = $request->attributes->get('auth_user');
        abort_unless($complaint->user_id === $user->user_id || $user->isStaff(), 404);

        if (! $complaint->isVerified()) {
            return back()->withErrors(['report' => 'A verification report is available once the complaint has been verified.']);
        }

        return ReportService::download($complaint, $user);
    }

    public function rewards(Request $request)
    {
        $user = $request->attributes->get('auth_user');

        return view('complaints.rewards', [
            'user' => $user->fresh(),
            'events' => RewardEvent::where('user_id', $user->user_id)->latest('created_at')->get(),
            'earned' => UserBadge::where('user_id', $user->user_id)->get()->keyBy('badge'),
            'catalog' => RewardService::BADGES,
            'verifiedCount' => Complaint::where('user_id', $user->user_id)->whereNotNull('verified_at')->count(),
        ]);
    }
}
