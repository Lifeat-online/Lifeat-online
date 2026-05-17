@extends('layouts.public')

@section('title', 'Request Transport | Life Platform')

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
        .transport-location-button { border: 1px solid rgb(var(--brand-rgb) / 0.35); border-radius: 12px; background: rgb(var(--brand-rgb) / 0.1); color: var(--text); font-weight: 800; padding: 0.78rem 0.95rem; white-space: nowrap; cursor: pointer; }
        .transport-location-button:disabled { cursor: wait; opacity: 0.68; }
        .transport-location-status { min-height: 1.2rem; color: var(--muted); font-size: 0.84rem; font-weight: 600; }
        .transport-alert { margin: 0.75rem 0 1.2rem; border-radius: 12px; padding: 0.85rem 1rem; font-size: 0.94rem; }
        .transport-alert.available { background: rgb(22 163 74 / 0.12); color: rgb(22 101 52); }
        .transport-alert.pending { background: rgb(245 158 11 / 0.14); color: rgb(146 64 14); }
        html[data-theme="dark"] .transport-alert.available { color: rgb(187 247 208); }
        html[data-theme="dark"] .transport-alert.pending { color: rgb(253 230 138); }
        @media (max-width: 760px) {
            .transport-form-grid.two,
            .transport-form-grid.four { grid-template-columns: 1fr; }
            .transport-location-row { grid-template-columns: 1fr; }
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

            <form method="post" action="{{ route('transport.requests.store') }}" class="transport-form-grid">
                @csrf
                <div class="transport-form-grid two">
                    <label class="transport-form-label">Service type
                        <select name="service_type" class="transport-form-field">
                            <option value="parcel">Parcel delivery</option>
                            <option value="ride">Passenger ride</option>
                            <option value="errand">Errand or collection</option>
                            <option value="heavy_goods">Large item delivery</option>
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
                            <input name="pickup_address" id="pickup_address" value="{{ old('pickup_address') }}" required class="transport-form-field" autocomplete="street-address">
                            <button type="button" class="transport-location-button" id="pickup-location-button">My Location</button>
                        </div>
                        <span class="transport-location-status" id="pickup-location-status" aria-live="polite"></span>
                    </div>
                    <label class="transport-form-label">Dropoff address
                        <input name="dropoff_address" value="{{ old('dropoff_address') }}" required class="transport-form-field">
                    </label>
                </div>
                <input type="hidden" name="pickup_latitude" id="pickup_latitude" value="{{ old('pickup_latitude') }}">
                <input type="hidden" name="pickup_longitude" id="pickup_longitude" value="{{ old('pickup_longitude') }}">

                <div class="transport-form-grid four">
                    <label class="transport-form-label">Distance km
                        <input name="distance_km" type="number" min="0.1" max="2000" step="0.1" value="{{ old('distance_km', 5) }}" required class="transport-form-field">
                    </label>
                    <label class="transport-form-label">People
                        <input name="passenger_count" type="number" min="0" max="80" value="{{ old('passenger_count', 0) }}" class="transport-form-field">
                    </label>
                    <label class="transport-form-label">Parcel kg
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
@endsection

@push('scripts')
    <script>
        (() => {
            const button = document.getElementById('pickup-location-button');
            const addressInput = document.getElementById('pickup_address');
            const latitudeInput = document.getElementById('pickup_latitude');
            const longitudeInput = document.getElementById('pickup_longitude');
            const status = document.getElementById('pickup-location-status');

            if (!button || !addressInput || !latitudeInput || !longitudeInput) return;

            const setStatus = (message) => {
                if (status) status.textContent = message;
            };

            const setLoading = (loading) => {
                button.disabled = loading;
                button.textContent = loading ? 'Finding...' : 'My Location';
            };

            const fallbackAddress = (lat, lng) => `${lat.toFixed(7)}, ${lng.toFixed(7)}`;

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

                        latitudeInput.value = String(lat);
                        longitudeInput.value = String(lng);
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
        })();
    </script>
@endpush
