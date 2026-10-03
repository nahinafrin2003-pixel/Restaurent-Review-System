@extends('layouts.app')
@section('title', 'Admin')
@section('content')
    <div class="page-head">
        <div><h1>Administration</h1><p class="sub">Review moderation, people, keys and the audit trail.</p></div>
        <div class="row"><a href="/admin/officers" class="btn">+ Provision officer</a><a href="/officer" class="btn ghost">Complaint queue</a></div>
    </div>
    <div class="grid grid-4">
        <div class="kpi info"><div class="label">Pending reviews</div><div class="value">{{ $counts['pending'] }} <span class="small muted">/ 10</span></div><div class="hint">{{ $counts['wait'] }} waitlisted</div></div>
        <div class="kpi brand"><div class="label">Approved reviews</div><div class="value">{{ $counts['approved'] }}</div><div class="hint">{{ $counts['rejected'] }} rejected</div></div>
        <div class="kpi accent"><div class="label">Open complaints</div><div class="value">{{ $counts['open_complaints'] }}</div><div class="hint">{{ $counts['restaurants'] }} restaurants</div></div>
        <div class="kpi"><div class="label">People</div><div class="value">{{ $counts['customers'] }}</div><div class="hint">customers · {{ $counts['active_officers'] }}/{{ $counts['officers'] }} officers active · {{ $counts['sessions'] }} live sessions</div></div>
    </div>
    <div class="split" style="margin-top:20px">
        <div class="card">
            <div class="card-head"><h2>Moderation queue</h2><a href="/admin/pending-requests" class="small">Full queue →</a></div>
            @include('admin.partials.review-table', ['reviews' => $pendingReviews])
        </div>
        <div class="card">
            <div class="card-head"><h3>Recent activity</h3><a href="/admin/audit" class="small">Audit log →</a></div>
            @foreach($recentAudit as $log)
                <div style="padding:6px 0;border-bottom:1px solid var(--line)">
                    <span class="tag">{{ $log->action }}</span>
                    <div class="small muted">{{ $log->user ? $log->user->roleLabel() . ' #' . $log->user_id : 'guest' }} · {{ $log->created_at->diffForHumans() }}</div>
                </div>
            @endforeach
            <div class="row" style="margin-top:12px"><a href="/admin/review-actions" class="btn ghost sm">Moderation trail</a><a href="/admin/keys" class="btn ghost sm">Key management</a></div>
        </div>
    </div>
@endsection
