@extends('layouts.app')
@section('title', 'Rewards')
@section('content')
    <div class="page-head"><div><h1>Rewards</h1><p class="sub">Earn points and badges when your complaints are verified by an officer.</p></div></div>
    <div class="grid grid-3">
        <div class="kpi accent"><div class="label">Points</div><div class="value">{{ (int) $user->points }}</div></div>
        <div class="kpi brand"><div class="label">Verified complaints</div><div class="value">{{ $verifiedCount }}</div></div>
        <div class="kpi info"><div class="label">Badges</div><div class="value">{{ $earned->count() }} / {{ count($catalog) }}</div></div>
    </div>
    <div class="grid grid-3" style="margin-top:20px">
        @foreach($catalog as $key => $b)
            <div class="badge-card {{ $earned->has($key) ? '' : 'locked' }}">
                <div class="medal">{{ $b['icon'] }}</div>
                <div><strong>{{ $b['label'] }}</strong><div class="small muted">{{ $b['rule'] }}</div>
                    @if($earned->has($key))<div class="small" style="color:var(--brand)">Earned {{ $earned[$key]->awarded_at->format('d M Y') }}</div>@endif</div>
            </div>
        @endforeach
    </div>
    <div class="card" style="margin-top:20px">
        <h2>How points work</h2>
        <p class="small">+{{ \App\Services\RewardService::POINTS_VERIFIED }} when a complaint is verified (+{{ \App\Services\RewardService::POINTS_EVIDENCE_BONUS }} bonus if you attached evidence) · +{{ \App\Services\RewardService::POINTS_RESOLVED }} when it is resolved.</p>
        <div class="table-wrap"><table>
            <thead><tr><th>Date</th><th>Reason</th><th>Points</th></tr></thead>
            <tbody>
            @forelse($events as $e)
                <tr><td class="small">{{ $e->created_at->format('d M Y') }}</td><td>{{ $e->reason }}</td><td><strong>+{{ $e->points }}</strong></td></tr>
            @empty
                <tr><td colspan="3" class="muted">No points yet.</td></tr>
            @endforelse
            </tbody>
        </table></div>
    </div>
@endsection
