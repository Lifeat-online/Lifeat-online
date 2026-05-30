<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Edit Driver</h2>
    </x-slot>

    <div class="py-10">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <a href="{{ route('transport.manager.drivers.index') }}" class="rounded-md border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Back to drivers</a>
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
                <h3 class="text-lg font-semibold text-gray-900">{{ $driver->user->name }}</h3>
                <p class="mt-1 text-sm text-gray-500">{{ $driver->vehicles->count() }} vehicle(s) linked - {{ $driver->activeDutySession ? ucfirst($driver->activeDutySession->status) : 'Off duty' }}</p>

                <form method="post" action="{{ route('transport.manager.drivers.update', $driver) }}" class="mt-6 space-y-5">
                    @csrf
                    @method('put')

                    <div class="grid gap-4 md:grid-cols-2">
                        <label class="block text-sm font-medium text-gray-700">Name
                            <input name="name" value="{{ old('name', $driver->user->name) }}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                        </label>
                        <label class="block text-sm font-medium text-gray-700">Email
                            <input name="email" type="email" value="{{ old('email', $driver->user->email) }}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                        </label>
                        <label class="block text-sm font-medium text-gray-700">Phone
                            <input name="phone" value="{{ old('phone', $driver->phone) }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                        </label>
                        <label class="block text-sm font-medium text-gray-700">Status
                            <select name="status" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                @foreach (['pending' => 'Pending', 'approved' => 'Approved', 'suspended' => 'Suspended'] as $value => $label)
                                    <option value="{{ $value }}" @selected(old('status', $driver->status) === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="block text-sm font-medium text-gray-700">ID number
                            <input name="id_number" value="{{ old('id_number', $driver->id_number) }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                        </label>
                        <label class="block text-sm font-medium text-gray-700">Licence number
                            <input name="license_number" value="{{ old('license_number', $driver->license_number) }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                        </label>
                        <label class="block text-sm font-medium text-gray-700">Emergency contact
                            <input name="emergency_contact_name" value="{{ old('emergency_contact_name', $driver->emergency_contact_name) }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                        </label>
                        <label class="block text-sm font-medium text-gray-700">Emergency phone
                            <input name="emergency_contact_phone" value="{{ old('emergency_contact_phone', $driver->emergency_contact_phone) }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                        </label>
                    </div>

                    <div class="flex flex-wrap gap-4 text-sm text-gray-700">
                        <label><input type="checkbox" name="can_transport_people" value="1" @checked(old('can_transport_people', $driver->can_transport_people)) class="rounded border-gray-300"> Can carry people</label>
                        <label><input type="checkbox" name="can_transport_parcels" value="1" @checked(old('can_transport_parcels', $driver->can_transport_parcels)) class="rounded border-gray-300"> Can carry parcels</label>
                    </div>

                    <label class="block text-sm font-medium text-gray-700">Notes
                        <textarea name="notes" rows="4" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">{{ old('notes', $driver->notes) }}</textarea>
                    </label>

                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <p class="text-sm text-gray-500">Last updated {{ $driver->updated_at?->diffForHumans() }}.</p>
                        <button class="rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Save changes</button>
                    </div>
                </form>
            </section>
        </div>
    </div>
</x-app-layout>
