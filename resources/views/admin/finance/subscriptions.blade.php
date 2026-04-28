<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Finance Subscriptions</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto space-y-6 sm:px-6 lg:px-8">
            <form method="get" action="{{ route('admin.finance.subscriptions.index') }}" class="grid gap-4 rounded-lg bg-white p-4 shadow-sm md:grid-cols-4">
                <select class="rounded-md border-gray-300 text-sm" name="status">
                    <option value="">All statuses</option>
                    @foreach (['active', 'pending', 'suspended', 'expired'] as $status)
                        <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ ucfirst($status) }}</option>
                    @endforeach
                </select>
                <select class="rounded-md border-gray-300 text-sm" name="ending_within_days">
                    <option value="">Any expiry window</option>
                    @foreach ([7, 14, 30] as $days)
                        <option value="{{ $days }}" @selected((string) ($filters['ending_within_days'] ?? '') === (string) $days)>Ending within {{ $days }} days</option>
                    @endforeach
                </select>
                <div class="flex gap-2">
                    <button class="rounded-md bg-indigo-600 px-4 py-2 text-sm text-white" type="submit">Apply Filters</button>
                    <a class="rounded-md bg-gray-100 px-4 py-2 text-sm text-gray-700" href="{{ route('admin.finance.subscriptions.index') }}">Reset</a>
                </div>
            </form>

            <div class="rounded-lg bg-white p-6 shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left">Package</th>
                                <th class="px-4 py-3 text-left">User</th>
                                <th class="px-4 py-3 text-left">Status</th>
                                <th class="px-4 py-3 text-left">Ends At</th>
                                <th class="px-4 py-3 text-left">Reminders</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($subscriptions as $subscription)
                                <tr>
                                    <td class="px-4 py-3"><a class="text-indigo-600" href="{{ route('admin.finance.subscriptions.show', $subscription) }}">{{ $subscription->package?->name }}</a></td>
                                    <td class="px-4 py-3">
                                        @if ($subscription->user)
                                            <a class="text-indigo-600" href="{{ route('admin.customers.show', $subscription->user) }}">{{ $subscription->user->name }}</a>
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">{{ ucfirst($subscription->status) }}</td>
                                    <td class="px-4 py-3">{{ optional($subscription->ends_at)->format('j M Y H:i') ?: '-' }}</td>
                                    <td class="px-4 py-3">{{ $subscription->reminders->count() }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-6">{{ $subscriptions->links() }}</div>
            </div>
        </div>
    </div>
</x-app-layout>
