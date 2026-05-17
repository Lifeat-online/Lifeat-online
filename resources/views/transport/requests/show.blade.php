@extends('layouts.public')

@section('title', 'Transport Request | Life Platform')

@push('head')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
@endpush

@push('styles')
    <style>
        .transport-request-shell { max-width: 1060px; margin: 0 auto; display: grid; gap: 1rem; }
        .transport-request-card { border: 1px solid rgb(var(--border-rgb) / 0.9); border-radius: 18px; padding: 1.35rem; background: rgb(var(--surface-rgb) / 0.94); box-shadow: var(--shadow-soft); }
        .transport-request-head { display: flex; gap: 1rem; align-items: flex-start; justify-content: space-between; }
        .transport-request-summary { border-radius: 14px; padding: 1rem; background: rgb(var(--muted-rgb) / 0.12); }
        .transport-notice { border-radius: 12px; padding: 0.85rem 1rem; font-size: 0.94rem; background: rgb(var(--brand-rgb) / 0.12); color: var(--text); }
        .transport-note-warning { border: 1px solid rgb(245 158 11 / 0.35); background: rgb(245 158 11 / 0.14); }
        .transport-note-success { border: 1px solid rgb(22 163 74 / 0.35); background: rgb(22 163 74 / 0.12); }
        .transport-offer { border: 1px solid rgb(var(--border-rgb) / 0.9); border-radius: 14px; padding: 1rem; }
        .transport-stack { display: grid; gap: 1rem; }
        .transport-tracking-map { height: min(52vh, 460px); border: 1px solid rgb(var(--border-rgb) / 0.9); border-radius: 14px; overflow: hidden; background: rgb(var(--muted-rgb) / 0.12); }
        .transport-action-row { display: flex; flex-wrap: wrap; gap: 0.75rem; align-items: center; justify-content: flex-end; margin-top: 1rem; }
        @media (max-width: 760px) {
            .transport-request-head { display: grid; }
            .transport-action-row { justify-content: flex-start; }
        }
    </style>
@endpush

@section('content')
    @php($realtimeStatuses = [\App\Models\TransportRequest::STATUS_DISPATCHING, \App\Models\TransportRequest::STATUS_ACCEPTED, \App\Models\TransportRequest::STATUS_DRIVER_ARRIVING, \App\Models\TransportRequest::STATUS_IN_TRANSIT])
    @php($canCancel = ! in_array($transportRequest->status, [\App\Models\TransportRequest::STATUS_COMPLETED, \App\Models\TransportRequest::STATUS_CANCELLED], true))
    @php($acceptedCancellationFee = $transportRequest->acceptedDriver ? (float) ($transportRequest->acceptedVehicle?->cancellation_fee ?? 0) : 0)

    <section class="section transport-request-shell" @if (in_array($transportRequest->status, $realtimeStatuses, true)) data-transport-realtime data-channel="transport.request.{{ $transportRequest->id }}" @endif>
        <div>
            <span class="badge">Taxi / Delivery</span>
            <h2 class="h2-tight">Transport Request {{ $transportRequest->request_number }}</h2>
        </div>

        @if (session('status'))
            <div class="transport-notice">{{ session('status') }}</div>
        @endif
        @if (in_array($transportRequest->status, $realtimeStatuses, true))
            <div data-transport-notice class="transport-notice">
                Live tracking updates are active for this request.
            </div>
        @endif

        <article class="transport-request-card">
            <div class="transport-request-head">
                <div>
                    <p class="eyebrow">{{ ucfirst(str_replace('_', ' ', $transportRequest->service_type)) }}</p>
                    <h3 data-transport-status>{{ ucfirst(str_replace('_', ' ', $transportRequest->status)) }}</h3>
                    <p class="muted">{{ $transportRequest->pickup_address }} to {{ $transportRequest->dropoff_address }}</p>
                    @if ($transportRequest->scheduled_pickup_at)
                        <p class="muted">Scheduled pickup: {{ $transportRequest->scheduled_pickup_at->format('Y-m-d H:i') }}</p>
                    @endif
                </div>
                <div class="transport-request-summary">
                    <p><span class="muted">Quote:</span> <strong>ZAR {{ number_format((float) $transportRequest->quoted_amount, 2) }}</strong></p>
                    <p><span class="muted">Platform 10%:</span> ZAR {{ number_format((float) $transportRequest->platform_fee, 2) }}</p>
                    <p><span class="muted">Driver:</span> ZAR {{ number_format((float) $transportRequest->driver_amount, 2) }}</p>
                    @if ((float) $transportRequest->cancellation_fee > 0)
                        <p><span class="muted">Cancellation fee:</span> ZAR {{ number_format((float) $transportRequest->cancellation_fee, 2) }}</p>
                    @endif
                </div>
            </div>

            @if ($transportRequest->acceptedDriver)
                <div class="transport-notice transport-note-success">
                    <p><strong>Driver assigned:</strong> {{ $transportRequest->acceptedDriver->user->name }}</p>
                    <p class="mb-0">Vehicle: {{ $transportRequest->acceptedVehicle?->name }} - {{ ucfirst($transportRequest->acceptedVehicle?->vehicle_type ?? 'vehicle') }}</p>
                </div>
            @elseif ($transportRequest->status === \App\Models\TransportRequest::STATUS_SCHEDULED)
                <div class="transport-notice transport-note-warning">
                    No driver is currently assigned. This request is scheduled and will be dispatched when drivers are available.
                </div>
            @else
                <div class="transport-notice">
                    Waiting for an available driver to accept. Realtime tracking is only opened while there is active driver/request activity.
                </div>
            @endif

            @if ($canCancel)
                <div class="transport-action-row">
                    <p class="muted mb-0">
                        @if ($transportRequest->acceptedDriver)
                            Cancelling now may apply a fee of ZAR {{ number_format($acceptedCancellationFee, 2) }}.
                        @else
                            You can cancel before driver acceptance with no cancellation fee.
                        @endif
                    </p>
                    <form method="post" action="{{ route('transport.requests.cancel', $transportRequest) }}" onsubmit="return confirm('Cancel this transport request?');">
                        @csrf
                        <button type="submit" class="button-link">Cancel request</button>
                    </form>
                </div>
            @endif
        </article>

        @if ($transportRequest->pickup_latitude && $transportRequest->pickup_longitude && in_array($transportRequest->status, $realtimeStatuses, true))
            <article class="transport-request-card" data-passenger-tracking
                data-tracking-url="{{ route('transport.requests.tracking', $transportRequest) }}"
                data-location-url="{{ route('transport.requests.passenger-location', $transportRequest) }}"
                data-pickup-lat="{{ (float) $transportRequest->pickup_latitude }}"
                data-pickup-lng="{{ (float) $transportRequest->pickup_longitude }}"
                data-dropoff-lat="{{ (float) $transportRequest->dropoff_latitude }}"
                data-dropoff-lng="{{ (float) $transportRequest->dropoff_longitude }}">
                <h3>Live route</h3>
                <p class="muted" data-passenger-tracking-status>
                    @if ($transportRequest->acceptedDriver)
                        Waiting for driver live location...
                    @else
                        Tracking will update after a driver accepts.
                    @endif
                </p>
                <div id="passenger-tracking-map" class="transport-tracking-map" aria-label="Live transport tracking map"></div>
            </article>
        @endif

        <article class="transport-request-card">
            <h3>Driver offers</h3>
            <div class="transport-stack">
                @forelse ($transportRequest->offers as $offer)
                    <div class="transport-offer">
                        <p><strong>{{ $offer->driver->user->name }} - {{ $offer->vehicle->name }}</strong></p>
                        <p class="muted mb-0">Status: {{ ucfirst($offer->status) }} - Quote: ZAR {{ number_format((float) $offer->quoted_amount, 2) }}</p>
                    </div>
                @empty
                    <p class="muted">No eligible drivers were available when this request was created.</p>
                @endforelse
            </div>
        </article>

        <article class="transport-request-card">
            <h3>Status history</h3>
            <div class="transport-stack">
                @foreach ($transportRequest->statusEvents as $event)
                    <div>
                        <p><strong>{{ ucfirst(str_replace('_', ' ', $event->status)) }}</strong></p>
                        <p class="muted mb-0">{{ $event->notes }} - {{ $event->created_at->diffForHumans() }}</p>
                    </div>
                @endforeach
            </div>
        </article>
    </section>
@endsection

@push('scripts')
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script>
        (() => {
            const root = document.querySelector('[data-passenger-tracking]');
            if (!root || !window.L) return;

            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const status = root.querySelector('[data-passenger-tracking-status]');
            const map = L.map('passenger-tracking-map');
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap contributors',
            }).addTo(map);

            const pickup = { lat: Number(root.dataset.pickupLat), lng: Number(root.dataset.pickupLng) };
            const dropoff = { lat: Number(root.dataset.dropoffLat), lng: Number(root.dataset.dropoffLng) };
            const bounds = [[pickup.lat, pickup.lng]];
            let driverMarker = null;
            let passengerMarker = null;

            L.marker([pickup.lat, pickup.lng]).addTo(map).bindPopup('Pickup point');
            if (Number.isFinite(dropoff.lat) && Number.isFinite(dropoff.lng)) {
                L.marker([dropoff.lat, dropoff.lng]).addTo(map).bindPopup('Dropoff');
                L.polyline([[pickup.lat, pickup.lng], [dropoff.lat, dropoff.lng]], { color: '#2563eb', weight: 4, dashArray: '8 8' }).addTo(map);
                bounds.push([dropoff.lat, dropoff.lng]);
            }
            map.fitBounds(bounds, { padding: [24, 24] });

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
                    maximumAge: 20000,
                    timeout: 15000,
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
                        driverMarker = driverMarker || L.marker(latLng).addTo(map).bindPopup('Driver');
                        driverMarker.setLatLng(latLng);
                    }

                    if (passengerLocation) {
                        const latLng = [passengerLocation.lat, passengerLocation.lng];
                        passengerMarker = passengerMarker || L.marker(latLng).addTo(map).bindPopup('You');
                        passengerMarker.setLatLng(latLng);
                    }

                    if (status) {
                        status.textContent = data.driver?.distance_to_pickup_km !== null
                            ? `Driver is ${data.driver.distance_to_pickup_km} km from the pickup point.`
                            : 'Waiting for driver live location...';
                    }
                } catch (_) {
                }
            };

            refreshTracking();
            setInterval(refreshTracking, 10000);
        })();
    </script>
@endpush
