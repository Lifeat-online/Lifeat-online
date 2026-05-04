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
    $defaultLat = -28.2319; // Bethlehem
    $defaultLng = 28.3093;
@endphp

@once
@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.min.css" />
<style>
    .leaflet-container { font-family: inherit; border-radius: 12px; }
    html[data-theme="dark"] .leaflet-tile-pane {
        filter: brightness(0.72) invert(1) hue-rotate(180deg) saturate(0.6) brightness(0.9);
    }
    /* Near me / Locate control */
    .leaflet-control-locate {
        background: #fff;
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        border-radius: 4px;
        border: 2px solid rgba(0,0,0,0.2);
        background-clip: padding-box;
        color: #444;
        transition: all 0.2s;
    }
    .leaflet-control-locate:hover {
        background-color: #f4f4f4;
        color: #000;
    }
    html[data-theme="dark"] .leaflet-control-locate {
        background: #1e293b;
        border-color: #334155;
        color: #cbd5e1;
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
<script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.min.js"></script>
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
        var startZoom = initialLat ? 16 : 9;

        var map = L.map(el).setView([startLat, startLng], startZoom);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(map);

        // ── Locate Me Control ──────────────────────────────────────────────
        var LocateControl = L.Control.extend({
            options: { position: 'topleft' },
            onAdd: function (map) {
                var container = L.DomUtil.create('div', 'leaflet-bar leaflet-control leaflet-control-locate');
                container.title = "Locate me";
                container.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width:18px; height:18px;"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" /></svg>';
                
                container.onclick = function() {
                    map.locate({ setView: true, maxZoom: 16 });
                };
                return container;
            }
        });
        map.addControl(new LocateControl());

        map.on('locationfound', function(e) {
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
            updateInputs(e.latlng.lat, e.latlng.lng);
        });

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

        // Fix for maps starting in hidden/dynamic containers
        setTimeout(function() {
            map.invalidateSize();
        }, 1000);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initPicker);
    } else {
        initPicker();
    }
})();
</script>
@endpush
