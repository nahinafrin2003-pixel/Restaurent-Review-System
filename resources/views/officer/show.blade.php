@extends('layouts.app')
@section('title', $complaint->reference)
@section('content')
    <div class="page-head">
        <div>
            <div class="row"><h1 style="font-family:var(--mono);font-size:1.4rem">{{ $complaint->reference }}</h1>@include('partials.status', ['status' => $complaint->status])</div>
            <p class="sub"><a href="/restaurants/{{ $complaint->restaurant_id }}">{{ $complaint->restaurant->name }}</a> · {{ $complaint->district }} · filed {{ $complaint->created_at->format('d M Y H:i') }} ({{ $complaint->created_at->diffForHumans() }})</p>
        </div>
        <div class="row">
            <a href="/officer" class="btn ghost">← Queue</a>
            @if($complaint->isVerified())<a href="/complaints/{{ $complaint->id }}/report" class="btn">⬇ PDF report</a>@endif
        </div>
    </div>

    @unless($integrity['ok'])
        <div class="alert error"><strong>Tamper alert:</strong> the decrypted complaint does not match its stored {{ ! $integrity['mac'] ? 'HMAC' : '' }}{{ ! $integrity['mac'] && ! $integrity['signature'] ? ' and ' : '' }}{{ ! $integrity['signature'] ? 'ECC signature' : '' }}. Do not verify until investigated.</div>
    @endunless

    <div class="split">
        <div class="stack">
            <div class="card">
                <div class="card-head"><h2>{{ $complaint->title }}</h2>@include('partials.integrity')</div>
                <p style="white-space:pre-line;color:var(--ink)">{{ $complaint->description }}</p>
                <dl class="dl">
                    <dt>Category</dt><dd>{{ $complaint->categoryLabel() }}
                        @if($complaint->auto_category)<span class="small muted"> · auto-classifier said <strong>{{ \App\Support\ComplaintCategory::label($complaint->auto_category) }}</strong> ({{ (int) round(($complaint->category_confidence ?? 0) * 100) }}% confidence)</span>@endif</dd>
                    <dt>Incident date</dt><dd>{{ optional($complaint->incident_date)->format('d M Y') ?? '—' }}</dd>
                    <dt>Complainant</dt><dd>{{ $complaint->user->username ?? '—' }} <span class="small muted">· {{ $otherComplaintsByUser }} other complaint(s) · {{ (int) ($complaint->user->points ?? 0) }} pts</span></dd>
                    <dt>Assigned officer</dt><dd>{{ $complaint->officer->username ?? 'Unassigned' }}</dd>
                    @if($complaint->duplicateOf)<dt>Duplicate of</dt><dd><a href="/officer/complaints/{{ $complaint->duplicate_of_id }}" class="mono">{{ $complaint->duplicateOf->reference }}</a></dd>@endif
                    @if($complaint->outcome)<dt>Public outcome</dt><dd>{{ $complaint->outcome }}</dd>@endif
                </dl>
            </div>

            <div class="card">
                <div class="card-head"><h2>Evidence ({{ $complaint->evidence->count() }})</h2>
                    <span class="small muted">{{ $ocrAvailable ? 'OCR: Tesseract available' : 'OCR: Tesseract not installed' }}</span></div>
                @include('partials.evidence-list', ['evidence' => $complaint->evidence, 'showOcr' => true])
                <p class="small muted" style="margin-top:8px">Every view or download of evidence is recorded in the audit log.</p>
            </div>

            @php $suggested = $complaint->duplicateCandidates->where('status', '!=', 'dismissed'); @endphp
            @if($suggested->isNotEmpty() || $reverseDuplicates->isNotEmpty())
                <div class="card">
                    <h2>Possible duplicates</h2>
                    <p class="small muted">Same restaurant, similar text/category/date. Score = 0.6·text cosine + 0.25·category + 0.15·date proximity.</p>
                    <div class="table-wrap"><table>
                        <thead><tr><th>Complaint</th><th>Category</th><th>Status</th><th>Score</th><th></th></tr></thead>
                        <tbody>
                        @foreach($suggested as $d)
                            <tr>
                                <td><a class="mono" href="/officer/complaints/{{ $d->candidate_id }}">{{ $d->candidate->reference ?? '#' . $d->candidate_id }}</a><div class="small muted">{{ \Illuminate\Support\Str::limit($d->candidate->title ?? '', 60) }}</div></td>
                                <td>{{ $d->candidate ? $d->candidate->categoryLabel() : '' }}</td>
                                <td>@if($d->candidate)@include('partials.status', ['status' => $d->candidate->status])@endif</td>
                                <td><strong>{{ number_format($d->score * 100) }}%</strong></td>
                                <td>
                                    @if($d->status === 'suggested')
                                        <form method="POST" action="/officer/complaints/{{ $complaint->id }}/duplicates/{{ $d->id }}" class="row">@csrf
                                            <button name="action" value="link" class="btn sm">Link</button>
                                            <button name="action" value="dismiss" class="btn ghost sm">Dismiss</button>
                                        </form>
                                    @else<span class="badge good">Linked</span>@endif
                                </td>
                            </tr>
                        @endforeach
                        @foreach($reverseDuplicates as $d)
                            <tr>
                                <td><a class="mono" href="/officer/complaints/{{ $d->complaint_id }}">{{ $d->complaint->reference }}</a><div class="small muted">newer report pointing here</div></td>
                                <td>{{ $d->complaint->categoryLabel() }}</td>
                                <td>@include('partials.status', ['status' => $d->complaint->status])</td>
                                <td><strong>{{ number_format($d->score * 100) }}%</strong></td>
                                <td>{!! $d->status === 'linked' ? '<span class="badge good">Linked</span>' : '<span class="small muted">review on that complaint</span>' !!}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table></div>
                </div>
            @endif

            <div class="card">
                <h2>Messages with customer</h2>
                @include('partials.messages', ['messages' => $complaint->messages])
            </div>
        </div>

        <div class="stack">
            <div class="card">
                <h3>Actions</h3>
                @if(! $complaint->assigned_officer_id || ($authUser->isAdmin()))
                    <form method="POST" action="/officer/complaints/{{ $complaint->id }}/assign" class="stack" style="margin-bottom:14px">
                        @csrf
                        @if($authUser->isAdmin() && $officers->isNotEmpty())
                            <div class="field" style="margin:0"><label>Assign to</label>
                                <select name="officer_id"><option value="">Myself</option>@foreach($officers as $o)<option value="{{ $o->user_id }}" @selected($complaint->assigned_officer_id === $o->user_id)>{{ $o->username }}</option>@endforeach</select></div>
                        @endif
                        <button class="btn block">{{ $complaint->assigned_officer_id ? 'Reassign' : 'Pick up this complaint' }}</button>
                    </form>
                @endif

                @if(count($transitions))
                    <form method="POST" action="/officer/complaints/{{ $complaint->id }}/status">
                        @csrf
                        <div class="field"><label for="status">Move to</label>
                            <select name="status" id="status" required onchange="document.getElementById('outcome-field').style.display=['resolved','rejected','closed'].includes(this.value)?'block':'none'">
                                @foreach($transitions as $t)<option value="{{ $t }}">{{ \App\Support\ComplaintStatus::label($t) }}</option>@endforeach
                            </select></div>
                        <div class="field"><label for="note">Note to customer</label>
                            <textarea name="note" id="note" rows="3" maxlength="2000" placeholder="Required when requesting evidence">{{ old('note') }}</textarea>
                            <div class="hint">Encrypted; shown in the customer's timeline and e-mailed.</div></div>
                        <div class="field" id="outcome-field" style="display:none"><label for="outcome">Public outcome</label>
                            <input name="outcome" id="outcome" maxlength="255" value="{{ old('outcome') }}" placeholder="e.g. Fined Tk 50,000 and kitchen sealed for 3 days">
                            <div class="hint">Shown anonymised on the transparency page.</div></div>
                        <button class="btn block">Update status</button>
                    </form>
                @else
                    <p class="small muted">This complaint is {{ strtolower($complaint->statusLabel()) }}; no further transitions.</p>
                @endif
            </div>

            <div class="card">
                <h3>Status history</h3>
                @include('partials.timeline', ['history' => $complaint->history, 'showActorNames' => true])
            </div>

            <div class="card">
                <h3>Cryptographic record</h3>
                <dl class="dl" style="grid-template-columns:1fr">
                    <dt>Content SHA-256</dt><dd class="mono">{{ $complaint->contentHash() }}</dd>
                    <dt>HMAC-SHA256</dt><dd class="mono">{{ $complaint->mac }}</dd>
                    <dt>ECDSA (r, s) · key #{{ $complaint->signature['kid'] ?? '?' }}</dt><dd class="mono">{{ $complaint->signature['r'] ?? '' }}<br>{{ $complaint->signature['s'] ?? '' }}</dd>
                    @if($complaint->report_sha256)<dt>Report SHA-256</dt><dd class="mono">{{ $complaint->report_sha256 }}</dd>@endif
                </dl>
                <a href="{{ $complaint->publicUrl() }}" target="_blank" class="small">Open public verification page →</a>
            </div>
        </div>
    </div>
@endsection
