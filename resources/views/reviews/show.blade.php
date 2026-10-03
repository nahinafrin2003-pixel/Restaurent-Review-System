@extends('layouts.app')
@section('title', 'Review')
@section('container_class', 'narrow')
@section('content')
    <div class="card">
        <div class="row between"><h1 style="margin:0">{{ $review->restaurant_name }}</h1><span class="badge s-{{ $review->status }}">{{ $review->statusLabel() }}</span></div>
        <p class="muted">{{ $review->city }} · {{ optional($review->created_at)->format('d M Y') }} · <strong>{{ $review->review_score }}/10</strong></p>
        <p style="white-space:pre-line;color:var(--ink)">{{ $review->review_text }}</p>
        <span class="badge {{ $integrity ? 'good' : 'bad' }}">{{ $integrity ? '✔ HMAC + ECC signature valid' : '✘ Integrity check failed' }}</span>
    </div>
@endsection
