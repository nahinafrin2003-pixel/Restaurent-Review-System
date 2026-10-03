<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    @page { margin: 28px 34px; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 10.5px; color: #1b1f1c; }
    .head { background: #0f4429; color: #fff; padding: 14px 16px; }
    .head h1 { margin: 0; font-size: 17px; }
    .head .sub { color: #cfe5d7; font-size: 10px; }
    h2 { font-size: 12.5px; color: #0f4429; border-bottom: 1px solid #d9d6cc; padding-bottom: 3px; margin: 16px 0 6px; }
    table { width: 100%; border-collapse: collapse; }
    td, th { padding: 4px 6px; vertical-align: top; text-align: left; }
    .kv td:first-child { width: 28%; color: #5c645e; font-weight: bold; }
    .grid th { background: #f0efe9; font-size: 9.5px; }
    .grid td, .grid th { border: 1px solid #e2e0d8; }
    .mono { font-family: DejaVu Sans Mono, monospace; font-size: 8.5px; word-wrap: break-word; }
    .ok { color: #17603a; font-weight: bold; } .bad { color: #b42318; font-weight: bold; }
    .box { background: #f6f5f1; border: 1px solid #e2e0d8; padding: 8px; white-space: pre-line; }
    .foot { margin-top: 18px; font-size: 8.5px; color: #7b847d; }
</style>
</head>
<body>
<div class="head">
    <table><tr>
        <td><h1>Complaint Verification Report</h1><div class="sub">RestoSafe consumer complaint platform · Reference {{ $complaint->reference }}</div></td>
        <td style="text-align:right;width:120px"><img src="{{ $qr }}" width="96" height="96" style="background:#fff;padding:3px"></td>
    </tr></table>
</div>

<h2>Summary</h2>
<table class="kv">
    <tr><td>Reference</td><td>{{ $complaint->reference }}</td></tr>
    <tr><td>Status at generation</td><td>{{ $complaint->statusLabel() }}</td></tr>
    <tr><td>Restaurant</td><td>{{ $complaint->restaurant->name }}, {{ $complaint->restaurant->address ? $complaint->restaurant->address . ', ' : '' }}{{ $complaint->district }}</td></tr>
    <tr><td>Category</td><td>{{ $complaint->categoryLabel() }}</td></tr>
    <tr><td>Incident date</td><td>{{ optional($complaint->incident_date)->format('d M Y') ?? '—' }}</td></tr>
    <tr><td>Submitted</td><td>{{ $complaint->created_at->format('d M Y H:i') }}</td></tr>
    <tr><td>Verified</td><td>{{ optional($complaint->verified_at)->format('d M Y H:i') ?? '—' }}</td></tr>
    <tr><td>Verification officer</td><td>{{ $complaint->officer->username ?? '—' }}</td></tr>
</table>

<h2>Complaint</h2>
<p><strong>{{ $complaint->title }}</strong></p>
<div class="box">{{ $complaint->description }}</div>

<h2>Evidence ({{ $complaint->evidence->count() }})</h2>
@if($complaint->evidence->isEmpty())
    <p>No evidence files were attached.</p>
@else
<table class="grid">
    <tr><th>#</th><th>Type</th><th>Size</th><th>Quality / OCR</th><th>SHA-256 fingerprint</th></tr>
    @foreach($complaint->evidence as $i => $ev)
        <tr>
            <td>{{ $i + 1 }}</td><td>{{ ucfirst($ev->type) }}</td><td>{{ $ev->humanSize() }}</td>
            <td>@if($ev->blur_score !== null){{ $ev->is_blurry ? 'Possibly blurry' : 'Sharp' }} ({{ $ev->blur_score }})@endif
                @if($ev->ocr_amount)<br>OCR amount: {{ $ev->ocr_amount }}@endif @if($ev->ocr_date)<br>OCR date: {{ $ev->ocr_date }}@endif</td>
            <td class="mono">{{ $ev->sha256 }}</td>
        </tr>
    @endforeach
</table>
@endif

<h2>Status history</h2>
<table class="grid">
    <tr><th style="width:24%">Date</th><th style="width:26%">Status</th><th>Note</th></tr>
    @foreach($complaint->history as $h)
        <tr><td>{{ $h->created_at->format('d M Y H:i') }}</td><td>{{ $h->toLabel() }}</td><td>{{ $h->note }}</td></tr>
    @endforeach
</table>

<h2>Tamper evidence</h2>
<table class="kv">
    <tr><td>Integrity at generation</td><td>HMAC: <span class="{{ $integrity['mac'] ? 'ok' : 'bad' }}">{{ $integrity['mac'] ? 'VALID' : 'INVALID' }}</span> · ECC signature: <span class="{{ $integrity['signature'] ? 'ok' : 'bad' }}">{{ $integrity['signature'] ? 'VALID' : 'INVALID' }}</span></td></tr>
    <tr><td>Content SHA-256</td><td class="mono">{{ $contentHash }}</td></tr>
    <tr><td>HMAC-SHA256</td><td class="mono">{{ $complaint->mac }}</td></tr>
    <tr><td>ECDSA r (secp256k1)</td><td class="mono">{{ $complaint->signature['r'] ?? '' }}</td></tr>
    <tr><td>ECDSA s</td><td class="mono">{{ $complaint->signature['s'] ?? '' }}</td></tr>
    <tr><td>Public verification</td><td class="mono">{{ $complaint->publicUrl() }}</td></tr>
</table>

<div class="foot">
    Generated {{ $generatedAt->format('d M Y H:i:s') }}{{ $generatedBy ? ' by ' . $generatedBy->roleLabel() : '' }}. The SHA-256 of this PDF is recorded on the platform; scan the QR code to compare it.
    All cryptography (SHA-256, HMAC, RSA-OAEP, secp256k1 ECDSA/EC-ElGamal) is implemented from scratch in the application.
</div>
</body>
</html>
