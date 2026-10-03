@extends('layouts.app')
@section('title', 'My reviews')
@section('content')
    @php $cooling = $activity->cooldown_end_time && $activity->cooldown_end_time->isFuture(); @endphp
    <div class="page-head">
        <div><h1>My reviews</h1><p class="sub">5 reviews per 24-hour window · up to 3 edits per review · edits go back to moderation.</p></div>
        <div class="row"><a href="/user/reviews" class="btn ghost">All approved reviews</a>
            @if($cooling)<span class="badge warn">Cooldown ends {{ $activity->cooldown_end_time->diffForHumans() }}</span>@else<a href="/reviews/create" class="btn">+ Write a review ({{ $activity->remaining_reviews }} left)</a>@endif</div>
    </div>
    <div class="table-wrap"><table>
        <thead><tr><th>Restaurant</th><th>Review</th><th>Score</th><th>Status</th><th>Integrity</th><th>Edits</th><th></th></tr></thead>
        <tbody>
        @forelse($reviews as $r)
            <tr>
                <td>{{ $r->restaurant_name }}<div class="small muted">{{ $r->city }}</div></td>
                <td style="max-width:360px">{{ \Illuminate\Support\Str::limit($r->review_text, 140) }}</td>
                <td><strong>{{ $r->review_score }}</strong>/10</td>
                <td><span class="badge s-{{ $r->status }}">{{ $r->statusLabel() }}</span></td>
                <td><span class="badge {{ $integrity[$r->review_id] ? 'good' : 'bad' }}">{{ $integrity[$r->review_id] ? '✔ HMAC + ECC' : '✘ failed' }}</span></td>
                <td>{{ $r->edit_count }}/{{ $r->max_edit_limit ?: 3 }}</td>
                <td>@if($r->status !== 'rejected' && $r->edit_count < ($r->max_edit_limit ?: 3) && $integrity[$r->review_id])<a class="btn ghost sm" href="/reviews/{{ $r->review_id }}/edit">Edit</a>@endif</td>
            </tr>
        @empty
            <tr><td colspan="7"><div class="empty"><div class="icon">⭐</div>No reviews yet. <a href="/reviews/create">Write your first</a>.</div></td></tr>
        @endforelse
        </tbody>
    </table></div>
@endsection
