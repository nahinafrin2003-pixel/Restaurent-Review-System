<div class="chat" id="chat">
    @forelse($messages as $m)
        <div class="msg {{ $m->sender_id === $authUser->user_id ? 'me' : '' }}">
            <div class="meta">{{ $m->sender_id === $authUser->user_id ? 'You' : ($m->sender && $m->sender->isStaff() ? 'Verification officer' : 'Customer') }} · {{ $m->created_at->format('d M, H:i') }} @unless($m->macValid())<span class="badge bad">MAC ✘</span>@endunless</div>
            <div class="text">{{ $m->body }}</div>
        </div>
    @empty
        <p class="small muted">No messages yet.</p>
    @endforelse
</div>
@if(!in_array($complaint->status, ['closed', 'rejected']))
    <form method="POST" action="/complaints/{{ $complaint->id }}/messages" style="margin-top:10px">
        @csrf
        <textarea name="body" rows="2" maxlength="2000" placeholder="Write a message…" required style="min-height:70px"></textarea>
        <div class="row" style="margin-top:8px"><button class="btn sm">Send</button><span class="small muted">Messages are encrypted with EC-ElGamal (secp256k1) and HMAC-protected.</span></div>
    </form>
@endif
<script>var c = document.getElementById('chat'); if (c) c.scrollTop = c.scrollHeight;</script>
