{{--
    Interactive Leaflet map for picking coordinates.
    Emits 'latitude' and 'longitude' values to hidden inputs.

    Props:
        latInputName   string (default: 'latitude')
        lngInputName   string (default: 'longitude')
        lat            float|null
        lng            float|null
        height         string (default: '300px')
--}}

@props([
    'latInputName' => 'latitude',
    'lngInputName' => 'longitude',
    'lat' => null,
    'lng' => null,
    'height' => '300px',
])

@php
    $mapId = 'location-picker-' . uniqid();
    $defaultLat = -28.2293;
    $defaultLng = 28.3194;
@endphp

@once
@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.min.css"
      integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
<style>
    .leaflet-container { font-family: inherit; border-radius: 12px; }
    html[data-theme="dark"] .leaflet-tile-pane {
        filter: brightness(0.72) invert(1) hue-rotate(180deg) saturate(0.6) brightness(0.9);
    }
</style>
@endpush
@endonce

<div class="location-picker-container stack">
    <div id="{{ $mapId }}" style="height: {{ $height }}; width: 100%; border: 1px solid var(--border);"></div>
    <div class="grid grid-2" style="margin-top: 0.5rem;">
        <div>
            <label class="text-xs muted">Latitude</label>
            <input type="number" step="any" name="{{ $latInputName }}" id="{{ $mapId }}-lat" value="{{ $lat }}" readonly class="bg-gray-50">
        </div>
        <div>
            <label class="text-xs muted">Longitude</label>
            <input type="number" step="any" name="{{ $lngInputName }}" id="{{ $mapId }}-lng" value="{{ $lng }}" readonly class="bg-gray-50">
        </div>
    </div>
    <p class="text-xs muted">Click on the map to set the exact location pin.</p>
</div>

@once
@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.min.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZU0=" crossorigin=""></script>
@endpush
@endonce

@push('scripts')
<script>
(function() {
    var mapId = {{ Js::from($mapId) }};
    var initialLat = {{ $lat ?? 'null' }};
    var initialLng = {{ $lng ?? 'null' }};
    var defaultLat = {{ $defaultLat }};
    var defaultLng = {{ $defaultLng }};

    function initPicker() {
        var el = document.getElementById(mapId);
        if (!el || el._leaflet_id) return;

        var startLat = initialLat || defaultLat;
        var startLng = initialLng || defaultLng;
        var startZoom = initialLat ? 16 : 8;

        var map = L.map(el).setView([startLat, startLng], startZoom);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(map);

        var marker = null;

        if (initialLat && initialLng) {
            marker = L.marker([initialLat, initialLng], { draggable: true }).addTo(map);
        }

        function updateInputs(lat, lng) {
            document.getElementById(mapId + '-lat').value = lat.toFixed(7);
            document.getElementById(mapId + '-lng').value = lng.toFixed(7);
        }

        map.on('click', function(e) {
            var lat = e.latlng.lat;
            var lng = e.latlng.lng;

            if (marker) {
                marker.setLatLng(e.latlng);
            } else {
                marker = L.marker(e.latlng, { draggable: true }).addTo(map);
                marker.on('dragend', function(event) {
                    var m = event.target;
                    var pos = m.getLatLng();
                    updateInputs(pos.lat, pos.lng);
                });
            }
            updateInputs(lat, lng);
        });

        if (marker) {
            marker.on('dragend', function(event) {
                var m = event.target;
                var pos = m.getLatLng();
                updateInputs(pos.lat, pos.lng);
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initPicker);
    } else {
        initPicker();
    }
})();
</script>
@endpush
