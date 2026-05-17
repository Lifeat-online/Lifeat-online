<x-app-layout>
    @push('styles')
        <style>
            @import url('https://unpkg.com/leaflet@1.9.4/dist/leaflet.css');
            .driver-tracking-map { height: min(52vh, 460px); border-radius: 0.75rem; border: 1px solid #e5e7eb; overflow: hidden; background: #f8fafc; }
        </style>
    @endpush

    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Live Driver Workspace</h2>
    </x-slot>

    <div class="py-10" data-transport-realtime data-channel="transport.driver.{{ $driver->id }}">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="rounded-md bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
            @endif
            <div data-transport-notice class="rounded-md bg-slate-50 px-4 py-3 text-sm text-slate-700">
                Live transport updates are active while you are on duty.
            </div>

            <section class="rounded-lg bg-white p-6 shadow-sm">
                <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">Available for requests</h3>
                        <p class="mt-1 text-sm text-gray-600">Vehicle: {{ $activeSession->vehicle->name }} · {{ ucfirst($activeSession->vehicle->vehicle_type) }}</p>
                    </div>
                    <form method="post" action="{{ route('transport.driver.clock-out') }}">
                        @csrf
                        <button class="rounded-md bg-slate-800 px-4 py-2 text-sm font-semibold text-white">Clock out</button>
                    </form>
                </div>
            </section>

            <section class="rounded-lg bg-white p-6 shadow-sm">
                <h3 class="text-lg font-semibold text-gray-900">Incoming requests</h3>
                <div class="mt-5 grid gap-4">
                    @forelse ($offers as $offer)
                        <article class="rounded-md border border-gray-200 p-4">
                            <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                                <div>
                                    <p class="text-sm uppercase tracking-wide text-gray-500">{{ ucfirst(str_replace('_', ' ', $offer->request->service_type)) }}</p>
                                    <h4 class="mt-1 font-semibold text-gray-900">{{ $offer->request->pickup_address }} to {{ $offer->request->dropoff_address }}</h4>
                                    <p class="mt-2 text-sm text-gray-600">{{ $offer->request->distance_km }} km · {{ ucfirst(str_replace('_', ' ', $offer->request->payment_method)) }}</p>
                                    @if ($offer->request->client_notes)
                                        <p class="mt-2 text-sm text-gray-600">{{ $offer->request->client_notes }}</p>
                                    @endif
                                </div>
                                <div class="md:text-right">
                                    <p class="text-lg font-semibold text-gray-900">ZAR {{ number_format((float) $offer->quoted_amount, 2) }}</p>
                                    <p class="text-sm text-gray-600">You earn ZAR {{ number_format((float) $offer->driver_amount, 2) }}</p>
                                    <form method="post" action="{{ route('transport.driver.offers.accept', $offer) }}" class="mt-3">
                                        @csrf
                                        <button class="rounded-md bg-green-700 px-4 py-2 text-sm font-semibold text-white">Accept request</button>
                                    </form>
                                </div>
                            </div>
                        </article>
                    @empty
                        <div class="rounded-md border border-dashed border-gray-300 p-6 text-center text-sm text-gray-500">
                            Standing by for matching ride, parcel, and delivery requests.
                        </div>
                    @endforelse
                </div>
            </section>

            @if ($activeRequests->isNotEmpty())
                <section class="rounded-lg bg-white p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-gray-900">Active route</h3>
                    <div class="mt-5 grid gap-5">
                        @foreach ($activeRequests as $activeRequest)
                            <article class="rounded-md border border-gray-200 p-4" data-driver-tracking
                                data-tracking-url="{{ route('transport.requests.tracking', $activeRequest) }}"
                                data-location-url="{{ route('transport.requests.driver-location', $activeRequest) }}"
                                data-pickup-lat="{{ (float) $activeRequest->pickup_latitude }}"
                                data-pickup-lng="{{ (float) $activeRequest->pickup_longitude }}"
                                data-dropoff-lat="{{ (float) $activeRequest->dropoff_latitude }}"
                                data-dropoff-lng="{{ (float) $activeRequest->dropoff_longitude }}">
                                <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                                    <div>
                                        <p class="text-sm uppercase tracking-wide text-gray-500">{{ ucfirst(str_replace('_', ' ', $activeRequest->status)) }}</p>
                                        <h4 class="mt-1 font-semibold text-gray-900">{{ $activeRequest->pickup_address }}</h4>
                                        <p class="mt-1 text-sm text-gray-600">Passenger: {{ $activeRequest->user->name }} · Dropoff: {{ $activeRequest->dropoff_address }}</p>
                                        <p class="mt-1 text-sm text-gray-600" data-driver-tracking-status>Waiting for live passenger location...</p>
                                    </div>
                                    <div class="text-sm text-gray-600 md:text-right">
                                        <p>Quote: ZAR {{ number_format((float) $activeRequest->quoted_amount, 2) }}</p>
                                        <p>You earn: ZAR {{ number_format((float) $activeRequest->driver_amount, 2) }}</p>
                                    </div>
                                </div>
                                <div id="driver-tracking-map-{{ $activeRequest->id }}" class="driver-tracking-map mt-4" aria-label="Driver route tracking map"></div>
                            </article>
                        @endforeach
                    </div>
                </section>
            @endif

            <section class="rounded-lg border border-red-200 bg-red-50 p-6">
                <h3 class="text-lg font-semibold text-red-950">Safety</h3>
                <p class="mt-2 text-sm text-red-900">The panic workflow will be connected to live safety events in the safety phase. Your configured emergency contact is already stored on the driver profile.</p>
                <button disabled class="mt-4 rounded-md bg-red-700 px-4 py-2 text-sm font-semibold text-white opacity-60">Panic button coming next</button>
            </section>
        </div>
    </div>

    @push('scripts')
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
        <script>
            (() => {
                const roots = document.querySelectorAll('[data-driver-tracking]');
                if (!roots.length || !window.L) return;

                const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

                roots.forEach((root) => {
                    const mapEl = root.querySelector('.driver-tracking-map');
                    const status = root.querySelector('[data-driver-tracking-status]');
                    if (!mapEl) return;

                    const pickup = { lat: Number(root.dataset.pickupLat), lng: Number(root.dataset.pickupLng) };
                    const dropoff = { lat: Number(root.dataset.dropoffLat), lng: Number(root.dataset.dropoffLng) };
                    const map = L.map(mapEl);
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        maxZoom: 19,
                        attribution: '&copy; OpenStreetMap contributors',
                    }).addTo(map);

                    const bounds = [[pickup.lat, pickup.lng]];
                    L.marker([pickup.lat, pickup.lng]).addTo(map).bindPopup('Pickup point');
                    if (Number.isFinite(dropoff.lat) && Number.isFinite(dropoff.lng)) {
                        L.marker([dropoff.lat, dropoff.lng]).addTo(map).bindPopup('Dropoff');
                        L.polyline([[pickup.lat, pickup.lng], [dropoff.lat, dropoff.lng]], { color: '#2563eb', weight: 4, dashArray: '8 8' }).addTo(map);
                        bounds.push([dropoff.lat, dropoff.lng]);
                    }
                    map.fitBounds(bounds, { padding: [24, 24] });

                    let driverMarker = null;
                    let passengerMarker = null;

                    const postLocation = (position) => {
                        fetch(root.dataset.locationUrl, {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrf,
                            },
                            credentials: 'same-origin',
                            body: JSON.stringify({
                                latitude: position.coords.latitude,
                                longitude: position.coords.longitude,
                            }),
                        }).catch(() => {});
                    };

                    if (navigator.geolocation) {
                        navigator.geolocation.watchPosition(postLocation, () => {}, {
                            enableHighAccuracy: true,
                            maximumAge: 15000,
                            timeout: 12000,
                        });
                    }

                    const refreshTracking = async () => {
                        try {
                            const response = await fetch(root.dataset.trackingUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                            if (!response.ok) return;
                            const data = await response.json();
                            const driverLocation = data.driver?.location;
                            const passengerLocation = data.passenger?.location;

                            if (driverLocation) {
                                const latLng = [driverLocation.lat, driverLocation.lng];
                                driverMarker = driverMarker || L.marker(latLng).addTo(map).bindPopup('You');
                                driverMarker.setLatLng(latLng);
                            }

                            if (passengerLocation) {
                                const latLng = [passengerLocation.lat, passengerLocation.lng];
                                passengerMarker = passengerMarker || L.marker(latLng).addTo(map).bindPopup('Passenger live location');
                                passengerMarker.setLatLng(latLng);
                            }

                            if (status) {
                                status.textContent = data.driver?.distance_to_passenger_km !== null
                                    ? `Passenger is ${data.driver.distance_to_passenger_km} km away. Pickup point is ${data.driver.distance_to_pickup_km ?? '-'} km away.`
                                    : 'Waiting for live passenger location...';
                            }
                        } catch (_) {
                        }
                    };

                    refreshTracking();
                    setInterval(refreshTracking, 10000);
                });
            })();
        </script>
    @endpush
</x-app-layout>
