<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Request Transport</h2>
    </x-slot>

    <div class="py-10">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <section class="rounded-lg bg-white p-6 shadow-sm">
                <h3 class="text-lg font-semibold text-gray-900">Ride, parcel, or delivery request</h3>
                @if ($activeDriverCount > 0)
                    <p class="mt-2 rounded-md bg-green-50 px-4 py-3 text-sm text-green-800">{{ $activeDriverCount }} driver(s) are currently available for immediate requests.</p>
                @else
                    <p class="mt-2 rounded-md bg-amber-50 px-4 py-3 text-sm text-amber-900">No drivers are online right now. You can still save a scheduled ride or delivery request.</p>
                @endif
                <form method="post" action="{{ route('transport.requests.store') }}" class="mt-6 space-y-5">
                    @csrf
                    <div class="grid gap-4 md:grid-cols-2">
                        <label class="block text-sm font-medium text-gray-700">Service type
                            <select name="service_type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                <option value="parcel">Parcel delivery</option>
                                <option value="ride">Passenger ride</option>
                                <option value="errand">Errand or collection</option>
                                <option value="heavy_goods">Large item delivery</option>
                            </select>
                        </label>
                        <label class="block text-sm font-medium text-gray-700">Payment
                            <select name="payment_method" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                <option value="payfast">Pay online with PayFast</option>
                                <option value="cash">Cash to driver</option>
                                <option value="card_machine">Driver card machine</option>
                            </select>
                        </label>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <label class="block text-sm font-medium text-gray-700">Timing
                            <select name="request_timing" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                <option value="immediate" @selected(old('request_timing') === 'immediate')>As soon as possible</option>
                                <option value="scheduled" @selected(old('request_timing') === 'scheduled' || $activeDriverCount === 0)>Schedule for later</option>
                            </select>
                        </label>
                        <label class="block text-sm font-medium text-gray-700">Scheduled pickup
                            <input name="scheduled_pickup_at" type="datetime-local" value="{{ old('scheduled_pickup_at') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                        </label>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <label class="block text-sm font-medium text-gray-700">Pickup address
                            <input name="pickup_address" value="{{ old('pickup_address') }}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                        </label>
                        <label class="block text-sm font-medium text-gray-700">Dropoff address
                            <input name="dropoff_address" value="{{ old('dropoff_address') }}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                        </label>
                    </div>

                    <div class="grid gap-4 md:grid-cols-4">
                        <label class="block text-sm font-medium text-gray-700">Distance km
                            <input name="distance_km" type="number" min="0.1" max="2000" step="0.1" value="{{ old('distance_km', 5) }}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                        </label>
                        <label class="block text-sm font-medium text-gray-700">People
                            <input name="passenger_count" type="number" min="0" max="80" value="{{ old('passenger_count', 0) }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                        </label>
                        <label class="block text-sm font-medium text-gray-700">Parcel kg
                            <input name="parcel_weight_kg" type="number" min="0" step="0.1" value="{{ old('parcel_weight_kg') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                        </label>
                        <label class="block text-sm font-medium text-gray-700">Vehicle
                            <select name="required_vehicle_type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                <option value="">Any suitable</option>
                                @foreach (['bicycle', 'scooter', 'motorcycle', 'car', 'bakkie', 'ldv', 'van', 'truck', 'trailer'] as $type)
                                    <option value="{{ $type }}">{{ ucfirst($type) }}</option>
                                @endforeach
                            </select>
                        </label>
                    </div>

                    <label class="block text-sm font-medium text-gray-700">Notes
                        <textarea name="client_notes" rows="4" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">{{ old('client_notes') }}</textarea>
                    </label>

                    <button class="rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white">Send to available drivers</button>
                </form>
            </section>
        </div>
    </div>
</x-app-layout>
