<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Subscription {{ $subscription->id }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto space-y-6 sm:px-6 lg:px-8">
            <div class="rounded-lg bg-white p-6 shadow-sm">
                <p><strong>Package:</strong> {{ $subscription->package?->name }}</p>
                <p><strong>User:</strong> {{ $subscription->user?->name }}</p>
                <p><strong>Status:</strong> {{ ucfirst($subscription->status) }}</p>
                <p><strong>Ends At:</strong> {{ optional($subscription->ends_at)->format('j M Y H:i') ?: '-' }}</p>
                <p><strong>Renewal Mode:</strong> {{ ucfirst($subscription->renewal_mode ?: 'manual') }}</p>
            </div>

            <div class="grid gap-6 lg:grid-cols-2">
                <div class="rounded-lg bg-white p-6 shadow-sm">
                    <h3 class="mb-4 text-lg font-semibold text-gray-900">Entitlements</h3>
                    <div class="space-y-3 text-sm">
                        @foreach ($subscription->entitlements as $entitlement)
                            <div class="rounded-md bg-gray-50 p-3">
                                <p class="font-medium">{{ $entitlement->entitlement_code }}</p>
                                <p>{{ ucfirst($entitlement->status) }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="rounded-lg bg-white p-6 shadow-sm">
                    <h3 class="mb-4 text-lg font-semibold text-gray-900">Reminders</h3>
                    <div class="space-y-3 text-sm">
                        @forelse ($subscription->reminders as $reminder)
                            <div class="rounded-md bg-gray-50 p-3">
                                <p class="font-medium">{{ ucfirst(str_replace('_', ' ', $reminder->reminder_type)) }}</p>
                                <p>{{ ucfirst($reminder->status) }}</p>
                            </div>
                        @empty
                            <p class="text-gray-500">No reminders logged.</p>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="rounded-lg bg-white p-6 shadow-sm">
                <h3 class="mb-4 text-lg font-semibold text-gray-900">Notifications</h3>
                <div class="space-y-3 text-sm">
                    @forelse ($notifications as $notification)
                        <div class="rounded-md bg-gray-50 p-3">
                            <p class="font-medium">{{ ucfirst(str_replace('_', ' ', $notification->notification_type)) }}</p>
                            <p>{{ $notification->recipient ?: 'No recipient' }}</p>
                            <p class="text-gray-500">{{ optional($notification->sent_at)->format('j M Y H:i') ?: '-' }}</p>
                        </div>
                    @empty
                        <p class="text-gray-500">No notifications logged.</p>
                    @endforelse
                </div>
            </div>

            <div class="rounded-lg bg-white p-6 shadow-sm">
                <h3 class="mb-4 text-lg font-semibold text-gray-900">Audit Timeline</h3>
                <div class="mb-4 flex flex-wrap gap-2 text-sm">
                    <a class="rounded-md bg-gray-100 px-3 py-1 text-gray-700" href="{{ route('admin.finance.subscriptions.show', $subscription) }}">All</a>
                    <a class="rounded-md bg-gray-100 px-3 py-1 text-gray-700" href="{{ route('admin.finance.subscriptions.show', [$subscription, 'timeline_source' => 'reminder']) }}">Reminders</a>
                    <a class="rounded-md bg-gray-100 px-3 py-1 text-gray-700" href="{{ route('admin.finance.subscriptions.show', [$subscription, 'timeline_source' => 'notification']) }}">Notifications</a>
                    <a class="rounded-md bg-gray-100 px-3 py-1 text-gray-700" href="{{ route('admin.finance.subscriptions.show', [$subscription, 'timeline_source' => 'audit']) }}">Admin Actions</a>
                </div>
                <form method="get" action="{{ route('admin.finance.subscriptions.show', $subscription) }}" class="mb-4 grid gap-3 md:grid-cols-3">
                    <select class="rounded-md border-gray-300 text-sm" name="timeline_source">
                        <option value="">All sources</option>
                        @foreach (['lifecycle', 'reminder', 'notification', 'audit'] as $source)
                            <option value="{{ $source }}" @selected(($timelineFilters['timeline_source'] ?? '') === $source)>{{ ucfirst($source) }}</option>
                        @endforeach
                    </select>
                    <input class="rounded-md border-gray-300 text-sm" name="timeline_type" placeholder="Type" value="{{ $timelineFilters['timeline_type'] ?? '' }}">
                    <button class="rounded-md bg-indigo-600 px-4 py-2 text-sm text-white" type="submit">Filter Timeline</button>
                </form>
                <div class="space-y-3 text-sm">
                    @foreach ($timeline as $entry)
                        <div class="rounded-md bg-gray-50 p-3">
                            <p class="font-medium">{{ $entry['title'] }} <span class="text-xs text-gray-500">[{{ $entry['badge'] }}]</span></p>
                            <p>{{ $entry['detail'] ?: '-' }}</p>
                            <p class="text-gray-500">{{ optional($entry['occurred_at'])->format('j M Y H:i') ?: '-' }}</p>
                        </div>
                    @endforeach
                </div>
                <div class="mt-6">{{ $timeline->links() }}</div>
            </div>
        </div>
    </div>
</x-app-layout>
