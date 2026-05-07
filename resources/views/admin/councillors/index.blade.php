<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Councillors</h2>
            <a href="{{ route('admin.councillors.create') }}" class="rounded-md bg-indigo-600 px-4 py-2 text-sm text-white">Add Councillor</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto space-y-6 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="rounded-lg bg-white p-6 shadow-sm">{{ session('status') }}</div>
            @endif

            <div class="rounded-lg bg-white p-6 shadow-sm">
                <form method="get" class="grid gap-3 md:grid-cols-4">
                    <div class="md:col-span-2">
                        <label class="block text-sm font-semibold text-gray-700">Search</label>
                        <input name="q" value="{{ $filters['q'] ?? '' }}" class="mt-1 w-full rounded-md border-gray-300" placeholder="Name, email, phone">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700">Active</label>
                        <select name="active" class="mt-1 w-full rounded-md border-gray-300">
                            <option value="">All</option>
                            <option value="yes" @selected(($filters['active'] ?? '') === 'yes')>Active</option>
                            <option value="no" @selected(($filters['active'] ?? '') === 'no')>Inactive</option>
                        </select>
                    </div>
                    <div class="flex items-end gap-2">
                        <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm text-white">Filter</button>
                        <a href="{{ route('admin.councillors.index') }}" class="rounded-md bg-slate-700 px-4 py-2 text-sm text-white">Reset</a>
                    </div>
                </form>
            </div>

            <div class="rounded-lg bg-white p-6 shadow-sm">
                <form method="post" action="{{ route('admin.councillors.bulk') }}">
                    @csrf
                    <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                        <div class="flex flex-wrap items-center gap-2">
                            <select class="rounded-md border-gray-300 text-sm" name="action" required>
                                <option value="" selected disabled>Bulk action…</option>
                                <option value="activate">Activate</option>
                                <option value="deactivate">Deactivate</option>
                                <option value="delete">Delete</option>
                            </select>
                            <button class="rounded-md bg-slate-700 px-4 py-2 text-sm text-white" type="submit" onclick="return confirm('Apply this bulk action to the selected councillors?');">Run</button>
                        </div>
                        <label class="flex items-center gap-2 text-sm text-gray-600">
                            <input id="select_all" type="checkbox" class="rounded border-gray-300">
                            Select all on this page
                        </label>
                    </div>
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="text-gray-500">
                            <th class="py-2"></th>
                            <th class="py-2">Name</th>
                            <th class="py-2">Phone</th>
                            <th class="py-2">Email</th>
                            <th class="py-2">Active</th>
                            <th class="py-2">Assigned Faults</th>
                            <th class="py-2 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($councillors as $councillor)
                            <tr class="border-t">
                                <td class="py-3"><input class="row_cb rounded border-gray-300" type="checkbox" name="ids[]" value="{{ $councillor->id }}"></td>
                                <td class="py-3 font-semibold text-gray-900">{{ $councillor->full_name }}</td>
                                <td class="py-3 text-gray-700">{{ $councillor->phone }}</td>
                                <td class="py-3 text-gray-700">{{ $councillor->email }}</td>
                                <td class="py-3 text-gray-700">{{ $councillor->is_active ? 'Yes' : 'No' }}</td>
                                <td class="py-3 text-gray-700">{{ $councillor->assigned_fault_reports_count }}</td>
                                <td class="py-3 text-right">
                                    <a href="{{ route('admin.councillors.edit', $councillor) }}" class="rounded-md bg-slate-700 px-3 py-1.5 text-xs text-white">Edit</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="py-8 text-center text-gray-500">No councillors found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                </form>
            </div>

            <div>{{ $councillors->links() }}</div>
        </div>
    </div>

    <script>
        (() => {
            const all = document.getElementById('select_all');
            const cbs = Array.from(document.querySelectorAll('.row_cb'));
            if (!all || cbs.length === 0) return;
            all.addEventListener('change', () => cbs.forEach((cb) => cb.checked = all.checked));
        })();
    </script>
</x-app-layout>
