@extends('layouts.app')
@section('title', 'Analytics')
@section('content')
    <div class="page-head">
        <div><h1>Complaint analytics</h1><p class="sub">Computed from non-sensitive columns only — no complaint text is decrypted for analytics.</p></div>
        <div class="tabs" style="margin:0">
            @foreach([3, 6, 12, 24] as $m)<a href="?months={{ $m }}" class="{{ $months === $m ? 'active' : '' }}">{{ $m }} months</a>@endforeach
            <a href="/officer/heatmap">Heatmap →</a>
        </div>
    </div>

    <div class="grid grid-4">
        <div class="kpi info"><div class="label">Complaints</div><div class="value">{{ $kpis['total'] }}</div><div class="hint">{{ $kpis['open'] }} still open</div></div>
        <div class="kpi brand"><div class="label">Verified</div><div class="value">{{ $kpis['verified'] }}</div><div class="hint">{{ $kpis['verification_rate'] }}% verification rate · {{ $kpis['rejected'] }} rejected</div></div>
        <div class="kpi accent"><div class="label">Avg. time to verify</div><div class="value">{{ $kpis['avg_verification_days'] !== null ? $kpis['avg_verification_days'] . ' d' : '—' }}</div></div>
        <div class="kpi"><div class="label">Avg. resolution time</div><div class="value">{{ $kpis['avg_resolution_days'] !== null ? $kpis['avg_resolution_days'] . ' d' : '—' }}</div><div class="hint">submission → resolved/closed</div></div>
    </div>

    <div class="card" style="margin-top:20px">
        <h2>Monthly trend</h2>
        <div class="chart-box"><canvas id="trend" aria-label="Monthly complaints submitted, verified and resolved"></canvas></div>
    </div>

    <div class="grid grid-2" style="margin-top:20px">
        <div class="card"><h2>By district</h2><div class="chart-box"><canvas id="district"></canvas></div></div>
        <div class="card"><h2>By category</h2><div class="chart-box"><canvas id="category"></canvas></div></div>
    </div>

    <div class="grid grid-2" style="margin-top:20px">
        <div class="card">
            <h2>Top offending restaurants</h2>
            <div class="table-wrap"><table>
                <thead><tr><th>#</th><th>Restaurant</th><th>District</th><th>Verified</th><th>Trust</th></tr></thead>
                <tbody>
                @forelse($topOffenders as $i => $row)
                    <tr><td>{{ $i + 1 }}</td><td><a href="/restaurants/{{ $row['restaurant']->id }}">{{ $row['restaurant']->name }}</a></td><td>{{ $row['restaurant']->district }}</td><td><strong>{{ $row['count'] }}</strong></td><td>@include('partials.trust', ['restaurant' => $row['restaurant']])</td></tr>
                @empty
                    <tr><td colspan="5" class="muted">No verified complaints in this period.</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </div>
        <div class="card"><h2>Status distribution</h2><div class="chart-box"><canvas id="status"></canvas></div></div>
    </div>
@endsection
@push('scripts')
<script src="/vendor/chartjs/chart.umd.min.js"></script>
<script>
    (function () {
        var ink = '#4a524c', grid = '#ebe9e2';
        Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;
        Chart.defaults.color = ink;
        var axes = function (horizontal) {
            var val = { beginAtZero: true, ticks: { precision: 0 }, grid: { color: grid }, border: { display: false } };
            var cat = { grid: { display: false }, border: { color: grid } };
            return horizontal ? { x: val, y: cat } : { x: cat, y: val };
        };
        var bar = function (id, data, horizontal) {
            new Chart(document.getElementById(id), {
                type: 'bar',
                data: { labels: Object.keys(data), datasets: [{ label: 'Complaints', data: Object.values(data), backgroundColor: '#17603a', borderRadius: 4, maxBarThickness: 28 }] },
                options: { indexAxis: horizontal ? 'y' : 'x', maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: axes(horizontal) }
            });
        };
        var m = @json($monthly);
        new Chart(document.getElementById('trend'), {
            type: 'line',
            data: { labels: m.labels, datasets: [
                { label: 'Submitted', data: m.submitted, borderColor: '#2a78d6', backgroundColor: '#2a78d6', borderWidth: 2, pointRadius: 4, tension: 0 },
                { label: 'Verified', data: m.verified, borderColor: '#eb6834', backgroundColor: '#eb6834', borderWidth: 2, pointRadius: 4, tension: 0 },
                { label: 'Resolved', data: m.resolved, borderColor: '#1baf7a', backgroundColor: '#1baf7a', borderWidth: 2, pointRadius: 4, tension: 0 }
            ] },
            options: { maintainAspectRatio: false, interaction: { mode: 'index', intersect: false }, plugins: { legend: { position: 'top', align: 'end', labels: { usePointStyle: true, boxWidth: 8 } } }, scales: axes(false) }
        });
        bar('district', @json($byDistrict), false);
        bar('category', @json($byCategory), true);
        bar('status', @json($byStatus), true);
    })();
</script>
@endpush
