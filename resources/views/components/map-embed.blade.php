{{--
    Reusable Leaflet / OpenStreetMap embed component.

    Props (single-pin mode):
        mapId   string   — unique HTML id for the map div (required)
        lat     float    — centre latitude
        lng     float    — centre longitude
        label   string   — popup label for the single pin
        height  string   — CSS height, default '260px'

    Props (multi-marker mode):
        mapId   string   — unique HTML id for the map div (required)
        markers array    — collection/array of objects with keys:
                           lat, lng, title, [url], [date|city], [featured]
        height  string   — CSS height, default '260px'

    Usage examples:
        Single pin:
            <x-map-embed mapId="listing-map" :lat="$listing->latitude" :lng="$listing->longitude" :label="$listing->title" />

        Multi-marker:
            <x-map-embed mapId="directory-map" :markers="$mapMarkers" />
--}}

@props([
    'mapId'   => 'map-' . uniqid(),
    'lat'     => null,
    'lng'     => null,
    'label'   => null,
    'markers' => [],
    'height'  => '260px',
])

@php
    $isSinglePin  = $lat !== null && $lng !== null;
    $hasMarkers   = count($markers) > 0;
    $defaultLat   = -28.2319;   // Bethlehem, Free State
    $defaultLng   = 28.3093;
    $defaultZoom  = 9;
@endphp

{{-- Leaflet CSS — loaded once per page --}}
@once
@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.min.css" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet.markercluster@1.5.3/dist/MarkerCluster.css" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css" />
<style>
    .leaflet-container {
        font-family: inherit;
        border-radius: inherit;
    }
    /* Desaturate tiles slightly in dark mode for better visual fit */
    html[data-theme="dark"] .leaflet-tile-pane {
        filter: brightness(0.72) invert(1) hue-rotate(180deg) saturate(0.6) brightness(0.9);
    }
    html[data-theme="dark"] .leaflet-marker-pane,
    html[data-theme="dark"] .leaflet-overlay-pane,
    html[data-theme="dark"] .leaflet-popup-pane {
        filter: invert(1) hue-rotate(180deg);
    }
    .life-marker-featured .life-marker-pin {
        background: #1d4ed8;
    }
    .life-marker-status-available .life-marker-pin {
        background: #16a34a;
    }
    .life-marker-status-busy .life-marker-pin {
        background: #f97316;
    }
    .life-marker-pin {
        width: 14px;
        height: 14px;
        border-radius: 50%;
        background: #3b82f6;
        border: 2.5px solid #fff;
        box-shadow: 0 2px 6px rgba(0,0,0,0.35);
    }
    /* Adjust cluster colors for premium look */
    .marker-cluster-small { background-color: rgba(181, 226, 191, 0.6); }
    .marker-cluster-small div { background-color: rgba(110, 204, 156, 0.6); }
    .marker-cluster-medium { background-color: rgba(241, 211, 87, 0.6); }
    .marker-cluster-medium div { background-color: rgba(240, 194, 12, 0.6); }
    .marker-cluster-large { background-color: rgba(253, 156, 115, 0.6); }
    .marker-cluster-large div { background-color: rgba(241, 128, 23, 0.6); }

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

{{-- Map container --}}
<div id="{{ $mapId }}"
     style="height: {{ $height }}; width: 100%; border-radius: inherit; background: var(--surface); position: relative; overflow: hidden;"
     aria-label="Interactive map"></div>

{{-- Leaflet JS + init — loaded once per page --}}
@once
@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>
@endpush
@endonce

@push('scripts')
<script>
(function () {
    var mapId    = {{ Js::from($mapId) }};
    var markers  = {{ Js::from($markers) }};
    var singleLat = {{ $isSinglePin ? (float) $lat : 'null' }};
    var singleLng = {{ $isSinglePin ? (float) $lng : 'null' }};
    var singleLabel = {{ Js::from($label) }};
    var defaultLat  = {{ $defaultLat }};
    var defaultLng  = {{ $defaultLng }};
    var defaultZoom = {{ $defaultZoom }};

    function initMap () {
        var el = document.getElementById(mapId);
        if (!el || el._leaflet_id) return;

        var map = L.map(el, { zoomControl: true, scrollWheelZoom: false });

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
            maxZoom: 19,
        }).addTo(map);

        // ── Single-pin mode ────────────────────────────────────────────────
        if (singleLat !== null && singleLng !== null) {
            map.setView([singleLat, singleLng], 15);
            var marker = L.marker([singleLat, singleLng]).addTo(map);
            if (singleLabel) {
                marker.bindPopup('<strong>' + singleLabel + '</strong>').openPopup();
            }
            return;
        }

        // ── Multi-marker mode ──────────────────────────────────────────────
        if (!markers || markers.length === 0) {
            map.setView([defaultLat, defaultLng], defaultZoom);
            return;
        }

        var bounds = [];
        var clusterGroup = L.markerClusterGroup({
            showCoverageOnHover: false,
            maxClusterRadius: 50
        });

        function escapeHtml(value) {
            return String(value || '').replace(/[&<>"']/g, function (char) {
                return {
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#039;'
                }[char];
            });
        }

        markers.forEach(function (m) {
            if (!m.lat || !m.lng) return;

            var markerClass = m.marker_class ? String(m.marker_class).replace(/[^a-zA-Z0-9_-]/g, '') : '';

            // Custom circle div-icon
            var icon = L.divIcon({
                className: [m.featured ? 'life-marker-featured' : '', markerClass].filter(Boolean).join(' '),
                html: '<div class="life-marker-pin"></div>',
                iconSize: [14, 14],
                iconAnchor: [7, 7],
                popupAnchor: [0, -10],
            });

            var popupHtml = '<strong>' + escapeHtml(m.title) + '</strong>';
            if (m.status_label) popupHtml += '<br><small>Status: ' + escapeHtml(m.status_label) + '</small>';
            if (m.vehicle) popupHtml += '<br><small>Vehicle: ' + escapeHtml(m.vehicle) + '</small>';
            if (m.seen) popupHtml += '<br><small>Last seen: ' + escapeHtml(m.seen) + '</small>';
            if (m.date)  popupHtml += '<br><small>' + escapeHtml(m.date) + '</small>';
            if (m.city)  popupHtml += '<br><small>' + escapeHtml(m.city) + '</small>';
            if (m.distance) popupHtml += '<br><small style="color:#059669;">' + parseFloat(m.distance).toFixed(1) + ' km away</small>';
            if (m.url)   popupHtml += '<br><a href="' + escapeHtml(m.url) + '" style="color:#1d4ed8;">View &rarr;</a>';

            var pin = L.marker([m.lat, m.lng], { icon: icon });
            pin.bindPopup(popupHtml);
            clusterGroup.addLayer(pin);
            bounds.push([m.lat, m.lng]);
        });

        map.addLayer(clusterGroup);

        if (bounds.length > 0) {
            map.fitBounds(bounds, { padding: [30, 30], maxZoom: 14 });
        } else {
            map.setView([defaultLat, defaultLng], defaultZoom);
        }

        // ── Locate Me Control ──────────────────────────────────────────────
        var LocateControl = L.Control.extend({
            options: { position: 'topleft' },
            onAdd: function (map) {
                var container = L.DomUtil.create('div', 'leaflet-bar leaflet-control leaflet-control-locate');
                container.title = "Locate me";
                container.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width:18px; height:18px;"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" /></svg>';
                
                container.onclick = function() {
                    map.locate({ setView: true, maxZoom: 14 });
                };
                return container;
            }
        });
        map.addControl(new LocateControl());

        map.on('locationfound', function(e) {
            L.circle(e.latlng, e.accuracy / 2).addTo(map);
            L.marker(e.latlng).addTo(map).bindPopup("You are within " + (e.accuracy / 2).toFixed(1) + " meters from this point").openPopup();
        });

        map.on('locationerror', function(e) {
            alert("Could not find your location: " + e.message);
        });

        // Fix for maps starting in hidden/dynamic containers
        setTimeout(function() {
            map.invalidateSize();
        }, 1000);
        
        // Fallback for slower loads
        setTimeout(function() {
            map.invalidateSize();
        }, 3000);
    }

    // Run after DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initMap);
    } else {
        initMap();
    }
}());
</script>
@endpush
