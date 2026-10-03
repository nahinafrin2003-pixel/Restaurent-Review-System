@extends('layouts.app')
@section('title', 'Add restaurant')
@section('container_class', 'narrow')
@section('content')
    <div class="page-head"><div><h1>Add a restaurant</h1><p class="sub">Click on the map to pin its exact location.</p></div></div>
    <form method="POST" action="/restaurants" class="card">
        @csrf
        <input type="hidden" name="return" value="{{ $returnTo }}">
        <div class="form-grid">
            <div class="field"><label for="name">Restaurant name</label><input id="name" name="name" value="{{ old('name') }}" required maxlength="200"></div>
            <div class="field"><label for="district">District</label>
                <select id="district" name="district" required>@foreach($districts as $d)<option @selected(old('district') === $d)>{{ $d }}</option>@endforeach</select>
            </div>
        </div>
        <div class="field"><label for="address">Address</label><input id="address" name="address" value="{{ old('address') }}" maxlength="255" placeholder="Road, area"></div>
        <div class="field">
            <label>Location</label>
            <div id="picker" class="map"></div>
            <div class="row small muted" style="margin-top:6px">
                <span>Lat <input type="text" id="latitude" name="latitude" value="{{ old('latitude') }}" readonly style="width:120px;display:inline-block;padding:4px 8px"></span>
                <span>Lng <input type="text" id="longitude" name="longitude" value="{{ old('longitude') }}" readonly style="width:120px;display:inline-block;padding:4px 8px"></span>
                <button type="button" class="btn ghost sm" id="locate">Use my location</button>
            </div>
        </div>
        <button class="btn">Save restaurant</button>
    </form>
@endsection
@push('scripts')
<script src="/js/maps.js"></script>
<script>
    (function () {
        var centres = @json($centres);
        var district = document.getElementById('district');
        var lat = document.getElementById('latitude'), lng = document.getElementById('longitude');
        RSMaps.load(@json($mapsKey)).then(function () {
            var start = lat.value ? [parseFloat(lat.value), parseFloat(lng.value)] : centres[district.value];
            var map = RSMaps.create(document.getElementById('picker'), start[0], start[1], lat.value ? 15 : 12);
            function pick(a, b) { lat.value = a.toFixed(7); lng.value = b.toFixed(7); map.setSingle(a, b); }
            if (lat.value) map.setSingle(start[0], start[1]);
            map.onClick(pick);
            district.addEventListener('change', function () { var c = centres[district.value]; map.center(c[0], c[1], 12); });
            document.getElementById('locate').addEventListener('click', function () {
                if (!navigator.geolocation) return alert('Geolocation is not supported by this browser.');
                navigator.geolocation.getCurrentPosition(function (p) { pick(p.coords.latitude, p.coords.longitude); map.center(p.coords.latitude, p.coords.longitude, 16); });
            });
        });
    })();
</script>
@endpush
