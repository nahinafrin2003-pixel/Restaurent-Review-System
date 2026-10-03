@php $icons = ['image' => '📷', 'video' => '🎬', 'audio' => '🎙️', 'receipt' => '🧾']; @endphp
@if($evidence->isEmpty())
    <p class="muted small">No evidence attached.</p>
@else
<div class="evidence-grid">
    @foreach($evidence as $ev)
        <div class="evidence">
            <a class="thumb" href="/evidence/{{ $ev->id }}" target="_blank" rel="noopener">
                @if($ev->isImage())<img src="/evidence/{{ $ev->id }}" alt="evidence" loading="lazy">@else{{ $icons[$ev->type] ?? '📎' }}@endif
            </a>
            <div class="body">
                <div class="name">{{ \Illuminate\Support\Str::limit($ev->original_name, 40) }}</div>
                <div class="muted">{{ \App\Models\ComplaintEvidence::TYPES[$ev->type] ?? $ev->type }} · {{ $ev->humanSize() }}</div>
                @if($ev->blur_score !== null)
                    <div style="margin-top:4px"><span class="badge {{ $ev->is_blurry ? 'warn' : 'good' }}" title="Laplacian variance {{ $ev->blur_score }}">{{ $ev->is_blurry ? '⚠ Possibly blurry' : '✔ Sharp' }}</span></div>
                @endif
                @if(!empty($showOcr) && ($ev->ocr_restaurant || $ev->ocr_date || $ev->ocr_amount))
                    <div style="margin-top:6px;background:var(--surface-2);padding:6px;border-radius:6px">
                        <strong>OCR</strong>
                        @if($ev->ocr_restaurant)<div>🏪 {{ $ev->ocr_restaurant }}</div>@endif
                        @if($ev->ocr_date)<div>📅 {{ $ev->ocr_date }}</div>@endif
                        @if($ev->ocr_amount)<div>💰 {{ $ev->ocr_amount }}</div>@endif
                    </div>
                @endif
                <div class="mono muted" style="margin-top:4px;font-size:.68rem" title="SHA-256">{{ substr($ev->sha256, 0, 24) }}…</div>
                <a href="/evidence/{{ $ev->id }}?download=1" class="small">Download</a>
            </div>
        </div>
    @endforeach
</div>
@endif
