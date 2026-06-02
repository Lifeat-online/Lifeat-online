<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
            <div>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Action Station</h2>
                <p class="mt-1 text-sm text-gray-500">AI handles eligible public-content review; humans handle denied content, financials, payouts, and paid writer work.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('admin.dashboard') }}" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700">Dashboard</a>
                <a href="{{ route('admin.audit-logs.index') }}" class="rounded-md bg-slate-700 px-4 py-2 text-sm font-medium text-white">Audit Logs</a>
            </div>
        </div>
    </x-slot>

    @php
        $canManageAiReview = Auth::user()->hasRole('admin', 'editor');
        $groups = [
            'all' => 'All',
            'content' => 'Content',
            'advertising' => 'Advertising',
            'finance' => 'Finance',
            'payouts' => 'Payouts',
        ];
        $visibleSections = collect($sections)
            ->filter(fn ($section) => $selectedGroup === 'all' || $section['group'] === $selectedGroup)
            ->values();
        $priorityClasses = [
            'high' => 'bg-rose-50 text-rose-700 ring-rose-200',
            'medium' => 'bg-amber-50 text-amber-700 ring-amber-200',
            'low' => 'bg-slate-50 text-slate-600 ring-slate-200',
        ];
    @endphp

    <div class="py-10">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="rounded-lg bg-green-50 p-4 text-sm text-green-800 shadow-sm">{{ session('status') }}</div>
            @endif

            @if ($errors->any())
                <div class="rounded-lg bg-rose-50 p-4 text-sm text-rose-800 shadow-sm">
                    {{ $errors->first() }}
                </div>
            @endif

            <div class="grid gap-4 md:grid-cols-5">
                <div class="rounded-lg bg-white p-5 shadow-sm">
                    <p class="text-sm text-gray-500">Open Actions</p>
                    <p class="mt-2 text-3xl font-bold text-gray-900">{{ $summary['total_actions'] }}</p>
                </div>
                <div class="rounded-lg bg-white p-5 shadow-sm">
                    <p class="text-sm text-gray-500">High Priority</p>
                    <p class="mt-2 text-3xl font-bold text-rose-700">{{ $summary['high_priority'] }}</p>
                </div>
                <div class="rounded-lg bg-white p-5 shadow-sm">
                    <p class="text-sm text-gray-500">AI Review Pending</p>
                    <p class="mt-2 text-3xl font-bold text-indigo-700">{{ $summary['ai_review_pending'] }}</p>
                </div>
                <div class="rounded-lg bg-white p-5 shadow-sm">
                    <p class="text-sm text-gray-500">Denied Content</p>
                    <p class="mt-2 text-3xl font-bold text-amber-700">{{ $summary['denied_content'] }}</p>
                </div>
                <div class="rounded-lg bg-white p-5 shadow-sm">
                    <p class="text-sm text-gray-500">Approved 7d</p>
                    <p class="mt-2 text-3xl font-bold text-emerald-700">{{ $summary['approved_last_7d'] }}</p>
                </div>
            </div>

            @if ($canManageAiReview)
                <section class="rounded-lg bg-white p-6 shadow-sm">
                    <div class="grid gap-6 lg:grid-cols-[1.4fr_1fr] lg:items-start">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900">AI Review And Autopublish</h3>
                            <p class="mt-2 text-sm text-gray-600">Public content can be AI-graded before publication. Approved items only auto-publish when the toggle is enabled, scores clear the threshold, and entitlement/linkage checks pass.</p>
                            <div class="mt-4 flex flex-wrap gap-2 text-xs font-semibold uppercase tracking-wide">
                                <span class="rounded-full bg-emerald-50 px-3 py-1 text-emerald-700">Eligible: listings, events, vouchers, ad creatives</span>
                                <span class="rounded-full bg-rose-50 px-3 py-1 text-rose-700">Human-only: writer pay, payouts, refunds, push sends</span>
                            </div>
                        </div>

                        <form method="POST" action="{{ route('admin.action-station.settings.update') }}" class="grid gap-4 rounded-lg border border-gray-200 p-4">
                            @csrf
                            <label class="flex items-center justify-between gap-4">
                                <span>
                                    <span class="block text-sm font-semibold text-gray-900">Auto-publish AI-approved content</span>
                                    <span class="text-xs text-gray-500">Denied or uncertain content still lands below for humans.</span>
                                </span>
                                <input type="checkbox" name="auto_publish" value="1" @checked($settings['auto_publish']) class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                            </label>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <label class="text-sm text-gray-700">
                                    Approval threshold
                                    <input type="number" name="approval_threshold" min="50" max="100" value="{{ $settings['approval_threshold'] }}" class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                </label>
                                <label class="text-sm text-gray-700">
                                    Batch size
                                    <input type="number" name="batch_limit" min="1" max="25" value="{{ $settings['batch_limit'] }}" class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                </label>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <button type="submit" class="rounded-md bg-slate-800 px-4 py-2 text-sm font-semibold text-white">Save Settings</button>
                            </div>
                        </form>
                    </div>

                    <form method="POST" action="{{ route('admin.action-station.review-all') }}" class="mt-5 flex flex-wrap items-center gap-3 border-t border-gray-100 pt-5">
                        @csrf
                        <input type="hidden" name="limit" value="{{ $settings['batch_limit'] }}">
                        <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white">Run AI Review Queue</button>
                        <p class="text-sm text-gray-500">Reviews up to {{ $settings['batch_limit'] }} pending item(s) using the current AI provider.</p>
                    </form>
                </section>
            @endif

            <div class="rounded-lg bg-white p-3 shadow-sm">
                <div class="flex flex-wrap gap-2">
                    @foreach ($groups as $group => $label)
                        <a href="{{ route('admin.action-station.index', ['group' => $group]) }}" class="rounded-md px-4 py-2 text-sm font-medium {{ $selectedGroup === $group ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-700' }}">
                            {{ $label }}
                        </a>
                    @endforeach
                </div>
            </div>

            <div class="grid gap-6 xl:grid-cols-2">
                @forelse ($visibleSections as $section)
                    <section class="rounded-lg bg-white p-6 shadow-sm">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900">{{ $section['title'] }}</h3>
                                <p class="mt-1 text-sm text-gray-500">{{ $section['description'] }}</p>
                            </div>
                            <span class="rounded-full bg-slate-100 px-3 py-1 text-sm font-semibold text-slate-700">{{ $section['count'] }}</span>
                        </div>

                        <div class="mt-5 divide-y divide-gray-100">
                            @forelse ($section['items'] as $item)
                                <div class="py-4">
                                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                        <div class="min-w-0">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-700">{{ $item['type'] }}</span>
                                                <span class="rounded-full px-2.5 py-1 text-xs font-semibold ring-1 {{ $priorityClasses[$item['priority'] ?? 'low'] ?? $priorityClasses['low'] }}">{{ ucfirst($item['priority'] ?? 'low') }}</span>
                                            </div>
                                            <a href="{{ $item['href'] }}" class="mt-2 block truncate font-semibold text-indigo-700">{{ $item['title'] }}</a>
                                            <p class="mt-1 text-sm text-gray-600">{{ $item['status'] }}</p>
                                            @if (! empty($item['meta']))
                                                <p class="mt-1 text-sm text-gray-500">{{ $item['meta'] }}</p>
                                            @endif
                                        </div>

                                        <div class="flex shrink-0 flex-wrap gap-2">
                                            @if ($canManageAiReview && isset($item['review_type'], $item['review_id']))
                                                <form method="POST" action="{{ route('admin.action-station.review') }}">
                                                    @csrf
                                                    <input type="hidden" name="type" value="{{ $item['review_type'] }}">
                                                    <input type="hidden" name="id" value="{{ $item['review_id'] }}">
                                                    <button type="submit" class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white">{{ $item['action_label'] }}</button>
                                                </form>
                                            @endif
                                            <a href="{{ $item['href'] }}" class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-700">{{ isset($item['review_type']) ? 'Open' : $item['action_label'] }}</a>
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <p class="mt-5 rounded-lg bg-slate-50 p-4 text-sm text-slate-600">No items in this queue right now.</p>
                            @endforelse
                        </div>
                    </section>
                @empty
                    <section class="rounded-lg bg-white p-6 shadow-sm">
                        <h3 class="text-lg font-semibold text-gray-900">No Queues</h3>
                        <p class="mt-2 text-sm text-gray-600">There are no action queues for this filter.</p>
                    </section>
                @endforelse
            </div>

            <section class="rounded-lg bg-white p-6 shadow-sm">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">Approved Content Report</h3>
                        <p class="mt-1 text-sm text-gray-500">AI-approved content is reported here so operators can audit what passed without reprocessing it.</p>
                    </div>
                    <span class="text-sm font-semibold text-emerald-700">{{ $approvedContent->count() }} recent approval(s)</span>
                </div>

                <div class="mt-5 overflow-hidden rounded-lg border border-gray-200">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-4 py-3">Content</th>
                                <th class="px-4 py-3">Type</th>
                                <th class="px-4 py-3">State</th>
                                <th class="px-4 py-3">Score</th>
                                <th class="px-4 py-3">Reviewed</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 bg-white">
                            @forelse ($approvedContent as $item)
                                <tr>
                                    <td class="px-4 py-3">
                                        <a href="{{ $item['href'] }}" class="font-semibold text-indigo-700">{{ $item['title'] }}</a>
                                        @if ($item['summary'] !== '')
                                            <p class="mt-1 max-w-xl text-xs text-gray-500">{{ $item['summary'] }}</p>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-gray-600">{{ $item['type'] }}</td>
                                    <td class="px-4 py-3 text-gray-600">{{ $item['status'] }}</td>
                                    <td class="px-4 py-3 font-semibold text-gray-900">{{ $item['score'] }}</td>
                                    <td class="px-4 py-3 text-gray-500">{{ optional($item['reviewed_at'])->diffForHumans() ?: '-' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-6 text-center text-gray-500">No AI-approved content has been recorded yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
