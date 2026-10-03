@extends('layouts.app')
@section('title', $restaurant->name)
@section('content')
    <div class="page-head">
        <div>
            <h1>{{ $restaurant->name }}</h1>
            <p class="sub">{{ $restaurant->district }}{{ $restaurant->address ? ' · ' . $restaurant->address : '' }}</p>
        </div>
        @if($authUser && $authUser->isCustomer())
            <div class="row">
                <a class="btn ghost" href="/reviews/create?restaurant_id={{ $restaurant->id }}">Write a review</a>
                <a class="btn danger" href="/complaints/create?restaurant_id={{ $restaurant->id }}">File a complaint</a>
            </div>
        @endif
    </div>

    <div class="grid grid-3">
        <div class="kpi {{ $restaurant->trustLevel() === 'good' ? 'brand' : ($restaurant->trustLevel() === 'warn' ? 'accent' : 'danger') }}">
            <div class="label">Trust score</div>
            <div class="value">@include('partials.trust', ['restaurant' => $restaurant])</div>
            <div class="hint">0–5, recalculated on every verified complaint</div>
        </div>
        <div class="kpi info"><div class="label">Average review</div><div class="value">{{ $avgReview ? number_format($avgReview, 1) . ' / 10' : '—' }}</div><div class="hint">{{ $reviews->count() }} approved review(s)</div></div>
        <div class="kpi danger"><div class="label">Verified complaints</div><div class="value">{{ $verifiedComplaints->count() }}</div><div class="hint">personal data never shown</div></div>
    </div>

    <div class="split" style="margin-top:20px">
        <div class="stack">
            <div class="card">
                <h2>Reviews</h2>
                @forelse($reviews as $review)
                    <div style="padding:12px 0;border-bottom:1px solid var(--line)">
                        <div class="row between"><strong>{{ $review->review_score }}/10</strong><span class="small muted">{{ optional($review->created_at)->format('d M Y') }}</span></div>
                        <p style="margin:6px 0 0">{{ $review->review_text }}</p>
                    </div>
                @empty
                    <div class="empty">No approved reviews yet.</div>
                @endforelse
            </div>
            <div class="card">
                <h2>Verified complaints</h2>
                @forelse($verifiedComplaints as $c)
                    <div class="row between" style="padding:8px 0;border-bottom:1px solid var(--line)">
                        <div><span class="tag">{{ $c->categoryLabel() }}</span> <span class="small muted">verified {{ $c->verified_at->format('M Y') }}</span>
                            @if($c->outcome)<div class="small">{{ $c->outcome }}</div>@endif</div>
                        @include('partials.status', ['status' => $c->status])
                    </div>
                @empty
                    <p class="muted">No verified complaints.</p>
                @endforelse
            </div>
        </div>
        <div class="card">
            <h3>Location</h3>
            <div id="map" class="map"></div>
            <p class="small muted" style="margin-top:8px">{{ $restaurant->latitude ? 'Pinned by the reporter.' : 'Approximate (district centre).' }}</p>
        </div>
    </div>
@endsection
@push('scripts')
<script src="/js/maps.js"></script>
<script>
    RSMaps.load(@json($mapsKey)).then(function () {
        var c = @json($restaurant->coordinates());
        var m = RSMaps.create(document.getElementById('map'), c[0], c[1], {{ $restaurant->latitude ? 15 : 11 }});
        m.marker(c[0], c[1], @json(e($restaurant->name)));
    });
</script>
@endpush
