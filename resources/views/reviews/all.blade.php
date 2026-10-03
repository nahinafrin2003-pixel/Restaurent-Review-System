@extends('layouts.app')
@section('title', 'Approved reviews')
@section('content')
    <div class="page-head"><div><h1>Approved reviews</h1><p class="sub">Each review's HMAC and ECC signature are verified as the page loads.</p></div></div>
    <div class="grid grid-2">
        @forelse($reviews as $r)
            <div class="card">
                <div class="row between"><strong>@if($r->restaurant_id)<a href="/restaurants/{{ $r->restaurant_id }}">{{ $r->restaurant_name }}</a>@else{{ $r->restaurant_name }}@endif</strong><span class="tag">{{ $r->review_score }}/10</span></div>
                <div class="small muted">{{ $r->city }} · {{ optional($r->created_at)->format('d M Y') }}</div>
                <p style="margin:8px 0;color:var(--ink)">{{ $r->review_text }}</p>
                <span class="badge {{ $integrity[$r->review_id] ? 'good' : 'bad' }}">{{ $integrity[$r->review_id] ? '✔ verified' : '✘ tampered' }}</span>
            </div>
        @empty
            <div class="card empty" style="grid-column:1/-1">No approved reviews yet.</div>
        @endforelse
    </div>
    {{ $reviews->links('partials.pager') }}
@endsection
