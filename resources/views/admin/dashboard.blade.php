<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $dashboardRoleFlags['isSupport'] ? 'Support Workspace' : 'Management Area' }}</h2>
                @if ($dashboardRoleFlags['isAdmin'] ?? false)
                    <p class="mt-1 text-sm text-gray-500">Operations and developer controls are separated so platform management stays easier to scan.</p>
                @endif
            </div>
            <div class="flex flex-wrap gap-2">
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

    @php
        $isAdmin = $dashboardRoleFlags['isAdmin'] ?? false;
        $isSupport = $dashboardRoleFlags['isSupport'] ?? false;
        $devToolsAvailable = in_array((string) config('app.env'), ['local', 'testing'], true)
            || filter_var((string) env('DEV_TOOLS_ENABLED', 'false'), FILTER_VALIDATE_BOOL);
        $devTestRunnerAvailable = in_array((string) config('app.env'), ['local', 'testing'], true)
            || (
                $devToolsAvailable
                && filter_var((string) env('DEV_TEST_RUNNER_ENABLED', 'false'), FILTER_VALIDATE_BOOL)
            );
        $devMetricSections = [
            [
                'title' => 'Civic Faults',
                'description' => 'Live fault reporting and moderation signals across the platform.',
                'cards' => [
                    ['label' => 'Faults Pending Approval', 'key' => 'faults.pending', 'href' => route('admin.fault-reports.index', ['approval' => 'pending'])],
                    ['label' => 'Faults Reported (1h)', 'key' => 'faults.reported_last_hour', 'href' => route('admin.fault-reports.index', ['sort' => 'newest'])],
                    ['label' => 'Avg Resolution Hours (last 50)', 'key' => 'faults.avg_resolution_hours_last_50', 'href' => null],
                    ['label' => 'Resolved (7d)', 'key' => 'faults.resolved_last_7d', 'href' => null],
                ],
            ],
            [
                'title' => 'Campaigns And Messaging',
                'description' => 'Advertising readiness and outbound delivery queues.',
                'cards' => [
                    ['label' => 'Ads Ready', 'key' => 'advertising.ads_ready', 'href' => route('admin.campaigns.ads.index', ['status' => 'ready'])],
                    ['label' => 'Push Pending', 'key' => 'advertising.push_pending', 'href' => route('admin.campaigns.push.index', ['sent' => 'no'])],
                    ['label' => 'Integrations Active', 'key' => 'integrations.active', 'href' => route('admin.integrations.index')],
                    ['label' => 'Vouchers', 'key' => 'core.vouchers', 'href' => route('admin.vouchers.index')],
                ],
            ],
        ];
        $devTestSuites = [
            ['label' => 'Full Suite', 'suite' => 'all', 'description' => 'Runs the entire Laravel test suite.'],
            ['label' => 'Unit', 'suite' => 'Unit', 'description' => 'Runs fast isolated unit coverage.'],
            ['label' => 'Feature', 'suite' => 'Feature', 'description' => 'Runs HTTP and integration workflows.'],
        ];
        $devQuickLinks = [
            ['label' => 'Settings', 'href' => route('admin.settings.index')],
            ['label' => 'Audit Logs', 'href' => route('admin.audit-logs.index')],
            ['label' => 'Fault Reports', 'href' => route('admin.fault-reports.index')],
            ['label' => 'Finance Console', 'href' => route('admin.finance.index')],
            ['label' => 'Integrations', 'href' => route('admin.integrations.index')],
        ];
    @endphp

    <div class="py-12">
        <div class="max-w-7xl mx-auto space-y-6 sm:px-6 lg:px-8">
            @if ($isAdmin)
                <div class="rounded-lg bg-white p-3 shadow-sm" data-dashboard-tabs>
                    <div class="flex flex-wrap gap-3">
                        <button type="button" data-tab-trigger="overview" class="rounded-md px-4 py-2 text-sm font-medium transition-colors">Overview</button>
                        @if ($devToolsAvailable)
                            <button type="button" data-tab-trigger="dev" class="rounded-md px-4 py-2 text-sm font-medium transition-colors">Dev</button>
                        @endif
                    </div>
                </div>
            @endif

            <section data-tab-panel="overview" class="space-y-6">
                @if ($isSupport)
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
            </section>

            @if ($isAdmin && $devToolsAvailable)
                <section data-tab-panel="dev" class="space-y-6" hidden>
                    <div class="rounded-lg bg-slate-950 p-6 text-white shadow-sm">
                        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                            <div>
                                <h3 class="text-lg font-semibold">Developer Control Center</h3>
                                <p class="mt-2 max-w-3xl text-sm text-slate-300">Monitor live platform activity, inspect access controls, run the test suite, and review the runtime details needed to manage this server effectively.</p>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                @foreach ($devQuickLinks as $link)
                                    <a href="{{ $link['href'] }}" class="rounded-md border border-slate-700 px-4 py-2 text-sm text-slate-100 transition hover:border-slate-500 hover:bg-slate-900">{{ $link['label'] }}</a>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                        @foreach ($devSummaryCards as $card)
                            <div class="rounded-lg bg-white p-6 shadow-sm">
                                <p class="text-sm text-gray-500">{{ $card['label'] }}</p>
                                <p class="mt-2 text-3xl font-bold text-gray-900">{{ $card['value'] }}</p>
                                <p class="mt-2 text-sm text-gray-500">{{ $card['note'] }}</p>
                            </div>
                        @endforeach
                    </div>

                    <div class="rounded-lg bg-white p-6 shadow-sm" data-metrics-root data-metrics-url="{{ route('admin.metrics') }}">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900">Live Metrics</h3>
                                <p class="mt-1 text-sm text-gray-500">Platform-wide metrics are grouped so Dev can inspect each operational area faster.</p>
                            </div>
                            <p class="text-sm text-gray-500" data-metrics-status>Updating...</p>
                        </div>

                        @foreach ($devMetricSections as $section)
                            <div class="mt-6 rounded-xl border border-slate-200 p-5">
                                <div>
                                    <h4 class="font-semibold text-gray-900">{{ $section['title'] }}</h4>
                                    <p class="mt-1 text-sm text-gray-500">{{ $section['description'] }}</p>
                                </div>
                                <div class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                                    @foreach ($section['cards'] as $card)
                                        @if ($card['href'])
                                            <a href="{{ $card['href'] }}" class="rounded-lg bg-slate-50 p-4 transition hover:bg-slate-100">
                                                <p class="text-sm text-gray-500">{{ $card['label'] }}</p>
                                                <p class="mt-2 text-2xl font-semibold text-gray-900" data-metrics="{{ $card['key'] }}">-</p>
                                            </a>
                                        @else
                                            <div class="rounded-lg bg-slate-50 p-4">
                                                <p class="text-sm text-gray-500">{{ $card['label'] }}</p>
                                                <p class="mt-2 text-2xl font-semibold text-gray-900" data-metrics="{{ $card['key'] }}">-</p>
                                            </div>
                                        @endif
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>

                    @if ($devTestRunnerAvailable)
                        <div class="grid gap-6">
                            <div class="rounded-lg bg-white p-6 shadow-sm" data-test-runner data-test-endpoint="{{ route('dev.tests.run') }}">
                                <input type="hidden" name="_token" value="{{ csrf_token() }}">
                                <div class="flex flex-wrap items-center justify-between gap-3">
                                    <div>
                                        <h3 class="text-lg font-semibold text-gray-900">Testing Area</h3>
                                        <p class="mt-1 text-sm text-gray-500">Run the Laravel test suite from the Dev tab when validating changes on this server.</p>
                                    </div>
                                    <p class="text-sm text-gray-500" data-test-status>Idle</p>
                                </div>
                                <div class="mt-4 grid gap-3 md:grid-cols-3">
                                    @foreach ($devTestSuites as $suite)
                                        <button type="button" data-test-suite="{{ $suite['suite'] }}" data-test-label="{{ $suite['label'] }}" class="rounded-lg border border-slate-200 px-4 py-3 text-left transition hover:border-indigo-300 hover:bg-indigo-50">
                                            <span class="block text-sm font-semibold text-gray-900">{{ $suite['label'] }}</span>
                                            <span class="mt-1 block text-sm text-gray-500">{{ $suite['description'] }}</span>
                                        </button>
                                    @endforeach
                                </div>
                                <div class="mt-4 rounded-lg bg-slate-950 p-4 text-sm text-slate-100">
                                    <p class="font-medium text-slate-300">Latest Output</p>
                                    <pre class="mt-3 overflow-x-auto whitespace-pre-wrap text-xs text-slate-100" data-test-output>Choose a suite to run `php artisan test` on this host.</pre>
                                </div>
                            </div>
                        </div>
                    @endif

                    <div class="grid gap-6 xl:grid-cols-[1.4fr,0.6fr]">
                        <div class="rounded-lg bg-white p-6 shadow-sm">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-900">Roles And Permissions</h3>
                                    <p class="mt-1 text-sm text-gray-500">Review role coverage, assigned capabilities, and the current primary-role distribution.</p>
                                </div>
                            </div>
                            <div class="mt-6 grid gap-4 lg:grid-cols-2">
                                @foreach ($devRoleCards as $role)
                                    <div class="rounded-xl border border-slate-200 p-5">
                                        <div class="flex flex-wrap items-start justify-between gap-3">
                                            <div>
                                                <h4 class="font-semibold text-gray-900">{{ $role['name'] }}</h4>
                                                <p class="mt-1 text-sm text-gray-500">{{ $role['slug'] }}</p>
                                            </div>
                                            <div class="text-right text-sm text-gray-500">
                                                <p>{{ $role['users_count'] }} users</p>
                                                <p>{{ $role['permissions_count'] }} permissions</p>
                                            </div>
                                        </div>
                                        <div class="mt-4 flex flex-wrap gap-2">
                                            @forelse ($role['permissions'] as $permission)
                                                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-700">{{ $permission }}</span>
                                            @empty
                                                <span class="text-sm text-gray-500">No linked permissions.</span>
                                            @endforelse
                                            @if ($role['extra_permissions_count'] > 0)
                                                <span class="rounded-full bg-indigo-50 px-3 py-1 text-xs font-medium text-indigo-700">+{{ $role['extra_permissions_count'] }} more</span>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <div class="space-y-6">
                            <div class="rounded-lg bg-white p-6 shadow-sm">
                                <h3 class="text-lg font-semibold text-gray-900">Primary Role Breakdown</h3>
                                <div class="mt-4 space-y-3">
                                    @foreach ($devPrimaryRoleBreakdown as $role)
                                        <div class="flex items-center justify-between rounded-lg bg-slate-50 px-4 py-3">
                                            <span class="text-sm text-gray-600">{{ ucwords(str_replace(['_', '-'], ' ', $role['label'])) }}</span>
                                            <span class="text-sm font-semibold text-gray-900">{{ $role['count'] }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                            <div class="rounded-lg bg-white p-6 shadow-sm">
                                <h3 class="text-lg font-semibold text-gray-900">Top Permission Coverage</h3>
                                <div class="mt-4 space-y-3">
                                    @foreach ($devPermissionCards as $permission)
                                        <div class="flex items-center justify-between rounded-lg bg-slate-50 px-4 py-3">
                                            <div>
                                                <p class="text-sm font-medium text-gray-900">{{ $permission['name'] }}</p>
                                                <p class="text-xs text-gray-500">{{ $permission['slug'] }}</p>
                                            </div>
                                            <span class="text-sm font-semibold text-gray-900">{{ $permission['roles_count'] }} roles</span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-lg bg-white p-6 shadow-sm">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900">Server Statistics</h3>
                                <p class="mt-1 text-sm text-gray-500">Runtime, environment, storage, and database details for the current host.</p>
                            </div>
                        </div>
                        <div class="mt-6 grid gap-6 xl:grid-cols-3">
                            @foreach ($devServerStatSections as $section)
                                <div class="rounded-xl border border-slate-200 p-5">
                                    <h4 class="font-semibold text-gray-900">{{ $section['title'] }}</h4>
                                    <div class="mt-4 space-y-3">
                                        @foreach ($section['stats'] as $stat)
                                            <div class="flex items-start justify-between gap-4 rounded-lg bg-slate-50 px-4 py-3">
                                                <span class="text-sm text-gray-500">{{ $stat['label'] }}</span>
                                                <span class="text-right text-sm font-medium text-gray-900">{{ $stat['value'] }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </section>
            @endif
        </div>
    </div>

    <script>
        (() => {
            const tabContainer = document.querySelector('[data-dashboard-tabs]');
            if (tabContainer) {
                const triggers = Array.from(tabContainer.querySelectorAll('[data-tab-trigger]'));
                const panels = Array.from(document.querySelectorAll('[data-tab-panel]'));
                const activeClasses = ['bg-indigo-600', 'text-white', 'shadow-sm'];
                const inactiveClasses = ['bg-slate-100', 'text-slate-600'];

                const setActiveTab = (name) => {
                    triggers.forEach((trigger) => {
                        const isActive = trigger.getAttribute('data-tab-trigger') === name;
                        trigger.classList.remove(...activeClasses, ...inactiveClasses);
                        trigger.classList.add(...(isActive ? activeClasses : inactiveClasses));
                    });

                    panels.forEach((panel) => {
                        panel.hidden = panel.getAttribute('data-tab-panel') !== name;
                    });
                };

                triggers.forEach((trigger) => {
                    trigger.addEventListener('click', () => setActiveTab(trigger.getAttribute('data-tab-trigger')));
                });

                setActiveTab('overview');
            }

            const roots = Array.from(document.querySelectorAll('[data-metrics-root]'));

            const setValue = (root, key, value) => {
                const el = root.querySelector(`[data-metrics="${key}"]`);
                if (!el) return;
                el.textContent = value === null || typeof value === 'undefined' ? '-' : String(value);
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
                    const res = await fetch(url, { headers: { Accept: 'application/json' } });
                    if (!res.ok) throw new Error(`HTTP ${res.status}`);
                    const payload = await res.json();
                    const flat = flatten(payload);
                    for (const [key, value] of Object.entries(flat)) {
                        setValue(root, key, value);
                    }
                    if (status) status.textContent = 'Live';
                } catch (error) {
                    if (status) status.textContent = 'Unavailable';
                }
            };

            roots.forEach((root) => {
                tick(root);
                window.setInterval(() => tick(root), 15000);
            });

            const testRunners = Array.from(document.querySelectorAll('[data-test-runner]'));
            testRunners.forEach((runner) => {
                const endpoint = runner.getAttribute('data-test-endpoint');
                const status = runner.querySelector('[data-test-status]');
                const output = runner.querySelector('[data-test-output]');
                const csrfToken = runner.querySelector('input[name="_token"]')?.value;
                const buttons = Array.from(runner.querySelectorAll('[data-test-suite]'));

                const setBusy = (busy) => {
                    buttons.forEach((button) => {
                        button.disabled = busy;
                        button.classList.toggle('opacity-60', busy);
                        button.classList.toggle('cursor-not-allowed', busy);
                    });
                };

                buttons.forEach((button) => {
                    button.addEventListener('click', async () => {
                        const suite = button.getAttribute('data-test-suite') || 'all';
                        const label = button.getAttribute('data-test-label') || suite;
                        setBusy(true);
                        if (status) status.textContent = `Running ${label}...`;
                        if (output) output.textContent = `Executing ${label} test run...`;

                        try {
                            const response = await fetch(endpoint, {
                                method: 'POST',
                                headers: {
                                    Accept: 'application/json',
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': csrfToken || '',
                                },
                                body: JSON.stringify({ suite }),
                            });
                            const payload = await response.json().catch(() => ({}));
                            const summary = payload.command
                                ? `${payload.command}\nexit: ${payload.exit_code} | duration: ${payload.duration_ms} ms\n\n`
                                : '';

                            if (output) {
                                output.textContent = `${summary}${payload.output || `Request failed with status ${response.status}.`}`;
                            }

                            if (status) {
                                status.textContent = payload.command
                                    ? `${payload.ok ? 'Passed' : 'Failed'} · ${label}`
                                    : `Request failed (${response.status})`;
                            }
                        } catch (error) {
                            if (status) status.textContent = 'Unavailable';
                            if (output) output.textContent = error instanceof Error ? error.message : 'Unable to run the test suite.';
                        } finally {
                            setBusy(false);
                        }
                    });
                });
            });

        })();
    </script>
</x-app-layout>
