<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Finance Notifications</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto space-y-6 sm:px-6 lg:px-8">
            <form method="get" action="{{ route('admin.finance.notifications.index') }}" class="grid gap-4 rounded-lg bg-white p-4 shadow-sm md:grid-cols-4">
                <input class="rounded-md border-gray-300 text-sm" name="type" placeholder="Notification type" value="{{ $filters['type'] ?? '' }}">
                <select class="rounded-md border-gray-300 text-sm" name="status">
                    <option value="">All statuses</option>
                    @foreach (['attention', 'pending', 'queued', 'sent', 'logged', 'failed'] as $status)
                        <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ ucfirst($status) }}</option>
                    @endforeach
                </select>
                <select class="rounded-md border-gray-300 text-sm" name="channel">
                    <option value="">All channels</option>
                    @foreach (['email', 'push'] as $channel)
                        <option value="{{ $channel }}" @selected(($filters['channel'] ?? '') === $channel)>{{ ucfirst($channel) }}</option>
                    @endforeach
                </select>
                <button class="rounded-md bg-indigo-600 px-4 py-2 text-sm text-white" type="submit">Apply Filters</button>
            </form>

            <div class="rounded-lg bg-white p-6 shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left">Type</th>
                                <th class="px-4 py-3 text-left">Recipient</th>
                                <th class="px-4 py-3 text-left">Channel</th>
                                <th class="px-4 py-3 text-left">Status</th>
                                <th class="px-4 py-3 text-left">Sent At</th>
                                <th class="px-4 py-3 text-left">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($notifications as $notification)
                                <tr>
                                    <td class="px-4 py-3">{{ $notification->notification_type }}</td>
                                    <td class="px-4 py-3">{{ $notification->recipient ?: '-' }}</td>
                                    <td class="px-4 py-3">{{ ucfirst($notification->channel) }}</td>
                                    <td class="px-4 py-3">
                                        <span class="@if($notification->status === 'failed') text-red-600 @elseif($notification->status === 'sent') text-green-600 @else text-gray-700 @endif">
                                            {{ ucfirst($notification->status) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">{{ optional($notification->sent_at)->format('j M Y H:i') ?: '-' }}</td>
                                    <td class="px-4 py-3"><a class="text-indigo-600" href="{{ route('admin.finance.notifications.show', $notification) }}">View</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-6">{{ $notifications->links() }}</div>
            </div>
        </div>
    </div>
</x-app-layout>
