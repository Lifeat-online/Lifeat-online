<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Transport Dev Setup</h2>
    </x-slot>

    <div class="py-10">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="rounded-md bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
            @endif

            @if (session('temporary_password'))
                <div class="rounded-md bg-amber-50 px-4 py-3 text-sm text-amber-900">
                    Temporary manager login for {{ session('manager_email') }}:
                    <strong>{{ session('temporary_password') }}</strong>
                </div>
            @endif

            <div class="grid gap-4 md:grid-cols-4">
                <div class="rounded-lg bg-white p-5 shadow-sm"><p class="text-sm text-gray-500">Managers</p><p class="mt-2 text-3xl font-bold">{{ $counts['managers'] }}</p></div>
                <div class="rounded-lg bg-white p-5 shadow-sm"><p class="text-sm text-gray-500">Approved drivers</p><p class="mt-2 text-3xl font-bold">{{ $counts['approvedDrivers'] }} / {{ $counts['drivers'] }}</p></div>
                <div class="rounded-lg bg-white p-5 shadow-sm"><p class="text-sm text-gray-500">Approved vehicles</p><p class="mt-2 text-3xl font-bold">{{ $counts['approvedVehicles'] }} / {{ $counts['vehicles'] }}</p></div>
                <div class="rounded-lg bg-white p-5 shadow-sm"><p class="text-sm text-gray-500">Live / open</p><p class="mt-2 text-3xl font-bold">{{ $counts['activeDuty'] }} / {{ $counts['openRequests'] }}</p></div>
            </div>

            <div class="grid gap-6 lg:grid-cols-2">
                <section class="rounded-lg bg-white p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-gray-900">Create transport manager</h3>
                    <p class="mt-1 text-sm text-gray-500">Dev/admin creates the manager here. Managers can then add drivers and vehicles from the Transport Manager page.</p>
                    <form method="post" action="{{ route('dev.transport.managers.store') }}" class="mt-5 space-y-4">
                        @csrf
                        <div class="grid gap-4 md:grid-cols-2">
                            <label class="block text-sm font-medium text-gray-700">Name
                                <input name="name" value="{{ old('name') }}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                            </label>
                            <label class="block text-sm font-medium text-gray-700">Email
                                <input name="email" type="email" value="{{ old('email') }}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                            </label>
                            <label class="block text-sm font-medium text-gray-700 md:col-span-2">Phone
                                <input name="phone" value="{{ old('phone') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                            </label>
                        </div>
                        <button class="rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white">Create manager</button>
                    </form>

                    <div class="mt-6 overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead><tr class="text-left text-gray-500"><th class="py-2">Manager</th><th>Created</th></tr></thead>
                            <tbody class="divide-y divide-gray-100">
                                @forelse ($managers as $manager)
                                    <tr>
                                        <td class="py-3">{{ $manager->name }}<br><span class="text-gray-500">{{ $manager->email }}</span></td>
                                        <td class="py-3">{{ $manager->created_at?->format('Y-m-d H:i') }}</td>
                                    </tr>
                                @empty
                                    <tr><td class="py-3 text-gray-500" colspan="2">No transport managers yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="rounded-lg bg-white p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-gray-900">Platform rules and safety</h3>
                    <form method="post" action="{{ route('dev.transport.settings.update') }}" class="mt-5 space-y-4">
                        @csrf
                        @method('put')
                        <div class="grid gap-4 md:grid-cols-3">
                            <label class="block text-sm font-medium text-gray-700">Platform fee %
                                <input name="platform_fee_percent" type="number" min="0" max="100" step="0.01" value="{{ old('platform_fee_percent', $settings['platform_fee_percent']) }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                            </label>
                            <label class="block text-sm font-medium text-gray-700">Offer limit
                                <input name="dispatch_offer_limit" type="number" min="1" max="100" value="{{ old('dispatch_offer_limit', $settings['dispatch_offer_limit']) }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                            </label>
                            <label class="block text-sm font-medium text-gray-700">Default radius km
                                <input name="default_search_radius_km" type="number" min="1" max="500" step="0.01" value="{{ old('default_search_radius_km', $settings['default_search_radius_km']) }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                            </label>
                        </div>
                        <div class="grid gap-4 md:grid-cols-2">
                            <label class="block text-sm font-medium text-gray-700">Safety phone
                                <input name="safety_contact_phone" value="{{ old('safety_contact_phone', $settings['safety_contact_phone']) }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                            </label>
                            <label class="block text-sm font-medium text-gray-700">Safety email
                                <input name="safety_contact_email" type="email" value="{{ old('safety_contact_email', $settings['safety_contact_email']) }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                            </label>
                        </div>
                        <label class="block text-sm font-medium text-gray-700">Panic button mode
                            <select name="panic_button_mode" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                @foreach (['manual_contact' => 'Manual contact list', 'support_dispatch' => 'Platform support dispatch', 'emergency_services' => 'Emergency services escalation'] as $value => $label)
                                    <option value="{{ $value }}" @selected(old('panic_button_mode', $settings['panic_button_mode']) === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                        <div class="grid gap-3 text-sm text-gray-700 md:grid-cols-2">
                            @foreach ([
                                'require_driver_id_number' => 'Require ID number',
                                'require_driver_license' => 'Require licence number',
                                'cash_enabled' => 'Allow cash',
                                'card_machine_enabled' => 'Allow driver card machines',
                                'payfast_enabled' => 'Allow PayFast',
                            ] as $key => $label)
                                <label><input type="checkbox" name="{{ $key }}" value="1" @checked(old($key, $settings[$key])) class="rounded border-gray-300"> {{ $label }}</label>
                            @endforeach
                        </div>
                        <button class="rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white">Save setup</button>
                    </form>
                </section>
            </div>

            <section class="rounded-lg bg-white p-6 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h3 class="text-lg font-semibold text-gray-900">Operations shortcuts</h3>
                    <div class="flex flex-wrap gap-2">
                        <a class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white" href="{{ route('transport.manager.dashboard') }}">Manager dashboard</a>
                        <a class="rounded-md bg-gray-100 px-4 py-2 text-sm font-semibold text-gray-700" href="{{ route('transport.index') }}">Public transport page</a>
                    </div>
                </div>

                <div class="mt-5 grid gap-6 lg:grid-cols-2">
                    <div class="overflow-x-auto">
                        <h4 class="mb-2 font-semibold text-gray-800">Recent requests</h4>
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <tbody class="divide-y divide-gray-100">
                                @forelse ($recentRequests as $request)
                                    <tr>
                                        <td class="py-3">{{ $request->request_number }}<br><span class="text-gray-500">{{ ucfirst($request->service_type) }} · {{ ucfirst($request->status) }}</span></td>
                                        <td class="py-3 text-right">R{{ number_format((float) $request->platform_fee, 2) }} fee</td>
                                    </tr>
                                @empty
                                    <tr><td class="py-3 text-gray-500">No requests yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="overflow-x-auto">
                        <h4 class="mb-2 font-semibold text-gray-800">Recent drivers and vehicles</h4>
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <tbody class="divide-y divide-gray-100">
                                @forelse ($recentDrivers as $driver)
                                    <tr>
                                        <td class="py-3">{{ $driver->user->name }}<br><span class="text-gray-500">{{ ucfirst($driver->status) }} · {{ $driver->vehicles->count() }} vehicle(s)</span></td>
                                        <td class="py-3 text-right">{{ $driver->manager?->name ?: 'No manager' }}</td>
                                    </tr>
                                @empty
                                    <tr><td class="py-3 text-gray-500">No drivers yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
