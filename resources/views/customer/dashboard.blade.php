@extends('layouts.app')
@section('title', 'Dashboard')
@section('content')
    <div class="page-head">
        <div><h1>Hello, {{ $user->username }}</h1><p class="sub">Your reviews, complaints and rewards in one place.</p></div>
        <div class="row">
            <a href="/reviews/create" class="btn ghost">Write a review</a>
            <a href="/complaints/create" class="btn danger">File a complaint</a>
        </div>
    </div>

    @if($counts['evidence_requested'] > 0)
        <div class="alert warn"><strong>Action needed:</strong> an officer requested more evidence on {{ $counts['evidence_requested'] }} complaint(s). <a href="/complaints">Open my complaints →</a></div>
    @endif

    <div class="grid grid-4">
        <div class="kpi info"><div class="label">Open complaints</div><div class="value">{{ $counts['open'] }}</div></div>
        <div class="kpi brand"><div class="label">Verified complaints</div><div class="value">{{ $counts['verified'] }}</div></div>
        <div class="kpi accent"><div class="label">Reward points</div><div class="value">{{ (int) $user->points }}</div><div class="hint"><a href="/rewards">View rewards</a></div></div>
        <div class="kpi"><div class="label">Reviews left</div>
            <div class="value">{{ $activity->cooldown_end_time && $activity->cooldown_end_time->isFuture() ? 0 : $activity->remaining_reviews }}</div>
            <div class="hint">@if($activity->cooldown_end_time && $activity->cooldown_end_time->isFuture()) Cooldown ends {{ $activity->cooldown_end_time->diffForHumans() }} @else 5 per 24-hour window @endif</div>
        </div>
    </div>

    <div class="split" style="margin-top:20px">
        <div class="card">
            <div class="card-head"><h2>Recent complaints</h2><a href="/complaints" class="small">All →</a></div>
            @forelse($complaints as $c)
                <a href="/complaints/{{ $c->id }}" class="row between" style="padding:10px 0;border-bottom:1px solid var(--line);color:inherit;text-decoration:none">
                    <div><strong class="mono" style="font-size:.85rem">{{ $c->reference }}</strong> · {{ $c->restaurant->name ?? '—' }}<div class="small muted">{{ $c->categoryLabel() }} · {{ $c->created_at->diffForHumans() }}</div></div>
                    @include('partials.status', ['status' => $c->status])
                </a>
            @empty
                <div class="empty"><div class="icon">📋</div>No complaints yet.</div>
            @endforelse
        </div>
        <div class="stack">
            <div class="card">
                <h3>Badges</h3>
                @foreach($badgeCatalog as $key => $b)
                    <div class="badge-card {{ in_array($key, $badges) ? '' : 'locked' }}" style="margin-bottom:8px">
                        <div class="medal">{{ $b['icon'] }}</div>
                        <div><strong>{{ $b['label'] }}</strong><div class="small muted">{{ $b['rule'] }}</div></div>
                    </div>
                @endforeach
            </div>
            <div class="card">
                <div class="card-head"><h3>My recent reviews</h3><a href="/reviews" class="small">All →</a></div>
                @forelse($reviews as $r)
                    <div class="row between" style="padding:6px 0"><span>{{ $r->restaurant_name }} · {{ $r->review_score }}/10</span><span class="badge s-{{ $r->status }}">{{ $r->statusLabel() }}</span></div>
                @empty
                    <p class="small muted">No reviews yet.</p>
                @endforelse
            </div>
        </div>
    </div>
@endsection
