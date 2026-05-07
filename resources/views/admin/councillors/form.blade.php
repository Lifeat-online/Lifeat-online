<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $pageTitle }}</h2>
            <div class="flex gap-2">
                <a href="{{ route('admin.councillors.index') }}" class="rounded-md bg-slate-700 px-4 py-2 text-sm text-white">Back</a>
                @if ($councillor->exists)
                    <form method="post" action="{{ route('admin.councillors.destroy', $councillor) }}" onsubmit="return confirm('Remove councillor?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="rounded-md bg-rose-600 px-4 py-2 text-sm text-white">Remove</button>
                    </form>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto space-y-6 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="rounded-lg bg-white p-6 shadow-sm">{{ session('status') }}</div>
            @endif

            <div class="rounded-lg bg-white p-6 shadow-sm">
                <form method="post" action="{{ $formAction }}">
                    @csrf
                    @if ($formMethod !== 'POST')
                        @method($formMethod)
                    @endif

                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700">Full name</label>
                            <input name="full_name" value="{{ old('full_name', $councillor->full_name) }}" class="mt-1 w-full rounded-md border-gray-300" required>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700">Linked user (optional)</label>
                            <select name="user_id" class="mt-1 w-full rounded-md border-gray-300">
                                <option value="">None</option>
                                @foreach ($users as $user)
                                    <option value="{{ $user->id }}" @selected((string) old('user_id', $councillor->user_id) === (string) $user->id)>{{ $user->name }} ({{ $user->email }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700">Phone</label>
                            <input name="phone" value="{{ old('phone', $councillor->phone) }}" class="mt-1 w-full rounded-md border-gray-300">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700">Email</label>
                            <input name="email" type="email" value="{{ old('email', $councillor->email) }}" class="mt-1 w-full rounded-md border-gray-300">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-semibold text-gray-700">Office address</label>
                            <input name="office_address" value="{{ old('office_address', $councillor->office_address) }}" class="mt-1 w-full rounded-md border-gray-300">
                        </div>
                    </div>

                    <div class="mt-6">
                        <label class="block text-sm font-semibold text-gray-700">Portfolios (comma separated)</label>
                        <input name="portfolios[]" value="" type="hidden">
                        <input id="portfolios_text" value="{{ old('portfolios_text', is_array($councillor->portfolios) ? implode(', ', $councillor->portfolios) : '') }}" class="mt-1 w-full rounded-md border-gray-300" placeholder="infrastructure, utilities, roads">
                    </div>

                    <div class="mt-6">
                        <div class="text-sm font-semibold text-gray-700">Category responsibilities</div>
                        <div class="mt-2 grid gap-2 md:grid-cols-3">
                            @php($selectedCats = old('category_responsibilities', $councillor->category_responsibilities ?? []))
                            @foreach ($categories as $key => $label)
                                <label class="flex items-start gap-2 text-sm text-gray-700">
                                    <input type="checkbox" name="category_responsibilities[]" value="{{ $key }}" class="mt-1" @checked(in_array($key, $selectedCats, true))>
                                    <span>{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>
                        <div class="mt-2 text-xs text-gray-500">If none are selected, the councillor can be assigned to any category inside their ward boundary.</div>
                    </div>

                    <div class="mt-6">
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $councillor->is_active ?? true))>
                            Active
                        </label>
                    </div>

                    <div class="mt-10 border-t pt-8">
                        <div class="flex items-center justify-between gap-4">
                            <h3 class="text-lg font-semibold text-gray-900">Ward / Area Boundaries</h3>
                            <a href="{{ route('faults.index') }}" class="text-sm text-indigo-700">View public map</a>
                        </div>

                        <div class="mt-4 grid gap-4 md:grid-cols-2">
                            <div class="rounded-lg border border-gray-200 p-4">
                                <div class="grid gap-3">
                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700">Existing areas</label>
                                        <select id="area_picker" class="mt-1 w-full rounded-md border-gray-300">
                                            <option value="">New area</option>
                                            @foreach ($councillor->areas ?? [] as $area)
                                                <option value="{{ $area->id }}">{{ $area->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <input type="hidden" name="area_id" id="area_id" value="">
                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700">Area name</label>
                                        <input name="area_name" id="area_name" class="mt-1 w-full rounded-md border-gray-300" placeholder="Ward 12">
                                    </div>
                                    <div>
                                        <label class="flex items-center gap-2 text-sm text-gray-700">
                                            <input type="checkbox" name="area_is_active" id="area_is_active" value="1" checked>
                                            Area active
                                        </label>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700">GeoJSON (Polygon or MultiPolygon)</label>
                                        <textarea name="area_geojson" id="area_geojson" rows="8" class="mt-1 w-full rounded-md border-gray-300" placeholder='{"type":"Polygon","coordinates":[[[28.8,-28.5],[28.81,-28.51],[28.79,-28.52],[28.8,-28.5]]]}'></textarea>
                                        <div class="mt-2 text-xs text-gray-500">Click on the map to add boundary points. Use “Close polygon” to fill this field.</div>
                                    </div>
                                    <div class="flex flex-wrap gap-2">
                                        <button type="button" class="rounded-md bg-slate-700 px-3 py-2 text-xs text-white" id="area_clear">Clear points</button>
                                        <button type="button" class="rounded-md bg-indigo-600 px-3 py-2 text-xs text-white" id="area_close">Close polygon</button>
                                    </div>
                                </div>
                            </div>

                            <div class="rounded-lg border border-gray-200 p-4">
                                <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
                                <div id="area_map" class="w-full rounded-lg border border-gray-200" style="height: 420px;"></div>
                                <div class="mt-2 text-xs text-gray-500">Tip: zoom in and click around the ward boundary; add at least 3 points, then close.</div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-8 flex justify-end">
                        <button type="submit" class="rounded-md bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script>
        (() => {
            const portfoliosText = document.getElementById('portfolios_text');
            const form = portfoliosText?.closest('form');
            if (form && portfoliosText) {
                form.addEventListener('submit', () => {
                    const existing = Array.from(form.querySelectorAll('input[name="portfolios[]"]'));
                    existing.forEach((i) => i.remove());

                    const values = portfoliosText.value
                        .split(',')
                        .map((v) => v.trim())
                        .filter((v) => v.length > 0);

                    values.forEach((v) => {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'portfolios[]';
                        input.value = v;
                        form.appendChild(input);
                    });
                });
            }

            const areas = @json(($councillor->areas ?? collect())->map(fn ($a) => [
                'id' => $a->id,
                'name' => $a->name,
                'geojson' => $a->geojson,
                'is_active' => $a->is_active,
            ]));

            const picker = document.getElementById('area_picker');
            const areaId = document.getElementById('area_id');
            const areaName = document.getElementById('area_name');
            const areaActive = document.getElementById('area_is_active');
            const geo = document.getElementById('area_geojson');

            const mapEl = document.getElementById('area_map');
            if (!mapEl || !window.L) return;
            const map = L.map(mapEl);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; OpenStreetMap contributors' }).addTo(map);
            map.setView([-28.5, 28.8], 9);

            let points = [];
            let markers = [];
            let polygonLayer = null;
            let geoLayer = null;

            const clear = () => {
                points = [];
                markers.forEach((m) => map.removeLayer(m));
                markers = [];
                if (polygonLayer) map.removeLayer(polygonLayer);
                polygonLayer = null;
                if (geoLayer) map.removeLayer(geoLayer);
                geoLayer = null;
            };

            const render = () => {
                if (polygonLayer) map.removeLayer(polygonLayer);
                polygonLayer = null;
                if (points.length >= 3) {
                    polygonLayer = L.polygon(points, { color: '#2563eb', weight: 2, fillColor: '#2563eb', fillOpacity: 0.12 }).addTo(map);
                }
            };

            map.on('click', (e) => {
                points.push([e.latlng.lat, e.latlng.lng]);
                const m = L.circleMarker(e.latlng, { radius: 5, color: '#0f172a', fillColor: '#0f172a', fillOpacity: 0.8 }).addTo(map);
                markers.push(m);
                render();
            });

            document.getElementById('area_clear')?.addEventListener('click', clear);

            document.getElementById('area_close')?.addEventListener('click', () => {
                if (points.length < 3) return;
                const coords = points.map(([lat, lng]) => [lng, lat]);
                coords.push(coords[0]);
                const payload = { type: 'Polygon', coordinates: [coords] };
                if (geo) geo.value = JSON.stringify(payload);
            });

            const loadArea = (id) => {
                clear();
                if (!id) {
                    if (areaId) areaId.value = '';
                    if (areaName) areaName.value = '';
                    if (geo) geo.value = '';
                    if (areaActive) areaActive.checked = true;
                    return;
                }

                const area = areas.find((a) => String(a.id) === String(id));
                if (!area) return;

                if (areaId) areaId.value = area.id;
                if (areaName) areaName.value = area.name;
                if (areaActive) areaActive.checked = !!area.is_active;
                if (geo) geo.value = JSON.stringify(area.geojson || {});

                if (area.geojson) {
                    geoLayer = L.geoJSON(area.geojson, { style: { color: '#2563eb', weight: 2, fillColor: '#2563eb', fillOpacity: 0.12 } }).addTo(map);
                    try {
                        map.fitBounds(geoLayer.getBounds(), { padding: [20, 20] });
                    } catch (_) {
                    }
                }
            };

            picker?.addEventListener('change', (e) => loadArea(e.target.value));
        })();
    </script>
</x-app-layout>

