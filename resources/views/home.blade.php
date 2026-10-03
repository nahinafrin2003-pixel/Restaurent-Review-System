@extends('layouts.app')
@section('title', 'Home')
@section('content')
    <section class="hero">
        <h1>Honest restaurant reviews. Verified food-safety complaints.</h1>
        <p>Rate where you eat, report food poisoning, hygiene problems or overcharging with evidence, and follow your complaint from submission to resolution — just like a BSTI consumer complaint, but transparent.</p>
        <div class="row" style="margin-top:18px">
            @if($authUser)
                <a href="{{ $authUser->homePath() }}" class="btn accent">Go to my dashboard</a>
            @else
                <a href="/register" class="btn accent">File a complaint</a>
                <a href="/login" class="btn light">Log in</a>
            @endif
            <a href="/transparency" class="btn light">See resolved cases</a>
        </div>
    </section>

    <div class="grid grid-4" style="margin-top:20px">
        <div class="kpi brand"><div class="label">Restaurants</div><div class="value">{{ $stats['restaurants'] }}</div></div>
        <div class="kpi info"><div class="label">Approved reviews</div><div class="value">{{ $stats['reviews'] }}</div></div>
        <div class="kpi accent"><div class="label">Complaints filed</div><div class="value">{{ $stats['complaints'] }}</div></div>
        <div class="kpi brand"><div class="label">Verified &amp; resolved</div><div class="value">{{ $stats['resolved'] }}</div></div>
    </div>

    <div class="split" style="margin-top:20px">
        <div class="card">
            <div class="card-head"><h2>Most trusted restaurants</h2><a href="/restaurants" class="small">All restaurants →</a></div>
            @forelse($topRestaurants as $r)
                <div class="row between" style="padding:10px 0;border-bottom:1px solid var(--line)">
                    <div><a href="/restaurants/{{ $r->id }}"><strong>{{ $r->name }}</strong></a><div class="small muted">{{ $r->district }}{{ $r->address ? ' · ' . $r->address : '' }}</div></div>
                    @include('partials.trust', ['restaurant' => $r])
                </div>
            @empty
                <div class="empty"><div class="icon">🍽️</div>No restaurants yet.</div>
            @endforelse
        </div>
        <div class="stack">
            <div class="card">
                <h3>On the watch list</h3>
                <p class="small">Restaurants with verified complaints, lowest trust first.</p>
                @forelse($watchList as $r)
                    <div class="row between" style="padding:6px 0">
                        <a href="/restaurants/{{ $r->id }}">{{ $r->name }}</a>
                        <span class="badge {{ $r->trustLevel() }}">{{ number_format($r->trust_score, 1) }} ★ · {{ $r->verified_complaints }} verified</span>
                    </div>
                @empty
                    <p class="small muted">Nothing here — good news.</p>
                @endforelse
            </div>
            <div class="card">
                <h3>How it works</h3>
                <ol class="small" style="padding-left:18px;margin:0;color:var(--ink-2)">
                    <li>Submit a complaint with photos, video, audio or the receipt.</li>
                    <li>A verification officer reviews the evidence and may ask for more.</li>
                    <li>Verified cases lower the restaurant's trust score and go to investigation.</li>
                    <li>You get a signed PDF report, a public QR verification page and reward points.</li>
                </ol>
            </div>
        </div>
    </div>
@endsection
