@extends('layouts.public')

@section('title', 'Report Civic Faults')

@push('head')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
    <meta name="lp-sw-url" content="{{ asset('sw.js') }}">
    <meta name="lp-sw-scope" content="/faults/">
@endpush

@push('styles')
    <style>
        .faults-grid { display: grid; gap: 1rem; grid-template-columns: 1fr; }
        .faults-map { height: min(72vh, 700px); border-radius: 16px; border: 1px solid var(--border); overflow: hidden; box-shadow: var(--shadow-soft); background: var(--surface); }
        .faults-controls { display: grid; gap: 1rem; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); }
        .faults-banner {
            border-radius: 20px;
            border: 1px solid var(--border);
            background:
                radial-gradient(900px circle at 18% 30%, rgb(var(--brand-rgb) / 0.16), transparent 52%),
                radial-gradient(900px circle at 86% 0%, rgb(var(--accent-rgb) / 0.12), transparent 58%),
                linear-gradient(135deg, rgb(var(--surface-rgb) / 0.94), rgb(var(--surface-rgb) / 0.74));
            padding: 1.25rem;
            color: rgb(var(--text-rgb) / 0.92);
            box-shadow: var(--shadow-soft);
        }
        html[data-theme="dark"] .faults-banner {
            border-color: rgb(var(--brand-rgb) / 0.16);
            background: linear-gradient(135deg, rgba(0, 92, 185, 0.22), rgba(0, 32, 91, 0.25));
            color: rgba(219, 234, 254, 0.96);
        }
        .faults-banner strong { color: rgb(var(--text-rgb) / 0.98); }
        html[data-theme="dark"] .faults-banner strong { color: #ffffff; }
        .faults-actions { display:flex; flex-wrap: wrap; gap: 0.75rem; align-items:center; justify-content: space-between; }
        .pill { display:inline-flex; align-items:center; gap:0.5rem; padding: 0.35rem 0.7rem; border-radius: 999px; border: 1px solid var(--border); background: var(--surface); color: var(--muted); font-size: 0.9rem; }
        .pill-dot { width: 10px; height: 10px; border-radius: 999px; display:inline-block; }
        .pill-dot.reported { background: #ef4444; }
        .pill-dot.acknowledged { background: #f59e0b; }
        .pill-dot.in_progress { background: #3b82f6; }
        .pill-dot.resolved { background: #10b981; }
        .subnote { margin: 0.35rem 0 0; color: rgb(var(--muted-rgb) / 0.95); }
        html[data-theme="dark"] .subnote { color: rgba(219, 234, 254, 0.9); }
    </style>
@endpush

@section('content')
    <div class="faults-grid">
        <div class="card" style="background: transparent; border: 0; box-shadow: none; padding: 0;">
            <div class="faults-banner" data-reveal>
                <div class="faults-actions">
                    <div>
                        <strong>DA Civic Infrastructure Fault Reporting</strong>
                        <p class="subnote" style="margin-top: 0.45rem;">Powered by DA — help us fix potholes, burst pipes, damaged streetlights, broken sidewalks and more.</p>
                    </div>
                    <div style="display:flex; gap:0.75rem; align-items:center; flex-wrap:wrap; justify-content:flex-end;">
                        <a class="button-link" href="{{ route('faults.report.create') }}">Report a fault</a>
                        <a class="button-link" href="{{ route('legal.privacy') }}">Privacy</a>
                    </div>
                </div>
            </div>
        </div>

        @if (session('status'))
            <div class="card">{{ session('status') }}</div>
        @endif

        <div class="card">
            <div class="faults-controls">
                <div>
                    <label for="category">Category</label>
                    <select id="category">
                        <option value="">All</option>
                        @foreach ($categories as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="status">Status</label>
                    <select id="status">
                        <option value="">All</option>
                        @foreach ($statuses as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="from">From</label>
                    <input id="from" type="date">
                </div>
                <div>
                    <label for="to">To</label>
                    <input id="to" type="date">
                </div>
                <div>
                    <label for="councillor_id">Councillor</label>
                    <select id="councillor_id">
                        <option value="">All</option>
                    </select>
                </div>
            </div>
            <div class="mt-10" style="display:flex; flex-wrap:wrap; gap:0.6rem;">
                @foreach ($statuses as $key => $label)
                    <span class="pill"><span class="pill-dot {{ $key }}"></span> {{ $label }}</span>
                @endforeach
                <span class="pill" id="offline-pill" style="display:none;"><span class="pill-dot" style="background:#64748b;"></span> Offline</span>
            </div>
        </div>

        <div id="faults-map" class="faults-map" aria-label="Fault map"></div>
    </div>
@endsection

@push('scripts')
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script>
        (() => {
            const mapEl = document.getElementById('faults-map');
            if (!mapEl || !window.L) return;

            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const endpoints = {
                faults: @json(route('faults.data.faults')),
                councillors: @json(route('faults.data.councillors')),
            };

            const statusColors = {
                reported: '#ef4444',
                acknowledged: '#f59e0b',
                in_progress: '#3b82f6',
                resolved: '#10b981',
            };

            const map = L.map(mapEl, { zoomControl: true });
            const tiles = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap contributors',
            });
            tiles.addTo(map);

            map.setView([-28.5, 28.8], 7);

            const faultsLayer = L.geoJSON([], {
                pointToLayer: (feature, latlng) => {
                    const status = feature?.properties?.status || 'reported';
                    const color = statusColors[status] || '#ef4444';

                    return L.circleMarker(latlng, {
                        radius: 7,
                        color,
                        weight: 2,
                        fillColor: color,
                        fillOpacity: 0.65,
                    });
                },
                onEachFeature: (feature, layer) => {
                    const p = feature.properties || {};
                    const councillor = p.councillor;
                    const portfolios = Array.isArray(councillor?.portfolios) ? councillor.portfolios.join(', ') : '';
                    const popup = `
                        <div style="min-width: 220px;">
                            <div style="font-weight: 800;">${escapeHtml(p.category_label || p.category || 'Fault')}</div>
                            <div style="margin-top: 0.25rem;">Status: <strong>${escapeHtml(p.status || '')}</strong></div>
                            <div>Severity: <strong>${escapeHtml(p.severity || '')}</strong></div>
                            <div style="margin-top: 0.35rem; color: #64748b;">Reported: ${escapeHtml((p.created_at || '').slice(0, 10))}</div>
                            ${councillor ? `
                                <hr style="border:0; border-top:1px solid #e5e7eb; margin: 0.6rem 0;">
                                <div style="font-weight: 800;">Councillor</div>
                                <div>${escapeHtml(councillor.full_name || '')}</div>
                                ${councillor.phone ? `<div>Phone: ${escapeHtml(councillor.phone)}</div>` : ''}
                                ${councillor.email ? `<div>Email: ${escapeHtml(councillor.email)}</div>` : ''}
                                ${councillor.office_address ? `<div>Office: ${escapeHtml(councillor.office_address)}</div>` : ''}
                                ${portfolios ? `<div style="margin-top:0.25rem; color:#64748b;">Portfolio: ${escapeHtml(portfolios)}</div>` : ''}
                            ` : ''}
                        </div>
                    `;
                    layer.bindPopup(popup);
                },
            });

            const councillorAreasLayer = L.layerGroup();
            councillorAreasLayer.addTo(map);
            faultsLayer.addTo(map);
            L.control.layers(null, { 'Faults': faultsLayer, 'Councillor Areas': councillorAreasLayer }, { collapsed: true }).addTo(map);

            function escapeHtml(value) {
                return String(value ?? '')
                    .replaceAll('&', '&amp;')
                    .replaceAll('<', '&lt;')
                    .replaceAll('>', '&gt;')
                    .replaceAll('"', '&quot;')
                    .replaceAll("'", '&#039;');
            }

            function centroidLatLng(geojson) {
                try {
                    const type = geojson?.type;
                    const coords = geojson?.coordinates;
                    if (!type || !coords) return null;

                    const firstRing = type === 'Polygon'
                        ? coords?.[0]
                        : (type === 'MultiPolygon' ? coords?.[0]?.[0] : null);

                    if (!Array.isArray(firstRing) || firstRing.length < 3) return null;

                    const points = firstRing.slice(0, -1).map((c) => ({ lng: Number(c[0]), lat: Number(c[1]) })).filter((p) => Number.isFinite(p.lat) && Number.isFinite(p.lng));
                    if (!points.length) return null;

                    const avg = points.reduce((acc, p) => ({ lat: acc.lat + p.lat, lng: acc.lng + p.lng }), { lat: 0, lng: 0 });
                    return { lat: avg.lat / points.length, lng: avg.lng / points.length };
                } catch (_) {
                    return null;
                }
            }

            function queryParams() {
                const category = document.getElementById('category')?.value || '';
                const status = document.getElementById('status')?.value || '';
                const from = document.getElementById('from')?.value || '';
                const to = document.getElementById('to')?.value || '';
                const councillor_id = document.getElementById('councillor_id')?.value || '';

                const params = new URLSearchParams();
                if (category) params.set('category', category);
                if (status) params.set('status', status);
                if (from) params.set('from', from);
                if (to) params.set('to', to);
                if (councillor_id) params.set('councillor_id', councillor_id);
                return params.toString();
            }

            async function loadCouncillors() {
                const res = await fetch(endpoints.councillors, { headers: { 'X-CSRF-TOKEN': csrf } });
                if (!res.ok) return;
                const data = await res.json();

                const select = document.getElementById('councillor_id');
                if (select) {
                    const keepFirst = select.querySelector('option[value=""]');
                    select.innerHTML = '';
                    if (keepFirst) select.appendChild(keepFirst);
                    (data.councillors || []).forEach((c) => {
                        const opt = document.createElement('option');
                        opt.value = c.id;
                        opt.textContent = c.full_name;
                        select.appendChild(opt);
                    });
                }

                councillorAreasLayer.clearLayers();
                (data.councillors || []).forEach((c) => {
                    const color = '#0ea5e9';
                    (c.areas || []).forEach((a) => {
                        if (!a.geojson) return;
                        const layer = L.geoJSON(a.geojson, {
                            style: { color, weight: 2, fillColor: color, fillOpacity: 0.08 },
                        });
                        const portfolios = Array.isArray(c.portfolios) ? c.portfolios.join(', ') : '';
                        const popup = `
                            <div style="min-width: 220px;">
                                <div style="font-weight: 800;">${escapeHtml(c.full_name || '')}</div>
                                <div style="color:#64748b;">${escapeHtml(a.name || 'Ward area')}</div>
                                ${c.phone ? `<div>Phone: ${escapeHtml(c.phone)}</div>` : ''}
                                ${c.email ? `<div>Email: ${escapeHtml(c.email)}</div>` : ''}
                                ${c.office_address ? `<div>Office: ${escapeHtml(c.office_address)}</div>` : ''}
                                ${portfolios ? `<div style="margin-top:0.25rem; color:#64748b;">Portfolio: ${escapeHtml(portfolios)}</div>` : ''}
                            </div>
                        `;
                        layer.eachLayer((l) => l.bindPopup(popup));
                        layer.addTo(councillorAreasLayer);

                        const centroid = centroidLatLng(a.geojson);
                        if (centroid) {
                            const marker = L.marker([centroid.lat, centroid.lng], { title: c.full_name || 'Councillor' });
                            marker.bindPopup(popup);
                            marker.addTo(councillorAreasLayer);
                        }
                    });
                });
            }

            async function loadFaults() {
                const res = await fetch(`${endpoints.faults}?${queryParams()}`, { headers: { 'X-CSRF-TOKEN': csrf } });
                if (!res.ok) return;
                const geo = await res.json();
                faultsLayer.clearLayers();
                faultsLayer.addData(geo);
            }

            async function init() {
                await loadCouncillors();
                await loadFaults();
            }

            ['category', 'status', 'from', 'to', 'councillor_id'].forEach((id) => {
                const el = document.getElementById(id);
                if (!el) return;
                el.addEventListener('change', () => loadFaults());
            });

            const offlinePill = document.getElementById('offline-pill');
            const updateOffline = () => {
                if (!offlinePill) return;
                offlinePill.style.display = navigator.onLine ? 'none' : 'inline-flex';
            };
            window.addEventListener('online', () => { updateOffline(); loadFaults(); });
            window.addEventListener('offline', updateOffline);
            updateOffline();

            init();
        })();
    </script>
@endpush
