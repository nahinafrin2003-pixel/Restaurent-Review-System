<div class="upload-grid">
    @foreach($evidenceTypes as $type => $cfg)
        <div class="field" style="margin:0">
            <label for="{{ $cfg['field'] }}">{{ ['image' => '📷 Photos', 'video' => '🎬 Video', 'audio' => '🎙️ Audio', 'receipt' => '🧾 Receipt'][$type] }}</label>
            <input type="file" id="{{ $cfg['field'] }}" name="{{ $cfg['field'] }}[]" multiple accept="{{ collect(explode(',', $cfg['mimes']))->map(fn ($e) => '.' . $e)->implode(',') }}">
            <div class="hint">{{ $cfg['hint'] }}</div>
        </div>
    @endforeach
</div>
<p class="small muted" style="margin-top:10px">Photos are checked for blur automatically; receipt photos are read with OCR when available. Every file is fingerprinted with SHA-256.</p>
