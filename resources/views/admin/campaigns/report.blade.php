<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Campaign Report</h2>
                <p class="mt-1 text-sm text-gray-500">{{ \Illuminate\Support\Carbon::parse($filters['from'])->format('j M Y') }} to {{ \Illuminate\Support\Carbon::parse($filters['to'])->format('j M Y') }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('admin.campaigns.ads.index') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700">Ad Campaigns</a>
                <a href="{{ route('admin.campaigns.push.index') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700">Push Campaigns</a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto space-y-6 sm:px-6 lg:px-8">
            <form method="get" action="{{ route('admin.campaigns.report') }}" class="grid gap-4 rounded-lg bg-white p-4 shadow-sm md:grid-cols-3">
                <label class="text-sm text-gray-600">
                    <span class="mb-1 block font-medium text-gray-700">From</span>
                    <input class="w-full rounded-md border-gray-300 text-sm" type="date" name="from" value="{{ $filters['from'] }}">
                </label>
                <label class="text-sm text-gray-600">
                    <span class="mb-1 block font-medium text-gray-700">To</span>
                    <input class="w-full rounded-md border-gray-300 text-sm" type="date" name="to" value="{{ $filters['to'] }}">
                </label>
                <div class="flex items-end">
                    <button class="w-full rounded-md bg-indigo-600 px-4 py-2 text-sm text-white" type="submit">Apply</button>
                </div>
            </form>

            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-6">
                <div class="rounded-lg bg-white p-4 shadow-sm">
                    <p class="text-xs uppercase text-gray-500">Impressions</p>
                    <p class="mt-2 text-2xl font-bold text-indigo-700">{{ number_format($summary['impressions']) }}</p>
                </div>
                <div class="rounded-lg bg-white p-4 shadow-sm">
                    <p class="text-xs uppercase text-gray-500">Clicks</p>
                    <p class="mt-2 text-2xl font-bold text-green-700">{{ number_format($summary['clicks']) }}</p>
                </div>
                <div class="rounded-lg bg-white p-4 shadow-sm">
                    <p class="text-xs uppercase text-gray-500">CTR</p>
                    <p class="mt-2 text-2xl font-bold">{{ $summary['ctr'] }}%</p>
                </div>
                <div class="rounded-lg bg-white p-4 shadow-sm">
                    <p class="text-xs uppercase text-gray-500">Push Deliveries</p>
                    <p class="mt-2 text-2xl font-bold text-indigo-700">{{ number_format($summary['push_deliveries']) }}</p>
                </div>
                <div class="rounded-lg bg-white p-4 shadow-sm">
                    <p class="text-xs uppercase text-gray-500">Push Opens</p>
                    <p class="mt-2 text-2xl font-bold text-green-700">{{ number_format($summary['push_opens']) }}</p>
                </div>
                <div class="rounded-lg bg-white p-4 shadow-sm">
                    <p class="text-xs uppercase text-gray-500">Open Rate</p>
                    <p class="mt-2 text-2xl font-bold">{{ $summary['push_open_rate'] }}%</p>
                </div>
            </div>

            <div class="rounded-lg bg-white p-6 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h3 class="text-lg font-semibold text-gray-900">Exports</h3>
                    <div class="flex flex-wrap gap-2">
                        <a class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700" href="{{ route('admin.campaigns.report.export', ['dataset' => 'ad-summary'] + $filters) }}">Ad summary CSV</a>
                        <a class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700" href="{{ route('admin.campaigns.report.export', ['dataset' => 'push-summary'] + $filters) }}">Push summary CSV</a>
                        <a class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700" href="{{ route('admin.campaigns.report.export', ['dataset' => 'tracking-events'] + $filters) }}">Tracking events CSV</a>
                    </div>
                </div>
            </div>

            <div class="rounded-lg bg-white p-6 shadow-sm">
                <h3 class="text-lg font-semibold text-gray-900">Daily Trend</h3>
                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left">Date</th>
                                <th class="px-4 py-3 text-left">Impressions</th>
                                <th class="px-4 py-3 text-left">Clicks</th>
                                <th class="px-4 py-3 text-left">CTR</th>
                                <th class="px-4 py-3 text-left">Push Deliveries</th>
                                <th class="px-4 py-3 text-left">Push Opens</th>
                                <th class="px-4 py-3 text-left">Open Rate</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($dailyRows as $row)
                                <tr>
                                    <td class="px-4 py-3">{{ $row['date']->format('j M Y') }}</td>
                                    <td class="px-4 py-3">{{ number_format($row['impressions']) }}</td>
                                    <td class="px-4 py-3">{{ number_format($row['clicks']) }}</td>
                                    <td class="px-4 py-3">{{ $row['ctr'] }}%</td>
                                    <td class="px-4 py-3">{{ number_format($row['push_deliveries']) }}</td>
                                    <td class="px-4 py-3">{{ number_format($row['push_opens']) }}</td>
                                    <td class="px-4 py-3">{{ $row['push_open_rate'] }}%</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-2">
                <div class="rounded-lg bg-white p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-gray-900">Top Ad Campaigns</h3>
                    <div class="mt-4 overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left">Campaign</th>
                                    <th class="px-4 py-3 text-left">Listing</th>
                                    <th class="px-4 py-3 text-left">Impressions</th>
                                    <th class="px-4 py-3 text-left">Clicks</th>
                                    <th class="px-4 py-3 text-left">CTR</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @forelse ($topAds as $campaign)
                                    @php
                                        $impressions = (int) $campaign->report_impressions;
                                        $clicks = (int) $campaign->report_clicks;
                                        $ctr = $impressions > 0 ? round(($clicks / $impressions) * 100, 2) : 0;
                                    @endphp
                                    <tr>
                                        <td class="px-4 py-3"><a class="font-medium text-indigo-600" href="{{ route('admin.campaigns.ads.show', $campaign) }}">{{ $campaign->title }}</a></td>
                                        <td class="px-4 py-3">{{ $campaign->listing?->title ?: '-' }}</td>
                                        <td class="px-4 py-3">{{ number_format($impressions) }}</td>
                                        <td class="px-4 py-3">{{ number_format($clicks) }}</td>
                                        <td class="px-4 py-3">{{ $ctr }}%</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="px-4 py-6 text-center text-gray-500">No ad campaign activity found.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="rounded-lg bg-white p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-gray-900">Top Push Campaigns</h3>
                    <div class="mt-4 overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left">Campaign</th>
                                    <th class="px-4 py-3 text-left">Listing</th>
                                    <th class="px-4 py-3 text-left">Deliveries</th>
                                    <th class="px-4 py-3 text-left">Opens</th>
                                    <th class="px-4 py-3 text-left">Open Rate</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @forelse ($topPushCampaigns as $campaign)
                                    @php
                                        $deliveries = (int) $campaign->report_deliveries;
                                        $opens = (int) $campaign->report_opens;
                                        $openRate = $deliveries > 0 ? round(($opens / $deliveries) * 100, 2) : 0;
                                    @endphp
                                    <tr>
                                        <td class="px-4 py-3"><a class="font-medium text-indigo-600" href="{{ route('admin.campaigns.push.show', $campaign) }}">{{ $campaign->title }}</a></td>
                                        <td class="px-4 py-3">{{ $campaign->listing?->title ?: '-' }}</td>
                                        <td class="px-4 py-3">{{ number_format($deliveries) }}</td>
                                        <td class="px-4 py-3">{{ number_format($opens) }}</td>
                                        <td class="px-4 py-3">{{ $openRate }}%</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="px-4 py-6 text-center text-gray-500">No push campaign activity found.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
