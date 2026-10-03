/*
 * Tiny map abstraction: Google Maps JavaScript API when GOOGLE_MAPS_API_KEY is
 * configured, otherwise Leaflet + OpenStreetMap so maps work out of the box.
 */
(function () {
  function loadScript(src) {
    return new Promise(function (resolve, reject) {
      var s = document.createElement('script');
      s.src = src; s.async = true; s.onload = resolve; s.onerror = reject;
      document.head.appendChild(s);
    });
  }
  function loadCss(href) {
    var l = document.createElement('link'); l.rel = 'stylesheet'; l.href = href; document.head.appendChild(l);
  }

  var ready = null;

  window.RSMaps = {
    provider: null,

    load: function (googleKey) {
      if (ready) return ready;
      if (googleKey) {
        this.provider = 'google';
        ready = new Promise(function (resolve, reject) {
          window.__rsGoogleReady = resolve;
          loadScript('https://maps.googleapis.com/maps/api/js?key=' + encodeURIComponent(googleKey) +
            '&libraries=visualization&callback=__rsGoogleReady&loading=async').catch(reject);
        });
      } else {
        this.provider = 'leaflet';
        loadCss('/vendor/leaflet/leaflet.css');
        ready = loadScript('/vendor/leaflet/leaflet.js')
          .then(function () { return loadScript('/vendor/leaflet/leaflet-heat.js'); });
      }
      return ready;
    },

    create: function (el, lat, lng, zoom) {
      var api = {}, single = null;
      if (this.provider === 'google') {
        var g = google.maps;
        var map = new g.Map(el, { center: { lat: lat, lng: lng }, zoom: zoom, mapTypeControl: false, streetViewControl: false });
        var info = new g.InfoWindow();
        api.raw = map;
        api.marker = function (la, ln, html) {
          var m = new g.Marker({ position: { lat: la, lng: ln }, map: map });
          if (html) m.addListener('click', function () { info.setContent(html); info.open(map, m); });
          return m;
        };
        api.setSingle = function (la, ln) {
          if (!single) single = new g.Marker({ map: map, position: { lat: la, lng: ln } });
          else single.setPosition({ lat: la, lng: ln });
        };
        api.onClick = function (cb) { map.addListener('click', function (e) { cb(e.latLng.lat(), e.latLng.lng()); }); };
        api.center = function (la, ln, z) { map.setCenter({ lat: la, lng: ln }); if (z) map.setZoom(z); };
        api.heat = function (points) {
          if (g.visualization && g.visualization.HeatmapLayer) {
            new g.visualization.HeatmapLayer({
              map: map, radius: 30, opacity: 0.75,
              data: points.map(function (p) { return { location: new g.LatLng(p.lat, p.lng), weight: p.weight }; })
            });
          } else {
            // Fallback when the visualization library is unavailable: density circles.
            points.forEach(function (p) {
              new g.Circle({ map: map, center: { lat: p.lat, lng: p.lng }, radius: 400 * p.weight, strokeOpacity: 0, fillColor: '#d92d20', fillOpacity: 0.18 });
            });
          }
        };
      } else {
        var L = window.L;
        var lmap = L.map(el).setView([lat, lng], zoom);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; OpenStreetMap contributors' }).addTo(lmap);
        api.raw = lmap;
        api.marker = function (la, ln, html) { var m = L.marker([la, ln]).addTo(lmap); if (html) m.bindPopup(html); return m; };
        api.setSingle = function (la, ln) { if (!single) single = L.marker([la, ln]).addTo(lmap); else single.setLatLng([la, ln]); };
        api.onClick = function (cb) { lmap.on('click', function (e) { cb(e.latlng.lat, e.latlng.lng); }); };
        api.center = function (la, ln, z) { lmap.setView([la, ln], z || lmap.getZoom()); };
        api.heat = function (points) {
          if (L.heatLayer) {
            var max = points.reduce(function (m, p) { return Math.max(m, p.weight); }, 1);
            L.heatLayer(points.map(function (p) { return [p.lat, p.lng, p.weight]; }), { radius: 28, blur: 22, max: max, minOpacity: 0.35 }).addTo(lmap);
          }
        };
      }
      return api;
    }
  };
})();
