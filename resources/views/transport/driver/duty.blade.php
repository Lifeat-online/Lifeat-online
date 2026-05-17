<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Driver Duty</h2>
    </x-slot>

    <div class="py-10">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="rounded-md bg-blue-50 px-4 py-3 text-sm text-blue-800">{{ session('status') }}</div>
            @endif

            <section class="rounded-lg bg-white p-6 shadow-sm">
                <h3 class="text-lg font-semibold text-gray-900">Availability</h3>

                @unless ($driver)
                    <p class="mt-4 text-sm text-gray-600">Your driver profile has not been created yet. A transport manager must add and approve your driver profile before you can go on duty.</p>
                @else
                    <dl class="mt-4 grid gap-4 sm:grid-cols-3">
                        <div><dt class="text-sm text-gray-500">Profile</dt><dd class="font-semibold">{{ ucfirst($driver->status) }}</dd></div>
                        <div><dt class="text-sm text-gray-500">People</dt><dd class="font-semibold">{{ $driver->can_transport_people ? 'Allowed' : 'No' }}</dd></div>
                        <div><dt class="text-sm text-gray-500">Parcels</dt><dd class="font-semibold">{{ $driver->can_transport_parcels ? 'Allowed' : 'No' }}</dd></div>
                    </dl>

                    @if ($activeSession)
                        <div class="mt-6 rounded-md border border-green-200 bg-green-50 p-4">
                            <p class="font-semibold text-green-900">You are on duty with {{ $activeSession->vehicle->name }}.</p>
                            <p class="mt-1 text-sm text-green-800">The live driver workspace is visible while this session is active.</p>
                            <div class="mt-4 flex flex-wrap gap-3">
                                <a href="{{ route('transport.driver.workspace') }}" class="rounded-md bg-green-700 px-4 py-2 text-sm font-semibold text-white">Open live workspace</a>
                                <form method="post" action="{{ route('transport.driver.clock-out') }}">
                                    @csrf
                                    <button class="rounded-md bg-slate-800 px-4 py-2 text-sm font-semibold text-white">Clock out</button>
                                </form>
                            </div>
                        </div>
                    @elseif (! $driver->isApproved())
                        <p class="mt-6 rounded-md bg-amber-50 px-4 py-3 text-sm text-amber-900">Your profile must be approved before you can clock in.</p>
                    @elseif ($approvedVehicles->isEmpty())
                        <p class="mt-6 rounded-md bg-amber-50 px-4 py-3 text-sm text-amber-900">You need at least one approved vehicle before you can clock in.</p>
                    @else
                        <form method="post" action="{{ route('transport.driver.clock-in') }}" class="mt-6 space-y-4">
                            @csrf
                            <label class="block text-sm font-medium text-gray-700">Approved vehicle
                                <select name="transport_vehicle_id" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                    @foreach ($approvedVehicles as $vehicle)
                                        <option value="{{ $vehicle->id }}">{{ $vehicle->name }} - {{ ucfirst($vehicle->vehicle_type) }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <button class="rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white">Clock in as available</button>
                        </form>
                    @endif
                @endunless
            </section>
        </div>
    </div>
</x-app-layout>
