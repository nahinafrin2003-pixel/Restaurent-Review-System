<ul class="timeline">
    @foreach($history as $h)
        <li>
            <div>@include('partials.status', ['status' => $h->to_status])</div>
            <div class="when">{{ $h->created_at->format('d M Y, H:i') }} · @if(! $h->actor) System @elseif($h->changed_by === $authUser?->user_id) You @elseif($h->actor->isStaff()) {{ ($showActorNames ?? false) ? $h->actor->username : 'Verification officer' }} @else Customer @endif</div>
            @if($h->note)<div class="note">{{ $h->note }}</div>@endif
        </li>
    @endforeach
</ul>
