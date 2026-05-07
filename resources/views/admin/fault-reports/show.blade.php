<x-app-layout>
    @push('styles')
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
        <style>
            #fault-report-map { height: min(46vh, 420px); border-radius: 12px; overflow: hidden; }
        </style>
    @endpush

    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Fault Report #{{ $report->id }}</h2>
            <a href="{{ route('admin.fault-reports.index') }}" class="rounded-md bg-slate-700 px-4 py-2 text-sm text-white">Back</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto space-y-6 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="rounded-lg bg-white p-6 shadow-sm">{{ session('status') }}</div>
            @endif

            <div class="grid gap-6 lg:grid-cols-3">
                <div class="rounded-lg bg-white p-6 shadow-sm lg:col-span-2">
                    <div id="fault-report-map" class="mb-6 border border-gray-200"></div>
                    <div class="grid gap-3 md:grid-cols-2">
                        <div>
                            <div class="text-xs text-gray-500">Category</div>
                            <div class="font-semibold text-gray-900">{{ $categories[$report->category] ?? $report->category }}</div>
                        </div>
                        <div>
                            <div class="text-xs text-gray-500">Severity</div>
                            <div class="font-semibold text-gray-900">{{ $severities[$report->severity] ?? $report->severity }}</div>
                        </div>
                        <div>
                            <div class="text-xs text-gray-500">Status</div>
                            <div class="font-semibold text-gray-900">{{ $statuses[$report->status] ?? $report->status }}</div>
                        </div>
                        <div>
                            <div class="text-xs text-gray-500">Approved for public map</div>
                            <div class="font-semibold text-gray-900">{{ $report->is_approved ? 'Yes' : 'No' }}</div>
                        </div>
                        <div class="md:col-span-2">
                            <div class="text-xs text-gray-500">Location</div>
                            <div class="font-semibold text-gray-900">{{ $report->latitude }}, {{ $report->longitude }}</div>
                            @if ($report->address_label)
                                <div class="mt-1 text-sm text-gray-700">{{ $report->address_label }}</div>
                            @endif
                        </div>
                        <div class="md:col-span-2">
                            <div class="text-xs text-gray-500">Description</div>
                            <div class="mt-1 text-gray-900">{{ $report->description }}</div>
                        </div>
                    </div>

                    @if ($report->photos->count())
                        <div class="mt-6 grid gap-3 md:grid-cols-3">
                            @foreach ($report->photos as $photo)
                                <a href="{{ asset('storage/'.$photo->path) }}" target="_blank" class="block overflow-hidden rounded-lg border border-gray-200">
                                    <img src="{{ asset('storage/'.$photo->path) }}" alt="Fault photo" class="h-40 w-full object-cover">
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="rounded-lg bg-white p-6 shadow-sm">
                    <div class="text-sm font-semibold text-gray-900">Moderation</div>
                    <div class="mt-2 text-sm text-gray-700">
                        Reporter: {{ $report->reporter?->name }} ({{ $report->reporter?->email }})
                    </div>
                    <div class="mt-2 text-sm text-gray-700">
                        Assigned councillor: {{ $report->assignedCouncillor?->full_name ?? 'Unassigned' }}
                    </div>
                    @if ($report->moderated_at)
                        <div class="mt-2 text-xs text-gray-500">Last moderated: {{ $report->moderated_at->format('Y-m-d H:i') }}</div>
                    @endif
                    @if ($report->rejected_at)
                        <div class="mt-2 rounded-md bg-rose-50 p-3 text-sm text-rose-800">
                            Rejected: {{ $report->rejection_reason }}
                        </div>
                    @endif

                    <form method="post" action="{{ route('admin.fault-reports.moderate', $report) }}" class="mt-4 space-y-3">
                        @csrf
                        <div>
                            <label class="block text-sm font-semibold text-gray-700">Decision</label>
                            <select name="decision" class="mt-1 w-full rounded-md border-gray-300" required>
                                <option value="approve">Approve</option>
                                <option value="reject">Reject</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700">Rejection reason (if rejecting)</label>
                            <input name="rejection_reason" class="mt-1 w-full rounded-md border-gray-300" maxlength="255">
                        </div>
                        <button type="submit" class="w-full rounded-md bg-indigo-600 px-4 py-2 text-sm text-white">Save moderation</button>
                    </form>

                    <form method="post" action="{{ route('admin.fault-reports.update', $report) }}" class="mt-6 space-y-3">
                        @csrf
                        @method('PUT')
                        <div class="text-sm font-semibold text-gray-900">Assignment & Status</div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700">Assigned councillor</label>
                            <select name="assigned_councillor_id" class="mt-1 w-full rounded-md border-gray-300">
                                <option value="">Unassigned</option>
                                @foreach ($councillors as $councillor)
                                    <option value="{{ $councillor->id }}" @selected((string) $report->assigned_councillor_id === (string) $councillor->id)>{{ $councillor->full_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700">Status</label>
                            <select name="status" class="mt-1 w-full rounded-md border-gray-300" required>
                                @foreach ($statuses as $key => $label)
                                    <option value="{{ $key }}" @selected($report->status === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <button type="submit" class="w-full rounded-md bg-slate-700 px-4 py-2 text-sm text-white">Save assignment</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
        <script>
            (() => {
                const el = document.getElementById('fault-report-map');
                if (!el || typeof L === 'undefined') return;

                const lat = Number(@json((float) $report->latitude));
                const lng = Number(@json((float) $report->longitude));
                if (!Number.isFinite(lat) || !Number.isFinite(lng)) return;

                const map = L.map(el, { zoomControl: true });
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '&copy; OpenStreetMap contributors',
                }).addTo(map);

                const color = @json($report->is_approved ? '#16a34a' : '#f59e0b');
                const marker = L.circleMarker([lat, lng], {
                    radius: 8,
                    color,
                    weight: 2,
                    fillColor: color,
                    fillOpacity: 0.7,
                }).addTo(map);

                const category = @json($categories[$report->category] ?? $report->category);
                const status = @json($statuses[$report->status] ?? $report->status);
                const address = @json($report->address_label ?? '');
                marker.bindPopup(`<div style="font-weight:600;">${String(category)}</div><div style="color:#64748b;">${String(status)}</div>${address ? `<div style="margin-top:0.25rem;">${String(address)}</div>` : ''}`);

                map.setView([lat, lng], 16);
                marker.openPopup();
            })();
        </script>
    @endpush
</x-app-layout>
