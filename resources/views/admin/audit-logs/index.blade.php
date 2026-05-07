<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Audit Logs</h2>
            <a href="{{ route('admin.dashboard') }}" class="rounded-md bg-slate-700 px-4 py-2 text-sm text-white">Back to dashboard</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto space-y-6 sm:px-6 lg:px-8">
            <div class="rounded-lg bg-white p-6 shadow-sm">
                <form method="get" action="{{ route('admin.audit-logs.index') }}" class="grid gap-3 md:grid-cols-6">
                    <input class="rounded-md border-gray-300 text-sm md:col-span-2" name="action" placeholder="Action (contains)..." value="{{ $filters['action'] ?? '' }}">
                    <input class="rounded-md border-gray-300 text-sm" name="actor_user_id" placeholder="Actor user id" value="{{ $filters['actor_user_id'] ?? '' }}">
                    <input class="rounded-md border-gray-300 text-sm" name="subject_type" placeholder="Subject type..." value="{{ $filters['subject_type'] ?? '' }}">
                    <input class="rounded-md border-gray-300 text-sm" name="subject_id" placeholder="Subject id" value="{{ $filters['subject_id'] ?? '' }}">
                    <select class="rounded-md border-gray-300 text-sm" name="sort">
                        <option value="newest" @selected(($filters['sort'] ?? '') === 'newest')>Newest</option>
                        <option value="oldest" @selected(($filters['sort'] ?? '') === 'oldest')>Oldest</option>
                    </select>
                    <div class="md:col-span-3">
                        <label class="block text-xs font-semibold text-gray-600">From</label>
                        <input type="date" class="mt-1 w-full rounded-md border-gray-300 text-sm" name="from" value="{{ $filters['from'] ?? '' }}">
                    </div>
                    <div class="md:col-span-3">
                        <label class="block text-xs font-semibold text-gray-600">To</label>
                        <input type="date" class="mt-1 w-full rounded-md border-gray-300 text-sm" name="to" value="{{ $filters['to'] ?? '' }}">
                    </div>
                    <div class="md:col-span-6 flex gap-2">
                        <button class="rounded-md bg-indigo-600 px-4 py-2 text-sm text-white" type="submit">Apply</button>
                        <a href="{{ route('admin.audit-logs.index') }}" class="rounded-md bg-slate-700 px-4 py-2 text-sm text-white">Reset</a>
                    </div>
                </form>
            </div>

            <div class="rounded-lg bg-white p-6 shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left">When</th>
                                <th class="px-4 py-3 text-left">Actor</th>
                                <th class="px-4 py-3 text-left">Action</th>
                                <th class="px-4 py-3 text-left">Subject</th>
                                <th class="px-4 py-3 text-left">IP</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($logs as $log)
                                <tr>
                                    <td class="px-4 py-3 text-gray-600">{{ optional($log->created_at)->format('j M Y H:i:s') }}</td>
                                    <td class="px-4 py-3">{{ $log->actor?->name ?: 'System' }}</td>
                                    <td class="px-4 py-3 font-medium text-gray-900">{{ $log->action }}</td>
                                    <td class="px-4 py-3 text-gray-600">
                                        <div>{{ class_basename($log->subject_type) }}</div>
                                        <div>#{{ $log->subject_id ?? '-' }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-gray-600">{{ $log->ip_address ?: '-' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-4 py-6 text-center text-gray-500">No audit logs found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-6">{{ $logs->links() }}</div>
            </div>
        </div>
    </div>
</x-app-layout>
