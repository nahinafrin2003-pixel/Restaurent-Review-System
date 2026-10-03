@if($reviews->isEmpty())
    <div class="empty"><div class="icon">✅</div>Nothing waiting.</div>
@else
<div class="table-wrap"><table>
    <thead><tr><th>#</th><th>Restaurant</th><th>Review</th><th>Score</th><th>Integrity</th><th>Submitted</th><th></th></tr></thead>
    <tbody>
    @foreach($reviews as $r)
        @php $ok = $r->verifyIntegrity(); @endphp
        <tr>
            <td>{{ $r->review_id }}@if($r->edit_count)<div class="small muted">edit {{ $r->edit_count }}</div>@endif</td>
            <td>{{ $r->restaurant_name }}<div class="small muted">{{ $r->city }}</div></td>
            <td style="max-width:340px">{{ \Illuminate\Support\Str::limit($r->review_text, 160) }}</td>
            <td><strong>{{ $r->review_score }}</strong>/10</td>
            <td><span class="badge {{ $ok ? 'good' : 'bad' }}">{{ $ok ? '✔ valid' : '✘ tampered' }}</span></td>
            <td class="small">{{ optional($r->created_at)->diffForHumans() }}</td>
            <td>
                @if($r->status === 'pending')
                <div class="row">
                    <form method="POST" action="/admin/reviews/{{ $r->review_id }}/approve">@csrf<button class="btn sm" @disabled(! $ok)>Approve</button></form>
                    <form method="POST" action="/admin/reviews/{{ $r->review_id }}/reject">@csrf<button class="btn danger sm">Reject</button></form>
                </div>
                @else<span class="badge s-wait">Waitlisted</span>@endif
            </td>
        </tr>
    @endforeach
    </tbody>
</table></div>
@endif
