<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Marketing Integrations</h2>
            <a href="{{ route('admin.integrations.create') }}" class="rounded-md bg-indigo-600 px-4 py-2 text-sm text-white">Create Integration</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto space-y-6 sm:px-6 lg:px-8">
            @if (session('status'))<div class="rounded-md bg-green-50 p-3 text-sm text-green-700">{{ session('status') }}</div>@endif

            <div class="rounded-lg bg-white p-6 shadow-sm">
                <form method="get" action="{{ route('admin.integrations.index') }}" class="grid gap-3 md:grid-cols-5">
                    <input class="rounded-md border-gray-300 text-sm md:col-span-2" name="q" placeholder="Search listing, type, provider…" value="{{ $filters['q'] ?? '' }}">
                    <select class="rounded-md border-gray-300 text-sm" name="status">
                        <option value="">All statuses</option>
                        <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Inactive</option>
                        <option value="active" @selected(($filters['status'] ?? '') === 'active')>Active</option>
                    </select>
                    <select class="rounded-md border-gray-300 text-sm" name="type">
                        <option value="">All types</option>
                        @foreach (($typeOptions ?? []) as $option)
                            <option value="{{ $option }}" @selected(($filters['type'] ?? '') === $option)>{{ $option }}</option>
                        @endforeach
                    </select>
                    <div class="flex gap-2">
                        <select class="w-full rounded-md border-gray-300 text-sm" name="sort">
                            <option value="newest" @selected(($filters['sort'] ?? '') === 'newest')>Newest</option>
                            <option value="oldest" @selected(($filters['sort'] ?? '') === 'oldest')>Oldest</option>
                        </select>
                        <button class="rounded-md bg-indigo-600 px-4 py-2 text-sm text-white" type="submit">Apply</button>
                    </div>
                </form>
            </div>

            <div class="rounded-lg bg-white p-6 shadow-sm">
                <form method="post" action="{{ route('admin.integrations.bulk') }}">
                    @csrf
                    <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                        <div class="flex flex-wrap items-center gap-2">
                            <select class="rounded-md border-gray-300 text-sm" name="action" required>
                                <option value="" selected disabled>Bulk action…</option>
                                <option value="activate">Activate</option>
                                <option value="deactivate">Deactivate</option>
                                <option value="delete">Delete</option>
                            </select>
                            <button class="rounded-md bg-slate-700 px-4 py-2 text-sm text-white" type="submit" onclick="return confirm('Apply this bulk action to the selected integrations?');">Run</button>
                        </div>
                        <label class="flex items-center gap-2 text-sm text-gray-600">
                            <input id="select_all" type="checkbox" class="rounded border-gray-300">
                            Select all on this page
                        </label>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left"></th>
                                    <th class="px-4 py-3 text-left">Listing</th>
                                    <th class="px-4 py-3 text-left">Type</th>
                                    <th class="px-4 py-3 text-left">Provider</th>
                                    <th class="px-4 py-3 text-left">Status</th>
                                    <th class="px-4 py-3 text-left">Updated</th>
                                    <th class="px-4 py-3 text-left">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @forelse ($integrations as $integration)
                                    <tr>
                                        <td class="px-4 py-3"><input class="row_cb rounded border-gray-300" type="checkbox" name="ids[]" value="{{ $integration->id }}"></td>
                                        <td class="px-4 py-3">{{ $integration->listing?->title ?: '-' }}</td>
                                        <td class="px-4 py-3">{{ $integration->type }}</td>
                                        <td class="px-4 py-3">{{ $integration->provider ?: '-' }}</td>
                                        <td class="px-4 py-3">{{ ucfirst($integration->status) }}</td>
                                        <td class="px-4 py-3 text-gray-600">{{ optional($integration->updated_at)->format('j M Y H:i') ?: '-' }}</td>
                                        <td class="px-4 py-3">
                                            <div class="flex gap-3">
                                                <a class="text-indigo-600" href="{{ route('admin.integrations.edit', $integration) }}">Edit</a>
                                                <form method="post" action="{{ route('admin.integrations.destroy', $integration) }}">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button class="text-red-600" type="submit" onclick="return confirm('Delete this integration?');">Delete</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="7" class="px-4 py-6 text-center text-gray-500">No integrations found.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </form>

                <div class="mt-6">{{ $integrations->links() }}</div>
            </div>
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
