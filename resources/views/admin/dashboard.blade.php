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
                @if (Auth::user()->hasRole('admin', 'editor'))
                    <a href="{{ route('admin.push-notifications.index') }}" class="rounded-md bg-indigo-600 px-4 py-2 text-sm text-white">Send Push</a>
                    <a href="{{ route('admin.article-briefs.index') }}" class="rounded-md bg-indigo-600 px-4 py-2 text-sm text-white">Brief Review</a>
                @endif
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
        $canAccessDevDashboard = $dashboardRoleFlags['canAccessDevDashboard'] ?? false;
        $devToolsAvailable = in_array((string) config('app.env'), ['local', 'testing'], true)
            || filter_var((string) env('DEV_TOOLS_ENABLED', 'false'), FILTER_VALIDATE_BOOL)
            || $canAccessDevDashboard;
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
        $devAiWriterCards = [
            ['label' => 'Sources', 'key' => 'active_sources'],
            ['label' => 'New Research', 'key' => 'new_research_items'],
            ['label' => 'Brief Review', 'key' => 'pending_review_briefs'],
            ['label' => 'Approved Briefs', 'key' => 'approved_briefs'],
            ['label' => 'Drafted Briefs', 'key' => 'drafted_briefs'],
            ['label' => 'Missing Images', 'key' => 'drafts_missing_images'],
        ];
    @endphp

    <div class="py-12">
        <div class="max-w-7xl mx-auto space-y-6 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="rounded-lg bg-green-50 p-4 text-sm text-green-800 shadow-sm">{{ session('status') }}</div>
            @endif

            @if ($canAccessDevDashboard)
                <div class="rounded-lg bg-white p-3 shadow-sm" data-dashboard-tabs>
                    <div class="flex flex-wrap gap-3">
                        <button type="button" data-tab-trigger="overview" class="rounded-md px-4 py-2 text-sm font-medium transition-colors">Overview</button>
                        @if ($devToolsAvailable)
                            <button type="button" data-tab-trigger="dev" class="rounded-md px-4 py-2 text-sm font-medium transition-colors">Dev</button>
                            <button type="button" data-tab-trigger="ai" class="rounded-md px-4 py-2 text-sm font-medium transition-colors">AI</button>
                            <button type="button" data-tab-trigger="translations" class="rounded-md px-4 py-2 text-sm font-medium transition-colors">Translations</button>
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

            @if ($canAccessDevDashboard && $devToolsAvailable)
                <section data-tab-panel="ai" class="space-y-6" hidden>
                    <div class="rounded-lg bg-white p-6 shadow-sm">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900">AI Provider Control</h3>
                                <p class="mt-1 text-sm text-gray-500">Testing should stay on OpenRouter with free models where possible. Direct OpenAI and Gemini keys are ready for production once quality and costs are settled.</p>
                                <a href="{{ route('admin.ai-operations.index') }}" class="mt-3 inline-flex rounded-md bg-slate-800 px-4 py-2 text-sm font-semibold text-white">Open AI Operations</a>
                            </div>
                            <div class="rounded-lg bg-slate-50 px-4 py-3 text-sm text-gray-600">
                                <p><strong>Active:</strong> {{ $devAiStatus['provider_label'] ?? 'OpenRouter' }}</p>
                                <p><strong>Status:</strong> {{ ($devAiStatus['configured'] ?? false) ? 'Configured' : 'Missing key or model' }}</p>
                                <p><strong>Model:</strong> {{ $devAiStatus['model'] ?: '-' }}</p>
                                <p><strong>Source:</strong> {{ $devAiStatus['source'] ?? 'Missing' }}</p>
                            </div>
                            <div class="rounded-lg bg-fuchsia-50 px-4 py-3 text-sm text-fuchsia-900">
                                <p><strong>Images:</strong> {{ $devAiImageStatus['provider_label'] ?? 'OpenAI Images' }}</p>
                                <p><strong>Status:</strong> {{ ($devAiImageStatus['configured'] ?? false) ? 'Configured' : 'Missing key or model' }}</p>
                                <p><strong>Model:</strong> {{ $devAiImageStatus['model'] ?: '-' }}</p>
                                <p><strong>Size:</strong> {{ $devAiImageStatus['size'] ?: '-' }}</p>
                            </div>
                            <div class="rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                                <p><strong>Voice:</strong> {{ $devVoiceStatus['provider_label'] ?? 'ElevenLabs' }}</p>
                                <p><strong>Status:</strong> {{ ($devVoiceStatus['configured'] ?? false) ? 'Configured' : 'Missing key or voice' }}</p>
                                <p><strong>English:</strong> {{ $devVoiceStatus['english_model'] ?: '-' }}</p>
                                <p><strong>Afrikaans:</strong> {{ $devVoiceStatus['afrikaans_model'] ?: '-' }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-lg bg-white p-6 shadow-sm" data-ai-writer-process data-endpoint="{{ route('dev.ai.writer.process') }}">
                        <input type="hidden" name="_token" value="{{ csrf_token() }}">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900">AI Writer Process</h3>
                                <div class="mt-3 flex flex-wrap gap-2">
                                    <a href="{{ route('admin.article-briefs.index') }}" class="rounded-md bg-indigo-50 px-3 py-2 text-sm font-medium text-indigo-700">Brief Review</a>
                                    <a href="{{ route('admin.ai-operations.index') }}" class="rounded-md bg-slate-100 px-3 py-2 text-sm font-medium text-slate-700">AI Operations</a>
                                </div>
                            </div>
                            <div class="rounded-lg bg-slate-50 px-4 py-3 text-sm text-gray-600">
                                <p><strong>Text:</strong> {{ $devAiStatus['provider_label'] ?? 'OpenRouter' }} / {{ $devAiStatus['model'] ?: '-' }}</p>
                                <p><strong>Images:</strong> {{ $devAiImageStatus['provider_label'] ?? 'OpenRouter Images' }} / {{ $devAiImageStatus['model'] ?: '-' }}</p>
                                <p><strong>Latest:</strong>
                                    @if ($devAiWriterStatus['latest_generation'] ?? null)
                                        {{ $devAiWriterStatus['latest_generation']['feature_key'] }} / {{ $devAiWriterStatus['latest_generation']['status'] }} / {{ $devAiWriterStatus['latest_generation']['provider'] }}
                                    @else
                                        No AI writer runs yet
                                    @endif
                                </p>
                            </div>
                        </div>

                        <div class="mt-5 grid gap-3 md:grid-cols-3 xl:grid-cols-6">
                            @foreach ($devAiWriterCards as $card)
                                <div class="rounded-lg bg-slate-50 p-4">
                                    <p class="text-xs font-medium uppercase text-gray-500">{{ $card['label'] }}</p>
                                    <p class="mt-2 text-2xl font-semibold text-gray-900" data-ai-writer-count="{{ $card['key'] }}">{{ $devAiWriterStatus[$card['key']] ?? 0 }}</p>
                                </div>
                            @endforeach
                        </div>

                        <div class="mt-5 grid gap-4 lg:grid-cols-[10rem,1fr,auto] lg:items-end">
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700">Limit</label>
                                <input class="w-full rounded-md border-gray-300 text-sm" type="number" min="1" max="10" value="3" data-ai-writer-limit>
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700">Source slug</label>
                                <input class="w-full rounded-md border-gray-300 text-sm" data-ai-writer-source placeholder="optional">
                            </div>
                            <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                                <input type="checkbox" class="rounded border-gray-300 text-indigo-600" data-ai-writer-seed>
                                <span>Seed sources</span>
                            </label>
                        </div>

                        <div class="mt-4 flex flex-wrap gap-2">
                            <button type="button" class="rounded-md bg-slate-700 px-4 py-2 text-sm font-semibold text-white disabled:opacity-60" data-ai-writer-action="collect">Collect</button>
                            <button type="button" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-60" data-ai-writer-action="brief">Brief</button>
                            <button type="button" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-60" data-ai-writer-action="write">Write</button>
                            <button type="button" class="rounded-md bg-fuchsia-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-60" data-ai-writer-action="images">Images</button>
                            <button type="button" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white disabled:opacity-60" data-ai-writer-action="all">Run pipeline</button>
                        </div>

                        <div class="mt-4 rounded-lg bg-gray-950 p-4 text-sm text-gray-100">
                            <p class="font-medium" data-ai-writer-status>Idle</p>
                            <pre class="mt-3 max-h-80 overflow-auto whitespace-pre-wrap text-xs leading-5" data-ai-writer-output>{}</pre>
                        </div>
                    </div>

                    <form method="post" action="{{ route('dev.ai.settings.store') }}" class="rounded-lg bg-white p-6 shadow-sm">
                        @csrf
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                            <div class="max-w-md">
                                <label class="mb-1 block text-sm font-medium text-gray-700">Active AI provider</label>
                                <select class="w-full rounded-md border-gray-300" name="provider">
                                    @foreach (($devAiStatus['providers'] ?? []) as $provider)
                                        <option value="{{ $provider['key'] }}" @selected(($devAiStatus['provider'] ?? 'openrouter') === $provider['key'])>{{ $provider['label'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white">Save AI settings</button>
                        </div>

                        <div class="mt-6 max-w-md">
                            <label class="mb-1 block text-sm font-medium text-gray-700">Active image provider</label>
                            <select class="w-full rounded-md border-gray-300" name="image_provider">
                                @foreach (($devAiImageStatus['providers'] ?? []) as $provider)
                                    <option value="{{ $provider['key'] }}" @selected(($devAiImageStatus['provider'] ?? 'openai') === $provider['key'])>{{ $provider['label'] }}</option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-xs text-gray-500">Testing default: OpenRouter. Preferred direct production choices: OpenAI Images or Google Gemini Images.</p>
                        </div>

                        <div class="mt-6 max-w-md">
                            <label class="mb-1 block text-sm font-medium text-gray-700">Active voice provider</label>
                            <select class="w-full rounded-md border-gray-300" name="voice_provider">
                                @foreach (($devVoiceStatus['providers'] ?? []) as $provider)
                                    <option value="{{ $provider['key'] }}" @selected(($devVoiceStatus['provider'] ?? 'elevenlabs') === $provider['key'])>{{ $provider['label'] }}</option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-xs text-gray-500">ElevenLabs is the default for Jimmy's spoken replies. NVIDIA Speech NIM is available for English/self-hosted testing.</p>
                        </div>

                        <div class="mt-6 grid gap-4 lg:grid-cols-2">
                            @foreach (($devAiStatus['providers'] ?? []) as $provider)
                                <div class="rounded-lg border border-gray-200 p-4">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <h4 class="font-semibold text-gray-900">{{ $provider['label'] }}</h4>
                                            <p class="text-xs text-gray-500">{{ $provider['type'] }} @if ($provider['configured']) / configured @else / not ready @endif</p>
                                        </div>
                                        <span class="rounded-full bg-slate-100 px-2 py-1 text-xs text-slate-700">{{ $provider['source'] }}</span>
                                    </div>
                                    <div class="mt-4 grid gap-3">
                                        <div>
                                            <label class="mb-1 block text-xs font-medium uppercase text-gray-500">Model / deployment</label>
                                            <input class="w-full rounded-md border-gray-300 text-sm" name="models[{{ $provider['key'] }}]" value="{{ $provider['model'] }}">
                                        </div>
                                        <div>
                                            <label class="mb-1 block text-xs font-medium uppercase text-gray-500">Base URL / endpoint</label>
                                            <input class="w-full rounded-md border-gray-300 text-sm" name="base_urls[{{ $provider['key'] }}]" value="{{ $provider['base_url'] }}">
                                        </div>
                                        @if (! $provider['key_optional'])
                                            <div>
                                                <label class="mb-1 block text-xs font-medium uppercase text-gray-500">API key</label>
                                                <input class="w-full rounded-md border-gray-300 text-sm" type="password" autocomplete="off" name="keys[{{ $provider['key'] }}]" placeholder="{{ $provider['masked_key'] ?: 'Paste key to save' }}">
                                            </div>
                                        @else
                                            <p class="text-xs text-gray-500">Local provider key is optional.</p>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div class="mt-6">
                            <h4 class="font-semibold text-gray-900">Image Providers</h4>
                            <p class="mt-1 text-sm text-gray-500">Used by the Image Agent for Jimmy article drafts. Generated images are labelled as AI-generated illustrations.</p>
                            <div class="mt-4 grid gap-4 lg:grid-cols-2">
                                @foreach (($devAiImageStatus['providers'] ?? []) as $provider)
                                    <div class="rounded-lg border border-fuchsia-100 bg-fuchsia-50/50 p-4">
                                        <div class="flex items-start justify-between gap-3">
                                            <div>
                                                <h4 class="font-semibold text-gray-900">{{ $provider['label'] }}</h4>
                                                <p class="text-xs text-gray-500">{{ $provider['type'] }} @if ($provider['configured']) / configured @else / not ready @endif</p>
                                            </div>
                                            <span class="rounded-full bg-white px-2 py-1 text-xs text-fuchsia-700">{{ $provider['source'] }}</span>
                                        </div>
                                        <div class="mt-4 grid gap-3">
                                            <div>
                                                <label class="mb-1 block text-xs font-medium uppercase text-gray-500">Image model</label>
                                                <input class="w-full rounded-md border-gray-300 text-sm" name="image_models[{{ $provider['key'] }}]" value="{{ $provider['model'] }}">
                                            </div>
                                            <div>
                                                <label class="mb-1 block text-xs font-medium uppercase text-gray-500">Fallback models</label>
                                                <input class="w-full rounded-md border-gray-300 text-sm" name="image_fallback_models[{{ $provider['key'] }}]" value="{{ implode(', ', $provider['fallback_models'] ?? []) }}" placeholder="model-one, model-two">
                                            </div>
                                            <div>
                                                <label class="mb-1 block text-xs font-medium uppercase text-gray-500">Base URL / endpoint</label>
                                                <input class="w-full rounded-md border-gray-300 text-sm" name="image_base_urls[{{ $provider['key'] }}]" value="{{ $provider['base_url'] }}">
                                            </div>
                                            <div>
                                                <label class="mb-1 block text-xs font-medium uppercase text-gray-500">Size</label>
                                                <input class="w-full rounded-md border-gray-300 text-sm" name="image_sizes[{{ $provider['key'] }}]" value="{{ $provider['size'] }}">
                                            </div>
                                            <div>
                                                <label class="mb-1 block text-xs font-medium uppercase text-gray-500">API key</label>
                                                <input class="w-full rounded-md border-gray-300 text-sm" type="password" autocomplete="off" name="image_keys[{{ $provider['key'] }}]" placeholder="{{ $provider['masked_key'] ?: 'Paste key to save' }}">
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <div class="mt-6">
                            <h4 class="font-semibold text-gray-900">Voice Providers</h4>
                            <p class="mt-1 text-sm text-gray-500">Used by Jimmy's speaker button. Select the active provider above, then configure ElevenLabs or NVIDIA Speech NIM here.</p>
                            <div class="mt-4 grid gap-4 lg:grid-cols-2">
                                @foreach (($devVoiceStatus['providers'] ?? []) as $provider)
                                    <div class="rounded-lg border border-emerald-100 bg-emerald-50/60 p-4">
                                        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                            <div>
                                                <h4 class="font-semibold text-gray-900">{{ $provider['label'] }}</h4>
                                                <p class="text-xs text-gray-500">{{ $provider['type'] }} @if ($provider['configured']) / configured @else / not ready @endif</p>
                                            </div>
                                            <span class="rounded-full bg-white px-2 py-1 text-xs text-emerald-700">{{ $provider['source'] }}</span>
                                        </div>
                                        <div class="mt-4 grid gap-3 lg:grid-cols-2">
                                            <div>
                                                <label class="mb-1 block text-xs font-medium uppercase text-gray-500">Voice ID / voice name</label>
                                                <input class="w-full rounded-md border-gray-300 text-sm" name="voice_voice_ids[{{ $provider['key'] }}]" value="{{ $provider['voice_id'] }}">
                                            </div>
                                            <div>
                                                <label class="mb-1 block text-xs font-medium uppercase text-gray-500">Output format</label>
                                                <input class="w-full rounded-md border-gray-300 text-sm" name="voice_output_formats[{{ $provider['key'] }}]" value="{{ $provider['output_format'] }}">
                                            </div>
                                            <div>
                                                <label class="mb-1 block text-xs font-medium uppercase text-gray-500">English model / language</label>
                                                <input class="w-full rounded-md border-gray-300 text-sm" name="voice_english_models[{{ $provider['key'] }}]" value="{{ $provider['english_model'] }}">
                                            </div>
                                            <div>
                                                <label class="mb-1 block text-xs font-medium uppercase text-gray-500">Afrikaans model / language</label>
                                                <input class="w-full rounded-md border-gray-300 text-sm" name="voice_afrikaans_models[{{ $provider['key'] }}]" value="{{ $provider['afrikaans_model'] }}">
                                            </div>
                                            <div>
                                                <label class="mb-1 block text-xs font-medium uppercase text-gray-500">Base URL</label>
                                                <input class="w-full rounded-md border-gray-300 text-sm" name="voice_base_urls[{{ $provider['key'] }}]" value="{{ $provider['base_url'] }}">
                                            </div>
                                            <div>
                                                <label class="mb-1 block text-xs font-medium uppercase text-gray-500">API key</label>
                                                <input class="w-full rounded-md border-gray-300 text-sm" type="password" autocomplete="off" name="voice_keys[{{ $provider['key'] }}]" placeholder="{{ $provider['masked_key'] ?: (($provider['key_optional'] ?? false) ? 'Optional for local testing' : 'Paste key to save') }}">
                                            </div>
                                        </div>
                                        @if (($provider['key'] ?? '') === 'nvidia')
                                            <p class="mt-3 rounded-md bg-white px-3 py-2 text-xs text-emerald-800">NVIDIA Speech NIM testing expects a hosted or local endpoint such as <code>http://localhost:9000/v1</code>; Jimmy calls <code>/audio/synthesize</code>.</p>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <div class="mt-4 max-w-md">
                            <label class="mb-1 block text-sm font-medium text-gray-700">Azure OpenAI API version</label>
                            <input class="w-full rounded-md border-gray-300 text-sm" name="azure_openai_api_version" value="{{ \App\Models\Setting::getValue('ai.azure_openai_api_version', config('services.ai.providers.azure_openai.api_version')) }}">
                        </div>
                        <p class="mt-3 text-sm text-gray-500">Environment variables still take priority over saved keys. Empty key fields keep the existing saved key.</p>
                    </form>

                    <div class="rounded-lg bg-white p-6 shadow-sm" data-voice-test data-endpoint="{{ route('ask-life.speak') }}">
                        <input type="hidden" name="_token" value="{{ csrf_token() }}">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900">Voice Test</h3>
                                <p class="mt-1 text-sm text-gray-500">Preview Jimmy's configured voice in English or Afrikaans before exposing spoken replies more widely.</p>
                            </div>
                            <div class="rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                                <p><strong>Provider:</strong> {{ $devVoiceStatus['provider_label'] ?? 'ElevenLabs' }}</p>
                                <p><strong>Voice ID:</strong> {{ $devVoiceStatus['voice_id'] ?: '-' }}</p>
                            </div>
                        </div>
                        <div class="mt-5 grid gap-4 lg:grid-cols-[1fr,12rem,auto] lg:items-end">
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700">Test sentence</label>
                                <textarea class="w-full rounded-md border-gray-300 text-sm" rows="2" maxlength="1000" data-voice-test-text>Hi, I am Jimmy. I can help you find local businesses, events, articles, vouchers, classifieds, and fault reports.</textarea>
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700">Language</label>
                                <select class="w-full rounded-md border-gray-300 text-sm" data-voice-test-locale>
                                    <option value="en">English</option>
                                    <option value="af">Afrikaans</option>
                                </select>
                            </div>
                            <button type="button" class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-60" data-voice-test-submit>Generate voice</button>
                        </div>
                        <div class="mt-4 grid gap-3 lg:grid-cols-[1fr,auto] lg:items-center">
                            <p class="text-sm text-gray-500" data-voice-test-status>Idle</p>
                            <audio class="w-full min-w-64" controls hidden data-voice-test-audio></audio>
                        </div>
                    </div>

                    <form method="post" action="{{ route('dev.ai.test') }}" class="rounded-lg bg-white p-6 shadow-sm">
                        @csrf
                        <h3 class="text-lg font-semibold text-gray-900">Provider Test</h3>
                        <p class="mt-1 text-sm text-gray-500">Runs one structured JSON request through the active provider and logs it in the AI audit table.</p>
                        <div class="mt-4 grid gap-4 lg:grid-cols-[1fr,auto] lg:items-end">
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700">Test prompt</label>
                                <input class="w-full rounded-md border-gray-300" name="prompt" value="Confirm Life@ AI provider routing is ready.">
                            </div>
                            <button type="submit" class="rounded-md bg-slate-700 px-4 py-2 text-sm font-semibold text-white">Run test</button>
                        </div>
                    </form>
                </section>

                <section data-tab-panel="translations" class="space-y-6" hidden>
                    <div class="rounded-lg bg-white p-6 shadow-sm">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900">Translation Control</h3>
                                <p class="mt-1 text-sm text-gray-500">Keep articles available in every configured language. New published articles translate from their original language into the other supported language automatically.</p>
                            </div>
                            <div class="rounded-lg bg-slate-50 px-4 py-3 text-sm text-gray-600">
                                <p><strong>Provider:</strong> <span data-translation-provider-label>{{ $devTranslationStatus['provider'] === 'google' ? 'Google Translate' : ($devTranslationStatus['provider'] === 'azure' ? 'Azure Translator' : 'OpenRouter') }}</span></p>
                                <p><strong>Status:</strong> <span data-translation-configured>{{ $devTranslationStatus['configured'] ? 'Configured' : 'Missing key' }}</span></p>
                                <p><strong>Source:</strong> <span data-translation-source>{{ $devTranslationStatus['source'] }}</span></p>
                            </div>
                        </div>
                        <div class="mt-5 grid gap-4 md:grid-cols-3">
                            <div class="rounded-lg bg-slate-50 p-4">
                                <p class="text-sm text-gray-500">Supported Languages</p>
                                <p class="mt-2 text-2xl font-semibold text-gray-900">{{ implode(', ', $devTranslationStatus['supported_locales']) }}</p>
                            </div>
                            <div class="rounded-lg bg-slate-50 p-4">
                                <p class="text-sm text-gray-500">Article Translations</p>
                                <p class="mt-2 text-2xl font-semibold text-gray-900">{{ $devTranslationStatus['article_translations'] }}</p>
                            </div>
                            <div class="rounded-lg bg-slate-50 p-4">
                                <p class="text-sm text-gray-500">Published Articles Missing Translation</p>
                                <p class="mt-2 text-2xl font-semibold text-gray-900">{{ $devTranslationStatus['published_articles_missing']->count() }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-lg bg-white p-6 shadow-sm" data-translation-settings-form data-endpoint="{{ route('dev.translations.key.store') }}">
                        <input type="hidden" name="_token" value="{{ csrf_token() }}">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900">Translation Provider</h3>
                                <p class="mt-1 text-sm text-gray-500">Use Google Translate for bulk platform translation. Azure and OpenRouter stay available as fallback options.</p>
                            </div>
                            <div class="rounded-lg bg-slate-50 px-4 py-3 text-sm text-gray-600">
                                <p><strong>Active:</strong> <span data-provider-status>{{ $devTranslationStatus['configured'] ? 'Configured' : 'Missing key' }}</span></p>
                                <p><strong>Google key:</strong> <span data-google-masked>{{ $devTranslationStatus['google_masked_key'] ?: 'Not saved' }}</span></p>
                                <p><strong>Azure key:</strong> <span data-azure-masked>{{ $devTranslationStatus['azure_masked_key'] ?: 'Not saved' }}</span></p>
                                <p><strong>OpenRouter key:</strong> <span data-openrouter-masked>{{ $devTranslationStatus['openrouter_masked_key'] ?: 'Not saved' }}</span></p>
                            </div>
                        </div>
                        <div class="mt-5 grid gap-4 lg:grid-cols-[0.6fr,1fr,0.6fr] lg:items-end">
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700">Provider</label>
                                <select class="w-full rounded-md border-gray-300" data-translation-provider>
                                    <option value="google" @selected($devTranslationStatus['provider'] === 'google')>Google Translate</option>
                                    <option value="azure" @selected($devTranslationStatus['provider'] === 'azure')>Azure Translator</option>
                                    <option value="openrouter" @selected($devTranslationStatus['provider'] === 'openrouter')>OpenRouter</option>
                                </select>
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700">Google Translate API key</label>
                                <input class="w-full rounded-md border-gray-300" type="password" autocomplete="off" data-google-key placeholder="Google Cloud Translation API key">
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700">Azure region</label>
                                <input class="w-full rounded-md border-gray-300" data-azure-region value="{{ $devTranslationStatus['azure_region'] }}" placeholder="global or your region">
                            </div>
                        </div>
                        <div class="mt-4 grid gap-4 lg:grid-cols-[1fr,1fr,auto] lg:items-end">
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700">Azure API key</label>
                                <input class="w-full rounded-md border-gray-300" type="password" autocomplete="off" data-azure-key placeholder="Azure Translator key">
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700">OpenRouter API key</label>
                                <input class="w-full rounded-md border-gray-300" type="password" autocomplete="off" data-openrouter-key placeholder="sk-or-...">
                            </div>
                            <button type="button" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-60" data-translation-settings-submit>Save settings</button>
                        </div>
                        <div class="mt-4 grid gap-4 lg:grid-cols-[1fr,auto] lg:items-end">
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700">OpenRouter model</label>
                                <input class="w-full rounded-md border-gray-300" data-openrouter-model value="{{ $devTranslationStatus['openrouter_model'] }}">
                            </div>
                        </div>
                        <p class="mt-3 text-sm text-gray-500" data-translation-settings-message>Environment variables still take priority over saved keys.</p>
                    </div>

                    <div class="rounded-lg bg-white p-6 shadow-sm" data-map-settings-form data-endpoint="{{ route('dev.maps.key.store') }}">
                        <input type="hidden" name="_token" value="{{ csrf_token() }}">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900">Google Maps Address Search</h3>
                                <p class="mt-1 text-sm text-gray-500">Use Google Places for South African address autocomplete and GPS reverse lookup. The key stays server-side.</p>
                            </div>
                            <div class="rounded-lg bg-slate-50 px-4 py-3 text-sm text-gray-600">
                                <p><strong>Status:</strong> <span data-map-status>{{ $devMapStatus['configured'] ? 'Configured' : 'Missing key' }}</span></p>
                                <p><strong>Source:</strong> <span data-map-source>{{ $devMapStatus['source'] }}</span></p>
                                <p><strong>Google Maps key:</strong> <span data-map-masked>{{ $devMapStatus['masked_key'] ?: 'Not saved' }}</span></p>
                            </div>
                        </div>
                        <div class="mt-5 grid gap-4 lg:grid-cols-[1fr,auto] lg:items-end">
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700">Google Maps API key</label>
                                <input class="w-full rounded-md border-gray-300" type="password" autocomplete="off" data-map-key placeholder="Google Maps / Places API key">
                            </div>
                            <button type="button" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-60" data-map-settings-submit>Save Maps key</button>
                        </div>
                        <p class="mt-3 text-sm text-gray-500" data-map-settings-message>Enable Places API and Geocoding API on the same Google Cloud key. GOOGLE_MAPS_API_KEY still takes priority over saved keys.</p>
                    </div>

                    <div class="rounded-lg bg-white p-6 shadow-sm" data-translation-batch data-endpoint="{{ route('dev.translations.batch') }}">
                        <input type="hidden" name="_token" value="{{ csrf_token() }}">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900">Platform Translation Run</h3>
                                <p class="mt-1 text-sm text-gray-500">Translate all public content at once, or run one section at a time when you want tighter control.</p>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <button type="button" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-60" data-batch-section="all">Translate everything</button>
                            </div>
                        </div>
                        <div class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-4" data-translation-section-grid>
                            @foreach ($devTranslationStatus['sections'] as $section)
                                <div class="rounded-lg border border-slate-200 p-4" data-section-card="{{ $section['key'] }}">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <h4 class="font-semibold text-gray-900">{{ $section['label'] }}</h4>
                                            <p class="mt-1 text-sm text-gray-500">
                                                <span data-section-missing>{{ $section['missing'] }}</span> missing /
                                                <span data-section-total>{{ $section['total'] }}</span> total
                                            </p>
                                        </div>
                                        <button type="button" class="rounded-md bg-slate-800 px-3 py-2 text-sm font-semibold text-white disabled:opacity-60" data-batch-section="{{ $section['key'] }}">Translate</button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        <div class="mt-5 grid gap-4 md:grid-cols-[1fr,auto] md:items-end">
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700">Items per run</label>
                                <input class="w-full rounded-md border-gray-300" type="number" min="1" max="100" value="20" data-batch-limit>
                            </div>
                            <label class="inline-flex items-center gap-2 rounded-md border border-slate-200 px-4 py-2 text-sm text-gray-700">
                                <input type="checkbox" data-batch-force>
                                <span>Re-translate current items</span>
                            </label>
                        </div>
                        <p class="mt-3 text-sm text-gray-500" data-batch-status>Idle</p>
                    </div>

                    <div class="grid gap-6 xl:grid-cols-[1fr,1fr]">
                        <div class="rounded-lg bg-white p-6 shadow-sm" data-translation-preview data-endpoint="{{ route('dev.translations.preview') }}">
                            <input type="hidden" name="_token" value="{{ csrf_token() }}">
                            <h3 class="text-lg font-semibold text-gray-900">Quick Translation Test</h3>
                            <div class="mt-4 space-y-4">
                                <div>
                                    <label class="mb-1 block text-sm font-medium text-gray-700">Target language</label>
                                    <select class="w-full rounded-md border-gray-300" data-translation-locale>
                                        @foreach ($devTranslationStatus['target_locales'] as $locale)
                                            <option value="{{ $locale }}">{{ config("localization.supported.{$locale}.name") }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="mb-1 block text-sm font-medium text-gray-700">Source text</label>
                                    <textarea class="w-full rounded-md border-gray-300" rows="5" required data-translation-source></textarea>
                                </div>
                                <button type="button" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-60" data-translation-submit>Translate</button>
                                <p class="text-sm text-gray-500" data-translation-status>Idle</p>
                                <textarea class="w-full rounded-md border-gray-300 bg-slate-50" rows="5" readonly data-translation-output></textarea>
                            </div>
                        </div>

                        <div class="rounded-lg bg-white p-6 shadow-sm">
                            <h3 class="text-lg font-semibold text-gray-900">Published Article Queue</h3>
                            <div class="mt-4 space-y-3">
                                @forelse ($devTranslationStatus['published_articles_missing'] as $article)
                                    <div class="rounded-lg border border-slate-200 p-4" data-article-translation data-endpoint="{{ route('dev.translations.articles.translate', $article) }}">
                                        <input type="hidden" name="_token" value="{{ csrf_token() }}">
                                        <div class="flex flex-wrap items-start justify-between gap-3">
                                            <div>
                                                <a href="{{ route('admin.articles.edit', $article) }}" class="font-semibold text-indigo-600">{{ $article->title }}</a>
                                                <p class="mt-1 text-sm text-gray-500">Original: {{ strtoupper($article->sourceLocale()) }} · Missing: {{ implode(', ', $article->missing_translation_locales) }} · Published {{ optional($article->published_at)->format('j M Y') }}</p>
                                            </div>
                                            <div class="flex flex-wrap items-center gap-2">
                                                <select class="rounded-md border-gray-300 text-sm" data-article-translation-locale>
                                                    @foreach ($article->missing_translation_locales as $locale)
                                                        <option value="{{ $locale }}">{{ config("localization.supported.{$locale}.name") }}</option>
                                                    @endforeach
                                                </select>
                                                <button type="button" class="rounded-md bg-slate-800 px-3 py-2 text-sm font-semibold text-white disabled:opacity-60" data-article-translation-submit>Translate now</button>
                                            </div>
                                        </div>
                                        <p class="mt-2 text-sm text-gray-500" data-article-translation-status>Waiting</p>
                                    </div>
                                @empty
                                    <p class="rounded-lg bg-slate-50 p-4 text-sm text-gray-500">No published article translation gaps found.</p>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </section>

                <section data-tab-panel="dev" class="space-y-6" hidden>
                    <div class="rounded-lg bg-slate-950 p-6 text-white shadow-sm">
                        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                            <div>
                                <h3 class="text-lg font-semibold">Developer Control Center</h3>
                                <p class="mt-2 max-w-3xl text-sm text-slate-300">Private Dev dashboard for jameskoen78@gmail.com. Monitor live platform activity, inspect access controls, run guarded tools, and review the runtime details needed to manage this server effectively.</p>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                @foreach ($devQuickLinks as $link)
                                    <a href="{{ $link['href'] }}" class="rounded-md border border-slate-700 px-4 py-2 text-sm text-slate-100 transition hover:border-slate-500 hover:bg-slate-900">{{ $link['label'] }}</a>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <div class="grid gap-4 lg:grid-cols-[1.1fr,0.9fr]">
                        <div class="rounded-lg bg-white p-6 shadow-sm" data-vapid-setup data-vapid-url="{{ route('dev.webpush.vapid.enable') }}">
                            <input type="hidden" name="_token" value="{{ csrf_token() }}">
                            <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-900">Push Notification Setup</h3>
                                    <p class="mt-1 text-sm text-gray-500">Generate and save browser push VAPID keys when they are missing, so the Enable alerts button can appear for users.</p>
                                </div>
                                <button type="button" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-60" data-vapid-enable>
                                    Enable VAPID keys
                                </button>
                            </div>
                            <div class="mt-5 grid gap-3 md:grid-cols-2">
                                <div class="rounded-lg bg-slate-50 p-4">
                                    <p class="text-sm text-gray-500">VAPID Status</p>
                                    <p class="mt-2 text-lg font-semibold {{ $devWebPushStatus['configured'] ? 'text-emerald-700' : 'text-amber-700' }}" data-vapid-status>
                                        {{ $devWebPushStatus['configured'] ? 'Configured' : 'Missing keys' }}
                                    </p>
                                </div>
                                <div class="rounded-lg bg-slate-50 p-4">
                                    <p class="text-sm text-gray-500">Storage</p>
                                    <p class="mt-2 text-lg font-semibold {{ $devWebPushStatus['storage_ready'] ? 'text-emerald-700' : 'text-red-700' }}" data-vapid-env>
                                        {{ $devWebPushStatus['storage_ready'] ? 'Settings DB' : 'Unavailable' }}
                                    </p>
                                </div>
                                <div class="rounded-lg bg-slate-50 p-4">
                                    <p class="text-sm text-gray-500">Public Key</p>
                                    <p class="mt-2 text-lg font-semibold {{ $devWebPushStatus['public_key_configured'] ? 'text-emerald-700' : 'text-amber-700' }}" data-vapid-public>
                                        {{ $devWebPushStatus['public_key_configured'] ? 'Set' : 'Missing' }}
                                    </p>
                                </div>
                                <div class="rounded-lg bg-slate-50 p-4">
                                    <p class="text-sm text-gray-500">Private Key</p>
                                    <p class="mt-2 text-lg font-semibold {{ $devWebPushStatus['private_key_configured'] ? 'text-emerald-700' : 'text-amber-700' }}" data-vapid-private>
                                        {{ $devWebPushStatus['private_key_configured'] ? 'Set' : 'Missing' }}
                                    </p>
                                </div>
                            </div>
                            <p class="mt-4 text-sm text-gray-500" data-vapid-message>Subject: {{ $devWebPushStatus['subject'] ?: 'Not set' }}</p>
                        </div>

                        <div class="rounded-lg bg-white p-6 shadow-sm">
                            <h3 class="text-lg font-semibold text-gray-900">Dev Dashboard</h3>
                            <div class="mt-4 grid gap-3">
                                <a href="{{ route('admin.push-notifications.index') }}" class="rounded-lg border border-slate-200 px-4 py-3 transition hover:border-indigo-300 hover:bg-indigo-50">
                                    <span class="block text-sm font-semibold text-gray-900">Send Push Notifications</span>
                                    <span class="mt-1 block text-sm text-gray-500">Open the backend sender once VAPID keys are configured.</span>
                                </a>
                                <a href="{{ route('admin.finance.notifications.index', ['channel' => 'push']) }}" class="rounded-lg border border-slate-200 px-4 py-3 transition hover:border-indigo-300 hover:bg-indigo-50">
                                    <span class="block text-sm font-semibold text-gray-900">Review Push Logs</span>
                                    <span class="mt-1 block text-sm text-gray-500">Inspect delivery history and attention states.</span>
                                </a>
                                <a href="{{ route('admin.metrics') }}" class="rounded-lg border border-slate-200 px-4 py-3 transition hover:border-indigo-300 hover:bg-indigo-50">
                                    <span class="block text-sm font-semibold text-gray-900">Metrics JSON</span>
                                    <span class="mt-1 block text-sm text-gray-500">View the live metrics endpoint directly.</span>
                                </a>
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

                    document.dispatchEvent(new CustomEvent('dashboard-tab:changed', { detail: { name } }));
                };

                triggers.forEach((trigger) => {
                    trigger.addEventListener('click', () => setActiveTab(trigger.getAttribute('data-tab-trigger')));
                });

                const requestedTab = new URLSearchParams(window.location.search).get('tab');
                const initialTab = triggers.some((trigger) => trigger.getAttribute('data-tab-trigger') === requestedTab)
                    ? requestedTab
                    : 'overview';

                setActiveTab(initialTab);
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

            const metricTimers = new Map();
            const metricLoading = new WeakSet();

            const metricRootIsActive = (root) => {
                const panel = root.closest('[data-tab-panel]');
                return !panel || !panel.hidden;
            };

            const refreshMetrics = async (root) => {
                if (document.hidden || !metricRootIsActive(root) || metricLoading.has(root)) return;
                metricLoading.add(root);
                try {
                    await tick(root);
                } finally {
                    metricLoading.delete(root);
                }
            };

            const startMetricPolling = () => {
                if (document.hidden) return;
                roots.forEach((root) => {
                    if (!metricRootIsActive(root)) return;
                    if (metricTimers.has(root)) return;
                    metricTimers.set(root, window.setInterval(() => refreshMetrics(root), 15000));
                });
            };

            const stopMetricPolling = () => {
                metricTimers.forEach((timer) => window.clearInterval(timer));
                metricTimers.clear();
            };

            const handleMetricVisibilityChange = () => {
                if (document.hidden) {
                    stopMetricPolling();
                    return;
                }

                roots.forEach((root) => refreshMetrics(root));
                startMetricPolling();
            };

            const handleMetricTabChange = () => {
                stopMetricPolling();
                roots.forEach((root) => refreshMetrics(root));
                startMetricPolling();
            };

            handleMetricTabChange();
            document.addEventListener('visibilitychange', handleMetricVisibilityChange);
            document.addEventListener('dashboard-tab:changed', handleMetricTabChange);
            window.addEventListener('pagehide', stopMetricPolling);

            const aiWriterProcess = document.querySelector('[data-ai-writer-process]');
            if (aiWriterProcess) {
                const endpoint = aiWriterProcess.getAttribute('data-endpoint');
                const token = aiWriterProcess.querySelector('input[name="_token"]')?.value;
                const buttons = Array.from(aiWriterProcess.querySelectorAll('[data-ai-writer-action]'));
                const limit = aiWriterProcess.querySelector('[data-ai-writer-limit]');
                const source = aiWriterProcess.querySelector('[data-ai-writer-source]');
                const seed = aiWriterProcess.querySelector('[data-ai-writer-seed]');
                const status = aiWriterProcess.querySelector('[data-ai-writer-status]');
                const output = aiWriterProcess.querySelector('[data-ai-writer-output]');

                const setWriterBusy = (busy) => {
                    buttons.forEach((button) => {
                        button.disabled = busy;
                    });
                };

                const renderWriterStatus = (payload) => {
                    Object.entries(payload?.status || {}).forEach(([key, value]) => {
                        const el = aiWriterProcess.querySelector(`[data-ai-writer-count="${key}"]`);
                        if (el) el.textContent = value;
                    });
                };

                const writerSummaryText = (payload) => {
                    const summaries = payload?.summaries || {};
                    const lines = [payload?.message || 'AI writer process finished.'];

                    Object.entries(summaries).forEach(([stage, summary]) => {
                        lines.push('');
                        lines.push(stage.toUpperCase());
                        Object.entries(summary || {}).forEach(([key, value]) => {
                            if (Array.isArray(value)) {
                                lines.push(`${key}: ${JSON.stringify(value, null, 2)}`);
                                return;
                            }

                            if (value && typeof value === 'object') {
                                lines.push(`${key}: ${JSON.stringify(value, null, 2)}`);
                                return;
                            }

                            lines.push(`${key}: ${value}`);
                        });
                    });

                    return lines.join('\n');
                };

                buttons.forEach((button) => {
                    button.addEventListener('click', async () => {
                        const action = button.getAttribute('data-ai-writer-action') || 'all';
                        setWriterBusy(true);
                        if (status) status.textContent = `Running ${action}...`;

                        try {
                            const response = await fetch(endpoint, {
                                method: 'POST',
                                headers: {
                                    Accept: 'application/json',
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': token || '',
                                },
                                body: JSON.stringify({
                                    action,
                                    limit: Number(limit?.value || 3),
                                    source: source?.value || '',
                                    seed_sources: Boolean(seed?.checked),
                                }),
                            });
                            const payload = await response.json().catch(() => ({}));
                            renderWriterStatus(payload);

                            if (!response.ok || !payload.ok) {
                                throw new Error(payload.message || `AI writer process failed with status ${response.status}.`);
                            }

                            if (status) status.textContent = payload.message || 'AI writer process finished.';
                            if (output) output.textContent = writerSummaryText(payload);
                        } catch (error) {
                            if (status) status.textContent = error instanceof Error ? error.message : 'Unable to run AI writer process.';
                            if (output) output.textContent = error instanceof Error ? error.message : 'Unable to run AI writer process.';
                        } finally {
                            setWriterBusy(false);
                        }
                    });
                });
            }

            const voiceTest = document.querySelector('[data-voice-test]');
            if (voiceTest) {
                const endpoint = voiceTest.getAttribute('data-endpoint');
                const token = voiceTest.querySelector('input[name="_token"]')?.value;
                const text = voiceTest.querySelector('[data-voice-test-text]');
                const locale = voiceTest.querySelector('[data-voice-test-locale]');
                const button = voiceTest.querySelector('[data-voice-test-submit]');
                const status = voiceTest.querySelector('[data-voice-test-status]');
                const audio = voiceTest.querySelector('[data-voice-test-audio]');

                button?.addEventListener('click', async () => {
                    const value = text?.value?.trim() || '';

                    if (value === '') {
                        if (status) status.textContent = 'Add a sentence to test first.';
                        text?.focus();
                        return;
                    }

                    button.disabled = true;
                    if (status) status.textContent = 'Generating voice sample...';

                    try {
                        const response = await fetch(endpoint, {
                            method: 'POST',
                            headers: {
                                Accept: 'application/json',
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': token || '',
                            },
                            body: JSON.stringify({
                                text: value,
                                locale: locale?.value || 'en',
                            }),
                        });
                        const payload = await response.json().catch(() => ({}));

                        if (!response.ok || !payload.ok || !payload.audio_url) {
                            throw new Error(payload.message || `Voice test failed with status ${response.status}.`);
                        }

                        if (audio) {
                            audio.src = payload.audio_url;
                            audio.hidden = false;
                            await audio.play();
                        }

                        if (status) {
                            status.textContent = payload.cached
                                ? `Playing cached ${payload.locale || locale?.value || 'en'} sample.`
                                : `Generated and playing ${payload.locale || locale?.value || 'en'} sample.`;
                        }
                    } catch (error) {
                        if (status) status.textContent = error instanceof Error ? error.message : 'Unable to generate the voice test.';
                    } finally {
                        button.disabled = false;
                    }
                });
            }

            const vapidSetup = document.querySelector('[data-vapid-setup]');
            if (vapidSetup) {
                const endpoint = vapidSetup.getAttribute('data-vapid-url');
                const button = vapidSetup.querySelector('[data-vapid-enable]');
                const token = vapidSetup.querySelector('input[name="_token"]')?.value;
                const status = vapidSetup.querySelector('[data-vapid-status]');
                const publicKey = vapidSetup.querySelector('[data-vapid-public]');
                const privateKey = vapidSetup.querySelector('[data-vapid-private]');
                const env = vapidSetup.querySelector('[data-vapid-env]');
                const message = vapidSetup.querySelector('[data-vapid-message]');

                const setText = (el, value) => {
                    if (el) el.textContent = value;
                };

                const renderVapid = (payload) => {
                    const current = payload.status || payload;
                    setText(status, current.configured ? 'Configured' : 'Missing keys');
                    setText(publicKey, current.public_key_configured ? 'Set' : 'Missing');
                    setText(privateKey, current.private_key_configured ? 'Set' : 'Missing');
                    setText(env, current.storage_ready ? 'Settings DB' : 'Unavailable');
                    setText(message, payload.message || `Subject: ${current.subject || 'Not set'}`);
                };

                button?.addEventListener('click', async () => {
                    if (!endpoint) return;
                    button.disabled = true;
                    setText(message, 'Generating VAPID keys...');

                    try {
                        const response = await fetch(endpoint, {
                            method: 'POST',
                            headers: {
                                Accept: 'application/json',
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': token || '',
                            },
                            body: JSON.stringify({}),
                        });
                        const payload = await response.json().catch(() => ({}));
                        renderVapid(payload);

                        if (!response.ok) {
                            setText(message, payload.message || `Request failed with status ${response.status}.`);
                        }
                    } catch (error) {
                        setText(message, error instanceof Error ? error.message : 'Unable to enable VAPID keys.');
                    } finally {
                        button.disabled = false;
                    }
                });
            }

            const translationSettingsForm = document.querySelector('[data-translation-settings-form]');
            if (translationSettingsForm) {
                const endpoint = translationSettingsForm.getAttribute('data-endpoint');
                const token = translationSettingsForm.querySelector('input[name="_token"]')?.value;
                const providerInput = translationSettingsForm.querySelector('[data-translation-provider]');
                const googleKeyInput = translationSettingsForm.querySelector('[data-google-key]');
                const azureKeyInput = translationSettingsForm.querySelector('[data-azure-key]');
                const azureRegionInput = translationSettingsForm.querySelector('[data-azure-region]');
                const openRouterKeyInput = translationSettingsForm.querySelector('[data-openrouter-key]');
                const openRouterModelInput = translationSettingsForm.querySelector('[data-openrouter-model]');
                const button = translationSettingsForm.querySelector('[data-translation-settings-submit]');
                const message = translationSettingsForm.querySelector('[data-translation-settings-message]');
                const status = translationSettingsForm.querySelector('[data-provider-status]');
                const googleMasked = translationSettingsForm.querySelector('[data-google-masked]');
                const azureMasked = translationSettingsForm.querySelector('[data-azure-masked]');
                const openRouterMasked = translationSettingsForm.querySelector('[data-openrouter-masked]');
                const summaryProvider = document.querySelector('[data-translation-provider-label]');
                const summaryConfigured = document.querySelector('[data-translation-configured]');
                const summarySource = document.querySelector('[data-translation-source]');

                button?.addEventListener('click', async () => {
                    button.disabled = true;
                    if (message) message.textContent = 'Saving key...';
                    try {
                        const response = await fetch(endpoint, {
                            method: 'POST',
                            headers: {
                                Accept: 'application/json',
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': token || '',
                            },
                            body: JSON.stringify({
                                provider: providerInput?.value || 'google',
                                google_api_key: googleKeyInput?.value || '',
                                azure_api_key: azureKeyInput?.value || '',
                                azure_region: azureRegionInput?.value || '',
                                openrouter_api_key: openRouterKeyInput?.value || '',
                                openrouter_model: openRouterModelInput?.value || '',
                            }),
                        });
                        const payload = await response.json().catch(() => ({}));
                        if (message) message.textContent = payload.message || `Request finished with status ${response.status}.`;
                        const providerLabel = payload.provider === 'openrouter'
                            ? 'OpenRouter'
                            : (payload.provider === 'azure' ? 'Azure Translator' : 'Google Translate');
                        if (status) status.textContent = payload.configured ? 'Configured' : 'Missing key';
                        if (googleMasked) googleMasked.textContent = payload.google_masked_key || 'Not saved';
                        if (azureMasked) azureMasked.textContent = payload.azure_masked_key || 'Not saved';
                        if (openRouterMasked) openRouterMasked.textContent = payload.openrouter_masked_key || 'Not saved';
                        if (summaryProvider) summaryProvider.textContent = providerLabel;
                        if (summaryConfigured) summaryConfigured.textContent = payload.configured ? 'Configured' : 'Missing key';
                        if (summarySource) summarySource.textContent = payload.source || 'Settings';
                        if (googleKeyInput && response.ok) googleKeyInput.value = '';
                        if (azureKeyInput && response.ok) azureKeyInput.value = '';
                        if (openRouterKeyInput && response.ok) openRouterKeyInput.value = '';
                        if (azureRegionInput && payload.azure_region !== undefined) azureRegionInput.value = payload.azure_region || '';
                        if (openRouterModelInput && payload.model && payload.provider === 'openrouter') openRouterModelInput.value = payload.model;
                    } catch (error) {
                        if (message) message.textContent = error instanceof Error ? error.message : 'Unable to save key.';
                    } finally {
                        button.disabled = false;
                    }
                });
            }

            const mapSettingsForm = document.querySelector('[data-map-settings-form]');
            if (mapSettingsForm) {
                const endpoint = mapSettingsForm.getAttribute('data-endpoint');
                const token = mapSettingsForm.querySelector('input[name="_token"]')?.value;
                const keyInput = mapSettingsForm.querySelector('[data-map-key]');
                const button = mapSettingsForm.querySelector('[data-map-settings-submit]');
                const message = mapSettingsForm.querySelector('[data-map-settings-message]');
                const status = mapSettingsForm.querySelector('[data-map-status]');
                const source = mapSettingsForm.querySelector('[data-map-source]');
                const masked = mapSettingsForm.querySelector('[data-map-masked]');

                button?.addEventListener('click', async () => {
                    button.disabled = true;
                    if (message) message.textContent = 'Saving Maps key...';

                    try {
                        const response = await fetch(endpoint, {
                            method: 'POST',
                            headers: {
                                Accept: 'application/json',
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': token || '',
                            },
                            body: JSON.stringify({
                                google_maps_api_key: keyInput?.value || '',
                            }),
                        });
                        const payload = await response.json().catch(() => ({}));
                        if (message) message.textContent = payload.message || `Request finished with status ${response.status}.`;
                        if (status) status.textContent = payload.configured ? 'Configured' : 'Missing key';
                        if (source) source.textContent = payload.source || 'Settings';
                        if (masked) masked.textContent = payload.masked_key || 'Not saved';
                        if (keyInput && response.ok) keyInput.value = '';
                    } catch (error) {
                        if (message) message.textContent = error instanceof Error ? error.message : 'Unable to save Maps key.';
                    } finally {
                        button.disabled = false;
                    }
                });
            }

            const translationBatch = document.querySelector('[data-translation-batch]');
            if (translationBatch) {
                const endpoint = translationBatch.getAttribute('data-endpoint');
                const token = translationBatch.querySelector('input[name="_token"]')?.value;
                const buttons = Array.from(translationBatch.querySelectorAll('[data-batch-section]'));
                const limit = translationBatch.querySelector('[data-batch-limit]');
                const force = translationBatch.querySelector('[data-batch-force]');
                const status = translationBatch.querySelector('[data-batch-status]');

                const setBusy = (busy) => {
                    buttons.forEach((button) => {
                        button.disabled = busy;
                    });
                };

                const renderBatchStatus = (sections) => {
                    (sections || []).forEach((section) => {
                        const card = translationBatch.querySelector(`[data-section-card="${section.key}"]`);
                        if (!card) return;
                        const missing = card.querySelector('[data-section-missing]');
                        const total = card.querySelector('[data-section-total]');
                        if (missing) missing.textContent = section.missing;
                        if (total) total.textContent = section.total;
                    });
                };

                const missingFor = (sections, selectedSection) => {
                    if (!Array.isArray(sections)) return 0;
                    if (selectedSection === 'all') {
                        return sections.reduce((total, section) => total + Number(section.missing || 0), 0);
                    }

                    return Number(sections.find((item) => item.key === selectedSection)?.missing || 0);
                };

                const runBatch = async (section) => {
                    const response = await fetch(endpoint, {
                        method: 'POST',
                        headers: {
                            Accept: 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': token || '',
                        },
                        body: JSON.stringify({
                            section,
                            limit: Number(limit?.value || 20),
                            force: Boolean(force?.checked),
                        }),
                    });

                    return response.json().catch(() => ({}));
                };

                buttons.forEach((button) => {
                    button.addEventListener('click', async () => {
                        const section = button.getAttribute('data-batch-section') || 'all';
                        setBusy(true);
                        const label = section === 'all' ? 'platform content' : section;
                        if (status) status.textContent = `Translating ${label}...`;

                        try {
                            let runs = 0;
                            let totalProcessed = 0;
                            let totalTranslated = 0;
                            let totalFailed = 0;
                            let payload = {};

                            do {
                                runs++;
                                payload = await runBatch(section);
                                renderBatchStatus(payload.status);
                                totalProcessed += Number(payload.processed || 0);
                                totalTranslated += Number(payload.translated || 0);
                                totalFailed += Number(payload.failed || 0);

                                const remaining = missingFor(payload.status, section);
                                if (status) {
                                    status.textContent = `${payload.message || 'Translation run finished.'} Remaining: ${remaining}. Runs: ${runs}.`;
                                }

                                if (Boolean(force?.checked) || remaining === 0 || payload.halted || payload.failed > 0) {
                                    break;
                                }

                                if (Number(payload.translated || 0) === 0 && Number(payload.skipped || 0) === 0) {
                                    if (status) status.textContent = `${payload.message || 'Translation run finished.'} Stopped because no progress was made.`;
                                    break;
                                }
                            } while (runs < 200);

                            const remaining = missingFor(payload.status, section);
                            if (status && remaining === 0 && !Boolean(force?.checked)) {
                                status.textContent = `Translation complete. Processed ${totalProcessed} targets across ${runs} run${runs === 1 ? '' : 's'}: ${totalTranslated} translated, ${totalFailed} failed.`;
                            }
                        } catch (error) {
                            if (status) status.textContent = error instanceof Error ? error.message : 'Unable to run translations.';
                        } finally {
                            setBusy(false);
                        }
                    });
                });
            }

            const translationPreview = document.querySelector('[data-translation-preview]');
            if (translationPreview) {
                const endpoint = translationPreview.getAttribute('data-endpoint');
                const token = translationPreview.querySelector('input[name="_token"]')?.value;
                const locale = translationPreview.querySelector('[data-translation-locale]');
                const source = translationPreview.querySelector('[data-translation-source]');
                const output = translationPreview.querySelector('[data-translation-output]');
                const status = translationPreview.querySelector('[data-translation-status]');
                const button = translationPreview.querySelector('[data-translation-submit]');

                button?.addEventListener('click', async () => {
                    const text = source?.value?.trim() || '';

                    if (text === '') {
                        if (output) output.value = '';
                        if (status) status.textContent = 'Add text to translate first.';
                        source?.focus();
                        return;
                    }

                    button.disabled = true;
                    if (status) status.textContent = 'Translating...';
                    try {
                        const response = await fetch(endpoint, {
                            method: 'POST',
                            headers: {
                                Accept: 'application/json',
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': token || '',
                            },
                            body: JSON.stringify({
                                text,
                                target_locale: locale?.value || 'af',
                            }),
                        });
                        const payload = await response.json().catch(() => ({}));
                        if (output) output.value = payload.translated || '';
                        const validationMessage = payload.message
                            || Object.values(payload.errors || {}).flat().join(' ')
                            || `Request finished with status ${response.status}.`;
                        if (status) status.textContent = validationMessage;
                    } catch (error) {
                        if (status) status.textContent = error instanceof Error ? error.message : 'Unable to translate.';
                    } finally {
                        button.disabled = false;
                    }
                });
            }

            document.querySelectorAll('[data-article-translation]').forEach((root) => {
                const endpoint = root.getAttribute('data-endpoint');
                const token = root.querySelector('input[name="_token"]')?.value;
                const button = root.querySelector('[data-article-translation-submit]');
                const status = root.querySelector('[data-article-translation-status]');
                const locale = root.querySelector('[data-article-translation-locale]');

                button?.addEventListener('click', async () => {
                    button.disabled = true;
                    if (status) status.textContent = 'Translating...';
                    try {
                        const response = await fetch(endpoint, {
                            method: 'POST',
                            headers: {
                                Accept: 'application/json',
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': token || '',
                            },
                            body: JSON.stringify({ target_locale: locale?.value || 'af', force: true }),
                        });
                        const payload = await response.json().catch(() => ({}));
                        if (status) status.textContent = payload.message || `Request finished with status ${response.status}.`;
                    } catch (error) {
                        if (status) status.textContent = error instanceof Error ? error.message : 'Unable to translate article.';
                    } finally {
                        button.disabled = false;
                    }
                });
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
