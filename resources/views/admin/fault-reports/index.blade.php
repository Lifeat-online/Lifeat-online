<x-app-layout>
    @push('styles')
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
        <style>
            #fault-reports-map { height: min(46vh, 420px); border-radius: 12px; overflow: hidden; }
        </style>
    @endpush

    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Fault Reports</h2>
            <a href="{{ route('faults.index') }}" class="rounded-md bg-slate-700 px-4 py-2 text-sm text-white">Public map</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto space-y-6 sm:px-6 lg:px-8">
            <div class="rounded-lg bg-white p-6 shadow-sm">
                <form method="get" class="grid gap-3 md:grid-cols-6">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700">Approval</label>
                        <select name="approval" class="mt-1 w-full rounded-md border-gray-300">
                            <option value="">All</option>
                            <option value="pending" @selected(($filters['approval'] ?? '') === 'pending')>Pending</option>
                            <option value="approved" @selected(($filters['approval'] ?? '') === 'approved')>Approved</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700">Category</label>
                        <select name="category" class="mt-1 w-full rounded-md border-gray-300">
                            <option value="">All</option>
                            @foreach ($categories as $key => $label)
                                <option value="{{ $key }}" @selected(($filters['category'] ?? '') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700">Status</label>
                        <select name="status" class="mt-1 w-full rounded-md border-gray-300">
                            <option value="">All</option>
                            @foreach ($statuses as $key => $label)
                                <option value="{{ $key }}" @selected(($filters['status'] ?? '') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700">Councillor</label>
                        <select name="councillor" class="mt-1 w-full rounded-md border-gray-300">
                            <option value="">All</option>
                            <option value="unassigned" @selected(($filters['councillor'] ?? '') === 'unassigned')>Unassigned</option>
                            @foreach ($councillors as $councillor)
                                <option value="{{ $councillor->id }}" @selected((string) ($filters['councillor'] ?? '') === (string) $councillor->id)>{{ $councillor->full_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700">Search</label>
                        <input name="q" value="{{ $filters['q'] ?? '' }}" class="mt-1 w-full rounded-md border-gray-300" placeholder="Description, reporter">
                    </div>
                    <div class="flex items-end gap-2">
                        <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm text-white">Filter</button>
                        <a href="{{ route('admin.fault-reports.index') }}" class="rounded-md bg-slate-700 px-4 py-2 text-sm text-white">Reset</a>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700">From</label>
                        <input name="from" type="date" value="{{ $filters['from'] ?? '' }}" class="mt-1 w-full rounded-md border-gray-300">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700">To</label>
                        <input name="to" type="date" value="{{ $filters['to'] ?? '' }}" class="mt-1 w-full rounded-md border-gray-300">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700">Sort</label>
                        <select name="sort" class="mt-1 w-full rounded-md border-gray-300">
                            <option value="newest" @selected(($filters['sort'] ?? 'newest') === 'newest')>Newest first</option>
                            <option value="oldest" @selected(($filters['sort'] ?? '') === 'oldest')>Oldest first</option>
                        </select>
                    </div>
                </form>
            </div>

            <div class="rounded-lg bg-white p-6 shadow-sm">
                @if (session('status'))
                    <div class="mb-4 rounded-md bg-green-50 p-3 text-sm text-green-700">{{ session('status') }}</div>
                @endif
                @if ($errors->any())
                    <div class="mb-4 rounded-md bg-red-50 p-3 text-sm text-red-700">
                        <ul class="list-disc pl-5 space-y-1">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                    </div>
                @endif

                <form id="bulk_form" method="post" action="{{ route('admin.fault-reports.bulk') }}">
                    @csrf
                    <div class="mb-4 grid gap-3 md:grid-cols-6">
                        <div class="md:col-span-2">
                            <label class="block text-sm font-semibold text-gray-700">Bulk action</label>
                            <select name="action" class="mt-1 w-full rounded-md border-gray-300" required>
                                <option value="approve">Approve</option>
                                <option value="reject">Reject</option>
                                <option value="assign">Assign councillor</option>
                                <option value="set_status">Set status</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700">Councillor</label>
                            <select name="assigned_councillor_id" class="mt-1 w-full rounded-md border-gray-300">
                                <option value="">Unassigned</option>
                                @foreach ($councillors as $councillor)
                                    <option value="{{ $councillor->id }}">{{ $councillor->full_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700">Status</label>
                            <select name="status" class="mt-1 w-full rounded-md border-gray-300">
                                <option value="">Select</option>
                                @foreach ($statuses as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-semibold text-gray-700">Rejection reason</label>
                            <input name="rejection_reason" class="mt-1 w-full rounded-md border-gray-300" maxlength="255" placeholder="If rejecting">
                        </div>
                        <div class="flex items-end">
                            <button type="submit" class="w-full rounded-md bg-slate-700 px-4 py-2 text-sm text-white">Apply to selected</button>
                        </div>
                    </div>

                    @php
                        $mapPoints = $reports->getCollection()
                            ->map(fn ($r) => [
                                'id' => $r->id,
                                'lat' => (float) $r->latitude,
                                'lng' => (float) $r->longitude,
                                'category' => $categories[$r->category] ?? $r->category,
                                'status' => $statuses[$r->status] ?? $r->status,
                                'approved' => (bool) $r->is_approved,
                                'url' => route('admin.fault-reports.show', $r),
                            ])
                            ->values()
                            ->all();
                    @endphp

                    <div class="mb-6">
                        <div id="fault-reports-map" class="border border-gray-200"></div>
                    </div>

                    <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="text-gray-500">
                            <th class="py-2 w-10"><input type="checkbox" id="select_all"></th>
                            <th class="py-2">ID</th>
                            <th class="py-2">Category</th>
                            <th class="py-2">Status</th>
                            <th class="py-2">Approved</th>
                            <th class="py-2">Councillor</th>
                            <th class="py-2">Reported</th>
                            <th class="py-2 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($reports as $report)
                            <tr class="border-t">
                                <td class="py-3">
                                    <input type="checkbox" name="ids[]" value="{{ $report->id }}" class="row_cb">
                                </td>
                                <td class="py-3 font-semibold text-gray-900">#{{ $report->id }}</td>
                                <td class="py-3 text-gray-700">{{ $categories[$report->category] ?? $report->category }}</td>
                                <td class="py-3 text-gray-700">{{ $statuses[$report->status] ?? $report->status }}</td>
                                <td class="py-3 text-gray-700">{{ $report->is_approved ? 'Yes' : 'No' }}</td>
                                <td class="py-3 text-gray-700">{{ $report->assignedCouncillor?->full_name }}</td>
                                <td class="py-3 text-gray-700">{{ $report->created_at?->format('Y-m-d H:i') }}</td>
                                <td class="py-3 text-right">
                                    <a href="{{ route('admin.fault-reports.show', $report) }}" class="font-medium text-indigo-600 hover:underline">Review</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="py-8 text-center text-gray-500">No reports found.</td></tr>
                        @endforelse
                    </tbody>
                    </table>
                </form>
                <script>
                    (() => {
                        const all = document.getElementById('select_all');
                        const cbs = Array.from(document.querySelectorAll('.row_cb'));
                        if (!all || cbs.length === 0) return;
                        all.addEventListener('change', () => cbs.forEach((cb) => cb.checked = all.checked));
                    })();
                </script>
            </div>

            <div>{{ $reports->links() }}</div>
        </div>
    </div>

    @push('scripts')
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
        <script>
            (() => {
                const el = document.getElementById('fault-reports-map');
                if (!el || typeof L === 'undefined') return;

                const points = @json($mapPoints);

                const map = L.map(el, { zoomControl: true });
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '&copy; OpenStreetMap contributors',
                }).addTo(map);

                const bounds = [];
                for (const p of points) {
                    if (!p || typeof p.lat !== 'number' || typeof p.lng !== 'number') continue;

                    const color = p.approved ? '#16a34a' : '#f59e0b';
                    const marker = L.circleMarker([p.lat, p.lng], {
                        radius: 7,
                        color,
                        weight: 2,
                        fillColor: color,
                        fillOpacity: 0.6,
                    }).addTo(map);

                    const safeCategory = String(p.category ?? '');
                    const safeStatus = String(p.status ?? '');
                    const safeId = String(p.id ?? '');
                    const safeUrl = String(p.url ?? '');
                    marker.bindPopup(`<div style="font-weight:600;">Fault #${safeId}</div><div>${safeCategory}</div><div style="color:#64748b;">${safeStatus}</div><div style="margin-top:0.5rem;"><a href="${safeUrl}">Open</a></div>`);
                    bounds.push([p.lat, p.lng]);
                }

                if (bounds.length > 0) {
                    map.fitBounds(bounds, { padding: [30, 30] });
                } else {
                    map.setView([-28.5, 28.8], 6);
                }
            })();
        </script>
    @endpush
</x-app-layout>
