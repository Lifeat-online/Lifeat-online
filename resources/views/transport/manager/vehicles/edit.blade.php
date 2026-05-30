<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Edit Vehicle</h2>
    </x-slot>

    <div class="py-10">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <a href="{{ route('transport.manager.vehicles.index') }}" class="rounded-md border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Back to vehicles</a>
                <a href="{{ route('transport.manager.dashboard') }}" class="rounded-md border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Transport Manager</a>
            </div>

            @if ($errors->any())
                <div class="rounded-md bg-red-50 px-4 py-3 text-sm text-red-800">
                    <ul class="list-disc space-y-1 pl-5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <section class="rounded-lg bg-white p-6 shadow-sm">
                <h3 class="text-lg font-semibold text-gray-900">{{ $vehicle->name }}</h3>
                <p class="mt-1 text-sm text-gray-500">{{ ucfirst($vehicle->vehicle_type) }} - {{ $vehicle->driver?->user?->name ?: 'Unassigned' }}</p>

                <form method="post" action="{{ route('transport.manager.vehicles.update', $vehicle) }}" class="mt-6 space-y-5">
                    @csrf
                    @method('put')

                    <div class="grid gap-4 md:grid-cols-2">
                        <label class="block text-sm font-medium text-gray-700">Driver
                            <select name="transport_driver_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                <option value="">Unassigned</option>
                                @foreach ($driverOptions as $driver)
                                    <option value="{{ $driver->id }}" @selected((string) old('transport_driver_id', $vehicle->transport_driver_id) === (string) $driver->id)>{{ $driver->user->name }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="block text-sm font-medium text-gray-700">Vehicle name
                            <input name="name" value="{{ old('name', $vehicle->name) }}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                        </label>
                        <label class="block text-sm font-medium text-gray-700">Type
                            <select name="vehicle_type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                @foreach (['bicycle', 'scooter', 'motorcycle', 'car', 'bakkie', 'ldv', 'van', 'truck', 'trailer'] as $type)
                                    <option value="{{ $type }}" @selected(old('vehicle_type', $vehicle->vehicle_type) === $type)>{{ ucfirst($type) }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="block text-sm font-medium text-gray-700">Registration
                            <input name="registration_number" value="{{ old('registration_number', $vehicle->registration_number) }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                        </label>
                        <label class="block text-sm font-medium text-gray-700">Status
                            <select name="status" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                @foreach (['pending' => 'Pending', 'approved' => 'Approved', 'suspended' => 'Suspended'] as $value => $label)
                                    <option value="{{ $value }}" @selected(old('status', $vehicle->status) === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="block text-sm font-medium text-gray-700">Pricing
                            <select name="pricing_mode" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                <option value="per_km" @selected(old('pricing_mode', $vehicle->pricing_mode) === 'per_km')>Per km</option>
                                <option value="per_km_plus_people" @selected(old('pricing_mode', $vehicle->pricing_mode) === 'per_km_plus_people')>Per km plus people</option>
                            </select>
                        </label>
                        <label class="block text-sm font-medium text-gray-700">Max passengers
                            <input name="max_passengers" type="number" min="0" value="{{ old('max_passengers', $vehicle->max_passengers) }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                        </label>
                        <label class="block text-sm font-medium text-gray-700">Max kg
                            <input name="max_weight_kg" type="number" min="0" step="0.01" value="{{ old('max_weight_kg', $vehicle->max_weight_kg) }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                        </label>
                    </div>

                    <div class="grid gap-4 md:grid-cols-3">
                        <label class="block text-sm font-medium text-gray-700">Base fee
                            <input name="base_fee" type="number" min="0" step="0.01" value="{{ old('base_fee', $vehicle->base_fee) }}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                        </label>
                        <label class="block text-sm font-medium text-gray-700">Per km
                            <input name="per_km_fee" type="number" min="0" step="0.01" value="{{ old('per_km_fee', $vehicle->per_km_fee) }}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                        </label>
                        <label class="block text-sm font-medium text-gray-700">Per person
                            <input name="per_person_fee" type="number" min="0" step="0.01" value="{{ old('per_person_fee', $vehicle->per_person_fee) }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                        </label>
                        <label class="block text-sm font-medium text-gray-700">Minimum fee
                            <input name="minimum_fee" type="number" min="0" step="0.01" value="{{ old('minimum_fee', $vehicle->minimum_fee) }}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                        </label>
                        <label class="block text-sm font-medium text-gray-700">Waiting fee
                            <input name="waiting_fee" type="number" min="0" step="0.01" value="{{ old('waiting_fee', $vehicle->waiting_fee) }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                        </label>
                        <label class="block text-sm font-medium text-gray-700">Cancellation fee
                            <input name="cancellation_fee" type="number" min="0" step="0.01" value="{{ old('cancellation_fee', $vehicle->cancellation_fee) }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                        </label>
                    </div>

                    <div class="flex flex-wrap gap-4 text-sm text-gray-700">
                        <label><input type="checkbox" name="can_carry_people" value="1" @checked(old('can_carry_people', $vehicle->can_carry_people)) class="rounded border-gray-300"> People</label>
                        <label><input type="checkbox" name="can_carry_parcels" value="1" @checked(old('can_carry_parcels', $vehicle->can_carry_parcels)) class="rounded border-gray-300"> Parcels</label>
                        <label><input type="checkbox" name="accepts_cash" value="1" @checked(old('accepts_cash', $vehicle->accepts_cash)) class="rounded border-gray-300"> Cash</label>
                        <label><input type="checkbox" name="has_card_machine" value="1" @checked(old('has_card_machine', $vehicle->has_card_machine)) class="rounded border-gray-300"> Driver card machine</label>
                        <label><input type="checkbox" name="accepts_payfast" value="1" @checked(old('accepts_payfast', $vehicle->accepts_payfast)) class="rounded border-gray-300"> PayFast</label>
                    </div>

                    <label class="block text-sm font-medium text-gray-700">Notes
                        <textarea name="notes" rows="4" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">{{ old('notes', $vehicle->notes) }}</textarea>
                    </label>

                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <p class="text-sm text-gray-500">Last updated {{ $vehicle->updated_at?->diffForHumans() }}.</p>
                        <button class="rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Save changes</button>
                    </div>
                </form>
            </section>
        </div>
    </div>
</x-app-layout>
