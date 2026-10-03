@extends('layouts.app')
@section('title', 'Verify ' . $reference)
@section('container_class', 'narrow')
@section('content')
    @if(! $complaint)
        <div class="card empty">
            <div class="icon">⚠️</div>
            <h2>Could not verify {{ $reference }}</h2>
            <p>This verification link is invalid or incomplete. Scan the QR code on the official report again.</p>
        </div>
    @else
        <div class="card">
            <div class="row between">
                <div><div class="small muted">Complaint reference</div><h1 class="mono" style="font-family:var(--mono);margin:0">{{ $complaint->reference }}</h1></div>
                @include('partials.status', ['status' => $complaint->status])
            </div>
            <hr>
            <div class="alert {{ $integrity['ok'] ? 'success' : 'error' }}" style="margin:0 0 16px">
                @if($integrity['ok'])
                    <strong>✔ Authentic record.</strong> The complaint's content matches its HMAC-SHA256 and its secp256k1 digital signature — it has not been altered since submission.
                @else
                    <strong>✘ Integrity check failed.</strong> The stored record does not match its signature.
                @endif
            </div>
            <dl class="dl">
                <dt>Restaurant</dt><dd><a href="/restaurants/{{ $complaint->restaurant_id }}">{{ $complaint->restaurant->name }}</a> ({{ $complaint->district }})</dd>
                <dt>Category</dt><dd>{{ $complaint->categoryLabel() }}</dd>
                <dt>Submitted</dt><dd>{{ $complaint->created_at->format('d M Y') }}</dd>
                <dt>Verified</dt><dd>{{ $complaint->verified_at ? $complaint->verified_at->format('d M Y') : 'Not yet verified' }}</dd>
                @if($complaint->resolved_at)<dt>Closed</dt><dd>{{ $complaint->resolved_at->format('d M Y') }}</dd>@endif
                @if($complaint->outcome)<dt>Outcome</dt><dd>{{ $complaint->outcome }}</dd>@endif
                <dt>Evidence files</dt><dd>{{ $complaint->evidence->count() }}</dd>
                <dt>Content hash</dt><dd class="mono">{{ $complaint->contentHash() }}</dd>
                @if($complaint->report_sha256)<dt>Report SHA-256</dt><dd class="mono">{{ $complaint->report_sha256 }}</dd>@endif
            </dl>
            @if($complaint->evidence->isNotEmpty())
                <h3 style="margin-top:18px">Evidence fingerprints</h3>
                <ul class="small mono" style="padding-left:18px">@foreach($complaint->evidence as $ev)<li>{{ ucfirst($ev->type) }} · {{ $ev->sha256 }}</li>@endforeach</ul>
            @endif
            <p class="small muted" style="margin-top:16px">No personal information about the complainant is published. Compare the report SHA-256 above with the hash of the PDF you hold to confirm it is genuine.</p>
        </div>
    @endif
@endsection
