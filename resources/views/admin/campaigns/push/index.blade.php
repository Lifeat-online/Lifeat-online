<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Push Campaigns</h2>
            <div class="flex flex-wrap gap-2">
                @if (auth()->user()?->hasRole('admin', 'editor', 'staff'))
                    <a href="{{ route('admin.campaigns.push.create') }}" class="rounded-md bg-indigo-600 px-4 py-2 text-sm text-white">Add push campaign</a>
                @endif
                <a href="{{ route('admin.campaigns.report') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700">Campaign Report</a>
                <a href="{{ route('admin.campaigns.ads.index') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700">View Ad Campaigns</a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto space-y-6 sm:px-6 lg:px-8">
            <form method="get" action="{{ route('admin.campaigns.push.index') }}" class="grid gap-4 rounded-lg bg-white p-4 shadow-sm md:grid-cols-5">
                <input class="rounded-md border-gray-300 text-sm" name="q" placeholder="Search by title or listing…" value="{{ $filters['q'] }}">
                <select class="rounded-md border-gray-300 text-sm" name="status">
                    <option value="">All statuses</option>
                    @foreach ($statusOptions as $option)
                        <option value="{{ $option }}" @selected($filters['status'] === $option)>{{ ucfirst($option) }}</option>
                    @endforeach
                </select>
                <select class="rounded-md border-gray-300 text-sm" name="sent">
                    <option value="">Sent & unsent</option>
                    <option value="no" @selected($filters['sent'] === 'no')>Unsent only</option>
                    <option value="yes" @selected($filters['sent'] === 'yes')>Sent only</option>
                </select>
                <select class="rounded-md border-gray-300 text-sm" name="sort">
                    @foreach ($sortOptions as $value => $label)
                        <option value="{{ $value }}" @selected($filters['sort'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <button class="rounded-md bg-indigo-600 px-4 py-2 text-sm text-white" type="submit">Apply</button>
            </form>

            <div class="rounded-lg bg-white p-6 shadow-sm">
                @if ($errors->has('campaign'))
                    <div class="mb-4 rounded-md bg-red-50 p-3 text-sm text-red-700">{{ $errors->first('campaign') }}</div>
                @endif
                @if (session('status'))
                    <div class="mb-4 rounded-md bg-green-50 p-3 text-sm text-green-700">{{ session('status') }}</div>
                @endif
                <form method="post" action="{{ route('admin.campaigns.push.bulk') }}">
                    @csrf
                    <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                        <div class="flex flex-wrap items-center gap-2">
                            <select class="rounded-md border-gray-300 text-sm" name="action" required>
                                <option value="" selected disabled>Bulk action…</option>
                                <option value="dispatch">Dispatch</option>
                            </select>
                            <button class="rounded-md bg-slate-700 px-4 py-2 text-sm text-white" type="submit" onclick="return confirm('Dispatch the selected campaigns now?');">Run</button>
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
                                <th class="px-4 py-3 text-left">Campaign</th>
                                <th class="px-4 py-3 text-left">Listing</th>
                                <th class="px-4 py-3 text-left">Owner</th>
                                <th class="px-4 py-3 text-left">Status</th>
                                <th class="px-4 py-3 text-left">Scheduled</th>
                                <th class="px-4 py-3 text-left">Sent At</th>
                                <th class="px-4 py-3 text-left">Performance</th>
                                <th class="px-4 py-3 text-left">Audience</th>
                                <th class="px-4 py-3 text-left">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($campaigns as $campaign)
                                <tr>
                                    <td class="px-4 py-3"><input class="row_cb rounded border-gray-300" type="checkbox" name="ids[]" value="{{ $campaign->id }}"></td>
                                    <td class="px-4 py-3">
                                        <a class="font-medium text-indigo-600" href="{{ route('admin.campaigns.push.show', $campaign) }}">{{ $campaign->title }}</a>
                                        @if ($campaign->headline)
                                            <div class="text-gray-500 truncate max-w-xs">{{ $campaign->headline }}</div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">{{ $campaign->listing?->title ?: '-' }}</td>
                                    <td class="px-4 py-3">{{ $campaign->owner?->email ?: '-' }}</td>
                                    <td class="px-4 py-3">
                                        <span class="@if($campaign->status === 'active') text-green-700 @elseif($campaign->status === 'scheduled') text-indigo-700 @elseif($campaign->sent_at) text-gray-400 @else text-gray-500 @endif">
                                            {{ $campaign->sent_at ? 'Sent' : ucfirst($campaign->status) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-gray-600">{{ optional($campaign->schedule_at)->format('j M Y H:i') ?: '-' }}</td>
                                    <td class="px-4 py-3 text-gray-600">{{ optional($campaign->sent_at)->format('j M Y H:i') ?: '-' }}</td>
                                    <td class="px-4 py-3 text-gray-600">
                                        <div>{{ number_format($campaign->push_delivery_count ?? 0) }} deliveries</div>
                                        <div>{{ number_format($campaign->open_count) }} opens</div>
                                        <div class="text-xs text-gray-400">{{ $campaign->openRate() }}% open rate</div>
                                    </td>
                                    <td class="px-4 py-3 text-gray-600">{{ ucfirst(str_replace('_', ' ', $campaign->audience_scope)) }}</td>
                                    <td class="px-4 py-3">
                                        <a class="text-indigo-600" href="{{ route('admin.campaigns.push.show', $campaign) }}">View</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10" class="px-4 py-6 text-center text-gray-500">No push campaigns found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                </form>
                <div class="mt-6">{{ $campaigns->links() }}</div>
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
