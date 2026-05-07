<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Fault Reports</h2>
            <a href="{{ route('faults.index') }}" class="rounded-md bg-slate-700 px-4 py-2 text-sm text-white">Public map</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto space-y-6 sm:px-6 lg:px-8">
            <div class="rounded-lg bg-white p-6 shadow-sm">
                <form method="get" class="grid gap-3 md:grid-cols-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700">Approval</label>
                        <select name="approval" class="mt-1 w-full rounded-md border-gray-300">
                            <option value="">All</option>
                            <option value="pending" @selected(request('approval') === 'pending')>Pending</option>
                            <option value="approved" @selected(request('approval') === 'approved')>Approved</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700">Category</label>
                        <select name="category" class="mt-1 w-full rounded-md border-gray-300">
                            <option value="">All</option>
                            @foreach ($categories as $key => $label)
                                <option value="{{ $key }}" @selected(request('category') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700">Status</label>
                        <select name="status" class="mt-1 w-full rounded-md border-gray-300">
                            <option value="">All</option>
                            @foreach ($statuses as $key => $label)
                                <option value="{{ $key }}" @selected(request('status') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex items-end gap-2">
                        <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm text-white">Filter</button>
                        <a href="{{ route('admin.fault-reports.index') }}" class="rounded-md bg-slate-700 px-4 py-2 text-sm text-white">Reset</a>
                    </div>
                </form>
            </div>

            <div class="rounded-lg bg-white p-6 shadow-sm">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="text-gray-500">
                            <th class="py-2">ID</th>
                            <th class="py-2">Category</th>
                            <th class="py-2">Status</th>
                            <th class="py-2">Approved</th>
                            <th class="py-2">Councillor</th>
                            <th class="py-2">Reported</th>
                            <th class="py-2 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($reports as $report)
                            <tr class="border-t">
                                <td class="py-3 font-semibold text-gray-900">#{{ $report->id }}</td>
                                <td class="py-3 text-gray-700">{{ $categories[$report->category] ?? $report->category }}</td>
                                <td class="py-3 text-gray-700">{{ $statuses[$report->status] ?? $report->status }}</td>
                                <td class="py-3 text-gray-700">{{ $report->is_approved ? 'Yes' : 'No' }}</td>
                                <td class="py-3 text-gray-700">{{ $report->assignedCouncillor?->full_name }}</td>
                                <td class="py-3 text-gray-700">{{ $report->created_at?->format('Y-m-d H:i') }}</td>
                                <td class="py-3 text-right">
                                    <a href="{{ route('admin.fault-reports.show', $report) }}" class="rounded-md bg-slate-700 px-3 py-1.5 text-xs text-white">Review</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="py-8 text-center text-gray-500">No reports found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div>{{ $reports->links() }}</div>
        </div>
    </div>
</x-app-layout>

