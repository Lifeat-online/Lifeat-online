@extends('layouts.public')

@section('title', 'Request Transport | Life Platform')

@push('head')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
@endpush

@push('styles')
    <style>
        .transport-form-shell { max-width: 920px; margin: 0 auto; }
        .transport-form-card { border: 1px solid rgb(var(--border-rgb) / 0.9); border-radius: 18px; padding: 1.5rem; background: rgb(var(--surface-rgb) / 0.94); box-shadow: var(--shadow-soft); }
        .transport-form-grid { display: grid; gap: 1rem; }
        .transport-form-grid.two { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .transport-form-grid.four { grid-template-columns: repeat(4, minmax(0, 1fr)); }
        .transport-form-label { display: grid; gap: 0.4rem; font-size: 0.9rem; font-weight: 700; color: var(--text); }
        .transport-form-field { width: 100%; border: 1px solid rgb(var(--border-rgb) / 1); border-radius: 12px; background: rgb(var(--surface-rgb) / 1); color: var(--text); padding: 0.78rem 0.9rem; box-shadow: none; }
        .transport-form-field:focus { outline: 2px solid rgb(var(--brand-rgb) / 0.35); border-color: rgb(var(--brand-rgb) / 0.9); }
        .transport-location-control { display: grid; gap: 0.5rem; }
        .transport-location-row { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 0.5rem; align-items: center; }
        .transport-address-wrap { position: relative; min-width: 0; }
        .transport-location-button { border: 1px solid rgb(var(--brand-rgb) / 0.35); border-radius: 12px; background: rgb(var(--brand-rgb) / 0.1); color: var(--text); font-weight: 800; padding: 0.78rem 0.95rem; white-space: nowrap; cursor: pointer; }
        .transport-location-button:disabled { cursor: wait; opacity: 0.68; }
        .transport-location-status { min-height: 1.2rem; color: var(--muted); font-size: 0.84rem; font-weight: 600; }
        .transport-address-suggestions { position: absolute; z-index: 30; top: calc(100% + 0.35rem); left: 0; right: 0; display: none; max-height: 16rem; overflow-y: auto; border: 1px solid rgb(var(--border-rgb) / 1); border-radius: 12px; background: rgb(var(--surface-rgb) / 1); box-shadow: var(--shadow-soft); }
        .transport-address-suggestions.is-open { display: grid; }
        .transport-address-option { border: 0; border-bottom: 1px solid rgb(var(--border-rgb) / 0.65); background: transparent; color: var(--text); padding: 0.75rem 0.85rem; text-align: left; cursor: pointer; }
        .transport-address-option:last-child { border-bottom: 0; }
        .transport-address-option:hover,
        .transport-address-option:focus { outline: none; background: rgb(var(--brand-rgb) / 0.09); }
        .transport-address-option strong { display: block; font-size: 0.9rem; line-height: 1.35; }
        .transport-address-option span { display: block; color: var(--muted); font-size: 0.78rem; line-height: 1.35; margin-top: 0.15rem; }
        .transport-service-field[hidden] { display: none; }
        .transport-review-modal[hidden] { display: none; }
        .transport-review-modal { position: fixed; inset: 0; z-index: 80; display: grid; place-items: center; padding: 1rem; background: rgb(15 23 42 / 0.64); }
        .transport-review-dialog { width: min(1040px, 100%); max-height: min(92vh, 980px); overflow: auto; border: 1px solid rgb(var(--border-rgb) / 0.95); border-radius: 18px; background: rgb(var(--surface-rgb) / 1); box-shadow: 0 28px 90px rgb(0 0 0 / 0.35); }
        .transport-review-head { display: flex; gap: 1rem; align-items: flex-start; justify-content: space-between; padding: 1.15rem 1.25rem; border-bottom: 1px solid rgb(var(--border-rgb) / 0.8); }
        .transport-review-head h3 { margin: 0; }
        .transport-review-body { display: grid; grid-template-columns: minmax(0, 1.1fr) minmax(280px, 0.9fr); gap: 1rem; padding: 1.25rem; }
        .transport-review-map { min-height: 340px; border: 1px solid rgb(var(--border-rgb) / 0.9); border-radius: 14px; overflow: hidden; background: rgb(var(--muted-rgb) / 0.12); }
        .transport-review-panel { display: grid; gap: 0.85rem; align-content: start; }
        .transport-review-list { display: grid; gap: 0.65rem; margin: 0; }
        .transport-review-item { display: grid; gap: 0.15rem; padding: 0.8rem; border: 1px solid rgb(var(--border-rgb) / 0.75); border-radius: 12px; }
        .transport-review-costs { display: grid; gap: 0.55rem; }
        .transport-review-cost { display: grid; gap: 0.2rem; padding: 0.78rem; border: 1px solid rgb(var(--border-rgb) / 0.78); border-radius: 12px; background: rgb(var(--muted-rgb) / 0.08); }
        .transport-review-cost strong { display: flex; justify-content: space-between; gap: 0.75rem; }
        .transport-review-actions { display: flex; flex-wrap: wrap; gap: 0.75rem; justify-content: flex-end; padding: 1rem 1.25rem 1.25rem; border-top: 1px solid rgb(var(--border-rgb) / 0.8); }
        .transport-alert { margin: 0.75rem 0 1.2rem; border-radius: 12px; padding: 0.85rem 1rem; font-size: 0.94rem; }
        .transport-alert.available { background: rgb(22 163 74 / 0.12); color: rgb(22 101 52); }
        .transport-alert.pending { background: rgb(245 158 11 / 0.14); color: rgb(146 64 14); }
        html[data-theme="dark"] .transport-alert.available { color: rgb(187 247 208); }
        html[data-theme="dark"] .transport-alert.pending { color: rgb(253 230 138); }
        @media (max-width: 760px) {
            .transport-form-grid.two,
            .transport-form-grid.four { grid-template-columns: 1fr; }
            .transport-location-row { grid-template-columns: 1fr; }
            .transport-review-body { grid-template-columns: 1fr; }
            .transport-review-map { min-height: 280px; }
        }
    </style>
@endpush

@section('content')
    <section class="section transport-form-shell">
        <div class="transport-form-card">
            <span class="badge">Taxi / Delivery</span>
            <h2 class="h2-tight">Request transport</h2>
            <p class="muted">Book a ride, parcel delivery, errand, or larger local delivery.</p>

            <h3>Ride, parcel, or delivery request</h3>
            @if ($activeDriverCount > 0)
                <p class="transport-alert available">{{ $activeDriverCount }} driver(s) are currently available for immediate requests.</p>
            @else
                <p class="transport-alert pending">No drivers are online right now. You can still save a scheduled ride or delivery request.</p>
            @endif

            <form method="post" action="{{ route('transport.requests.store') }}" id="transport-request-form" class="transport-form-grid">
                @csrf
                <div class="transport-form-grid two">
                    <label class="transport-form-label">Service type
                        <select name="service_type" id="service_type" class="transport-form-field">
                            <option value="parcel" @selected(old('service_type', 'parcel') === 'parcel')>Parcel delivery</option>
                            <option value="ride" @selected(old('service_type') === 'ride')>Passenger ride</option>
                            <option value="errand" @selected(old('service_type') === 'errand')>Errand or collection</option>
                            <option value="heavy_goods" @selected(old('service_type') === 'heavy_goods')>Large item delivery</option>
                        </select>
                    </label>
                    <label class="transport-form-label">Payment
                        <select name="payment_method" class="transport-form-field">
                            <option value="payfast">Pay online with PayFast</option>
                            <option value="cash">Cash to driver</option>
                            <option value="card_machine">Driver card machine</option>
                        </select>
                    </label>
                </div>

                <div class="transport-form-grid two">
                    <label class="transport-form-label">Timing
                        <select name="request_timing" class="transport-form-field">
                            <option value="immediate" @selected(old('request_timing') === 'immediate')>As soon as possible</option>
                            <option value="scheduled" @selected(old('request_timing') === 'scheduled' || $activeDriverCount === 0)>Schedule for later</option>
                        </select>
                    </label>
                    <label class="transport-form-label">Scheduled pickup
                        <input name="scheduled_pickup_at" type="datetime-local" value="{{ old('scheduled_pickup_at') }}" class="transport-form-field">
                    </label>
                </div>

                <div class="transport-form-grid two">
                    <div class="transport-form-label transport-location-control">
                        <span>Pickup address</span>
                        <div class="transport-location-row">
                            <div class="transport-address-wrap">
                                <input name="pickup_address" id="pickup_address" value="{{ old('pickup_address') }}" required class="transport-form-field" autocomplete="off" data-address-autocomplete data-address-prefix="pickup">
                                <div class="transport-address-suggestions" id="pickup_address_suggestions" role="listbox"></div>
                            </div>
                            <button type="button" class="transport-location-button" id="pickup-location-button">My Location</button>
                        </div>
                        <span class="transport-location-status" id="pickup-location-status" aria-live="polite"></span>
                    </div>
                    <div class="transport-form-label transport-location-control">
                        <span>Dropoff address</span>
                        <div class="transport-address-wrap">
                            <input name="dropoff_address" id="dropoff_address" value="{{ old('dropoff_address') }}" required class="transport-form-field" autocomplete="off" data-address-autocomplete data-address-prefix="dropoff">
                            <div class="transport-address-suggestions" id="dropoff_address_suggestions" role="listbox"></div>
                        </div>
                        <span class="transport-location-status" id="dropoff-location-status" aria-live="polite"></span>
                    </div>
                </div>
                <input type="hidden" name="pickup_latitude" id="pickup_latitude" value="{{ old('pickup_latitude') }}">
                <input type="hidden" name="pickup_longitude" id="pickup_longitude" value="{{ old('pickup_longitude') }}">
                <input type="hidden" name="dropoff_latitude" id="dropoff_latitude" value="{{ old('dropoff_latitude') }}">
                <input type="hidden" name="dropoff_longitude" id="dropoff_longitude" value="{{ old('dropoff_longitude') }}">

                <div class="transport-form-grid four">
                    <label class="transport-form-label">Distance km
                        <input name="distance_km" id="distance_km" type="number" min="0.1" max="2000" step="0.1" value="{{ old('distance_km', 5) }}" required class="transport-form-field">
                    </label>
                    <label class="transport-form-label transport-service-field" data-service-field="ride">People
                        <input name="passenger_count" type="number" min="0" max="80" value="{{ old('passenger_count', 0) }}" class="transport-form-field">
                    </label>
                    <label class="transport-form-label transport-service-field" data-service-field="parcel heavy_goods">Parcel kg
                        <input name="parcel_weight_kg" type="number" min="0" step="0.1" value="{{ old('parcel_weight_kg') }}" class="transport-form-field">
                    </label>
                    <label class="transport-form-label">Vehicle
                        <select name="required_vehicle_type" class="transport-form-field">
                            <option value="">Any suitable</option>
                            @foreach (['bicycle', 'scooter', 'motorcycle', 'car', 'bakkie', 'ldv', 'van', 'truck', 'trailer'] as $type)
                                <option value="{{ $type }}">{{ ucfirst($type) }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>

                <label class="transport-form-label">Notes
                    <textarea name="client_notes" rows="4" class="transport-form-field">{{ old('client_notes') }}</textarea>
                </label>

                <button class="button" type="submit">Send to available drivers</button>
            </form>
        </div>
    </section>

    <div class="transport-review-modal" id="transport-review-modal" hidden>
        <div class="transport-review-dialog" role="dialog" aria-modal="true" aria-labelledby="transport-review-title">
            <div class="transport-review-head">
                <div>
                    <span class="badge">Review request</span>
                    <h3 id="transport-review-title">Confirm transport details</h3>
                    <p class="muted mb-0">Check the route and estimated pricing before drivers are notified.</p>
                </div>
                <button type="button" class="button-link" id="transport-review-close">Close</button>
            </div>
            <div class="transport-review-body">
                <div id="transport-review-map" class="transport-review-map" aria-label="Requested route map"></div>
                <div class="transport-review-panel">
                    <div>
                        <h4>Request details</h4>
                        <div class="transport-review-list" id="transport-review-details"></div>
                    </div>
                    <div>
                        <h4>Potential costs</h4>
                        <div class="transport-review-costs" id="transport-review-costs"></div>
                    </div>
                </div>
            </div>
            <div class="transport-review-actions">
                <button type="button" class="button-link" id="transport-review-edit">Edit request</button>
                <button type="button" class="button" id="transport-review-confirm">Accept and send to drivers</button>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script>
        (() => {
            const form = document.getElementById('transport-request-form');
            const button = document.getElementById('pickup-location-button');
            const addressInput = document.getElementById('pickup_address');
            const latitudeInput = document.getElementById('pickup_latitude');
            const longitudeInput = document.getElementById('pickup_longitude');
            const distanceInput = document.getElementById('distance_km');
            const status = document.getElementById('pickup-location-status');

            if (!form || !button || !addressInput || !latitudeInput || !longitudeInput) return;

            const pricingVehicles = @json($pricingVehicles);
            const platformFeeRate = Math.max(0, Number(@json($platformFeePercent)) || 0) / 100;
            const serviceType = document.getElementById('service_type');
            const paymentMethod = document.querySelector('[name="payment_method"]');
            const requiredVehicleType = document.querySelector('[name="required_vehicle_type"]');
            const passengerCountInput = document.querySelector('[name="passenger_count"]');
            const parcelWeightInput = document.querySelector('[name="parcel_weight_kg"]');
            const reviewModal = document.getElementById('transport-review-modal');
            const reviewDetails = document.getElementById('transport-review-details');
            const reviewCosts = document.getElementById('transport-review-costs');
            const reviewMapEl = document.getElementById('transport-review-map');
            const reviewConfirm = document.getElementById('transport-review-confirm');
            const reviewClose = document.getElementById('transport-review-close');
            const reviewEdit = document.getElementById('transport-review-edit');
            let reviewConfirmed = false;
            let reviewMap = null;
            let reviewRouteLayer = null;
            let reviewMarkers = [];
            let currentLocation = null;

            const setStatus = (message) => {
                if (status) status.textContent = message;
            };

            const setLoading = (loading) => {
                button.disabled = loading;
                button.textContent = loading ? 'Finding...' : 'My Location';
            };

            const fallbackAddress = (lat, lng) => `${lat.toFixed(7)}, ${lng.toFixed(7)}`;
            const southAfricaViewbox = '16.344976,-22.126612,32.830120,-34.819166';

            const debounce = (callback, wait = 350) => {
                let timer = null;

                return (...args) => {
                    clearTimeout(timer);
                    timer = setTimeout(() => callback(...args), wait);
                };
            };

            const closeSuggestions = (container) => {
                if (!container) return;
                container.classList.remove('is-open');
                container.replaceChildren();
            };

            const coordinatesFor = (prefix) => {
                const lat = Number(document.getElementById(`${prefix}_latitude`)?.value);
                const lng = Number(document.getElementById(`${prefix}_longitude`)?.value);

                return Number.isFinite(lat) && Number.isFinite(lng) ? { lat, lng } : null;
            };

            const distanceKm = (from, to) => {
                const toRadians = (value) => value * Math.PI / 180;
                const radiusKm = 6371;
                const dLat = toRadians(to.lat - from.lat);
                const dLng = toRadians(to.lng - from.lng);
                const lat1 = toRadians(from.lat);
                const lat2 = toRadians(to.lat);
                const a = Math.sin(dLat / 2) ** 2 + Math.cos(lat1) * Math.cos(lat2) * Math.sin(dLng / 2) ** 2;

                return radiusKm * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
            };

            const updateTripDistance = () => {
                if (!distanceInput) return;

                const pickup = coordinatesFor('pickup');
                const dropoff = coordinatesFor('dropoff');

                if (!pickup || !dropoff) return;

                distanceInput.value = Math.max(distanceKm(pickup, dropoff), 0.1).toFixed(1);
            };

            const formatMoney = (amount) => `ZAR ${Number(amount || 0).toFixed(2)}`;

            const labelForSelect = (select) => select?.selectedOptions?.[0]?.textContent?.trim() || '';

            const estimateFare = (vehicle, distance, people) => {
                const base = Number(vehicle.base_fee) || 0;
                const perKm = Number(vehicle.per_km_fee) || 0;
                const perPerson = Number(vehicle.per_person_fee) || 0;
                const minimum = Number(vehicle.minimum_fee) || 0;
                let amount = base + (Math.max(0, distance) * perKm);

                if (vehicle.pricing_mode === 'per_km_plus_people') {
                    amount += Math.max(0, people) * perPerson;
                }

                const quoted = Math.max(amount, minimum);
                const platformFee = quoted * platformFeeRate;

                return {
                    quoted_amount: Math.round(quoted * 100) / 100,
                    platform_fee: Math.round(platformFee * 100) / 100,
                    driver_amount: Math.round((quoted - platformFee) * 100) / 100,
                };
            };

            const matchingVehicles = () => {
                const service = serviceType?.value || 'parcel';
                const payment = paymentMethod?.value || 'payfast';
                const requiredType = requiredVehicleType?.value || '';
                const people = Number(passengerCountInput?.value) || 0;
                const parcelWeight = Number(parcelWeightInput?.value);
                const hasParcelWeight = Number.isFinite(parcelWeight) && parcelWeight > 0;
                const distance = Number(distanceInput?.value) || 0;

                return pricingVehicles
                    .filter((vehicle) => {
                        if (requiredType && vehicle.vehicle_type !== requiredType) return false;

                        if (payment === 'cash' && !vehicle.accepts_cash) return false;
                        if (payment === 'card_machine' && !vehicle.has_card_machine) return false;
                        if (payment === 'payfast' && !vehicle.accepts_payfast) return false;

                        if (service === 'ride') {
                            return vehicle.can_transport_people
                                && vehicle.can_carry_people
                                && Number(vehicle.max_passengers || 0) >= Math.max(1, people);
                        }

                        return vehicle.can_transport_parcels
                            && vehicle.can_carry_parcels
                            && (!hasParcelWeight || vehicle.max_weight_kg === null || Number(vehicle.max_weight_kg) >= parcelWeight);
                    })
                    .map((vehicle) => ({
                        ...vehicle,
                        fare: estimateFare(vehicle, distance, service === 'ride' ? Math.max(1, people) : 0),
                    }))
                    .sort((a, b) => a.fare.quoted_amount - b.fare.quoted_amount);
            };

            const appendReviewItem = (label, value) => {
                const item = document.createElement('div');
                item.className = 'transport-review-item';

                const title = document.createElement('strong');
                title.textContent = label;

                const detail = document.createElement('span');
                detail.className = 'muted';
                detail.textContent = value || '-';

                item.append(title, detail);
                reviewDetails?.append(item);
            };

            const renderReviewDetails = () => {
                if (!reviewDetails) return;

                reviewDetails.replaceChildren();
                appendReviewItem('Service', labelForSelect(serviceType));
                appendReviewItem('Payment', labelForSelect(paymentMethod));
                appendReviewItem('Pickup', document.getElementById('pickup_address')?.value || '');
                appendReviewItem('Dropoff', document.getElementById('dropoff_address')?.value || '');
                appendReviewItem('Distance', `${Number(distanceInput?.value || 0).toFixed(1)} km`);

                if (serviceType?.value === 'ride') {
                    appendReviewItem('Passengers', String(Math.max(1, Number(passengerCountInput?.value) || 1)));
                }

                if (['parcel', 'heavy_goods'].includes(serviceType?.value || '') && parcelWeightInput?.value) {
                    appendReviewItem('Parcel weight', `${Number(parcelWeightInput.value).toFixed(1)} kg`);
                }

                if (requiredVehicleType?.value) {
                    appendReviewItem('Vehicle', labelForSelect(requiredVehicleType));
                }
            };

            const renderCostEstimates = () => {
                if (!reviewCosts) return;

                const vehicles = matchingVehicles();
                reviewCosts.replaceChildren();

                if (vehicles.length === 0) {
                    const empty = document.createElement('p');
                    empty.className = 'muted mb-0';
                    empty.textContent = 'No currently available registered vehicles match these request details. You can edit the request or continue so it can be saved for later dispatch.';
                    reviewCosts.append(empty);
                    return;
                }

                const range = document.createElement('div');
                range.className = 'transport-review-cost';
                const cheapest = vehicles[0].fare.quoted_amount;
                const highest = vehicles[vehicles.length - 1].fare.quoted_amount;
                range.innerHTML = `<strong><span>Estimated range</span><span>${formatMoney(cheapest)} - ${formatMoney(highest)}</span></strong><span class="muted">${vehicles.length} matching available vehicle(s)</span>`;
                reviewCosts.append(range);

                vehicles.slice(0, 6).forEach((vehicle) => {
                    const row = document.createElement('div');
                    row.className = 'transport-review-cost';

                    const title = document.createElement('strong');
                    const name = document.createElement('span');
                    name.textContent = `${vehicle.name} (${vehicle.vehicle_type})`;
                    const amount = document.createElement('span');
                    amount.textContent = formatMoney(vehicle.fare.quoted_amount);
                    title.append(name, amount);

                    const detail = document.createElement('span');
                    detail.className = 'muted';
                    detail.textContent = `Driver: ${formatMoney(vehicle.fare.driver_amount)} | Platform: ${formatMoney(vehicle.fare.platform_fee)}`;

                    row.append(title, detail);
                    reviewCosts.append(row);
                });
            };

            const resetReviewMap = () => {
                reviewRouteLayer?.remove();
                reviewRouteLayer = null;
                reviewMarkers.forEach((marker) => marker.remove());
                reviewMarkers = [];
            };

            const drawFallbackRoute = (pickup, dropoff) => {
                if (!reviewMap || !window.L) return;

                reviewRouteLayer = L.polyline([[pickup.lat, pickup.lng], [dropoff.lat, dropoff.lng]], {
                    color: '#2563eb',
                    weight: 5,
                    opacity: 0.75,
                    dashArray: '8 8',
                }).addTo(reviewMap);
                reviewMap.fitBounds(reviewRouteLayer.getBounds(), { padding: [24, 24] });
            };

            const drawReviewRoute = async (pickup, dropoff) => {
                if (!reviewMapEl || !window.L) return;

                if (!reviewMap) {
                    reviewMap = L.map(reviewMapEl);
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        maxZoom: 19,
                        attribution: '&copy; OpenStreetMap contributors',
                    }).addTo(reviewMap);
                }

                resetReviewMap();
                reviewMarkers = [
                    L.marker([pickup.lat, pickup.lng]).addTo(reviewMap).bindPopup('Pickup'),
                    L.marker([dropoff.lat, dropoff.lng]).addTo(reviewMap).bindPopup('Dropoff'),
                ];

                try {
                    const url = new URL(`https://router.project-osrm.org/route/v1/driving/${pickup.lng},${pickup.lat};${dropoff.lng},${dropoff.lat}`);
                    url.searchParams.set('overview', 'full');
                    url.searchParams.set('geometries', 'geojson');
                    const response = await fetch(url.toString(), { headers: { Accept: 'application/json' } });

                    if (!response.ok) throw new Error('Route unavailable');

                    const data = await response.json();
                    const coordinates = data?.routes?.[0]?.geometry?.coordinates;

                    if (!Array.isArray(coordinates) || coordinates.length === 0) {
                        throw new Error('Route unavailable');
                    }

                    reviewRouteLayer = L.polyline(coordinates.map(([lng, lat]) => [lat, lng]), {
                        color: '#2563eb',
                        weight: 5,
                        opacity: 0.85,
                    }).addTo(reviewMap);
                    reviewMap.fitBounds(reviewRouteLayer.getBounds(), { padding: [24, 24] });
                } catch (_) {
                    drawFallbackRoute(pickup, dropoff);
                }

                setTimeout(() => reviewMap?.invalidateSize(), 80);
            };

            const openReviewModal = async () => {
                const pickup = coordinatesFor('pickup');
                const dropoff = coordinatesFor('dropoff');

                if (!pickup || !dropoff) {
                    alert('Please select pickup and dropoff addresses from the suggestions so the route and distance can be confirmed.');
                    return false;
                }

                updateTripDistance();
                renderReviewDetails();
                renderCostEstimates();
                reviewModal.hidden = false;
                await drawReviewRoute(pickup, dropoff);
                reviewConfirm?.focus();

                return true;
            };

            const closeReviewModal = () => {
                reviewModal.hidden = true;
            };

            const sortByDistance = (results, origin) => {
                if (!origin) return results;

                return results
                    .map((result) => {
                        const lat = Number(result.lat);
                        const lng = Number(result.lon);
                        const resultOrigin = Number.isFinite(lat) && Number.isFinite(lng) ? { lat, lng } : null;

                        return {
                            ...result,
                            distance_km: resultOrigin ? distanceKm(origin, resultOrigin) : Number.POSITIVE_INFINITY,
                        };
                    })
                    .sort((a, b) => a.distance_km - b.distance_km);
            };

            const searchAddresses = async (query, signal, origin = null) => {
                const url = new URL('https://nominatim.openstreetmap.org/search');
                url.searchParams.set('format', 'jsonv2');
                url.searchParams.set('q', query);
                url.searchParams.set('addressdetails', '1');
                url.searchParams.set('countrycodes', 'za');
                url.searchParams.set('viewbox', southAfricaViewbox);
                url.searchParams.set('bounded', '1');
                url.searchParams.set('limit', '8');

                const response = await fetch(url.toString(), {
                    headers: { Accept: 'application/json' },
                    signal,
                });

                if (!response.ok) return [];

                const results = await response.json();
                return sortByDistance(Array.isArray(results) ? results : [], origin);
            };

            const labelForResult = (result) => {
                const address = result?.address || {};
                return address.name || address.road || address.suburb || address.city || address.town || address.village || result.display_name || 'Address';
            };

            const initAddressAutocomplete = (input) => {
                const prefix = input.dataset.addressPrefix;
                const suggestions = document.getElementById(`${input.id}_suggestions`);
                const lat = document.getElementById(`${prefix}_latitude`);
                const lng = document.getElementById(`${prefix}_longitude`);
                const fieldStatus = document.getElementById(`${prefix}-location-status`);
                let controller = null;

                if (!suggestions || !lat || !lng) return;

                const setFieldStatus = (message) => {
                    if (fieldStatus) fieldStatus.textContent = message;
                };

                const renderResults = (results) => {
                    suggestions.replaceChildren();

                    results.forEach((result) => {
                        const option = document.createElement('button');
                        option.type = 'button';
                        option.className = 'transport-address-option';
                        option.setAttribute('role', 'option');

                        const title = document.createElement('strong');
                        title.textContent = labelForResult(result);

                        const detail = document.createElement('span');
                        const distance = Number.isFinite(result.distance_km) ? `${result.distance_km.toFixed(1)} km away - ` : '';
                        detail.textContent = `${distance}${result.display_name || ''}`;

                        option.append(title, detail);
                        option.addEventListener('click', () => {
                            input.value = result.display_name || input.value;
                            lat.value = result.lat || '';
                            lng.value = result.lon || '';
                            closeSuggestions(suggestions);
                            updateTripDistance();
                            setFieldStatus('Address selected.');
                        });

                        suggestions.append(option);
                    });

                    suggestions.classList.toggle('is-open', results.length > 0);
                    setFieldStatus(results.length > 0 ? 'Choose the correct address from the list.' : 'No matching addresses found.');
                };

                const runSearch = debounce(async () => {
                    const query = input.value.trim();
                    lat.value = '';
                    lng.value = '';

                    if (query.length < 3) {
                        closeSuggestions(suggestions);
                        setFieldStatus('');
                        return;
                    }

                    controller?.abort();
                    controller = new AbortController();
                    setFieldStatus('Searching addresses...');

                    try {
                        const origin = prefix === 'dropoff'
                            ? coordinatesFor('pickup') || currentLocation
                            : currentLocation || coordinatesFor('pickup');
                        const results = await searchAddresses(query, controller.signal, origin);
                        renderResults(results);
                    } catch (error) {
                        if (error.name !== 'AbortError') {
                            closeSuggestions(suggestions);
                            setFieldStatus('Address search is unavailable. You can still type the address manually.');
                        }
                    }
                });

                input.addEventListener('input', runSearch);
                input.addEventListener('focus', runSearch);
                input.addEventListener('keydown', (event) => {
                    if (event.key === 'Escape') closeSuggestions(suggestions);
                });
            };

            document.querySelectorAll('[data-address-autocomplete]').forEach(initAddressAutocomplete);

            const updateServiceFields = () => {
                if (!serviceType) return;

                const selected = serviceType.value;
                document.querySelectorAll('[data-service-field]').forEach((field) => {
                    const visibleFor = (field.dataset.serviceField || '').split(' ');
                    const isVisible = visibleFor.includes(selected);

                    field.hidden = !isVisible;
                    field.querySelectorAll('input, select, textarea').forEach((input) => {
                        input.disabled = !isVisible;
                    });
                });
            };

            serviceType?.addEventListener('change', updateServiceFields);
            updateServiceFields();

            document.addEventListener('click', (event) => {
                document.querySelectorAll('.transport-address-suggestions').forEach((suggestions) => {
                    if (!suggestions.parentElement?.contains(event.target)) {
                        closeSuggestions(suggestions);
                    }
                });
            });

            const reverseGeocode = async (lat, lng) => {
                const url = new URL('https://nominatim.openstreetmap.org/reverse');
                url.searchParams.set('format', 'jsonv2');
                url.searchParams.set('lat', String(lat));
                url.searchParams.set('lon', String(lng));
                url.searchParams.set('addressdetails', '1');

                const response = await fetch(url.toString(), {
                    headers: { Accept: 'application/json' },
                });

                if (!response.ok) return null;

                const data = await response.json();
                return data?.display_name || null;
            };

            button.addEventListener('click', () => {
                if (!navigator.geolocation) {
                    setStatus('GPS location is not supported by this browser.');
                    return;
                }

                setLoading(true);
                setStatus('Requesting GPS permission...');

                navigator.geolocation.getCurrentPosition(
                    async (position) => {
                        const lat = position.coords.latitude;
                        const lng = position.coords.longitude;

                        currentLocation = { lat, lng };
                        latitudeInput.value = String(lat);
                        longitudeInput.value = String(lng);
                        updateTripDistance();
                        setStatus('GPS found. Looking up address...');

                        try {
                            addressInput.value = await reverseGeocode(lat, lng) || fallbackAddress(lat, lng);
                            setStatus('Pickup address filled from your current location.');
                        } catch (_) {
                            addressInput.value = fallbackAddress(lat, lng);
                            setStatus('GPS found. Address lookup failed, so coordinates were used.');
                        } finally {
                            setLoading(false);
                        }
                    },
                    () => {
                        setLoading(false);
                        setStatus('Unable to get your location. Please allow GPS access or enter the address manually.');
                    },
                    { enableHighAccuracy: true, timeout: 12000, maximumAge: 60000 }
                );
            });

            form.addEventListener('submit', async (event) => {
                if (reviewConfirmed) return;

                event.preventDefault();
                await openReviewModal();
            });

            reviewConfirm?.addEventListener('click', () => {
                reviewConfirmed = true;
                closeReviewModal();
                form.requestSubmit();
            });

            reviewClose?.addEventListener('click', closeReviewModal);
            reviewEdit?.addEventListener('click', closeReviewModal);

            reviewModal?.addEventListener('click', (event) => {
                if (event.target === reviewModal) closeReviewModal();
            });

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && reviewModal && !reviewModal.hidden) {
                    closeReviewModal();
                }
            });
        })();
    </script>
@endpush
