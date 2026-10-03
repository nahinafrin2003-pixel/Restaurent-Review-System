@extends('layouts.app')
@section('title', 'Transparency')
@section('content')
    <div class="page-head">
        <div><h1>Transparency register</h1><p class="sub">Verified complaints that have been resolved or closed. Anonymised — no complainant data is ever published.</p></div>
    </div>
    <div class="grid grid-3">
        <div class="kpi brand"><div class="label">Resolved cases</div><div class="value">{{ $totals['resolved'] }}</div></div>
        <div class="kpi accent"><div class="label">Restaurants involved</div><div class="value">{{ $totals['restaurants'] }}</div></div>
        <div class="kpi info"><div class="label">Resolved this year</div><div class="value">{{ $totals['this_year'] }}</div></div>
    </div>
    <form class="filters card flat" method="GET" style="margin:20px 0 16px">
        <div class="field"><label>District</label><select name="district"><option value="">All</option>@foreach($districts as $d)<option @selected(request('district') === $d)>{{ $d }}</option>@endforeach</select></div>
        <div class="field"><label>Category</label><select name="category"><option value="">All</option>@foreach($categories as $k => $l)<option value="{{ $k }}" @selected(request('category') === $k)>{{ $l }}</option>@endforeach</select></div>
        <button class="btn">Filter</button>
    </form>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Reference</th><th>Restaurant</th><th>District</th><th>Category</th><th>Outcome</th><th>Status</th><th>Closed</th></tr></thead>
            <tbody>
            @forelse($complaints as $c)
                <tr>
                    <td class="mono">{{ $c->reference }}</td>
                    <td>@if($c->restaurant)<a href="/restaurants/{{ $c->restaurant_id }}">{{ $c->restaurant->name }}</a>@endif</td>
                    <td>{{ $c->district }}</td>
                    <td>{{ $c->categoryLabel() }}</td>
                    <td>{{ $c->outcome ?: '—' }}</td>
                    <td>@include('partials.status', ['status' => $c->status])</td>
                    <td class="small">{{ optional($c->resolved_at)->format('M Y') }}</td>
                </tr>
            @empty
                <tr><td colspan="7"><div class="empty"><div class="icon">📂</div>No resolved complaints yet.</div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $complaints->links('partials.pager') }}
@endsection
