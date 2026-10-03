<div style="font-family:Segoe UI,Arial,sans-serif;max-width:560px;margin:auto;color:#1b1f1c">
    <div style="background:#0f4429;color:#fff;padding:16px 20px;border-radius:10px 10px 0 0">
        <strong>RestoSafe</strong> · Complaint update
    </div>
    <div style="border:1px solid #e2e0d8;border-top:0;padding:20px;border-radius:0 0 10px 10px">
        <p>Your complaint <strong>{{ $complaint->reference }}</strong> about <strong>{{ $complaint->restaurant->name ?? 'the restaurant' }}</strong> has been updated.</p>
        <p style="font-size:16px">
            @if($fromLabel && $fromLabel !== $toLabel)<span style="color:#7b847d">{{ $fromLabel }}</span> → @endif
            <strong style="color:#17603a">{{ $toLabel }}</strong>
        </p>
        @if($note)
            <div style="background:#f0efe9;border-radius:8px;padding:10px 12px;white-space:pre-line">{{ $note }}</div>
        @endif
        <p style="margin-top:18px"><a href="{{ $url }}" style="background:#17603a;color:#fff;padding:10px 16px;border-radius:8px;text-decoration:none">View complaint</a></p>
        <p style="color:#7b847d;font-size:12px">You are receiving this because you filed a consumer complaint on RestoSafe.</p>
    </div>
</div>
