<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $dashboardRoleFlags['isSupport'] ? 'Support Workspace' : 'Management Area' }}</h2>
            <div class="flex gap-2">
                <a href="{{ route('admin.customers.index') }}" class="rounded-md bg-slate-700 px-4 py-2 text-sm text-white">Customer Lookup</a>
                <a href="{{ route('admin.finance.index') }}" class="rounded-md bg-slate-700 px-4 py-2 text-sm text-white">Finance</a>
                <a href="{{ route('admin.campaigns.ads.index') }}" class="rounded-md bg-slate-700 px-4 py-2 text-sm text-white">Ad Campaigns</a>
                <a href="{{ route('admin.campaigns.push.index') }}" class="rounded-md bg-slate-700 px-4 py-2 text-sm text-white">Push Campaigns</a>
                <a href="{{ route('admin.vouchers.index') }}" class="rounded-md bg-slate-700 px-4 py-2 text-sm text-white">Vouchers</a>
                <a href="{{ route('admin.integrations.index') }}" class="rounded-md bg-slate-700 px-4 py-2 text-sm text-white">Integrations</a>
                <a href="{{ route('admin.audit-logs.index') }}" class="rounded-md bg-slate-700 px-4 py-2 text-sm text-white">Audit Logs</a>
                <a href="{{ route('admin.wallet.index') }}" class="rounded-md bg-slate-700 px-4 py-2 text-sm text-white">Staff Wallets</a>
                <a href="{{ route('admin.payout-requests.index') }}" class="rounded-md bg-slate-700 px-4 py-2 text-sm text-white">Payout Requests</a>
                @if ($dashboardRoleFlags['canCreateContent'])
                    <a href="{{ route('admin.classifieds.index') }}" class="rounded-md bg-slate-700 px-4 py-2 text-sm text-white">Moderate Classifieds</a>
                    <a href="{{ route('admin.listings.create') }}" class="rounded-md bg-indigo-600 px-4 py-2 text-sm text-white">New Listing</a>
                    <a href="{{ route('admin.events.create') }}" class="rounded-md bg-indigo-600 px-4 py-2 text-sm text-white">New Event</a>
                    <a href="{{ route('admin.articles.create') }}" class="rounded-md bg-indigo-600 px-4 py-2 text-sm text-white">New Article</a>
                @endif
                @if ($dashboardRoleFlags['canReviewApplications'])
                    <a href="{{ route('admin.writer-applications.index') }}" class="rounded-md bg-slate-700 px-4 py-2 text-sm text-white">Review Applications</a>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto space-y-6 sm:px-6 lg:px-8">
            @if ($dashboardRoleFlags['isSupport'])
                <div class="rounded-lg bg-white p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-gray-900">Support Priorities</h3>
                    <p class="mt-2 text-sm text-gray-600">Use this workspace to investigate customer journeys, finance history, and notification trails without exposing write-only recovery actions.</p>
                    <div class="mt-4 flex flex-wrap gap-3">
                        <a href="{{ route('admin.customers.index') }}" class="rounded-md bg-indigo-50 px-4 py-2 text-sm text-indigo-700">Open Customer Lookup</a>
                        <a href="{{ route('admin.finance.orders.index') }}" class="rounded-md bg-indigo-50 px-4 py-2 text-sm text-indigo-700">Review Orders</a>
                        <a href="{{ route('admin.finance.payments.index') }}" class="rounded-md bg-indigo-50 px-4 py-2 text-sm text-indigo-700">Review Payments</a>
                        <a href="{{ route('admin.finance.notifications.index') }}" class="rounded-md bg-indigo-50 px-4 py-2 text-sm text-indigo-700">Review Notifications</a>
                        <a href="{{ route('admin.finance.notifications.index', ['channel' => 'push']) }}" class="rounded-md bg-indigo-50 px-4 py-2 text-sm text-indigo-700">Review Push Logs</a>
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-5">
                    <div class="rounded-lg bg-white p-6 shadow-sm"><p class="text-sm text-gray-500">Customers</p><p class="mt-2 text-3xl font-bold">{{ $counts['users'] }}</p></div>
                    <div class="rounded-lg bg-white p-6 shadow-sm"><p class="text-sm text-gray-500">Orders</p><p class="mt-2 text-3xl font-bold">{{ $supportCounts['orders'] }}</p></div>
                    <div class="rounded-lg bg-white p-6 shadow-sm"><p class="text-sm text-gray-500">Payments</p><p class="mt-2 text-3xl font-bold">{{ $supportCounts['payments'] }}</p></div>
                    <div class="rounded-lg bg-white p-6 shadow-sm"><p class="text-sm text-gray-500">Subscriptions</p><p class="mt-2 text-3xl font-bold">{{ $supportCounts['subscriptions'] }}</p></div>
                    <div class="rounded-lg bg-white p-6 shadow-sm"><p class="text-sm text-gray-500">Notifications</p><p class="mt-2 text-3xl font-bold">{{ $supportCounts['notifications'] }}</p></div>
                </div>

                <div class="rounded-lg bg-white p-6 shadow-sm" data-metrics-root data-metrics-url="{{ route('admin.metrics') }}">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <h3 class="text-lg font-semibold text-gray-900">Live Metrics</h3>
                        <p class="text-sm text-gray-500" data-metrics-status>Updating…</p>
                    </div>
                    <div class="mt-4 grid gap-4 md:grid-cols-4">
                        <a href="{{ route('admin.fault-reports.index', ['approval' => 'pending']) }}" class="rounded-lg bg-slate-50 p-4">
                            <p class="text-sm text-gray-500">Faults Pending Approval</p>
                            <p class="mt-2 text-2xl font-semibold" data-metrics="faults.pending">—</p>
                        </a>
                        <a href="{{ route('admin.fault-reports.index', ['sort' => 'newest']) }}" class="rounded-lg bg-slate-50 p-4">
                            <p class="text-sm text-gray-500">Faults Reported (1h)</p>
                            <p class="mt-2 text-2xl font-semibold" data-metrics="faults.reported_last_hour">—</p>
                        </a>
                        <a href="{{ route('admin.campaigns.ads.index', ['status' => 'ready']) }}" class="rounded-lg bg-slate-50 p-4">
                            <p class="text-sm text-gray-500">Ads Ready</p>
                            <p class="mt-2 text-2xl font-semibold" data-metrics="advertising.ads_ready">—</p>
                        </a>
                        <a href="{{ route('admin.campaigns.push.index', ['sent' => 'no']) }}" class="rounded-lg bg-slate-50 p-4">
                            <p class="text-sm text-gray-500">Push Pending</p>
                            <p class="mt-2 text-2xl font-semibold" data-metrics="advertising.push_pending">—</p>
                        </a>
                    </div>
                    <div class="mt-4 grid gap-4 md:grid-cols-4">
                        <div class="rounded-lg bg-slate-50 p-4">
                            <p class="text-sm text-gray-500">Avg Resolution Hours (last 50)</p>
                            <p class="mt-2 text-2xl font-semibold" data-metrics="faults.avg_resolution_hours_last_50">—</p>
                        </div>
                        <div class="rounded-lg bg-slate-50 p-4">
                            <p class="text-sm text-gray-500">Resolved (7d)</p>
                            <p class="mt-2 text-2xl font-semibold" data-metrics="faults.resolved_last_7d">—</p>
                        </div>
                        <div class="rounded-lg bg-slate-50 p-4">
                            <p class="text-sm text-gray-500">Integrations Active</p>
                            <p class="mt-2 text-2xl font-semibold" data-metrics="integrations.active">—</p>
                        </div>
                        <div class="rounded-lg bg-slate-50 p-4">
                            <p class="text-sm text-gray-500">Vouchers</p>
                            <p class="mt-2 text-2xl font-semibold" data-metrics="core.vouchers">—</p>
                        </div>
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-3">
                    <a href="{{ route('admin.finance.payments.index', ['status' => 'failed']) }}" class="rounded-lg bg-white p-6 shadow-sm">
                        <p class="text-sm text-gray-500">Failed Payments</p>
                        <p class="mt-2 text-3xl font-bold text-amber-700">{{ $supportCounts['failedPayments'] }}</p>
                    </a>
                    <a href="{{ route('admin.finance.notifications.index', ['status' => 'attention']) }}" class="rounded-lg bg-white p-6 shadow-sm">
                        <p class="text-sm text-gray-500">Pending Notifications</p>
                        <p class="mt-2 text-3xl font-bold text-amber-700">{{ $supportCounts['pendingNotifications'] }}</p>
                    </a>
                    <a href="{{ route('admin.finance.subscriptions.index', ['status' => 'active', 'ending_within_days' => 7]) }}" class="rounded-lg bg-white p-6 shadow-sm">
                        <p class="text-sm text-gray-500">Expiring In 7 Days</p>
                        <p class="mt-2 text-3xl font-bold text-amber-700">{{ $supportCounts['expiringSubscriptions'] }}</p>
                    </a>
                    <a href="{{ route('admin.finance.notifications.index', ['channel' => 'push']) }}" class="rounded-lg bg-white p-6 shadow-sm">
                        <p class="text-sm text-gray-500">Push Deliveries</p>
                        <p class="mt-2 text-3xl font-bold text-amber-700">{{ $supportCounts['pushDeliveries'] }}</p>
                    </a>
                    <a href="{{ route('admin.campaigns.push.index', ['sent' => 'no']) }}" class="rounded-lg bg-white p-6 shadow-sm">
                        <p class="text-sm text-gray-500">Pending Push Sends</p>
                        <p class="mt-2 text-3xl font-bold text-amber-700">{{ $supportCounts['pendingPushCampaigns'] }}</p>
                    </a>
                    <a href="{{ route('admin.campaigns.ads.index', ['status' => 'ready']) }}" class="rounded-lg bg-white p-6 shadow-sm">
                        <p class="text-sm text-gray-500">Ads Awaiting Approval</p>
                        <p class="mt-2 text-3xl font-bold text-amber-700">{{ $supportCounts['adCampaignsPendingApproval'] }}</p>
                    </a>
                    <a href="{{ route('admin.campaigns.ads.index', ['status' => 'active']) }}" class="rounded-lg bg-white p-6 shadow-sm">
                        <p class="text-sm text-gray-500">Active Ad Campaigns</p>
                        <p class="mt-2 text-3xl font-bold">{{ $supportCounts['adCampaignsActive'] }}</p>
                    </a>
                    <a href="{{ route('admin.payout-requests.index', ['status' => 'requested']) }}" class="rounded-lg bg-white p-6 shadow-sm">
                        <p class="text-sm text-gray-500">Pending Payout Requests</p>
                        <p class="mt-2 text-3xl font-bold {{ $supportCounts['pendingPayoutRequests'] > 0 ? 'text-amber-700' : '' }}">{{ $supportCounts['pendingPayoutRequests'] }}</p>
                    </a>
                </div>

                <div class="grid gap-6 lg:grid-cols-3">
                    <div class="rounded-lg bg-white p-6 shadow-sm">
                        <h3 class="font-semibold"><a href="{{ route('admin.finance.payments.index', ['status' => 'failed']) }}" class="text-indigo-600">Failed Payments Queue</a></h3>
                        <div class="mt-4 space-y-3">
                            @forelse ($supportQueues['failedPayments'] as $item)
                                <div>
                                    <a href="{{ route('admin.finance.payments.show', $item) }}" class="font-medium text-indigo-600">Payment {{ $item->id }}</a>
                                    <p class="text-sm text-gray-500">{{ $item->order?->order_number ?: 'No order' }} · {{ $item->order?->user?->name ?: 'No customer' }}</p>
                                </div>
                            @empty
                                <p class="text-sm text-gray-500">No failed payments in the current queue.</p>
                            @endforelse
                        </div>
                    </div>
                    <div class="rounded-lg bg-white p-6 shadow-sm">
                        <h3 class="font-semibold"><a href="{{ route('admin.finance.notifications.index', ['status' => 'attention']) }}" class="text-indigo-600">Notification Queue</a></h3>
                        <div class="mt-4 space-y-3">
                            @forelse ($supportQueues['pendingNotifications'] as $item)
                                <div>
                                    <a href="{{ route('admin.finance.notifications.show', $item) }}" class="font-medium text-indigo-600">{{ ucfirst(str_replace('_', ' ', $item->notification_type)) }}</a>
                                    <p class="text-sm text-gray-500">{{ $item->recipient ?: 'No recipient' }} · {{ ucfirst($item->status) }}</p>
                                </div>
                            @empty
                                <p class="text-sm text-gray-500">No pending notifications in the current queue.</p>
                            @endforelse
                        </div>
                    </div>
                    <div class="rounded-lg bg-white p-6 shadow-sm">
                        <h3 class="font-semibold"><a href="{{ route('admin.finance.subscriptions.index', ['status' => 'active', 'ending_within_days' => 7]) }}" class="text-indigo-600">Expiring Subscriptions</a></h3>
                        <div class="mt-4 space-y-3">
                            @forelse ($supportQueues['expiringSubscriptions'] as $item)
                                <div>
                                    <a href="{{ route('admin.finance.subscriptions.show', $item) }}" class="font-medium text-indigo-600">{{ $item->package?->name ?: 'Package' }}</a>
                                    <p class="text-sm text-gray-500">{{ $item->user?->name ?: 'No customer' }} · Ends {{ optional($item->ends_at)->format('j M Y') ?: '-' }}</p>
                                </div>
                            @empty
                                <p class="text-sm text-gray-500">No subscriptions nearing expiry.</p>
                            @endforelse
                        </div>
                    </div>
                </div>

                <div class="grid gap-6 lg:grid-cols-2">
                    <div class="rounded-lg bg-white p-6 shadow-sm">
                        <h3 class="font-semibold">Recent Listings</h3>
                        <div class="mt-4 space-y-3">@foreach ($latestListings as $item)<div><a href="{{ route('admin.listings.edit', $item) }}" class="font-medium text-indigo-600">{{ $item->title }}</a><p class="text-sm text-gray-500">{{ ucfirst($item->status) }}</p></div>@endforeach</div>
                    </div>
                    <div class="rounded-lg bg-white p-6 shadow-sm">
                        <h3 class="font-semibold">Recent Events</h3>
                        <div class="mt-4 space-y-3">@foreach ($latestEvents as $item)<div><a href="{{ route('admin.events.edit', $item) }}" class="font-medium text-indigo-600">{{ $item->title }}</a><p class="text-sm text-gray-500">{{ ucfirst($item->status) }}</p></div>@endforeach</div>
                    </div>
                </div>
            @else
                <div class="grid gap-4 md:grid-cols-5">
                    <div class="rounded-lg bg-white p-6 shadow-sm"><p class="text-sm text-gray-500">Users</p><p class="mt-2 text-3xl font-bold">{{ $counts['users'] }}</p></div>
                    <div class="rounded-lg bg-white p-6 shadow-sm"><p class="text-sm text-gray-500">Listings</p><p class="mt-2 text-3xl font-bold">{{ $counts['listings'] }}</p></div>
                    <div class="rounded-lg bg-white p-6 shadow-sm"><p class="text-sm text-gray-500">Events</p><p class="mt-2 text-3xl font-bold">{{ $counts['events'] }}</p></div>
                    <div class="rounded-lg bg-white p-6 shadow-sm"><p class="text-sm text-gray-500">Articles</p><p class="mt-2 text-3xl font-bold">{{ $counts['articles'] }}</p></div>
                    <div class="rounded-lg bg-white p-6 shadow-sm"><p class="text-sm text-gray-500">Applications</p><p class="mt-2 text-3xl font-bold">{{ $counts['writerApplications'] }}</p></div>
                </div>

                <div class="rounded-lg bg-white p-6 shadow-sm" data-metrics-root data-metrics-url="{{ route('admin.metrics') }}">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <h3 class="text-lg font-semibold text-gray-900">Live Metrics</h3>
                        <p class="text-sm text-gray-500" data-metrics-status>Updating…</p>
                    </div>
                    <div class="mt-4 grid gap-4 md:grid-cols-4">
                        <a href="{{ route('admin.fault-reports.index', ['approval' => 'pending']) }}" class="rounded-lg bg-slate-50 p-4">
                            <p class="text-sm text-gray-500">Faults Pending Approval</p>
                            <p class="mt-2 text-2xl font-semibold" data-metrics="faults.pending">—</p>
                        </a>
                        <a href="{{ route('admin.fault-reports.index', ['sort' => 'newest']) }}" class="rounded-lg bg-slate-50 p-4">
                            <p class="text-sm text-gray-500">Faults Reported (1h)</p>
                            <p class="mt-2 text-2xl font-semibold" data-metrics="faults.reported_last_hour">—</p>
                        </a>
                        <a href="{{ route('admin.campaigns.ads.index', ['status' => 'ready']) }}" class="rounded-lg bg-slate-50 p-4">
                            <p class="text-sm text-gray-500">Ads Ready</p>
                            <p class="mt-2 text-2xl font-semibold" data-metrics="advertising.ads_ready">—</p>
                        </a>
                        <a href="{{ route('admin.campaigns.push.index', ['sent' => 'no']) }}" class="rounded-lg bg-slate-50 p-4">
                            <p class="text-sm text-gray-500">Push Pending</p>
                            <p class="mt-2 text-2xl font-semibold" data-metrics="advertising.push_pending">—</p>
                        </a>
                    </div>
                    <div class="mt-4 grid gap-4 md:grid-cols-4">
                        <div class="rounded-lg bg-slate-50 p-4">
                            <p class="text-sm text-gray-500">Avg Resolution Hours (last 50)</p>
                            <p class="mt-2 text-2xl font-semibold" data-metrics="faults.avg_resolution_hours_last_50">—</p>
                        </div>
                        <div class="rounded-lg bg-slate-50 p-4">
                            <p class="text-sm text-gray-500">Resolved (7d)</p>
                            <p class="mt-2 text-2xl font-semibold" data-metrics="faults.resolved_last_7d">—</p>
                        </div>
                        <div class="rounded-lg bg-slate-50 p-4">
                            <p class="text-sm text-gray-500">Integrations Active</p>
                            <p class="mt-2 text-2xl font-semibold" data-metrics="integrations.active">—</p>
                        </div>
                        <div class="rounded-lg bg-slate-50 p-4">
                            <p class="text-sm text-gray-500">Vouchers</p>
                            <p class="mt-2 text-2xl font-semibold" data-metrics="core.vouchers">—</p>
                        </div>
                    </div>
                </div>

                <div class="grid gap-6 lg:grid-cols-4">
                    <div class="rounded-lg bg-white p-6 shadow-sm">
                        <h3 class="font-semibold">Recent Listings</h3>
                        <div class="mt-4 space-y-3">@foreach ($latestListings as $item)<div><a href="{{ route('admin.listings.edit', $item) }}" class="font-medium text-indigo-600">{{ $item->title }}</a><p class="text-sm text-gray-500">{{ ucfirst($item->status) }}</p></div>@endforeach</div>
                    </div>
                    <div class="rounded-lg bg-white p-6 shadow-sm">
                        <h3 class="font-semibold">Recent Events</h3>
                        <div class="mt-4 space-y-3">@foreach ($latestEvents as $item)<div><a href="{{ route('admin.events.edit', $item) }}" class="font-medium text-indigo-600">{{ $item->title }}</a><p class="text-sm text-gray-500">{{ ucfirst($item->status) }}</p></div>@endforeach</div>
                    </div>
                    <div class="rounded-lg bg-white p-6 shadow-sm">
                        <h3 class="font-semibold">Recent Articles</h3>
                        <div class="mt-4 space-y-3">@foreach ($latestArticles as $item)<div><a href="{{ route('admin.articles.edit', $item) }}" class="font-medium text-indigo-600">{{ $item->title }}</a><p class="text-sm text-gray-500">{{ ucfirst($item->status) }}</p></div>@endforeach</div>
                    </div>
                    <div class="rounded-lg bg-white p-6 shadow-sm">
                        <h3 class="font-semibold">Recent Applications</h3>
                        <div class="mt-4 space-y-3">@foreach ($latestWriterApplications as $item)<div><a href="{{ route('admin.writer-applications.show', $item) }}" class="font-medium text-indigo-600">{{ $item->fullName() }}</a><p class="text-sm text-gray-500">{{ str_replace('_', ' ', ucfirst($item->status)) }}</p></div>@endforeach</div>
                    </div>
                </div>
            @endif
        </div>
    </div>

    <script>
        (() => {
            const roots = Array.from(document.querySelectorAll('[data-metrics-root]'));
            if (roots.length === 0) return;

            const setValue = (root, key, value) => {
                const el = root.querySelector(`[data-metrics="${key}"]`);
                if (!el) return;
                el.textContent = value === null || typeof value === 'undefined' ? '—' : String(value);
            };

            const flatten = (obj, prefix = '') => {
                const out = {};
                for (const [k, v] of Object.entries(obj || {})) {
                    const nextKey = prefix ? `${prefix}.${k}` : k;
                    if (v && typeof v === 'object' && !Array.isArray(v)) {
                        Object.assign(out, flatten(v, nextKey));
                    } else {
                        out[nextKey] = v;
                    }
                }
                return out;
            };

            const tick = async (root) => {
                const url = root.getAttribute('data-metrics-url');
                const status = root.querySelector('[data-metrics-status]');
                try {
                    const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
                    if (!res.ok) throw new Error(`HTTP ${res.status}`);
                    const payload = await res.json();
                    const flat = flatten(payload);
                    for (const [key, value] of Object.entries(flat)) {
                        setValue(root, key, value);
                    }
                    if (status) status.textContent = 'Live';
                } catch (e) {
                    if (status) status.textContent = 'Unavailable';
                }
            };

            roots.forEach((root) => {
                tick(root);
                window.setInterval(() => tick(root), 15000);
            });
        })();
    </script>
</x-app-layout>
