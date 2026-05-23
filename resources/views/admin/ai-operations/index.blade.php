<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">AI Operations</h2>
                <p class="mt-1 text-sm text-gray-500">Generation audit trail, retry controls, provider signals, and prompt overrides.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('admin.dashboard', ['tab' => 'ai']) }}" class="rounded-md bg-slate-700 px-4 py-2 text-sm font-semibold text-white">AI Dashboard</a>
                <a href="{{ route('admin.article-briefs.index') }}" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white">Brief Review</a>
            </div>
        </div>
    </x-slot>

    @php
        $statusClasses = [
            'accepted' => 'bg-emerald-100 text-emerald-800',
            'draft' => 'bg-blue-100 text-blue-800',
            'edited' => 'bg-amber-100 text-amber-800',
            'failed' => 'bg-red-100 text-red-800',
            'rejected' => 'bg-slate-200 text-slate-700',
        ];
    @endphp

    <div class="py-12">
        <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="rounded-lg bg-green-50 p-4 text-sm text-green-800 shadow-sm">{{ session('status') }}</div>
            @endif

            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-lg bg-white p-5 shadow-sm">
                    <h3 class="font-semibold text-gray-900">Status</h3>
                    <div class="mt-4 space-y-2">
                        @forelse ($statusStats as $stat)
                            <div class="flex items-center justify-between gap-3 text-sm">
                                <span class="capitalize text-gray-600">{{ str_replace('_', ' ', $stat['label']) }}</span>
                                <strong class="text-gray-900">{{ $stat['count'] }}</strong>
                            </div>
                        @empty
                            <p class="text-sm text-gray-500">No AI generations yet.</p>
                        @endforelse
                    </div>
                </div>
                <div class="rounded-lg bg-white p-5 shadow-sm">
                    <h3 class="font-semibold text-gray-900">Providers</h3>
                    <div class="mt-4 space-y-2">
                        @forelse ($providerStats as $stat)
                            <div class="flex items-center justify-between gap-3 text-sm">
                                <span class="text-gray-600">{{ $stat['label'] }}</span>
                                <strong class="text-gray-900">{{ $stat['count'] }}</strong>
                            </div>
                        @empty
                            <p class="text-sm text-gray-500">No provider activity logged.</p>
                        @endforelse
                    </div>
                </div>
                <div class="rounded-lg bg-white p-5 shadow-sm">
                    <h3 class="font-semibold text-gray-900">Top Features</h3>
                    <div class="mt-4 space-y-2">
                        @forelse ($featureStats as $stat)
                            <div class="flex items-center justify-between gap-3 text-sm">
                                <span class="text-gray-600">{{ str_replace('_', ' ', $stat['label']) }}</span>
                                <strong class="text-gray-900">{{ $stat['count'] }}</strong>
                            </div>
                        @empty
                            <p class="text-sm text-gray-500">No feature activity logged.</p>
                        @endforelse
                    </div>
                </div>
                <div class="rounded-lg bg-white p-5 shadow-sm">
                    <h3 class="font-semibold text-gray-900">Estimated Spend</h3>
                    <p class="mt-4 text-3xl font-bold text-gray-900">{{ $costs->format($costSummary['current_month']) }}</p>
                    <p class="mt-1 text-sm text-gray-500">This month</p>
                    <p class="mt-3 text-xs text-gray-500">All-time: {{ $costs->format($costSummary['total']) }}</p>
                    <p class="mt-1 text-xs text-gray-500">USD to ZAR rate: {{ number_format($costSummary['exchange_rate'], 2) }}</p>
                </div>
            </div>

            @if ($budgetStatus['warning'] || $budgetStatus['blocking_active'])
                <div class="rounded-lg {{ $budgetStatus['blocking_active'] ? 'bg-red-50 text-red-800' : 'bg-amber-50 text-amber-800' }} p-4 text-sm shadow-sm">
                    {{ $budgetStatus['message'] }}
                </div>
            @endif

            <section class="rounded-lg bg-white p-6 shadow-sm">
                <div class="flex flex-col gap-2 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">Cost Tracking</h3>
                        <p class="mt-1 text-sm text-gray-500">Estimates are stored in rand using configurable USD provider rates and `AI_COST_USD_TO_ZAR`.</p>
                    </div>
                    <div class="rounded-lg bg-slate-50 px-4 py-3 text-sm text-gray-600">
                        <p><strong>Currency:</strong> {{ $costSummary['currency'] }}</p>
                        <p><strong>Rate:</strong> 1 USD = R{{ number_format($costSummary['exchange_rate'], 2) }}</p>
                    </div>
                </div>
                <div class="mt-5 rounded-lg border border-slate-200 p-4">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div class="min-w-0 flex-1">
                            <h4 class="font-semibold text-gray-900">Monthly Budget</h4>
                            <p class="mt-1 text-sm text-gray-500">{{ $budgetStatus['message'] }}</p>
                            <div class="mt-4 h-3 overflow-hidden rounded-full bg-slate-100">
                                <div class="h-full rounded-full {{ $budgetStatus['blocking_active'] ? 'bg-red-600' : ($budgetStatus['warning'] ? 'bg-amber-500' : 'bg-emerald-500') }}" style="width: {{ min(100, $budgetStatus['percent_used']) }}%"></div>
                            </div>
                            <div class="mt-3 grid gap-2 text-sm text-gray-600 sm:grid-cols-4">
                                <p><strong>Limit:</strong> {{ $budgetStatus['formatted_limit'] }}</p>
                                <p><strong>Spent:</strong> {{ $budgetStatus['formatted_spent'] }}</p>
                                <p><strong>Remaining:</strong> {{ $budgetStatus['formatted_remaining'] }}</p>
                                <p><strong>Used:</strong> {{ number_format($budgetStatus['percent_used'], 1) }}%</p>
                            </div>
                        </div>
                        <div class="rounded-lg bg-slate-50 px-4 py-3 text-sm text-gray-600">
                            <p><strong>Warning:</strong> {{ number_format($budgetStatus['warning_percent'], 0) }}% at {{ $budgetStatus['formatted_warning_at'] }}</p>
                            <p><strong>Hard stop:</strong> {{ $budgetStatus['hard_stop_enabled'] ? 'On' : 'Off' }}</p>
                            <p><strong>Exempt:</strong> {{ implode(', ', $budgetStatus['exempt_features']) ?: 'None' }}</p>
                        </div>
                    </div>

                    @if ($canManageAiOperations)
                        <form method="post" action="{{ route('admin.ai-operations.budget.update') }}" class="mt-5 grid gap-4 lg:grid-cols-[10rem,10rem,1fr,auto] lg:items-end">
                            @csrf
                            @method('put')
                            <div>
                                <label class="mb-1 block text-xs font-medium uppercase text-gray-500">Monthly budget</label>
                                <input type="number" min="0" step="0.01" name="monthly_limit_zar" value="{{ $budgetStatus['limit'] }}" class="w-full rounded-md border-gray-300 text-sm">
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-medium uppercase text-gray-500">Warn at %</label>
                                <input type="number" min="1" max="100" step="1" name="warning_percent" value="{{ $budgetStatus['warning_percent'] }}" class="w-full rounded-md border-gray-300 text-sm">
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-medium uppercase text-gray-500">Exempt features</label>
                                <input name="exempt_features" value="{{ implode(', ', $budgetStatus['exempt_features']) }}" class="w-full rounded-md border-gray-300 text-sm" placeholder="settings_test, ask_life">
                            </div>
                            <div class="flex flex-wrap items-center gap-3">
                                <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                                    <input type="checkbox" name="hard_stop_enabled" value="1" @checked($budgetStatus['hard_stop_enabled'])>
                                    <span>Hard stop</span>
                                </label>
                                <button type="submit" class="rounded-md bg-slate-800 px-4 py-2 text-sm font-semibold text-white">Save budget</button>
                            </div>
                        </form>
                    @endif
                </div>
                <div class="mt-5 grid gap-4 lg:grid-cols-3">
                    <div class="rounded-lg border border-gray-200 p-4">
                        <h4 class="font-semibold text-gray-900">By Provider</h4>
                        <div class="mt-4 space-y-3">
                            @forelse ($costSummary['by_provider'] as $row)
                                <div class="flex items-center justify-between gap-3 text-sm">
                                    <span class="text-gray-600">{{ $row['label'] }}</span>
                                    <span class="font-semibold text-gray-900">{{ $row['formatted'] }}</span>
                                </div>
                            @empty
                                <p class="text-sm text-gray-500">No cost estimates yet.</p>
                            @endforelse
                        </div>
                    </div>
                    <div class="rounded-lg border border-gray-200 p-4">
                        <h4 class="font-semibold text-gray-900">By Feature</h4>
                        <div class="mt-4 space-y-3">
                            @forelse ($costSummary['by_feature'] as $row)
                                <div class="flex items-center justify-between gap-3 text-sm">
                                    <span class="text-gray-600">{{ str_replace('_', ' ', $row['label']) }}</span>
                                    <span class="font-semibold text-gray-900">{{ $row['formatted'] }}</span>
                                </div>
                            @empty
                                <p class="text-sm text-gray-500">No feature costs yet.</p>
                            @endforelse
                        </div>
                    </div>
                    <div class="rounded-lg border border-gray-200 p-4">
                        <h4 class="font-semibold text-gray-900">By Month</h4>
                        <div class="mt-4 space-y-3">
                            @forelse ($costSummary['by_month'] as $row)
                                <div class="flex items-center justify-between gap-3 text-sm">
                                    <span class="text-gray-600">{{ $row['label'] }}</span>
                                    <span class="font-semibold text-gray-900">{{ $row['formatted'] }}</span>
                                </div>
                            @empty
                                <p class="text-sm text-gray-500">No monthly costs yet.</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            </section>

            <section class="rounded-lg bg-white p-6 shadow-sm">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">Generation Log</h3>
                        <p class="mt-1 text-sm text-gray-500">Every AI run is captured with feature, provider, model, input summary, output preview, status, and error details.</p>
                    </div>
                    <form method="get" action="{{ route('admin.ai-operations.index') }}" class="grid gap-3 lg:grid-cols-[11rem,10rem,11rem,16rem,auto] lg:items-end">
                        <div>
                            <label class="mb-1 block text-xs font-medium uppercase text-gray-500">Feature</label>
                            <select name="feature" class="w-full rounded-md border-gray-300 text-sm">
                                <option value="">All</option>
                                @foreach ($featureOptions as $feature)
                                    <option value="{{ $feature }}" @selected($filters['feature'] === $feature)>{{ str_replace('_', ' ', $feature) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium uppercase text-gray-500">Status</label>
                            <select name="status" class="w-full rounded-md border-gray-300 text-sm">
                                <option value="">All</option>
                                @foreach ($statusOptions as $status)
                                    <option value="{{ $status }}" @selected($filters['status'] === $status)>{{ str_replace('_', ' ', $status) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium uppercase text-gray-500">Provider</label>
                            <select name="provider" class="w-full rounded-md border-gray-300 text-sm">
                                <option value="">All</option>
                                @foreach ($providerOptions as $provider)
                                    <option value="{{ $provider }}" @selected($filters['provider'] === $provider)>{{ $provider }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium uppercase text-gray-500">Search</label>
                            <input name="q" value="{{ $filters['q'] }}" class="w-full rounded-md border-gray-300 text-sm" placeholder="Model, error, input">
                        </div>
                        <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white">Filter</button>
                    </form>
                </div>

                <div class="mt-6 overflow-hidden rounded-lg border border-gray-200">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-gray-500">
                                <tr>
                                    <th class="px-4 py-3">Run</th>
                                    <th class="px-4 py-3">Feature</th>
                                    <th class="px-4 py-3">Provider</th>
                                    <th class="px-4 py-3">Input / Output</th>
                                    <th class="px-4 py-3">Owner</th>
                                    <th class="px-4 py-3">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 bg-white">
                                @forelse ($generations as $generation)
                                    @php
                                        $sourceLabel = $generation->source_type ? class_basename($generation->source_type).' #'.$generation->source_id : 'No source';
                                        $outputPreview = is_array($generation->output_payload)
                                            ? json_encode($generation->output_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                                            : '';
                                        $canRetry = $canManageAiOperations
                                            && in_array($generation->feature_key, $retryableFeatures, true)
                                            && filled($generation->input_payload);
                                    @endphp
                                    <tr class="align-top">
                                        <td class="px-4 py-4">
                                            <div class="font-semibold text-gray-900">#{{ $generation->id }}</div>
                                            <span class="mt-2 inline-flex rounded-full px-2 py-1 text-xs font-semibold {{ $statusClasses[$generation->status] ?? 'bg-slate-100 text-slate-700' }}">{{ str_replace('_', ' ', $generation->status) }}</span>
                                            <p class="mt-2 text-xs text-gray-500">{{ $generation->created_at?->format('Y-m-d H:i') }}</p>
                                            @if ($generation->retry_of_id)
                                                <p class="mt-1 text-xs text-indigo-600">Retry of #{{ $generation->retry_of_id }}</p>
                                            @endif
                                        </td>
                                        <td class="px-4 py-4">
                                            <div class="font-medium text-gray-900">{{ str_replace('_', ' ', $generation->feature_key) }}</div>
                                            <p class="mt-1 text-xs text-gray-500">{{ $generation->prompt_version ?: 'No prompt version' }}</p>
                                            <p class="mt-1 text-xs text-gray-500">{{ $sourceLabel }}</p>
                                        </td>
                                        <td class="px-4 py-4">
                                            <div class="font-medium text-gray-900">{{ $generation->provider ?: '-' }}</div>
                                            <p class="mt-1 max-w-56 break-words text-xs text-gray-500">{{ $generation->model ?: '-' }}</p>
                                            <p class="mt-1 text-xs text-gray-500">In {{ $generation->token_input_estimate ?: 0 }} / Out {{ $generation->token_output_estimate ?: 0 }}</p>
                                            <p class="mt-1 text-xs font-semibold text-gray-700">Cost {{ $costs->format($generation->cost_estimate) }}</p>
                                        </td>
                                        <td class="px-4 py-4">
                                            <p class="max-w-xl whitespace-pre-wrap text-gray-700">{{ \Illuminate\Support\Str::limit($generation->input_summary ?: 'No input summary stored.', 260) }}</p>
                                            @if ($generation->error_message)
                                                <p class="mt-3 rounded-md bg-red-50 p-2 text-xs text-red-800">{{ $generation->error_message }}</p>
                                            @elseif ($outputPreview)
                                                <details class="mt-3">
                                                    <summary class="cursor-pointer text-xs font-semibold text-indigo-600">Output preview</summary>
                                                    <pre class="mt-2 max-h-56 overflow-auto rounded-md bg-slate-950 p-3 text-xs text-slate-100">{{ \Illuminate\Support\Str::limit($outputPreview, 3000) }}</pre>
                                                </details>
                                            @endif
                                        </td>
                                        <td class="px-4 py-4">
                                            <p class="font-medium text-gray-900">{{ $generation->user?->name ?: 'System' }}</p>
                                            <p class="mt-1 text-xs text-gray-500">{{ $generation->output_language ?: 'No language' }}</p>
                                            @if ($generation->reviewer)
                                                <p class="mt-1 text-xs text-gray-500">Reviewed by {{ $generation->reviewer->name }}</p>
                                            @endif
                                        </td>
                                        <td class="px-4 py-4">
                                            @if ($canRetry)
                                                <form method="post" action="{{ route('admin.ai-operations.generations.retry', $generation) }}" onsubmit="return confirm('Retry this AI generation? This may call the configured provider again.');">
                                                    @csrf
                                                    <button type="submit" class="rounded-md bg-slate-800 px-3 py-2 text-xs font-semibold text-white">Retry</button>
                                                </form>
                                            @elseif (in_array($generation->feature_key, $retryableFeatures, true))
                                                <p class="text-xs text-gray-500">Older run. No stored input payload.</p>
                                            @else
                                                <p class="text-xs text-gray-500">Manual retry only.</p>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-4 py-8 text-center text-sm text-gray-500">No AI generations match the current filters.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="mt-4">
                    {{ $generations->links() }}
                </div>
            </section>

            <section class="rounded-lg bg-white p-6 shadow-sm">
                <div class="flex flex-col gap-2 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">Prompt Templates</h3>
                        <p class="mt-1 text-sm text-gray-500">Edit system instructions without changing code. Output schemas stay fixed so feature code still receives predictable JSON.</p>
                    </div>
                    @if (! $canManageAiOperations)
                        <p class="rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-800">Only the Dev owner can save prompt overrides.</p>
                    @endif
                </div>

                <div class="mt-6 grid gap-4">
                    @foreach ($promptTemplates as $featureKey => $prompt)
                        <div class="rounded-lg border border-gray-200 p-4">
                            <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                                <div>
                                    <h4 class="font-semibold text-gray-900">{{ \Illuminate\Support\Str::headline($featureKey) }}</h4>
                                    <p class="mt-1 text-xs text-gray-500">
                                        Version: {{ $prompt['version'] }} /
                                        Output: {{ $prompt['output_language'] }}
                                        @if ($prompt['is_custom'])
                                            / custom override active
                                        @endif
                                    </p>
                                </div>
                                @if ($canManageAiOperations && $prompt['is_custom'])
                                    <form method="post" action="{{ route('admin.ai-operations.prompts.reset', $featureKey) }}">
                                        @csrf
                                        @method('delete')
                                        <button type="submit" class="rounded-md bg-slate-100 px-3 py-2 text-xs font-semibold text-slate-700">Reset to default</button>
                                    </form>
                                @endif
                            </div>

                            <form method="post" action="{{ route('admin.ai-operations.prompts.update', $featureKey) }}" class="mt-4 grid gap-4">
                                @csrf
                                @method('put')
                                <div class="grid gap-3 lg:grid-cols-[1fr,12rem]">
                                    <div>
                                        <label class="mb-1 block text-xs font-medium uppercase text-gray-500">Version</label>
                                        <input class="w-full rounded-md border-gray-300 text-sm" name="version" value="{{ $prompt['is_custom'] ? $prompt['version'] : '' }}" placeholder="{{ $prompt['default_version'] }}">
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs font-medium uppercase text-gray-500">Output language</label>
                                        <input class="w-full rounded-md border-gray-300 text-sm" name="output_language" value="{{ $prompt['is_custom'] ? $prompt['output_language'] : '' }}" placeholder="{{ $prompt['default_output_language'] }}">
                                    </div>
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs font-medium uppercase text-gray-500">System prompt</label>
                                    <textarea class="min-h-40 w-full rounded-md border-gray-300 text-sm" name="system" @disabled(! $canManageAiOperations)>{{ $prompt['system'] }}</textarea>
                                    @if ($prompt['is_custom'])
                                        <details class="mt-2">
                                            <summary class="cursor-pointer text-xs font-semibold text-indigo-600">Show default prompt</summary>
                                            <p class="mt-2 whitespace-pre-wrap rounded-md bg-slate-50 p-3 text-xs text-gray-600">{{ $prompt['default_system'] }}</p>
                                        </details>
                                    @endif
                                </div>
                                <details>
                                    <summary class="cursor-pointer text-xs font-semibold text-indigo-600">Schema contract</summary>
                                    <dl class="mt-2 grid gap-2 rounded-md bg-slate-50 p-3 text-xs text-gray-600 md:grid-cols-2">
                                        @foreach ($prompt['schema'] as $field => $description)
                                            <div>
                                                <dt class="font-semibold text-gray-800">{{ $field }}</dt>
                                                <dd>{{ $description }}</dd>
                                            </div>
                                        @endforeach
                                    </dl>
                                </details>
                                @if ($canManageAiOperations)
                                    <div>
                                        <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white">Save prompt override</button>
                                    </div>
                                @endif
                            </form>
                        </div>
                    @endforeach
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
