<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Push Campaign: {{ $campaign->title }}</h2>
                <p class="mt-1 text-sm text-gray-500">{{ $campaign->listing?->title }} • {{ $campaign->owner?->email }}</p>
            </div>
            <a href="{{ route('admin.campaigns.push.index') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700">Back to list</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto space-y-6 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-3 text-sm text-green-700">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="rounded-md bg-red-50 p-3 text-sm text-red-700">
                    <ul class="list-disc pl-5 space-y-1">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                </div>
            @endif

            <div class="grid gap-6 lg:grid-cols-3">
                {{-- Campaign content ─────────────────────────────────────── --}}
                <div class="space-y-6 lg:col-span-2">
                    <div class="rounded-lg bg-white p-6 shadow-sm">
                        <h3 class="text-lg font-semibold text-gray-900">Campaign Content</h3>
                        <div class="mt-4 space-y-3 text-sm">
                            @if ($campaign->headline)
                                <div><p class="text-gray-500">Headline</p><p class="font-medium">{{ $campaign->headline }}</p></div>
                            @endif
                            <div><p class="text-gray-500">Message</p><p class="whitespace-pre-line text-gray-700 rounded-md bg-gray-50 p-3">{{ $campaign->message }}</p></div>
                            <div class="grid gap-4 md:grid-cols-2">
                                <div><p class="text-gray-500">Linked listing</p><p class="font-medium">{{ $campaign->listing?->title ?: '-' }}</p></div>
                                <div><p class="text-gray-500">Linked event</p><p class="font-medium">{{ $campaign->event?->title ?: '-' }}</p></div>
                                <div><p class="text-gray-500">Audience scope</p><p class="font-medium">{{ ucfirst(str_replace('_', ' ', $campaign->audience_scope)) }}</p></div>
                                <div><p class="text-gray-500">Target area</p><p class="font-medium">{{ $campaign->audienceSummary() }}</p></div>
                                <div><p class="text-gray-500">Scheduled for</p><p class="font-medium">{{ optional($campaign->schedule_at)->format('j M Y H:i') ?: 'Immediate on dispatch' }}</p></div>
                                <div><p class="text-gray-500">Sent at</p><p class="font-medium">{{ optional($campaign->sent_at)->format('j M Y H:i') ?: 'Not yet sent' }}</p></div>
                                <div><p class="text-gray-500">Package</p><p class="font-medium">{{ $campaign->activeSubscription?->package?->name ?: '-' }}</p></div>
                                <div><p class="text-gray-500">Package expires</p><p class="font-medium">{{ optional($campaign->package_expires_at)->format('j M Y') ?: '-' }}</p></div>
                            </div>
                        </div>
                    </div>

                    {{-- Analytics ───────────────────────────────────────── --}}
                    @if ($campaign->sent_at)
                        <div class="rounded-lg bg-white p-6 shadow-sm">
                            <h3 class="text-lg font-semibold text-gray-900">Performance</h3>
                            <div class="mt-4 grid gap-4 sm:grid-cols-3 text-center">
                                <div>
                                    <p class="text-2xl font-bold text-indigo-700">{{ number_format($deliveryLogs->count()) }}</p>
                                    <p class="text-sm text-gray-500">Delivery events</p>
                                </div>
                                <div>
                                    <p class="text-2xl font-bold text-green-700">{{ number_format($campaign->open_count) }}</p>
                                    <p class="text-sm text-gray-500">Opens (pixel)</p>
                                </div>
                                <div>
                                    <p class="text-2xl font-bold">{{ $campaign->openRate() }}%</p>
                                    <p class="text-sm text-gray-500">Open rate</p>
                                </div>
                            </div>
                            <p class="mt-3 text-xs text-gray-400">Open rate is measured via tracking pixel. Embed <code class="bg-gray-100 px-1 rounded">{{ route('ad-tracking.push-open', $campaign) }}</code> in the push landing page to track opens.</p>
                        </div>
                    @endif

                    {{-- Delivery history ──────────────────────────────────── --}}
                    <div class="rounded-lg bg-white p-6 shadow-sm">
                        <h3 class="text-lg font-semibold text-gray-900">Delivery History</h3>
                        @if ($deliveryLogs->isEmpty())
                            <p class="mt-3 text-sm text-gray-500">No delivery events recorded yet.</p>
                        @else
                            <div class="mt-4 overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200 text-sm">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-4 py-3 text-left">Type</th>
                                            <th class="px-4 py-3 text-left">Recipient / Audience</th>
                                            <th class="px-4 py-3 text-left">Status</th>
                                            <th class="px-4 py-3 text-left">Sent At</th>
                                            <th class="px-4 py-3 text-left">Log</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        @foreach ($deliveryLogs as $log)
                                            <tr>
                                                <td class="px-4 py-3">{{ $log->notification_type }}</td>
                                                <td class="px-4 py-3">{{ $log->recipient ?: '-' }}</td>
                                                <td class="px-4 py-3">
                                                    <span class="{{ $log->status === 'sent' ? 'text-green-700' : ($log->status === 'failed' ? 'text-red-600' : 'text-gray-500') }}">
                                                        {{ ucfirst($log->status) }}
                                                    </span>
                                                </td>
                                                <td class="px-4 py-3">{{ optional($log->sent_at)->format('j M Y H:i') ?: '-' }}</td>
                                                <td class="px-4 py-3">
                                                    <a class="text-indigo-600" href="{{ route('admin.finance.notifications.show', $log) }}">Detail</a>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Admin actions sidebar ───────────────────────────────── --}}
                <div class="space-y-6">
                    <div class="rounded-lg bg-white p-6 shadow-sm">
                        <h3 class="text-lg font-semibold text-gray-900">Status</h3>
                        <div class="mt-3 space-y-2 text-sm">
                            <span class="inline-block rounded-full px-3 py-1 text-sm font-semibold
                                @if($campaign->sent_at) bg-gray-100 text-gray-600
                                @elseif($campaign->status === 'active') bg-green-100 text-green-800
                                @elseif($campaign->status === 'scheduled') bg-indigo-100 text-indigo-800
                                @else bg-slate-100 text-slate-600 @endif">
                                {{ $campaign->sent_at ? 'Sent' : ucfirst($campaign->status) }}
                            </span>
                        </div>
                    </div>

                    <div class="rounded-lg bg-white p-6 shadow-sm space-y-3">
                        <h3 class="text-lg font-semibold text-gray-900">Admin Actions</h3>

                        @if (! $campaign->sent_at && in_array($campaign->status, ['active', 'scheduled']))
                            <form method="post" action="{{ route('admin.campaigns.push.dispatch', $campaign) }}">
                                @csrf
                                <p class="mb-3 text-sm text-gray-500">Manually dispatch this campaign now, bypassing any scheduled time.</p>
                                <button class="w-full rounded-md bg-indigo-600 px-4 py-2 text-sm text-white">Dispatch Now</button>
                            </form>
                        @elseif ($campaign->sent_at)
                            <p class="text-sm text-gray-500">Campaign has already been dispatched. No further actions available.</p>
                        @else
                            <p class="text-sm text-gray-500">Campaign is in <strong>{{ $campaign->status }}</strong> state. The owner must activate or schedule it before it can be dispatched.</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
