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
    $defaultLat   = -28.2293;   // Eastern Freestate centre (Senekal area)
    $defaultLng   = 28.3194;
    $defaultZoom  = 8;
@endphp

{{-- Leaflet CSS — loaded o@once
@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.min.css"
      integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css" />
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css" />
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
</style>
@endpush
@endonce

{{-- Map container --}}
<div id="{{ $mapId }}"
     style="height: {{ $height }}; width: 100%; border-radius: inherit; background: var(--surface);"
     aria-label="Interactive map"></div>

{{-- Leaflet JS + init — loaded once per page --}}
@once
@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.min.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZU0=" crossorigin=""></script>
<script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>
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

        markers.forEach(function (m) {
            if (!m.lat || !m.lng) return;

            // Custom circle div-icon
            var icon = L.divIcon({
                className: m.featured ? 'life-marker-featured' : '',
                html: '<div class="life-marker-pin"></div>',
                iconSize: [14, 14],
                iconAnchor: [7, 7],
                popupAnchor: [0, -10],
            });

            var popupHtml = '<strong>' + m.title + '</strong>';
            if (m.date)  popupHtml += '<br><small>' + m.date + '</small>';
            if (m.city)  popupHtml += '<br><small>' + m.city + '</small>';
            if (m.distance) popupHtml += '<br><small style="color:#059669;">' + parseFloat(m.distance).toFixed(1) + ' km away</small>';
            if (m.url)   popupHtml += '<br><a href="' + m.url + '" style="color:#1d4ed8;">View &rarr;</a>';

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
