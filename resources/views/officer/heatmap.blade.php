@extends('layouts.app')
@section('title', 'Complaint heatmap')
@section('content')
    <div class="page-head">
        <div><h1>Complaint density heatmap</h1><p class="sub">Verified complaints weigh 3×. {{ count($points) }} complaint(s) at {{ count($markers) }} restaurant(s). Map: {{ $mapsKey ? 'Google Maps' : 'OpenStreetMap (set GOOGLE_MAPS_API_KEY to use Google Maps)' }}.</p></div>
        <a href="/officer/analytics" class="btn ghost">← Analytics</a>
    </div>
    <form class="filters card flat" method="GET" style="margin-bottom:16px">
        <div class="field"><label>Category</label><select name="category"><option value="">All</option>@foreach($categories as $k => $l)<option value="{{ $k }}" @selected(request('category') === $k)>{{ $l }}</option>@endforeach</select></div>
        <div class="field"><label>Show</label><select name="only"><option value="">All complaints</option><option value="verified" @selected(request('only') === 'verified')>Verified only</option></select></div>
        <div class="field"><label>Period</label><select name="months">@foreach([3, 6, 12, 24, 36] as $m)<option value="{{ $m }}" @selected($months === $m)>Last {{ $m }} months</option>@endforeach</select></div>
        <button class="btn">Apply</button>
        <label class="check" style="margin-left:auto"><input type="checkbox" id="toggle-markers" checked> Show restaurant pins</label>
    </form>
    <div id="heatmap" class="map tall"></div>
@endsection
@push('scripts')
<script src="/js/maps.js"></script>
<script>
    (function () {
        var points = @json($points), markers = @json($markers);
        RSMaps.load(@json($mapsKey)).then(function () {
            var map = RSMaps.create(document.getElementById('heatmap'), 23.7, 90.35, 7);
            map.heat(points);
            var pins = markers.map(function (m) {
                var html = '<strong>' + m.name.replace(/</g, '&lt;') + '</strong><br>' + m.district + '<br>' + m.count + ' complaint(s), ' + m.verified + ' verified<br><a href="/restaurants/' + m.id + '">Open restaurant</a>';
                return map.marker(m.lat, m.lng, html);
            });
            document.getElementById('toggle-markers').addEventListener('change', function (e) {
                pins.forEach(function (p) {
                    if (RSMaps.provider === 'google') p.setMap(e.target.checked ? map.raw : null);
                    else e.target.checked ? p.addTo(map.raw) : p.remove();
                });
            });
        });
    })();
</script>
@endpush
