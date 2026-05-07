<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">My Assigned Fault Reports</h2>
            <a href="{{ route('faults.index') }}" class="rounded-md bg-slate-700 px-4 py-2 text-sm text-white">Public map</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto space-y-6 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="rounded-lg bg-white p-6 shadow-sm">{{ session('status') }}</div>
            @endif

            <div class="rounded-lg bg-white p-6 shadow-sm">
                <form method="get" class="flex flex-wrap items-end gap-3">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700">Status</label>
                        <select name="status" class="mt-1 w-56 rounded-md border-gray-300">
                            <option value="">All</option>
                            @foreach ($statuses as $key => $label)
                                <option value="{{ $key }}" @selected(request('status') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm text-white">Filter</button>
                    <a href="{{ route('councillor.faults.index') }}" class="rounded-md bg-slate-700 px-4 py-2 text-sm text-white">Reset</a>
                </form>
            </div>

            <div class="rounded-lg bg-white p-6 shadow-sm">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="text-gray-500">
                            <th class="py-2">Report</th>
                            <th class="py-2">Category</th>
                            <th class="py-2">Reported</th>
                            <th class="py-2">Public</th>
                            <th class="py-2">Status</th>
                            <th class="py-2 text-right">Update</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($reports as $report)
                            <tr class="border-t align-top">
                                <td class="py-3">
                                    <div class="font-semibold text-gray-900">#{{ $report->id }}</div>
                                    <div class="text-xs text-gray-500">{{ $report->latitude }}, {{ $report->longitude }}</div>
                                    @if ($report->address_label)
                                        <div class="text-xs text-gray-500">{{ $report->address_label }}</div>
                                    @endif
                                </td>
                                <td class="py-3 text-gray-700">{{ $categories[$report->category] ?? $report->category }}</td>
                                <td class="py-3 text-gray-700">{{ $report->created_at?->format('Y-m-d H:i') }}</td>
                                <td class="py-3 text-gray-700">{{ $report->is_approved ? 'Yes' : 'No' }}</td>
                                <td class="py-3 text-gray-700">{{ $statuses[$report->status] ?? $report->status }}</td>
                                <td class="py-3 text-right">
                                    <form method="post" action="{{ route('councillor.faults.status', $report) }}" class="flex items-center justify-end gap-2">
                                        @csrf
                                        <select name="status" class="rounded-md border-gray-300 text-sm">
                                            @foreach ($statuses as $key => $label)
                                                <option value="{{ $key }}" @selected($report->status === $key)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                        <button type="submit" class="rounded-md bg-indigo-600 px-3 py-1.5 text-xs text-white">Save</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="py-8 text-center text-gray-500">No assigned reports.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div>{{ $reports->links() }}</div>
        </div>
    </div>
</x-app-layout>

