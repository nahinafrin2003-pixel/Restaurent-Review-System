@extends('layouts.app')
@section('title', 'Verification queue')
@section('content')
    <div class="page-head">
        <div><h1>Verification queue</h1><p class="sub">Oldest open complaints first. Pick one up to start verification.</p></div>
        <div class="row"><a href="/officer/analytics" class="btn ghost">Analytics</a><a href="/officer/heatmap" class="btn ghost">Heatmap</a></div>
    </div>

    <div class="grid grid-4">
        <div class="kpi info"><div class="label">Assigned to me</div><div class="value">{{ $mine }}</div></div>
        <div class="kpi accent"><div class="label">Unassigned</div><div class="value">{{ $unassigned }}</div></div>
        <div class="kpi"><div class="label">Awaiting evidence</div><div class="value">{{ $counts['evidence_requested'] }}</div></div>
        <div class="kpi danger"><div class="label">Unread customer messages</div><div class="value">{{ $unreadMessages }}</div></div>
    </div>

    <div class="tabs" style="margin-top:20px">
        @foreach(['open' => 'All open', 'mine' => 'Mine', 'unassigned' => 'Unassigned', 'closed' => 'Finished', 'all' => 'Everything'] as $key => $label)
            <a href="?scope={{ $key }}" class="{{ $scope === $key ? 'active' : '' }}">{{ $label }}</a>
        @endforeach
    </div>

    <form class="filters card flat" method="GET" style="margin-bottom:16px">
        <input type="hidden" name="scope" value="{{ $scope }}">
        <div class="field"><label>Reference</label><input name="ref" value="{{ request('ref') }}" placeholder="CMP-2026-…"></div>
        <div class="field"><label>Status</label><select name="status"><option value="">Any</option>@foreach($statuses as $k => $l)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $l }} ({{ $counts[$k] }})</option>@endforeach</select></div>
        <div class="field"><label>Category</label><select name="category"><option value="">Any</option>@foreach($categories as $k => $l)<option value="{{ $k }}" @selected(request('category') === $k)>{{ $l }}</option>@endforeach</select></div>
        <div class="field"><label>District</label><select name="district"><option value="">Any</option>@foreach($districts as $d)<option @selected(request('district') === $d)>{{ $d }}</option>@endforeach</select></div>
        <button class="btn">Apply</button>
        <a href="/officer?scope={{ $scope }}" class="btn ghost">Reset</a>
    </form>

    <div class="table-wrap">
        <table>
            <thead><tr><th>Reference</th><th>Restaurant</th><th>Category</th><th>Status</th><th>Evidence</th><th>Officer</th><th>Age</th><th></th></tr></thead>
            <tbody>
            @forelse($complaints as $c)
                <tr>
                    <td class="mono">{{ $c->reference }}
                        @if($c->duplicate_count > 0)<div><span class="badge warn" title="Possible duplicates detected">⧉ {{ $c->duplicate_count }} possible dup.</span></div>@endif
                        @if($c->duplicate_of_id)<div><span class="badge">linked duplicate</span></div>@endif
                    </td>
                    <td>{{ $c->restaurant->name ?? '—' }}<div class="small muted">{{ $c->district }}</div></td>
                    <td>{{ $c->categoryLabel() }}@if($c->category_confidence !== null && $c->category === $c->auto_category)<div class="small muted">auto · {{ (int) round($c->category_confidence * 100) }}%</div>@endif</td>
                    <td>@include('partials.status', ['status' => $c->status])</td>
                    <td>{{ $c->evidence_count }}</td>
                    <td class="small">{{ $c->officer ? $c->officer->username : '—' }}</td>
                    <td class="small">{{ $c->created_at->diffForHumans(null, true) }}</td>
                    <td><a href="/officer/complaints/{{ $c->id }}" class="btn sm">Review</a></td>
                </tr>
            @empty
                <tr><td colspan="8"><div class="empty"><div class="icon">✅</div>Queue is empty.</div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $complaints->links('partials.pager') }}
@endsection
