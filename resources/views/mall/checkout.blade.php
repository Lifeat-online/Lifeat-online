@extends('layouts.public')

@section('title', 'Checkout at '.$store->name)

@include('mall.partials.styles')
@include('mall.partials.address-autocomplete')

@section('content')
    <div class="mall-shell">
        <a href="{{ route('mall.cart.show', $store) }}" class="mall-button secondary" style="width:max-content;">Back to Basket</a>
        <div class="mall-split">
            <section class="mall-card">
                <h1>Checkout at {{ $store->name }}</h1>
                @foreach ($cart->items as $item)
                    <div class="mall-line-item">
                        <img src="{{ $item->product?->main_image_url }}" alt="">
                        <div>
                            <strong>{{ $item->product?->name ?? 'Deleted product' }}</strong>
                            <div class="mall-muted">{{ $item->quantity }} x R {{ $item->unit_price }}</div>
                        </div>
                        <strong>R {{ $item->line_total }}</strong>
                    </div>
                @endforeach
            </section>

            <aside class="mall-sidebar">
                <strong>PayFast checkout</strong>
                <div class="mall-total-row"><span>Product subtotal</span><span>R {{ $cart->total }}</span></div>
                @php
                    $storePickupConfigured = $store->pickup_latitude !== null && $store->pickup_longitude !== null;
                @endphp
                <form method="post" action="{{ route('mall.checkout.initiate', $store) }}" class="mall-shell" data-mall-checkout-form data-store-pickup-configured="{{ $storePickupConfigured ? '1' : '0' }}" data-store-pickup-lat="{{ $store->pickup_latitude }}" data-store-pickup-lng="{{ $store->pickup_longitude }}">
                    @csrf
                    @php
                        $selectedArea = old('delivery_area', $defaultDeliveryArea);
                        $selectedMethod = old('delivery_method', config('mall.delivery.default_method', 'pickup'));
                    @endphp
                    <div class="mall-shell">
                        <strong>Delivery</strong>
                        <div class="mall-chip-row">
                            @foreach ($deliveryAreas as $area => $label)
                                <label class="mall-chip">
                                    <input type="radio" name="delivery_area" value="{{ $area }}" @checked($selectedArea === $area)>
                                    {{ $label }}
                                </label>
                            @endforeach
                        </div>
                        @foreach ($deliveryOptionsByArea as $area => $options)
                            <div class="mall-card mall-delivery-panel" data-delivery-area="{{ $area }}" @if ($selectedArea !== $area) hidden @endif style="padding:.75rem;">
                                <strong>{{ $deliveryAreas[$area] ?? ucfirst($area) }}</strong>
                                @foreach ($options as $provider => $option)
                                    <label class="mall-delivery-option">
                                        <input type="radio" name="delivery_method" value="{{ $provider }}" data-provider="{{ $provider }}" @checked($selectedMethod === $provider && $selectedArea === $area) @disabled($provider === 'taxi' && ! $storePickupConfigured)>
                                        <span>
                                            <strong>{{ $option['label'] }}</strong>
                                            <span class="mall-muted">{{ $option['description'] }}</span>
                                        </span>
                                        <span>
                                            @if ($option['delivery_fee'] === null)
                                                {{ $provider === 'taxi' ? 'Vehicle rate' : 'Live rate' }}
                                            @else
                                                R {{ $option['delivery_fee'] }}
                                            @endif
                                        </span>
                                    </label>
                                    @if (in_array($provider, ['taxi', 'pudo'], true))
                                        <div class="mall-delivery-address-slot" data-delivery-address-slot="{{ $provider }}"></div>
                                    @endif
                                @endforeach
                                @if ($area === 'local')
                                    <div class="mall-shell" style="gap:.55rem;">
                                        <div class="mall-alert">
                                            <strong>Store pickup point</strong>
                                            <div>{{ $store->pickup_address ?: 'No pickup point has been set for this store yet.' }}</div>
                                            @if ($storePickupConfigured)
                                                <div class="mall-muted">Taxi quotes use this pickup point and your selected delivery address.</div>
                                            @else
                                                <div class="mall-muted">Taxi delivery will be available after the store pickup address is saved in mall store admin.</div>
                                            @endif
                                        </div>
                                        <div class="mall-grid">
                                            <div class="mall-alert">
                                                <span class="mall-muted">Parcel estimate</span>
                                                <strong>{{ number_format((float) $parcelWeightKg, 3) }} kg</strong>
                                                @if (! empty($missingParcelWeightProducts))
                                                    <div class="mall-muted">Vendor kg estimate needed for {{ implode(', ', $missingParcelWeightProducts) }}.</div>
                                                @else
                                                    <div class="mall-muted">Calculated from vendor product estimates in your basket.</div>
                                                @endif
                                            </div>
                                            <label>
                                                <span class="mall-muted">Vehicle type</span>
                                                <select class="mall-select" name="required_vehicle_type" data-taxi-vehicle-type>
                                                    <option value="">Any available parcel vehicle</option>
                                                    @foreach ($activeTaxiVehicleTypes as $vehicleType)
                                                        <option value="{{ $vehicleType }}" @selected(old('required_vehicle_type') === $vehicleType)>{{ $vehicleType === 'ldv' ? 'LDV' : ucfirst($vehicleType) }}</option>
                                                    @endforeach
                                                </select>
                                                @if (empty($activeTaxiVehicleTypes))
                                                    <span class="mall-muted">No active parcel vehicle categories can carry this basket right now.</span>
                                                @endif
                                            </label>
                                        </div>
                                        <div class="mall-alert" data-taxi-estimate>
                                            Select your delivery address to calculate taxi delivery from active vehicle rates.
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                        <div class="mall-delivery-address-block" data-mall-delivery-address-block hidden>
                            <label>
                                <span class="mall-muted">Delivery address or PUDO locker details</span>
                                <div class="mall-address-row">
                                    <div class="mall-address-wrap">
                                        <input class="mall-input" id="mall_delivery_address" name="delivery_address" value="{{ old('delivery_address') }}" maxlength="500" autocomplete="off" data-mall-address-autocomplete data-address-prefix="delivery" data-latitude-target="mall_delivery_latitude" data-longitude-target="mall_delivery_longitude" data-status-target="mall_delivery_address_status" @if ($storePickupConfigured) data-origin-lat="{{ $store->pickup_latitude }}" data-origin-lng="{{ $store->pickup_longitude }}" @endif>
                                        <div class="mall-address-suggestions" id="mall_delivery_address_suggestions" role="listbox"></div>
                                    </div>
                                    <button class="mall-button secondary" type="button" data-mall-locate-delivery>Locate Me</button>
                                </div>
                            </label>
                            <span class="mall-address-status" id="mall_delivery_address_status" aria-live="polite"></span>
                            <div class="mall-alert" data-pudo-panel hidden>
                                <strong>PUDO locker</strong>
                                <p class="mall-muted" style="margin:0;">Use your address or location to find nearby PUDO lockers, then select one for a live delivery rate.</p>
                                <button class="mall-button secondary" type="button" data-pudo-find-lockers style="width:max-content;">Find PUDO Lockers</button>
                                <div class="mall-shell" data-pudo-locker-results style="gap:.5rem;"></div>
                                <div class="mall-muted" data-pudo-quote-status aria-live="polite"></div>
                            </div>
                        </div>
                    </div>
                    <input type="hidden" name="delivery_latitude" id="mall_delivery_latitude" value="{{ old('delivery_latitude') }}">
                    <input type="hidden" name="delivery_longitude" id="mall_delivery_longitude" value="{{ old('delivery_longitude') }}">
                    <input type="hidden" name="pudo_locker_code" id="mall_pudo_locker_code" value="{{ old('pudo_locker_code') }}">
                    <input type="hidden" name="pudo_locker_name" id="mall_pudo_locker_name" value="{{ old('pudo_locker_name') }}">
                    <input type="hidden" name="pudo_locker_latitude" id="mall_pudo_locker_latitude" value="{{ old('pudo_locker_latitude') }}">
                    <input type="hidden" name="pudo_locker_longitude" id="mall_pudo_locker_longitude" value="{{ old('pudo_locker_longitude') }}">
                    <label>
                        <span class="mall-muted">Contact phone</span>
                        <input class="mall-input" name="contact_phone" value="{{ old('contact_phone') }}" maxlength="30">
                    </label>
                    <label>
                        <span class="mall-muted">Notes</span>
                        <textarea class="mall-textarea" name="notes" maxlength="500">{{ old('notes') }}</textarea>
                    </label>
                    <button class="mall-button" type="submit">Pay Securely</button>
                </form>
            </aside>
        </div>
    </div>
    <script>
        const mallTaxiPricingVehicles = @json($taxiPricingVehicles);
        const mallCartParcelWeightKg = Number(@json($parcelWeightKg));
        const mallMissingParcelWeightProducts = @json($missingParcelWeightProducts);
        const mallAddressReverseEndpoint = @json(route('maps.places.reverse'));
        const mallPudoLockersEndpoint = @json(route('mall.pudo.lockers'));
        const mallPudoQuoteEndpoint = @json(route('mall.checkout.pudo.quote', $store));
        const mallCsrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
        const mallCheckoutForm = document.querySelector('[data-mall-checkout-form]');
        const mallDeliveryAddressBlock = document.querySelector('[data-mall-delivery-address-block]');
        const mallStorePickup = mallCheckoutForm?.dataset.storePickupConfigured === '1'
            ? {
                lat: Number(mallCheckoutForm.dataset.storePickupLat),
                lng: Number(mallCheckoutForm.dataset.storePickupLng),
            }
            : null;

        function formatMallMoney(value) {
            return `R ${Number(value || 0).toFixed(2)}`;
        }

        function mallCoordinatesFromInputs() {
            const latValue = document.getElementById('mall_delivery_latitude')?.value;
            const lngValue = document.getElementById('mall_delivery_longitude')?.value;

            if (! latValue || ! lngValue) {
                return null;
            }

            const lat = Number(latValue);
            const lng = Number(lngValue);

            return Number.isFinite(lat) && Number.isFinite(lng) ? { lat, lng } : null;
        }

        function mallDistanceKm(from, to) {
            const toRadians = (value) => value * Math.PI / 180;
            const radiusKm = 6371;
            const dLat = toRadians(to.lat - from.lat);
            const dLng = toRadians(to.lng - from.lng);
            const lat1 = toRadians(from.lat);
            const lat2 = toRadians(to.lat);
            const a = Math.sin(dLat / 2) ** 2 + Math.cos(lat1) * Math.cos(lat2) * Math.sin(dLng / 2) ** 2;

            return radiusKm * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
        }

        function currentTaxiDistance() {
            const delivery = mallCoordinatesFromInputs();

            if (! mallStorePickup || ! delivery) {
                return null;
            }

            return Math.max(mallDistanceKm(mallStorePickup, delivery), 0.1);
        }

        function selectedDeliveryMethod() {
            return document.querySelector('input[name="delivery_method"]:checked')?.value || '';
        }

        function updateDeliveryAddressPlacement() {
            if (! mallDeliveryAddressBlock) {
                return;
            }

            const method = selectedDeliveryMethod();
            const needsAddress = ['taxi', 'pudo'].includes(method);
            const slot = document.querySelector(`[data-delivery-address-slot="${method}"]`);
            const pudoPanel = document.querySelector('[data-pudo-panel]');

            mallDeliveryAddressBlock.hidden = ! needsAddress;

            if (pudoPanel) {
                pudoPanel.hidden = method !== 'pudo';
            }

            if (needsAddress && slot && ! slot.contains(mallDeliveryAddressBlock)) {
                slot.appendChild(mallDeliveryAddressBlock);
            }
        }

        function setDeliveryAddressStatus(message) {
            const status = document.getElementById('mall_delivery_address_status');

            if (status) {
                status.textContent = message || '';
            }
        }

        function fallbackAddress(lat, lng) {
            return `${lat.toFixed(7)}, ${lng.toFixed(7)}`;
        }

        async function reverseGeocodeDeliveryAddress(lat, lng) {
            if (! mallAddressReverseEndpoint) {
                return null;
            }

            const url = new URL(mallAddressReverseEndpoint, window.location.origin);
            url.searchParams.set('lat', String(lat));
            url.searchParams.set('lng', String(lng));

            const response = await fetch(url.toString(), {
                headers: { Accept: 'application/json' },
            });

            if (! response.ok) {
                return null;
            }

            const payload = await response.json();

            return payload.address || null;
        }

        function updateTaxiEstimate() {
            const target = document.querySelector('[data-taxi-estimate]');
            const vehicleType = document.querySelector('[data-taxi-vehicle-type]')?.value || '';

            if (! target) {
                return;
            }

            if (! mallStorePickup) {
                target.textContent = 'Taxi delivery needs the store pickup point to be configured first.';
                return;
            }

            if (mallMissingParcelWeightProducts.length > 0) {
                target.textContent = `Taxi delivery needs vendor parcel kg estimates for: ${mallMissingParcelWeightProducts.join(', ')}.`;
                return;
            }

            const distance = currentTaxiDistance();

            if (! Number.isFinite(distance) || distance <= 0) {
                target.textContent = 'Select your delivery address to calculate taxi delivery from active vehicle rates.';
                return;
            }

            const weight = mallCartParcelWeightKg;
            const hasWeight = Number.isFinite(weight) && weight > 0;
            const candidates = mallTaxiPricingVehicles
                .filter((vehicle) => ! vehicleType || vehicle.vehicle_type === vehicleType)
                .filter((vehicle) => ! hasWeight || vehicle.max_weight_kg === null || Number(vehicle.max_weight_kg) >= weight)
                .map((vehicle) => {
                    const amount = Math.max(
                        Number(vehicle.minimum_fee || 0),
                        Number(vehicle.base_fee || 0) + (distance * Number(vehicle.per_km_fee || 0))
                    );

                    return {
                        ...vehicle,
                        amount: Math.round(amount * 100) / 100,
                    };
                })
                .sort((a, b) => a.amount - b.amount);

            if (candidates.length === 0) {
                target.textContent = 'No active taxi delivery vehicle can quote this distance and parcel right now.';
                return;
            }

            const cheapest = candidates[0];
            target.textContent = `${cheapest.name} estimate: ${formatMallMoney(cheapest.amount)} (${distance.toFixed(1)} km, ${cheapest.vehicle_type}, R ${Number(cheapest.per_km_fee || 0).toFixed(2)}/km)`;
        }

        document.addEventListener('change', (event) => {
            if (! event.target.matches('input[name="delivery_area"]')) {
                return;
            }

            document.querySelectorAll('[data-delivery-area]').forEach((panel) => {
                const isActive = panel.dataset.deliveryArea === event.target.value;
                panel.hidden = ! isActive;
                panel.querySelectorAll('input[name="delivery_method"]').forEach((input) => {
                    input.disabled = ! isActive || (input.dataset.provider === 'taxi' && ! mallStorePickup);
                });

                if (isActive && ! panel.querySelector('input[name="delivery_method"]:checked')) {
                    (panel.querySelector('input[name="delivery_method"]:not([data-provider="pickup"]):not(:disabled)')
                        || panel.querySelector('input[name="delivery_method"]'))?.click();
                }
            });

            updateDeliveryAddressPlacement();
            updateTaxiEstimate();
        });

        document.querySelectorAll('[data-delivery-area][hidden] input[name="delivery_method"]').forEach((input) => {
            input.disabled = true;
        });

        document.querySelectorAll('[data-taxi-vehicle-type], #mall_delivery_latitude, #mall_delivery_longitude').forEach((input) => {
            input.addEventListener('input', updateTaxiEstimate);
            input.addEventListener('change', updateTaxiEstimate);
        });

        document.querySelectorAll('input[name="delivery_method"]').forEach((input) => {
            input.addEventListener('change', () => {
                updateDeliveryAddressPlacement();
                updateTaxiEstimate();
            });
        });

        document.addEventListener('mall:address-coordinates-updated', updateTaxiEstimate);
        document.addEventListener('mall:address-coordinates-updated', () => {
            if (selectedDeliveryMethod() === 'pudo') {
                loadPudoLockers();
            }
        });

        function selectedPudoLockerCode() {
            return document.getElementById('mall_pudo_locker_code')?.value || '';
        }

        function setPudoStatus(message) {
            const target = document.querySelector('[data-pudo-quote-status]');

            if (target) {
                target.textContent = message || '';
            }
        }

        function clearPudoLocker() {
            ['mall_pudo_locker_code', 'mall_pudo_locker_name', 'mall_pudo_locker_latitude', 'mall_pudo_locker_longitude'].forEach((id) => {
                const input = document.getElementById(id);
                if (input) input.value = '';
            });
        }

        async function loadPudoLockers() {
            const results = document.querySelector('[data-pudo-locker-results]');
            const address = document.getElementById('mall_delivery_address')?.value || '';

            if (! results || selectedDeliveryMethod() !== 'pudo') {
                return;
            }

            clearPudoLocker();
            results.replaceChildren();
            setPudoStatus('Loading PUDO lockers...');

            const delivery = mallCoordinatesFromInputs();
            const url = new URL(mallPudoLockersEndpoint, window.location.origin);

            if (delivery) {
                url.searchParams.set('lat', String(delivery.lat));
                url.searchParams.set('lng', String(delivery.lng));
            } else if (address.trim().length >= 3) {
                url.searchParams.set('q', address.trim());
            } else {
                setPudoStatus('Type and select an address, or use Locate Me, to find nearby PUDO lockers.');
                return;
            }

            try {
                const response = await fetch(url.toString(), { headers: { Accept: 'application/json' } });
                const payload = await response.json();

                if (! response.ok || ! payload.ok) {
                    setPudoStatus(payload.message || 'PUDO lockers are unavailable right now.');
                    return;
                }

                if (! Array.isArray(payload.lockers) || payload.lockers.length === 0) {
                    setPudoStatus('No PUDO lockers were found near that location.');
                    return;
                }

                payload.lockers.forEach((locker) => {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'mall-button secondary';
                    button.style.justifyContent = 'space-between';
                    button.dataset.pudoLockerCode = locker.code || '';
                    button.textContent = `${locker.name || locker.code}${locker.distance_km ? ` - ${Number(locker.distance_km).toFixed(1)} km` : ''}`;
                    button.addEventListener('click', () => selectPudoLocker(locker));
                    results.append(button);
                });

                setPudoStatus('Select a PUDO locker to calculate the live delivery rate.');
            } catch (_) {
                setPudoStatus('PUDO lockers could not be loaded right now.');
            }
        }

        async function selectPudoLocker(locker) {
            const code = document.getElementById('mall_pudo_locker_code');
            const name = document.getElementById('mall_pudo_locker_name');
            const lat = document.getElementById('mall_pudo_locker_latitude');
            const lng = document.getElementById('mall_pudo_locker_longitude');
            const address = document.getElementById('mall_delivery_address');

            if (code) code.value = locker.code || '';
            if (name) name.value = locker.name || '';
            if (lat) lat.value = locker.latitude || '';
            if (lng) lng.value = locker.longitude || '';
            if (address) address.value = `PUDO Locker: ${locker.name || locker.code}`;

            setPudoStatus('Getting live PUDO rate...');

            try {
                const response = await fetch(mallPudoQuoteEndpoint, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': mallCsrfToken,
                    },
                    body: JSON.stringify({
                        pudo_locker_code: locker.code || '',
                        pudo_locker_name: locker.name || '',
                        pudo_locker_latitude: locker.latitude || null,
                        pudo_locker_longitude: locker.longitude || null,
                    }),
                });
                const payload = await response.json();

                if (! response.ok || ! payload.ok) {
                    setPudoStatus(payload.message || 'PUDO could not quote this locker right now.');
                    return;
                }

                setPudoStatus(`${payload.quote.label || 'PUDO'}: R ${payload.quote.delivery_fee}`);
            } catch (_) {
                setPudoStatus('PUDO could not quote this locker right now.');
            }
        }

        document.querySelector('[data-pudo-find-lockers]')?.addEventListener('click', loadPudoLockers);

        document.querySelector('[data-mall-locate-delivery]')?.addEventListener('click', (event) => {
            const button = event.currentTarget;
            const addressInput = document.getElementById('mall_delivery_address');
            const latitudeInput = document.getElementById('mall_delivery_latitude');
            const longitudeInput = document.getElementById('mall_delivery_longitude');

            if (! navigator.geolocation || ! addressInput || ! latitudeInput || ! longitudeInput) {
                setDeliveryAddressStatus('Location is not available in this browser. Type and select your address instead.');
                return;
            }

            button.disabled = true;
            setDeliveryAddressStatus('Finding your location...');

            navigator.geolocation.getCurrentPosition(async (position) => {
                const lat = Number(position.coords.latitude);
                const lng = Number(position.coords.longitude);

                latitudeInput.value = String(lat);
                longitudeInput.value = String(lng);
                updateTaxiEstimate();
                setDeliveryAddressStatus('Location found. Looking up address...');

                try {
                    addressInput.value = await reverseGeocodeDeliveryAddress(lat, lng) || fallbackAddress(lat, lng);
                    setDeliveryAddressStatus('Delivery address filled from your current location.');
                } catch (_) {
                    addressInput.value = fallbackAddress(lat, lng);
                    setDeliveryAddressStatus('Location found. Address lookup failed, so coordinates were used.');
                } finally {
                    updateTaxiEstimate();
                    if (selectedDeliveryMethod() === 'pudo') {
                        loadPudoLockers();
                    }
                    button.disabled = false;
                }
            }, () => {
                setDeliveryAddressStatus('Location permission was not granted. Type and select your address instead.');
                button.disabled = false;
            }, {
                enableHighAccuracy: true,
                maximumAge: 60000,
                timeout: 12000,
            });
        });

        mallCheckoutForm?.addEventListener('submit', (event) => {
            const selectedDeliveryMethod = document.querySelector('input[name="delivery_method"]:checked')?.value;

            if (selectedDeliveryMethod !== 'taxi') {
                if (selectedDeliveryMethod === 'pudo' && ! selectedPudoLockerCode()) {
                    event.preventDefault();
                    setPudoStatus('Select a PUDO locker before checkout.');
                }

                return;
            }

            const target = document.querySelector('[data-taxi-estimate]');

            if (mallMissingParcelWeightProducts.length > 0) {
                event.preventDefault();
                if (target) {
                    target.textContent = `Taxi delivery needs vendor parcel kg estimates for: ${mallMissingParcelWeightProducts.join(', ')}.`;
                    target.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
                return;
            }

            if (mallCoordinatesFromInputs()) {
                return;
            }

            event.preventDefault();
            if (target) {
                target.textContent = 'Select your delivery address from the address suggestions so taxi delivery can be quoted.';
                target.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        });

        updateDeliveryAddressPlacement();
        updateTaxiEstimate();
    </script>
@endsection
