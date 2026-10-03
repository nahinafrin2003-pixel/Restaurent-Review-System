@extends('layouts.app')
@section('title', $complaint->reference)
@section('content')
    <div class="page-head">
        <div>
            <div class="row"><h1 class="mono" style="font-family:var(--mono);font-size:1.4rem">{{ $complaint->reference }}</h1>@include('partials.status', ['status' => $complaint->status])</div>
            <p class="sub">{{ $complaint->restaurant->name }} · {{ $complaint->district }} · {{ $complaint->categoryLabel() }}</p>
        </div>
        <div class="row">
            @if($complaint->isVerified())
                <a href="/complaints/{{ $complaint->id }}/report" class="btn">⬇ Verification report (PDF)</a>
            @endif
            <a href="{{ $complaint->publicUrl() }}" target="_blank" class="btn ghost">Public verification page</a>
        </div>
    </div>

    @unless($integrity['ok'])
        <div class="alert error"><strong>Integrity warning:</strong> this complaint's stored data does not match its HMAC/ECC signature. The administrator has been alerted.</div>
    @endunless

    @if($complaint->status === 'evidence_requested')
        <div class="alert warn"><strong>The officer needs more evidence.</strong> See the latest note in the timeline, then upload files below — your complaint returns to the verification queue automatically.</div>
    @endif

    <div class="split">
        <div class="stack">
            <div class="card">
                <div class="card-head"><h2>{{ $complaint->title }}</h2>@include('partials.integrity')</div>
                <p style="white-space:pre-line;color:var(--ink)">{{ $complaint->description }}</p>
                <dl class="dl">
                    <dt>Incident date</dt><dd>{{ optional($complaint->incident_date)->format('d M Y') ?? '—' }}</dd>
                    <dt>Category</dt><dd>{{ $complaint->categoryLabel() }} @if($complaint->auto_category && $complaint->auto_category !== $complaint->category)<span class="small muted">(auto-detected: {{ \App\Support\ComplaintCategory::label($complaint->auto_category) }})</span>@endif</dd>
                    <dt>Submitted</dt><dd>{{ $complaint->created_at->format('d M Y, H:i') }}</dd>
                    @if($complaint->outcome)<dt>Outcome</dt><dd>{{ $complaint->outcome }}</dd>@endif
                </dl>
            </div>

            <div class="card">
                <h2>Evidence</h2>
                @include('partials.evidence-list', ['evidence' => $complaint->evidence])
                @if($complaint->acceptsEvidence())
                    <hr>
                    <form method="POST" action="/complaints/{{ $complaint->id }}/evidence" enctype="multipart/form-data" onsubmit="this.querySelector('button').disabled=true">
                        @csrf
                        <h3>Add evidence</h3>
                        @include('partials.evidence-inputs')
                        <button class="btn" style="margin-top:12px">Upload</button>
                    </form>
                @endif
            </div>

            <div class="card">
                <h2>Messages with the officer</h2>
                @include('partials.messages', ['messages' => $complaint->messages])
            </div>
        </div>

        <div class="stack">
            <div class="card">
                <h3>Status history</h3>
                @include('partials.timeline', ['history' => $complaint->history])
            </div>
            <div class="card" style="text-align:center">
                <h3>Public QR verification</h3>
                <div class="qr-box">{!! \App\Services\QrService::svg($complaint->publicUrl(), 170) !!}</div>
                <p class="small muted" style="margin-top:8px">Anyone can scan this to confirm the complaint's status and integrity — no personal data is shown.</p>
            </div>
        </div>
    </div>
@endsection
