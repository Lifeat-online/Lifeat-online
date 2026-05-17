<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Transport Manager</h2>
    </x-slot>

    <div class="py-10">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="rounded-md bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
            @endif

            <div class="grid gap-4 md:grid-cols-4">
                <div class="rounded-lg bg-white p-5 shadow-sm"><p class="text-sm text-gray-500">Drivers</p><p class="mt-2 text-3xl font-bold">{{ $counts['drivers'] }}</p></div>
                <div class="rounded-lg bg-white p-5 shadow-sm"><p class="text-sm text-gray-500">Approved drivers</p><p class="mt-2 text-3xl font-bold">{{ $counts['approvedDrivers'] }}</p></div>
                <div class="rounded-lg bg-white p-5 shadow-sm"><p class="text-sm text-gray-500">Vehicles</p><p class="mt-2 text-3xl font-bold">{{ $counts['vehicles'] }}</p></div>
                <div class="rounded-lg bg-white p-5 shadow-sm"><p class="text-sm text-gray-500">On duty</p><p class="mt-2 text-3xl font-bold">{{ $counts['activeDuty'] }}</p></div>
            </div>

            <div class="grid gap-6 lg:grid-cols-2">
                <section class="rounded-lg bg-white p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-gray-900">Add driver</h3>
                    <form method="post" action="{{ route('transport.manager.drivers.store') }}" class="mt-5 space-y-4">
                        @csrf
                        <div class="grid gap-4 md:grid-cols-2">
                            <label class="block text-sm font-medium text-gray-700">Name
                                <input name="name" value="{{ old('name') }}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                            </label>
                            <label class="block text-sm font-medium text-gray-700">Email
                                <input name="email" type="email" value="{{ old('email') }}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                            </label>
                            <label class="block text-sm font-medium text-gray-700">Phone
                                <input name="phone" value="{{ old('phone') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                            </label>
                            <label class="block text-sm font-medium text-gray-700">ID number
                                <input name="id_number" value="{{ old('id_number') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                            </label>
                            <label class="block text-sm font-medium text-gray-700">Licence number
                                <input name="license_number" value="{{ old('license_number') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                            </label>
                            <label class="block text-sm font-medium text-gray-700">Status
                                <select name="status" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                    <option value="pending">Pending</option>
                                    <option value="approved">Approved</option>
                                    <option value="suspended">Suspended</option>
                                </select>
                            </label>
                        </div>
                        <div class="grid gap-4 md:grid-cols-2">
                            <label class="block text-sm font-medium text-gray-700">Emergency contact
                                <input name="emergency_contact_name" value="{{ old('emergency_contact_name') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                            </label>
                            <label class="block text-sm font-medium text-gray-700">Emergency phone
                                <input name="emergency_contact_phone" value="{{ old('emergency_contact_phone') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                            </label>
                        </div>
                        <div class="flex flex-wrap gap-4 text-sm text-gray-700">
                            <label><input type="checkbox" name="can_transport_people" value="1" class="rounded border-gray-300"> Can carry people</label>
                            <label><input type="checkbox" name="can_transport_parcels" value="1" checked class="rounded border-gray-300"> Can carry parcels</label>
                        </div>
                        <label class="block text-sm font-medium text-gray-700">Notes
                            <textarea name="notes" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">{{ old('notes') }}</textarea>
                        </label>
                        <button class="rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white">Save driver</button>
                    </form>
                </section>

                <section class="rounded-lg bg-white p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-gray-900">Add vehicle</h3>
                    <form method="post" action="{{ route('transport.manager.vehicles.store') }}" class="mt-5 space-y-4">
                        @csrf
                        <div class="grid gap-4 md:grid-cols-2">
                            <label class="block text-sm font-medium text-gray-700">Driver
                                <select name="transport_driver_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                    <option value="">Unassigned</option>
                                    @foreach ($driverOptions as $driver)
                                        <option value="{{ $driver->id }}">{{ $driver->user->name }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="block text-sm font-medium text-gray-700">Vehicle name
                                <input name="name" required placeholder="Bike, Bakkie, Delivery van" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                            </label>
                            <label class="block text-sm font-medium text-gray-700">Type
                                <select name="vehicle_type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                    @foreach (['bicycle', 'scooter', 'motorcycle', 'car', 'bakkie', 'ldv', 'van', 'truck', 'trailer'] as $type)
                                        <option value="{{ $type }}">{{ ucfirst($type) }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="block text-sm font-medium text-gray-700">Registration
                                <input name="registration_number" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                            </label>
                            <label class="block text-sm font-medium text-gray-700">Status
                                <select name="status" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                    <option value="pending">Pending</option>
                                    <option value="approved">Approved</option>
                                    <option value="suspended">Suspended</option>
                                </select>
                            </label>
                            <label class="block text-sm font-medium text-gray-700">Pricing
                                <select name="pricing_mode" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                    <option value="per_km">Per km</option>
                                    <option value="per_km_plus_people">Per km plus people</option>
                                </select>
                            </label>
                            <label class="block text-sm font-medium text-gray-700">Max passengers
                                <input name="max_passengers" type="number" min="0" value="0" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                            </label>
                            <label class="block text-sm font-medium text-gray-700">Max kg
                                <input name="max_weight_kg" type="number" min="0" step="0.01" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                            </label>
                        </div>
                        <div class="grid gap-4 md:grid-cols-3">
                            <label class="block text-sm font-medium text-gray-700">Base fee
                                <input name="base_fee" type="number" min="0" step="0.01" value="0" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                            </label>
                            <label class="block text-sm font-medium text-gray-700">Per km
                                <input name="per_km_fee" type="number" min="0" step="0.01" value="0" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                            </label>
                            <label class="block text-sm font-medium text-gray-700">Minimum fee
                                <input name="minimum_fee" type="number" min="0" step="0.01" value="0" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                            </label>
                        </div>
                        <div class="flex flex-wrap gap-4 text-sm text-gray-700">
                            <label><input type="checkbox" name="can_carry_people" value="1" class="rounded border-gray-300"> People</label>
                            <label><input type="checkbox" name="can_carry_parcels" value="1" checked class="rounded border-gray-300"> Parcels</label>
                            <label><input type="checkbox" name="accepts_cash" value="1" checked class="rounded border-gray-300"> Cash</label>
                            <label><input type="checkbox" name="has_card_machine" value="1" class="rounded border-gray-300"> Driver card machine</label>
                            <label><input type="checkbox" name="accepts_payfast" value="1" checked class="rounded border-gray-300"> PayFast</label>
                        </div>
                        <button class="rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white">Save vehicle</button>
                    </form>
                </section>
            </div>

            <section class="rounded-lg bg-white p-6 shadow-sm">
                <h3 class="text-lg font-semibold text-gray-900">Recent drivers</h3>
                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead><tr class="text-left text-gray-500"><th class="py-2">Driver</th><th>Status</th><th>Vehicles</th><th>Duty</th></tr></thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($drivers as $driver)
                                <tr>
                                    <td class="py-3">{{ $driver->user->name }}<br><span class="text-gray-500">{{ $driver->user->email }}</span></td>
                                    <td class="py-3">{{ ucfirst($driver->status) }}</td>
                                    <td class="py-3">{{ $driver->vehicles->count() }}</td>
                                    <td class="py-3">{{ $driver->activeDutySession ? ucfirst($driver->activeDutySession->status) : 'Off duty' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
