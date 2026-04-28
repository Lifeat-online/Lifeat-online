<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Ad Campaigns</h2>
            <a href="{{ route('admin.campaigns.push.index') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700">View Push Campaigns</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto space-y-6 sm:px-6 lg:px-8">
            <form method="get" action="{{ route('admin.campaigns.ads.index') }}" class="grid gap-4 rounded-lg bg-white p-4 shadow-sm md:grid-cols-3">
                <input class="rounded-md border-gray-300 text-sm" name="q" placeholder="Search by title or listing…" value="{{ $filters['q'] }}">
                <select class="rounded-md border-gray-300 text-sm" name="status">
                    <option value="">All statuses</option>
                    @foreach ($statusOptions as $option)
                        <option value="{{ $option }}" @selected($filters['status'] === $option)>{{ ucfirst($option) }}</option>
                    @endforeach
                </select>
                <button class="rounded-md bg-indigo-600 px-4 py-2 text-sm text-white" type="submit">Apply</button>
            </form>

            @if ($filters['status'] === 'ready')
                <div class="rounded-md bg-amber-50 border border-amber-200 p-3 text-sm text-amber-800">
                    Showing campaigns awaiting creative approval. Review each campaign below and approve or return to draft.
                </div>
            @endif

            <div class="rounded-lg bg-white p-6 shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left">Campaign</th>
                                <th class="px-4 py-3 text-left">Listing</th>
                                <th class="px-4 py-3 text-left">Owner</th>
                                <th class="px-4 py-3 text-left">Status</th>
                                <th class="px-4 py-3 text-left">Schedule</th>
                                <th class="px-4 py-3 text-left">Package</th>
                                <th class="px-4 py-3 text-left">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($campaigns as $campaign)
                                <tr>
                                    <td class="px-4 py-3">
                                        <a class="font-medium text-indigo-600" href="{{ route('admin.campaigns.ads.show', $campaign) }}">{{ $campaign->title }}</a>
                                        @if ($campaign->headline)
                                            <div class="text-gray-500">{{ $campaign->headline }}</div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">{{ $campaign->listing?->title ?: '-' }}</td>
                                    <td class="px-4 py-3">{{ $campaign->owner?->email ?: '-' }}</td>
                                    <td class="px-4 py-3">
                                        <span class="@if($campaign->status === 'active') text-green-700 @elseif($campaign->status === 'ready') text-amber-700 font-semibold @elseif($campaign->status === 'paused') text-gray-500 @else text-gray-400 @endif">
                                            {{ ucfirst($campaign->status) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-gray-600">
                                        {{ optional($campaign->start_at)->format('j M Y') ?: '-' }}
                                        @if ($campaign->end_at) → {{ $campaign->end_at->format('j M Y') }} @endif
                                    </td>
                                    <td class="px-4 py-3">{{ $campaign->activeSubscription?->package?->name ?: '-' }}</td>
                                    <td class="px-4 py-3">
                                        <a class="text-indigo-600" href="{{ route('admin.campaigns.ads.show', $campaign) }}">Review</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-6 text-center text-gray-500">No ad campaigns found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-6">{{ $campaigns->links() }}</div>
            </div>
        </div>
    </div>
</x-app-layout>
