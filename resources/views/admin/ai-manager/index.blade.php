<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">AI Manager</h2>
                <p class="mt-1 text-sm text-gray-500">Autonomy policy, operating brief, revenue allocation, and proposed manager actions.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('admin.action-station.index') }}" class="rounded-md bg-slate-700 px-4 py-2 text-sm font-semibold text-white">Action Station</a>
                <a href="{{ route('admin.ai-operations.index') }}" class="rounded-md bg-slate-700 px-4 py-2 text-sm font-semibold text-white">AI Operations</a>
                <form method="post" action="{{ route('admin.ai-manager.recommendations.store') }}">
                    @csrf
                    <button type="submit" class="rounded-md bg-emerald-700 px-4 py-2 text-sm font-semibold text-white">Generate Actions</button>
                </form>
            </div>
        </div>
    </x-slot>

    @php
        $modeClasses = [
            'observer' => 'bg-blue-100 text-blue-800',
            'approval' => 'bg-indigo-100 text-indigo-800',
            'budgeted' => 'bg-amber-100 text-amber-800',
            'autonomous' => 'bg-red-100 text-red-800',
        ];
        $riskClasses = [
            'low' => 'bg-emerald-100 text-emerald-800',
            'medium' => 'bg-amber-100 text-amber-800',
            'high' => 'bg-red-100 text-red-800',
            'critical' => 'bg-red-200 text-red-900',
        ];
        $statusClasses = [
            'proposed' => 'bg-blue-100 text-blue-800',
            'approved' => 'bg-indigo-100 text-indigo-800',
            'dismissed' => 'bg-slate-200 text-slate-700',
            'blocked' => 'bg-red-100 text-red-800',
            'executed' => 'bg-emerald-100 text-emerald-800',
        ];
        $canManageAiManager = Auth::user()->hasRole('admin');
    @endphp

    <div class="py-12">
        <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="rounded-lg bg-green-50 p-4 text-sm text-green-800 shadow-sm">{{ session('status') }}</div>
            @endif

            @if ($errors->any())
                <div class="rounded-lg bg-red-50 p-4 text-sm text-red-800 shadow-sm">
                    {{ $errors->first() }}
                </div>
            @endif

            <section class="rounded-lg bg-white p-6 shadow-sm">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <div class="flex flex-wrap items-center gap-3">
                            <h3 class="text-lg font-semibold text-gray-900">Operating Brief</h3>
                            <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $modeClasses[$policy['mode']] ?? 'bg-slate-100 text-slate-700' }}">
                                {{ $policy['modes'][$policy['mode']] ?? ucfirst($policy['mode']) }}
                            </span>
                            @if ($policy['emergency_stop'])
                                <span class="rounded-full bg-red-100 px-3 py-1 text-xs font-semibold text-red-800">Emergency Stop</span>
                            @endif
                        </div>
                        <p class="mt-3 max-w-3xl text-base text-gray-700">{{ $brief['headline'] }}</p>
                        <p class="mt-2 max-w-3xl text-sm text-gray-500">{{ $brief['next_step'] }}</p>
                    </div>
                    <div class="rounded-lg bg-slate-50 px-4 py-3 text-sm text-gray-600">
                        <p><strong>Open actions:</strong> {{ $openActionCount }}</p>
                        <p><strong>AI budget:</strong> {{ $kpis['ai_budget']['message'] }}</p>
                    </div>
                </div>

                @if (! empty($brief['warnings']))
                    <div class="mt-5 grid gap-3 md:grid-cols-2">
                        @foreach ($brief['warnings'] as $warning)
                            <div class="rounded-lg bg-amber-50 p-4 text-sm text-amber-800">{{ $warning }}</div>
                        @endforeach
                    </div>
                @endif
            </section>

            <div class="grid gap-6 xl:grid-cols-[1.05fr,0.95fr]">
                <section class="rounded-lg bg-white p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-gray-900">Autonomy Policy</h3>
                    <p class="mt-1 text-sm text-gray-500">This controls what the manager may recommend or execute in later phases. Phase 0 still creates proposed actions only.</p>

                    @if ($canManageAiManager)
                        <form method="post" action="{{ route('admin.ai-manager.policy.update') }}" class="mt-5 space-y-5">
                            @csrf
                            @method('put')

                            <div class="grid gap-4 md:grid-cols-2">
                                <div>
                                    <label class="mb-1 block text-xs font-medium uppercase text-gray-500">Mode</label>
                                    <select name="mode" class="w-full rounded-md border-gray-300 text-sm">
                                        @foreach ($policy['modes'] as $key => $label)
                                            <option value="{{ $key }}" @selected($policy['mode'] === $key)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs font-medium uppercase text-gray-500">Article fund %</label>
                                    <input type="number" name="article_fund_percent" min="0" max="100" step="0.01" value="{{ $policy['article_fund_percent'] }}" class="w-full rounded-md border-gray-300 text-sm">
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs font-medium uppercase text-gray-500">Platform ad spend cap</label>
                                    <input type="number" name="monthly_platform_ad_cap" min="0" step="0.01" value="{{ $policy['monthly_platform_ad_cap'] }}" class="w-full rounded-md border-gray-300 text-sm">
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs font-medium uppercase text-gray-500">Max actions per run</label>
                                    <input type="number" name="max_actions_per_run" min="1" max="25" step="1" value="{{ $policy['max_actions_per_run'] }}" class="w-full rounded-md border-gray-300 text-sm">
                                </div>
                            </div>

                            <div class="grid gap-3 md:grid-cols-2">
                                <label class="flex items-start gap-3 rounded-lg border border-slate-200 p-3 text-sm text-gray-700">
                                    <input type="checkbox" name="allow_public_publishing" value="1" class="mt-1" @checked($policy['allow_public_publishing'])>
                                    <span><strong class="block text-gray-900">Allow public publishing</strong> Permit future autonomous publishing after allowlists are implemented.</span>
                                </label>
                                <label class="flex items-start gap-3 rounded-lg border border-slate-200 p-3 text-sm text-gray-700">
                                    <input type="checkbox" name="allow_direct_marketing" value="1" class="mt-1" @checked($policy['allow_direct_marketing'])>
                                    <span><strong class="block text-gray-900">Allow direct marketing</strong> Permit future push/email sends after consent checks are implemented.</span>
                                </label>
                                <label class="flex items-start gap-3 rounded-lg border border-slate-200 p-3 text-sm text-gray-700">
                                    <input type="checkbox" name="allow_external_ad_spend" value="1" class="mt-1" @checked($policy['allow_external_ad_spend'])>
                                    <span><strong class="block text-gray-900">Allow external ad spend</strong> Permit future paid platform promotion inside the monthly cap.</span>
                                </label>
                                <label class="flex items-start gap-3 rounded-lg border border-slate-200 p-3 text-sm text-gray-700">
                                    <input type="checkbox" name="require_human_payout_approval" value="1" class="mt-1" @checked($policy['require_human_payout_approval'])>
                                    <span><strong class="block text-gray-900">Require payout approval</strong> Keep writer, owner, refund, and payout money movements human-approved.</span>
                                </label>
                                <label class="flex items-start gap-3 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800 md:col-span-2">
                                    <input type="checkbox" name="emergency_stop" value="1" class="mt-1" @checked($policy['emergency_stop'])>
                                    <span><strong class="block">Emergency stop</strong> Pause autonomous execution while allowing observer reports.</span>
                                </label>
                            </div>

                            <div class="flex justify-end">
                                <button type="submit" class="rounded-md bg-slate-800 px-4 py-2 text-sm font-semibold text-white">Save Policy</button>
                            </div>
                        </form>
                    @else
                        <div class="mt-5 rounded-lg bg-slate-50 p-4 text-sm text-slate-700">
                            Policy editing is limited to admins. Editors can still review the operating brief and action ledger.
                        </div>
                    @endif
                </section>

                <section class="rounded-lg bg-white p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-gray-900">Revenue Allocation</h3>
                    <p class="mt-1 text-sm text-gray-500">{{ $allocation['month'] }} paid advertising/package revenue split by the current article-fund policy.</p>

                    <div class="mt-5 grid gap-4 sm:grid-cols-2">
                        <div class="rounded-lg border border-slate-200 p-4">
                            <p class="text-sm text-gray-500">Advertising revenue</p>
                            <p class="mt-2 text-2xl font-bold text-gray-900">{{ $allocation['formatted_advertising_revenue'] }}</p>
                        </div>
                        <div class="rounded-lg border border-slate-200 p-4">
                            <p class="text-sm text-gray-500">Article fund</p>
                            <p class="mt-2 text-2xl font-bold text-emerald-700">{{ $allocation['formatted_article_fund'] }}</p>
                            <p class="mt-1 text-xs text-gray-500">{{ number_format($policy['article_fund_percent'], 2) }}% of ad revenue</p>
                        </div>
                        <div class="rounded-lg border border-slate-200 p-4">
                            <p class="text-sm text-gray-500">Owner share</p>
                            <p class="mt-2 text-2xl font-bold text-gray-900">{{ $allocation['formatted_owner_share'] }}</p>
                            <p class="mt-1 text-xs text-gray-500">{{ number_format($policy['owner_share_percent'], 2) }}% remaining share</p>
                        </div>
                        <div class="rounded-lg border border-slate-200 p-4">
                            <p class="text-sm text-gray-500">Pending writer liability</p>
                            <p class="mt-2 text-2xl font-bold {{ $allocation['article_fund_remaining'] < 0 ? 'text-red-700' : 'text-gray-900' }}">{{ $allocation['formatted_pending_writer_liability'] }}</p>
                            <p class="mt-1 text-xs text-gray-500">Remaining reserve: {{ $allocation['formatted_article_fund_remaining'] }}</p>
                        </div>
                    </div>
                </section>
            </div>

            <section class="rounded-lg bg-white p-6 shadow-sm">
                <h3 class="text-lg font-semibold text-gray-900">Platform Signals</h3>
                <div class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <a href="{{ route('admin.campaigns.ads.index', ['status' => 'ready']) }}" class="rounded-lg border border-slate-200 p-4">
                        <p class="text-sm text-gray-500">Ads ready</p>
                        <p class="mt-2 text-2xl font-bold text-gray-900">{{ $kpis['ready_ads'] }}</p>
                    </a>
                    <a href="{{ route('admin.campaigns.ads.index', ['status' => 'active']) }}" class="rounded-lg border border-slate-200 p-4">
                        <p class="text-sm text-gray-500">Active ads</p>
                        <p class="mt-2 text-2xl font-bold text-gray-900">{{ $kpis['active_ads'] }}</p>
                    </a>
                    <a href="{{ route('admin.campaigns.push.index', ['sent' => 'no']) }}" class="rounded-lg border border-slate-200 p-4">
                        <p class="text-sm text-gray-500">Unsent push campaigns</p>
                        <p class="mt-2 text-2xl font-bold text-gray-900">{{ $kpis['pending_push_campaigns'] }}</p>
                    </a>
                    <a href="{{ route('admin.article-briefs.index') }}" class="rounded-lg border border-slate-200 p-4">
                        <p class="text-sm text-gray-500">Pending article briefs</p>
                        <p class="mt-2 text-2xl font-bold text-gray-900">{{ $kpis['pending_article_briefs'] }}</p>
                    </a>
                    <div class="rounded-lg border border-slate-200 p-4">
                        <p class="text-sm text-gray-500">Underperforming ads</p>
                        <p class="mt-2 text-2xl font-bold {{ $kpis['underperforming_ads'] > 0 ? 'text-amber-700' : 'text-gray-900' }}">{{ $kpis['underperforming_ads'] }}</p>
                    </div>
                    <a href="{{ route('admin.articles.index', ['status' => 'pending_review']) }}" class="rounded-lg border border-slate-200 p-4">
                        <p class="text-sm text-gray-500">Writer articles pending</p>
                        <p class="mt-2 text-2xl font-bold text-gray-900">{{ $kpis['writer_articles_pending_review'] }}</p>
                    </a>
                    <div class="rounded-lg border border-slate-200 p-4">
                        <p class="text-sm text-gray-500">Articles published 30d</p>
                        <p class="mt-2 text-2xl font-bold text-gray-900">{{ $kpis['published_articles_30d'] }}</p>
                    </div>
                    <a href="{{ route('admin.ai-operations.index', ['status' => 'failed']) }}" class="rounded-lg border border-slate-200 p-4">
                        <p class="text-sm text-gray-500">AI failures 7d</p>
                        <p class="mt-2 text-2xl font-bold {{ $kpis['failed_ai_generations_7d'] > 0 ? 'text-red-700' : 'text-gray-900' }}">{{ $kpis['failed_ai_generations_7d'] }}</p>
                    </a>
                </div>
            </section>

            <section class="rounded-lg bg-white p-6 shadow-sm">
                <div class="flex flex-col gap-2 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">Action Ledger</h3>
                        <p class="mt-1 text-sm text-gray-500">Every AI Manager proposal is stored for owner review before future automation is allowed.</p>
                    </div>
                    @if (! empty($domains))
                        <div class="flex flex-wrap gap-2">
                            @foreach ($domains as $domain)
                                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs text-slate-700">{{ ucfirst($domain['domain']) }}: {{ $domain['total'] }}</span>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="mt-5 overflow-hidden rounded-lg border border-slate-200">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                            <tr>
                                <th class="px-4 py-3">Action</th>
                                <th class="px-4 py-3">Domain</th>
                                <th class="px-4 py-3">Risk</th>
                                <th class="px-4 py-3">Mode</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3">Decision</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            @forelse ($actions as $action)
                                <tr class="align-top">
                                    <td class="px-4 py-4">
                                        <p class="font-semibold text-gray-900">{{ $action->title }}</p>
                                        <p class="mt-1 max-w-2xl text-gray-600">{{ $action->summary }}</p>
                                        @if ($action->rationale)
                                            <p class="mt-2 max-w-2xl text-xs text-gray-500">{{ $action->rationale }}</p>
                                        @endif
                                        <p class="mt-2 text-xs text-gray-400">Proposed {{ $action->created_at?->diffForHumans() }}</p>
                                    </td>
                                    <td class="px-4 py-4 text-gray-700">{{ ucfirst($action->domain) }}</td>
                                    <td class="px-4 py-4">
                                        <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $riskClasses[$action->risk_level] ?? 'bg-slate-100 text-slate-700' }}">{{ ucfirst($action->risk_level) }}</span>
                                    </td>
                                    <td class="px-4 py-4">
                                        <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $modeClasses[$action->required_mode] ?? 'bg-slate-100 text-slate-700' }}">{{ ucfirst($action->required_mode) }}</span>
                                    </td>
                                    <td class="px-4 py-4">
                                        <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $statusClasses[$action->status] ?? 'bg-slate-100 text-slate-700' }}">{{ ucfirst($action->status) }}</span>
                                        @if ($action->reviewer)
                                            <p class="mt-2 text-xs text-gray-500">{{ $action->reviewer->name }}</p>
                                        @endif
                                    </td>
                                    <td class="px-4 py-4">
                                        <div class="flex flex-wrap gap-2">
                                            @foreach (['approved' => 'Approve', 'blocked' => 'Block', 'dismissed' => 'Dismiss', 'executed' => 'Mark Done'] as $status => $label)
                                                <form method="post" action="{{ route('admin.ai-manager.actions.update', $action) }}">
                                                    @csrf
                                                    <input type="hidden" name="status" value="{{ $status }}">
                                                    <button type="submit" class="rounded-md border border-slate-300 px-3 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50">{{ $label }}</button>
                                                </form>
                                            @endforeach
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-8 text-center text-sm text-gray-500">No AI Manager actions yet. Generate actions to create the first operating brief proposals.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">
                    {{ $actions->links() }}
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
