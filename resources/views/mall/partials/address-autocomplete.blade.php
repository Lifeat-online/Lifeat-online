@once
    @push('styles')
        <style>
            .mall-address-wrap { position: relative; display: grid; gap: .35rem; }
            .mall-address-suggestions { position: absolute; z-index: 40; top: calc(100% + .25rem); left: 0; right: 0; display: none; max-height: 260px; overflow: auto; border: 1px solid var(--border); border-radius: 8px; background: var(--surface); box-shadow: 0 18px 45px rgb(0 0 0 / .2); }
            .mall-address-suggestions.is-open { display: grid; }
            .mall-address-option { display: grid; gap: .15rem; width: 100%; padding: .72rem .85rem; border: 0; border-bottom: 1px solid var(--border); background: transparent; color: var(--text); text-align: left; cursor: pointer; }
            .mall-address-option:last-child { border-bottom: 0; }
            .mall-address-option:hover, .mall-address-option:focus { background: color-mix(in srgb, var(--accent, #3B82F6) 12%, var(--surface)); outline: none; }
            .mall-address-option span, .mall-address-status { color: var(--muted); font-size: .82rem; line-height: 1.35; }
        </style>
    @endpush

    @push('scripts')
        <script>
            (() => {
                if (window.mallAddressAutocompleteReady) {
                    return;
                }

                window.mallAddressAutocompleteReady = true;

                const endpoints = {
                    autocomplete: @json(route('maps.places.autocomplete')),
                    details: @json(route('maps.places.details')),
                };

                const debounce = (callback, wait = 320) => {
                    let timer = null;

                    return (...args) => {
                        clearTimeout(timer);
                        timer = setTimeout(() => callback(...args), wait);
                    };
                };

                const labelFor = (result) => result.display_name || result.formatted_address || result.label || result.description || 'Address';
                const subLabelFor = (result) => result.detail || result.secondary_text || result.subtitle || result.place_name || '';

                const closeSuggestions = (suggestions) => {
                    if (! suggestions) {
                        return;
                    }

                    suggestions.classList.remove('is-open');
                    suggestions.replaceChildren();
                };

                const setStatus = (input, message) => {
                    const target = input.dataset.statusTarget ? document.getElementById(input.dataset.statusTarget) : null;

                    if (target) {
                        target.textContent = message || '';
                    }
                };

                const originFor = (input) => {
                    const lat = Number(input.dataset.originLat);
                    const lng = Number(input.dataset.originLng);

                    return Number.isFinite(lat) && Number.isFinite(lng) ? { lat, lng } : null;
                };

                const coordinateInputsFor = (input) => {
                    return {
                        lat: input.dataset.latitudeTarget ? document.getElementById(input.dataset.latitudeTarget) : null,
                        lng: input.dataset.longitudeTarget ? document.getElementById(input.dataset.longitudeTarget) : null,
                    };
                };

                const notifyCoordinateChange = (input) => {
                    const event = new CustomEvent('mall:address-coordinates-updated', {
                        bubbles: true,
                        detail: { prefix: input.dataset.addressPrefix || null },
                    });

                    input.dispatchEvent(event);
                    document.dispatchEvent(event);
                };

                const searchAddresses = async (query, signal, origin = null) => {
                    const url = new URL(endpoints.autocomplete, window.location.origin);
                    url.searchParams.set('q', query);

                    if (origin) {
                        url.searchParams.set('lat', String(origin.lat));
                        url.searchParams.set('lng', String(origin.lng));
                    }

                    const response = await fetch(url.toString(), {
                        headers: { Accept: 'application/json' },
                        signal,
                    });

                    if (! response.ok) {
                        return [];
                    }

                    const payload = await response.json();

                    return Array.isArray(payload.results) ? payload.results : [];
                };

                const fetchAddressDetails = async (placeId) => {
                    if (! placeId) {
                        return null;
                    }

                    const url = new URL(endpoints.details, window.location.origin);
                    url.searchParams.set('place_id', placeId);

                    const response = await fetch(url.toString(), {
                        headers: { Accept: 'application/json' },
                    });

                    if (! response.ok) {
                        return null;
                    }

                    const payload = await response.json();

                    return payload.result || null;
                };

                const initAddressAutocomplete = (input) => {
                    if (input.dataset.mallAddressReady === 'true') {
                        return;
                    }

                    input.dataset.mallAddressReady = 'true';

                    const suggestions = document.getElementById(`${input.id}_suggestions`);
                    const coordinates = coordinateInputsFor(input);
                    let controller = null;

                    if (! suggestions || ! coordinates.lat || ! coordinates.lng) {
                        return;
                    }

                    const clearCoordinates = () => {
                        coordinates.lat.value = '';
                        coordinates.lng.value = '';
                        notifyCoordinateChange(input);
                    };

                    const selectResult = async (result) => {
                        closeSuggestions(suggestions);
                        setStatus(input, 'Confirming address...');

                        const details = await fetchAddressDetails(result.place_id);
                        const lat = details?.lat ?? details?.latitude ?? result.lat;
                        const lng = details?.lon ?? details?.lng ?? details?.longitude ?? result.lon;

                        if (lat !== undefined && lng !== undefined) {
                            input.value = details?.display_name || details?.formatted_address || labelFor(result);
                            coordinates.lat.value = String(lat);
                            coordinates.lng.value = String(lng);
                            setStatus(input, 'Address selected.');
                            notifyCoordinateChange(input);
                            return;
                        }

                        clearCoordinates();
                        setStatus(input, 'Select another suggestion so the delivery distance can be confirmed.');
                    };

                    const renderResults = (results) => {
                        suggestions.replaceChildren();

                        results.forEach((result) => {
                            const option = document.createElement('button');
                            option.type = 'button';
                            option.className = 'mall-address-option';
                            option.setAttribute('role', 'option');

                            const title = document.createElement('strong');
                            title.textContent = labelFor(result);
                            option.append(title);

                            const subtitle = subLabelFor(result);
                            if (subtitle) {
                                const detail = document.createElement('span');
                                detail.textContent = subtitle;
                                option.append(detail);
                            }

                            option.addEventListener('click', () => selectResult(result));
                            suggestions.append(option);
                        });

                        suggestions.classList.toggle('is-open', results.length > 0);
                    };

                    input.addEventListener('input', debounce(async () => {
                        clearCoordinates();

                        const query = input.value.trim();
                        if (query.length < 3) {
                            closeSuggestions(suggestions);
                            setStatus(input, '');
                            return;
                        }

                        controller?.abort();
                        controller = new AbortController();
                        setStatus(input, 'Searching addresses...');

                        try {
                            renderResults(await searchAddresses(query, controller.signal, originFor(input)));
                            setStatus(input, suggestions.children.length ? 'Select an address to confirm distance.' : 'No address suggestions found.');
                        } catch (error) {
                            if (error.name !== 'AbortError') {
                                closeSuggestions(suggestions);
                                setStatus(input, 'Address search is unavailable. Try again shortly.');
                            }
                        }
                    }));
                };

                document.querySelectorAll('[data-mall-address-autocomplete]').forEach(initAddressAutocomplete);

                document.addEventListener('click', (event) => {
                    document.querySelectorAll('.mall-address-suggestions.is-open').forEach((suggestions) => {
                        if (! suggestions.parentElement?.contains(event.target)) {
                            closeSuggestions(suggestions);
                        }
                    });
                });
            })();
        </script>
    @endpush
@endonce
