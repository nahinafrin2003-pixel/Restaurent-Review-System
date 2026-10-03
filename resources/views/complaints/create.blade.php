@extends('layouts.app')
@section('title', 'File a complaint')
@section('container_class', 'narrow')
@section('content')
    <div class="page-head"><div><h1>File a consumer complaint</h1><p class="sub">Your complaint text is encrypted (RSA-2048) and signed (secp256k1) the moment you submit.</p></div></div>

    <form method="POST" action="/complaints" enctype="multipart/form-data" class="stack" onsubmit="var b=document.getElementById('submitbtn');b.disabled=true;b.innerHTML='<span class=&quot;spinner&quot;></span> Encrypting &amp; uploading…'">
        @csrf
        <div class="card">
            <h2>1. Restaurant</h2>
            <div class="field">
                <label for="restaurant_id">Which restaurant?</label>
                <select id="restaurant_id" name="restaurant_id" required>
                    <option value="">— Select a restaurant —</option>
                    @foreach($restaurants as $r)
                        <option value="{{ $r->id }}" @selected((string) old('restaurant_id', $selectedRestaurant) === (string) $r->id)>{{ $r->name }} ({{ $r->district }})</option>
                    @endforeach
                </select>
                <div class="hint">Not listed? <a href="/restaurants/create?return=/complaints/create">Add it with a map pin</a>.</div>
            </div>
        </div>

        <div class="card">
            <h2>2. What happened?</h2>
            <div class="form-grid">
                <div class="field">
                    <label for="category">Category</label>
                    <select id="category" name="category">
                        <option value="auto">✨ Detect automatically</option>
                        @foreach($categories as $key => $label)<option value="{{ $key }}" @selected(old('category') === $key)>{{ $label }}</option>@endforeach
                    </select>
                    <div class="hint" id="cat-hint">We'll categorise from your description.</div>
                </div>
                <div class="field">
                    <label for="incident_date">Date of incident</label>
                    <input type="date" id="incident_date" name="incident_date" value="{{ old('incident_date', now()->toDateString()) }}" max="{{ now()->toDateString() }}" required>
                </div>
            </div>
            <div class="field">
                <label for="title">Short title</label>
                <input id="title" name="title" value="{{ old('title') }}" maxlength="150" placeholder="e.g. Got sick after eating chicken biryani" required>
            </div>
            <div class="field">
                <label for="description">Describe the incident</label>
                <textarea id="description" name="description" rows="6" maxlength="4000" placeholder="What did you order, when, what went wrong, how much you paid…" required>{{ old('description') }}</textarea>
                <div class="hint">At least 20 characters. Mention symptoms, prices, or certificates where relevant.</div>
            </div>
        </div>

        <div class="card">
            <h2>3. Evidence <span class="small muted">(optional, but speeds up verification)</span></h2>
            @include('partials.evidence-inputs')
        </div>

        <div class="row"><button class="btn danger" id="submitbtn">Submit complaint</button><a href="/complaints" class="btn ghost">Cancel</a></div>
    </form>
@endsection
@push('scripts')
<script>
    // Live preview of the keyword categoriser (the server makes the final decision).
    (function () {
        var kw = @json(\App\Support\ComplaintCategory::KEYWORDS), labels = @json(\App\Support\ComplaintCategory::LABELS);
        var hint = document.getElementById('cat-hint'), sel = document.getElementById('category');
        function guess() {
            if (sel.value !== 'auto') { hint.textContent = ''; return; }
            var t = ' ' + (document.getElementById('title').value + ' ' + document.getElementById('description').value).toLowerCase().replace(/[^a-z0-9\s]+/g, ' ').replace(/\s+/g, ' ') + ' ';
            var best = null, bestScore = 0;
            Object.keys(kw).forEach(function (c) {
                var s = 0; Object.keys(kw[c]).forEach(function (k) { if (t.indexOf(' ' + k + ' ') !== -1) s += kw[c][k]; });
                if (s > bestScore) { bestScore = s; best = c; }
            });
            hint.textContent = bestScore >= 2 ? 'Looks like: ' + labels[best] : "We'll categorise from your description.";
        }
        ['title', 'description'].forEach(function (id) { document.getElementById(id).addEventListener('input', guess); });
        sel.addEventListener('change', guess);
    })();
</script>
@endpush
