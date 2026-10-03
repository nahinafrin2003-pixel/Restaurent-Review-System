<?php

namespace App\Http\Controllers;

use App\Models\Complaint;
use App\Models\ComplaintDuplicate;
use App\Models\ComplaintMessage;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\ComplaintWorkflow;
use App\Services\EvidenceAnalyzer;
use App\Support\ComplaintCategory;
use App\Support\ComplaintStatus;
use App\Support\Districts;
use Illuminate\Http\Request;

class OfficerController extends Controller
{
    public function dashboard(Request $request)
    {
        $user = $request->attributes->get('auth_user');
        $scope = $request->query('scope', 'open');

        $query = Complaint::with(['restaurant', 'officer'])->withCount(['evidence', 'duplicateCandidates as duplicate_count' => fn ($q) => $q->where('status', 'suggested')]);

        match ($scope) {
            'mine' => $query->where('assigned_officer_id', $user->user_id)->whereIn('status', ComplaintStatus::OPEN_STATES),
            'unassigned' => $query->whereNull('assigned_officer_id')->whereIn('status', ComplaintStatus::OPEN_STATES),
            'closed' => $query->whereIn('status', ComplaintStatus::FINAL_STATES),
            'all' => null,
            default => $query->whereIn('status', ComplaintStatus::OPEN_STATES),
        };

        foreach (['status', 'category', 'district'] as $filter) {
            if ($value = $request->query($filter)) {
                $query->where($filter, $value);
            }
        }
        if ($ref = trim((string) $request->query('ref'))) {
            $query->where('reference', 'like', '%' . strtoupper($ref) . '%');
        }

        // Oldest first for open work so nothing starves.
        $scope === 'closed' || $scope === 'all' ? $query->latest() : $query->orderBy('created_at');

        $counts = [];
        foreach (ComplaintStatus::LABELS as $status => $label) {
            $counts[$status] = Complaint::where('status', $status)->count();
        }

        return view('officer.dashboard', [
            'complaints' => $query->paginate(15)->withQueryString(),
            'scope' => $scope,
            'counts' => $counts,
            'mine' => Complaint::where('assigned_officer_id', $user->user_id)->whereIn('status', ComplaintStatus::OPEN_STATES)->count(),
            'unassigned' => Complaint::whereNull('assigned_officer_id')->whereIn('status', ComplaintStatus::OPEN_STATES)->count(),
            'unreadMessages' => ComplaintMessage::whereNull('read_at')
                ->whereIn('complaint_id', Complaint::where('assigned_officer_id', $user->user_id)->select('id'))
                ->whereNotIn('sender_id', User::whereIn('role', ['officer', 'admin'])->select('user_id'))->count(),
            'categories' => ComplaintCategory::LABELS,
            'statuses' => ComplaintStatus::LABELS,
            'districts' => Districts::all(),
        ]);
    }

    public function show(Request $request, Complaint $complaint)
    {
        @set_time_limit(120);
        $user = $request->attributes->get('auth_user');
        $complaint->load(['restaurant', 'user', 'officer', 'history.actor', 'evidence', 'messages.sender', 'duplicateCandidates.candidate.restaurant', 'duplicateOf']);

        $integrity = $complaint->integrity();
        AuditLogger::log('complaint.viewed', $complaint, ['reference' => $complaint->reference, 'integrity' => $integrity['ok']]);
        if (! $integrity['ok']) {
            AuditLogger::log('complaint.integrity_failed', $complaint, $integrity);
        }

        // Messages from the customer are now read.
        ComplaintMessage::where('complaint_id', $complaint->id)->where('sender_id', $complaint->user_id)->whereNull('read_at')->update(['read_at' => now()]);

        // Complaints that list *this* one as a candidate too (reverse direction)
        $reverse = ComplaintDuplicate::with('complaint.restaurant')->where('candidate_id', $complaint->id)->where('status', '!=', 'dismissed')->get();

        return view('officer.show', [
            'complaint' => $complaint,
            'integrity' => $integrity,
            'transitions' => ComplaintStatus::TRANSITIONS[$complaint->status] ?? [],
            'reverseDuplicates' => $reverse,
            'officers' => $user->isAdmin() ? User::where('role', 'officer')->where('is_active', true)->get() : collect(),
            'ocrAvailable' => EvidenceAnalyzer::ocrAvailable(),
            'otherComplaintsByUser' => Complaint::where('user_id', $complaint->user_id)->where('id', '!=', $complaint->id)->count(),
        ]);
    }

    public function assign(Request $request, Complaint $complaint)
    {
        $user = $request->attributes->get('auth_user');
        $officerId = $user->isAdmin() && $request->filled('officer_id') ? (int) $request->input('officer_id') : $user->user_id;

        if ($officerId !== $user->user_id) {
            $officer = User::where('user_id', $officerId)->where('role', 'officer')->where('is_active', true)->firstOrFail();
            $officerId = $officer->user_id;
        }

        $complaint->update(['assigned_officer_id' => $officerId]);
        AuditLogger::log('complaint.assigned', $complaint, ['reference' => $complaint->reference, 'officer_id' => $officerId]);

        // Picking up a fresh complaint starts verification.
        if ($complaint->status === ComplaintStatus::SUBMITTED && $officerId === $user->user_id) {
            ComplaintWorkflow::transition($complaint, ComplaintStatus::UNDER_VERIFICATION, $user, 'Complaint picked up for verification.');
        }

        return back()->with('status', 'Complaint assigned.');
    }

    public function updateStatus(Request $request, Complaint $complaint)
    {
        @set_time_limit(300);
        $user = $request->attributes->get('auth_user');

        $data = $request->validate([
            'status' => 'required|in:' . implode(',', array_keys(ComplaintStatus::LABELS)),
            'note' => 'nullable|string|max:2000',
            'outcome' => 'nullable|string|max:255',
        ]);

        if ($data['status'] === ComplaintStatus::EVIDENCE_REQUESTED && blank($data['note'] ?? null)) {
            return back()->withErrors(['note' => 'Please tell the customer which evidence you need.'])->withInput();
        }
        if (in_array($data['status'], [ComplaintStatus::RESOLVED, ComplaintStatus::REJECTED, ComplaintStatus::CLOSED], true) && blank($data['outcome'] ?? null)) {
            return back()->withErrors(['outcome' => 'Please record a short public outcome (shown anonymised on the transparency page).'])->withInput();
        }

        try {
            ComplaintWorkflow::transition($complaint, $data['status'], $user, $data['note'] ?? null, $data['outcome'] ?? null);
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return back()->with('status', 'Status updated to "' . ComplaintStatus::label($data['status']) . '". The customer has been notified.');
    }

    public function resolveDuplicate(Request $request, Complaint $complaint, ComplaintDuplicate $duplicate)
    {
        abort_unless($duplicate->complaint_id === $complaint->id || $duplicate->candidate_id === $complaint->id, 404);
        $user = $request->attributes->get('auth_user');
        $action = $request->validate(['action' => 'required|in:link,dismiss'])['action'];

        $duplicate->update(['status' => $action === 'link' ? 'linked' : 'dismissed', 'reviewed_by' => $user->user_id]);

        if ($action === 'link') {
            // The newer complaint points at the older one as its primary report.
            [$newer, $older] = $duplicate->complaint->created_at >= $duplicate->candidate->created_at
                ? [$duplicate->complaint, $duplicate->candidate]
                : [$duplicate->candidate, $duplicate->complaint];
            $newer->update(['duplicate_of_id' => $older->id]);
        }

        AuditLogger::log('complaint.duplicate_' . $action, $complaint, ['pair' => [$duplicate->complaint_id, $duplicate->candidate_id], 'score' => $duplicate->score]);

        return back()->with('status', $action === 'link' ? 'Complaints linked as duplicates.' : 'Duplicate suggestion dismissed.');
    }
}
