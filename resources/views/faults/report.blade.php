@extends('layouts.public')

@section('title', 'Report a Fault')

@push('head')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
@endpush

@push('styles')
    <style>
        .report-grid { display:grid; gap: 1rem; grid-template-columns: 1fr; }
        .report-map { height: min(56vh, 520px); border-radius: 16px; border: 1px solid var(--border); overflow: hidden; box-shadow: var(--shadow-soft); background: var(--surface); }
        .two-col { display:grid; gap: 1rem; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); align-items: start; }
        .help { color: var(--muted); font-size: 0.92rem; margin-top: 0.35rem; }
        .char-counter { display:flex; justify-content: space-between; gap: 0.75rem; align-items:center; font-size: 0.9rem; color: var(--muted); margin-top: 0.35rem; }
        .notice { border-radius: 16px; border: 1px solid var(--border); background: rgba(59, 130, 246, 0.06); padding: 1rem; }
    </style>
@endpush

@section('content')
    <div class="report-grid">
        <div class="card">
            <h2 class="h2-tight">Report a civic infrastructure fault</h2>
            <p class="muted mb-0">Powered by DA. Reports are moderated before appearing on the public map.</p>
        </div>

        @if ($errors->any())
            <div class="card">
                <strong>Fix the following:</strong>
                <ul class="list-spaced">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="notice" id="offline-notice" style="display:none;">
            You are currently offline. You can still complete this form — it will be queued and automatically submitted when your connection is restored.
        </div>

        <div id="report-map" class="report-map" aria-label="Report location map"></div>

        <div class="card">
            <form method="post" action="{{ route('faults.report.store') }}" enctype="multipart/form-data" id="fault-report-form">
                @csrf
                <input type="hidden" name="client_uuid" id="client_uuid" value="">
                <input type="hidden" name="latitude" id="latitude" value="{{ old('latitude') }}">
                <input type="hidden" name="longitude" id="longitude" value="{{ old('longitude') }}">

                <div class="two-col">
                    <div>
                        <label for="category">Fault Category</label>
                        <select name="category" id="category" required>
                            @foreach ($categories as $key => $label)
                                <option value="{{ $key }}" @selected(old('category') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <div class="help">Choose the closest category so the correct councillor is notified automatically.</div>
                    </div>
                    <div>
                        <label for="severity">Severity</label>
                        <select name="severity" id="severity" required>
                            @foreach ($severities as $key => $label)
                                <option value="{{ $key }}" @selected(old('severity') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <div class="help">Urgent is for immediate safety hazards only.</div>
                    </div>
                </div>

                <div class="mt-10">
                    <label for="address_label">Nearest landmark (optional)</label>
                    <input type="text" name="address_label" id="address_label" value="{{ old('address_label') }}" maxlength="255" placeholder="e.g., Corner of Main Rd & 3rd Ave">
                </div>

                <div class="mt-10">
                    <label for="description">Description (max 500 characters)</label>
                    <textarea name="description" id="description" maxlength="500" rows="5" required>{{ old('description') }}</textarea>
                    <div class="char-counter">
                        <span>Be specific: size, nearby landmarks, visible hazards.</span>
                        <span><span id="desc-count">0</span>/500</span>
                    </div>
                </div>

                <div class="mt-10">
                    <label for="photos">Photos (up to 5)</label>
                    <input type="file" name="photos[]" id="photos" accept="image/*" capture="environment" multiple>
                    <div class="help">You can use your camera or photo gallery. Maximum 5MB per photo.</div>
                </div>

                <div class="mt-10">
                    <label style="display:flex; gap: 0.75rem; align-items:flex-start;">
                        <input type="checkbox" name="consent" value="1" required style="width:auto; margin-top:0.15rem;">
                        <span>
                            I consent to processing my information for fault reporting and follow-up communication in accordance with the
                            <a href="{{ route('legal.privacy') }}">Privacy Policy</a>.
                        </span>
                    </label>
                </div>

                <div class="mt-10" style="display:flex; flex-wrap:wrap; gap:0.75rem; align-items:center; justify-content: space-between;">
                    <button type="button" class="button-link" id="use-location">Use my current location</button>
                    <button type="submit" class="button">Submit report</button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script>
        (() => {
            const form = document.getElementById('fault-report-form');
            const mapEl = document.getElementById('report-map');
            if (!form || !mapEl || !window.L) return;

            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const submitUrl = form.getAttribute('action');

            const clientUuidEl = document.getElementById('client_uuid');
            if (clientUuidEl && !clientUuidEl.value) {
                const uuid = () => {
                    if (crypto?.randomUUID) return crypto.randomUUID();
                    if (crypto?.getRandomValues) {
                        const bytes = new Uint8Array(16);
                        crypto.getRandomValues(bytes);
                        bytes[6] = (bytes[6] & 0x0f) | 0x40;
                        bytes[8] = (bytes[8] & 0x3f) | 0x80;
                        const hex = Array.from(bytes, (b) => b.toString(16).padStart(2, '0')).join('');
                        return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
                    }
                    return '';
                };
                clientUuidEl.value = uuid();
            }

            const offlineNotice = document.getElementById('offline-notice');
            const setOfflineUi = () => {
                if (!offlineNotice) return;
                offlineNotice.style.display = navigator.onLine ? 'none' : 'block';
            };
            window.addEventListener('online', setOfflineUi);
            window.addEventListener('offline', setOfflineUi);
            setOfflineUi();

            const latInput = document.getElementById('latitude');
            const lngInput = document.getElementById('longitude');

            const defaultLat = Number(latInput?.value) || -28.5;
            const defaultLng = Number(lngInput?.value) || 28.8;

            const map = L.map(mapEl);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap contributors',
            }).addTo(map);
            map.setView([defaultLat, defaultLng], 14);

            const marker = L.marker([defaultLat, defaultLng], { draggable: true }).addTo(map);

            const syncLatLng = (lat, lng) => {
                if (latInput) latInput.value = String(lat);
                if (lngInput) lngInput.value = String(lng);
            };

            marker.on('dragend', () => {
                const pos = marker.getLatLng();
                syncLatLng(pos.lat, pos.lng);
            });

            map.on('click', (e) => {
                marker.setLatLng(e.latlng);
                syncLatLng(e.latlng.lat, e.latlng.lng);
            });

            syncLatLng(defaultLat, defaultLng);

            const useLocationBtn = document.getElementById('use-location');
            if (useLocationBtn) {
                useLocationBtn.addEventListener('click', () => {
                    if (!navigator.geolocation) return;
                    navigator.geolocation.getCurrentPosition(
                        (pos) => {
                            const lat = pos.coords.latitude;
                            const lng = pos.coords.longitude;
                            marker.setLatLng([lat, lng]);
                            map.setView([lat, lng], 17);
                            syncLatLng(lat, lng);
                        },
                        () => {},
                        { enableHighAccuracy: true, timeout: 12000, maximumAge: 60000 }
                    );
                });
            }

            const desc = document.getElementById('description');
            const counter = document.getElementById('desc-count');
            const updateCounter = () => {
                if (!desc || !counter) return;
                counter.textContent = String(desc.value.length);
            };
            if (desc) {
                desc.addEventListener('input', updateCounter);
                updateCounter();
            }

            const dbName = 'da-fault-reports';
            const storeName = 'queue';

            function openDb() {
                return new Promise((resolve, reject) => {
                    const req = indexedDB.open(dbName, 1);
                    req.onupgradeneeded = () => {
                        const db = req.result;
                        if (!db.objectStoreNames.contains(storeName)) {
                            db.createObjectStore(storeName, { keyPath: 'client_uuid' });
                        }
                    };
                    req.onsuccess = () => resolve(req.result);
                    req.onerror = () => reject(req.error);
                });
            }

            async function queueOffline(payload) {
                const db = await openDb();
                await new Promise((resolve, reject) => {
                    const tx = db.transaction(storeName, 'readwrite');
                    tx.objectStore(storeName).put(payload);
                    tx.oncomplete = () => resolve();
                    tx.onerror = () => reject(tx.error);
                });
            }

            async function drainQueue() {
                if (!navigator.onLine) return;
                const db = await openDb();
                const items = await new Promise((resolve, reject) => {
                    const tx = db.transaction(storeName, 'readonly');
                    const req = tx.objectStore(storeName).getAll();
                    req.onsuccess = () => resolve(req.result || []);
                    req.onerror = () => reject(req.error);
                });

                for (const item of items) {
                    try {
                        const formData = new FormData();
                        Object.entries(item.fields || {}).forEach(([k, v]) => formData.append(k, v));
                        (item.photos || []).forEach((p) => formData.append('photos[]', p.blob, p.name || 'photo.jpg'));
                        const res = await fetch(submitUrl, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf }, body: formData, credentials: 'same-origin' });
                        if (!res.ok) continue;

                        await new Promise((resolve, reject) => {
                            const tx = db.transaction(storeName, 'readwrite');
                            tx.objectStore(storeName).delete(item.client_uuid);
                            tx.oncomplete = () => resolve();
                            tx.onerror = () => reject(tx.error);
                        });
                    } catch (_) {
                    }
                }
            }

            window.addEventListener('online', () => drainQueue());
            drainQueue();

            form.addEventListener('submit', async (e) => {
                const photosInput = document.getElementById('photos');
                if (photosInput?.files?.length > 5) {
                    e.preventDefault();
                    alert('Please select up to 5 photos.');
                    return;
                }

                if (navigator.onLine) return;
                e.preventDefault();

                const fields = {};
                for (const [k, v] of new FormData(form).entries()) {
                    if (String(k).startsWith('photos')) continue;
                    fields[k] = v;
                }
                const photos = [];
                const files = Array.from(photosInput?.files || []);
                for (const f of files.slice(0, 5)) {
                    photos.push({ name: f.name, blob: f });
                }

                await queueOffline({
                    client_uuid: fields.client_uuid,
                    fields,
                    photos,
                    queued_at: Date.now(),
                });

                form.reset();
                alert('Saved offline. It will submit automatically when your connection is restored.');
            });
        })();
    </script>
@endpush
