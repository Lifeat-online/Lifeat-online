<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Transport Drivers</h2>
    </x-slot>

    <div class="py-10">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="rounded-md bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
            @endif

            <div class="flex flex-wrap items-center justify-between gap-3">
                <a href="{{ route('transport.manager.dashboard') }}" class="rounded-md border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Back to manager</a>
                <a href="{{ route('transport.manager.dashboard', ['form' => 'driver']) }}#add-driver" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Add driver</a>
            </div>

            <section class="rounded-lg bg-white p-6 shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead>
                            <tr class="text-left text-gray-500">
                                <th class="py-2 pr-4">Driver</th>
                                <th class="py-2 pr-4">Phone</th>
                                <th class="py-2 pr-4">Status</th>
                                <th class="py-2 pr-4">Vehicles</th>
                                <th class="py-2 pr-4">Duty</th>
                                <th class="py-2 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($drivers as $driver)
                                <tr>
                                    <td class="py-3 pr-4">
                                        <span class="font-semibold text-gray-900">{{ $driver->user->name }}</span><br>
                                        <span class="text-gray-500">{{ $driver->user->email }}</span>
                                    </td>
                                    <td class="py-3 pr-4">{{ $driver->phone ?: 'Not set' }}</td>
                                    <td class="py-3 pr-4">{{ ucfirst($driver->status) }}</td>
                                    <td class="py-3 pr-4">{{ $driver->vehicles->count() }}</td>
                                    <td class="py-3 pr-4">
                                        {{ $driver->activeDutySession ? ucfirst($driver->activeDutySession->status) : 'Off duty' }}
                                    </td>
                                    <td class="py-3 text-right">
                                        <a href="{{ route('transport.manager.drivers.edit', $driver) }}" class="rounded-md border border-slate-300 px-3 py-1.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Edit</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="py-6 text-center text-gray-500">No drivers have been added yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-5">
                    {{ $drivers->links() }}
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
