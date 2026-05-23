<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Brief Review Queue</h2>
                <p class="mt-1 text-sm text-gray-500">Editorial Agent briefs wait here for a human editor before Jimmy can write a draft.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('admin.articles.index') }}" class="rounded-md bg-slate-700 px-4 py-2 text-sm text-white">Articles</a>
                <a href="{{ route('admin.dashboard') }}" class="rounded-md bg-slate-100 px-4 py-2 text-sm text-slate-700">Dashboard</a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="rounded-lg bg-green-50 p-4 text-sm text-green-800 shadow-sm">{{ session('status') }}</div>
            @endif

            @if ($errors->any())
                <div class="rounded-lg bg-red-50 p-4 text-sm text-red-700 shadow-sm">
                    {{ implode(' ', $errors->all()) }}
                </div>
            @endif

            <div class="grid gap-4 md:grid-cols-4">
                <a href="{{ route('admin.article-briefs.index') }}" class="rounded-lg bg-white p-5 shadow-sm">
                    <p class="text-sm text-gray-500">Pending review</p>
                    <p class="mt-2 text-3xl font-bold text-amber-700">{{ $counts['pending'] }}</p>
                </a>
                <a href="{{ route('admin.article-briefs.index', ['status' => 'approved']) }}" class="rounded-lg bg-white p-5 shadow-sm">
                    <p class="text-sm text-gray-500">Approved for Jimmy</p>
                    <p class="mt-2 text-3xl font-bold text-green-700">{{ $counts['approved'] }}</p>
                </a>
                <a href="{{ route('admin.article-briefs.index', ['status' => 'rejected']) }}" class="rounded-lg bg-white p-5 shadow-sm">
                    <p class="text-sm text-gray-500">Rejected</p>
                    <p class="mt-2 text-3xl font-bold text-slate-700">{{ $counts['rejected'] }}</p>
                </a>
                <a href="{{ route('admin.article-briefs.index', ['status' => 'drafted']) }}" class="rounded-lg bg-white p-5 shadow-sm">
                    <p class="text-sm text-gray-500">Drafted by Jimmy</p>
                    <p class="mt-2 text-3xl font-bold text-indigo-700">{{ $counts['drafted'] }}</p>
                </a>
            </div>

            <div class="rounded-lg bg-white p-6 shadow-sm">
                <form method="get" action="{{ route('admin.article-briefs.index') }}" class="grid gap-3 md:grid-cols-5">
                    <input class="rounded-md border-gray-300 text-sm md:col-span-2" name="q" placeholder="Search briefs..." value="{{ $filters['q'] ?? '' }}">
                    <select class="rounded-md border-gray-300 text-sm" name="status">
                        <option value="all" @selected(($filters['status'] ?? '') === 'all')>All statuses</option>
                        @foreach ($statusOptions as $option)
                            <option value="{{ $option }}" @selected(($filters['status'] ?? '') === $option)>{{ str_replace('_', ' ', ucfirst($option)) }}</option>
                        @endforeach
                    </select>
                    <button class="rounded-md bg-indigo-600 px-4 py-2 text-sm text-white" type="submit">Apply</button>
                </form>
            </div>

            <div class="space-y-5">
                @forelse ($briefs as $brief)
                    @php
                        $sourceUrls = collect($brief->source_urls ?? [])->filter()->implode("\n");
                        $suggestedTags = collect($brief->suggested_tags ?? [])->filter()->implode(', ');
                        $draftArticle = $brief->article;
                    @endphp
                    <article class="rounded-lg bg-white p-6 shadow-sm">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                            <div>
                                <div class="flex flex-wrap items-center gap-2 text-xs uppercase tracking-wide text-gray-500">
                                    <span>{{ str_replace('_', ' ', ucfirst($brief->status)) }}</span>
                                    @if ($brief->suggestedCategory)
                                        <span class="rounded-full bg-indigo-50 px-2 py-1 text-indigo-700">{{ $brief->suggestedCategory->name }}</span>
                                    @endif
                                    @if ($brief->researchItem?->source_name)
                                        <span>{{ $brief->researchItem->source_name }}</span>
                                    @endif
                                    @if ($draftArticle)
                                        <span class="rounded-full bg-green-50 px-2 py-1 text-green-700">Article draft linked</span>
                                    @endif
                                </div>
                                <h3 class="mt-2 text-lg font-semibold text-gray-900">{{ $brief->title }}</h3>
                                <p class="mt-2 text-sm text-gray-600">{{ $brief->researchItem?->title }}</p>
                                @if ($brief->researchItem?->source_url)
                                    <a class="mt-2 inline-block text-sm text-indigo-600" href="{{ $brief->researchItem->source_url }}" target="_blank" rel="noopener">Open source</a>
                                @endif
                            </div>
                            <div class="grid grid-cols-2 gap-2 text-sm md:grid-cols-4 lg:min-w-96">
                                <div class="rounded-md bg-slate-50 p-3"><span class="text-gray-500">Locality</span><strong class="block">{{ number_format((float) $brief->locality_score, 0) }}</strong></div>
                                <div class="rounded-md bg-slate-50 p-3"><span class="text-gray-500">News</span><strong class="block">{{ number_format((float) $brief->newsworthiness_score, 0) }}</strong></div>
                                <div class="rounded-md bg-slate-50 p-3"><span class="text-gray-500">Confidence</span><strong class="block">{{ number_format((float) $brief->confidence_score, 0) }}</strong></div>
                                <div class="rounded-md bg-slate-50 p-3"><span class="text-gray-500">Duplicate</span><strong class="block">{{ number_format((float) $brief->duplicate_risk, 0) }}</strong></div>
                            </div>
                        </div>

                        <form method="post" action="{{ route('admin.article-briefs.update', $brief) }}" class="mt-5 grid gap-4 md:grid-cols-2">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="status" value="{{ $filters['status'] ?? '' }}">
                            <input type="hidden" name="q" value="{{ $filters['q'] ?? '' }}">

                            <div class="md:col-span-2">
                                <label class="mb-1 block text-sm font-medium">Brief title</label>
                                <input class="w-full rounded-md border-gray-300" name="title" value="{{ old('title', $brief->title) }}">
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium">Category</label>
                                <select class="w-full rounded-md border-gray-300" name="suggested_category_id">
                                    <option value="">No category</option>
                                    @foreach ($categories as $category)
                                        <option value="{{ $category->id }}" @selected((string) old('suggested_category_id', $brief->suggested_category_id) === (string) $category->id)>{{ $category->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium">Suggested tags</label>
                                <input class="w-full rounded-md border-gray-300" name="suggested_tags" value="{{ old('suggested_tags', $suggestedTags) }}">
                            </div>
                            <div class="md:col-span-2">
                                <label class="mb-1 block text-sm font-medium">Angle</label>
                                <textarea class="w-full rounded-md border-gray-300" name="angle" rows="4">{{ old('angle', $brief->angle) }}</textarea>
                            </div>
                            <div class="md:col-span-2">
                                <label class="mb-1 block text-sm font-medium">Source URLs</label>
                                <textarea class="w-full rounded-md border-gray-300" name="source_urls" rows="3">{{ old('source_urls', $sourceUrls) }}</textarea>
                            </div>
                            <div class="grid gap-3 md:col-span-2 md:grid-cols-4">
                                <div>
                                    <label class="mb-1 block text-sm font-medium">Locality</label>
                                    <input class="w-full rounded-md border-gray-300" type="number" min="0" max="100" step="0.01" name="locality_score" value="{{ old('locality_score', $brief->locality_score) }}">
                                </div>
                                <div>
                                    <label class="mb-1 block text-sm font-medium">Newsworthiness</label>
                                    <input class="w-full rounded-md border-gray-300" type="number" min="0" max="100" step="0.01" name="newsworthiness_score" value="{{ old('newsworthiness_score', $brief->newsworthiness_score) }}">
                                </div>
                                <div>
                                    <label class="mb-1 block text-sm font-medium">Confidence</label>
                                    <input class="w-full rounded-md border-gray-300" type="number" min="0" max="100" step="0.01" name="confidence_score" value="{{ old('confidence_score', $brief->confidence_score) }}">
                                </div>
                                <div>
                                    <label class="mb-1 block text-sm font-medium">Duplicate risk</label>
                                    <input class="w-full rounded-md border-gray-300" type="number" min="0" max="100" step="0.01" name="duplicate_risk" value="{{ old('duplicate_risk', $brief->duplicate_risk) }}">
                                </div>
                            </div>
                            <div class="md:col-span-2">
                                <label class="mb-1 block text-sm font-medium">Editorial notes</label>
                                <textarea class="w-full rounded-md border-gray-300" name="editorial_notes" rows="3">{{ old('editorial_notes', $brief->editorial_notes) }}</textarea>
                            </div>
                            <div class="md:col-span-2">
                                <button class="rounded-md bg-slate-700 px-4 py-2 text-sm font-semibold text-white" type="submit">Save edits</button>
                                @if ($draftArticle)
                                    <a href="{{ route('admin.articles.edit', $draftArticle) }}" class="ml-2 inline-flex rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white">Open draft</a>
                                @endif
                            </div>
                        </form>

                        <div class="mt-4 flex flex-wrap gap-3">
                            @if ($brief->status === 'approved' && ! $draftArticle)
                                <form method="post" action="{{ route('admin.article-briefs.draft', $brief) }}">
                                    @csrf
                                    <input type="hidden" name="status" value="{{ $filters['status'] ?? '' }}">
                                    <input type="hidden" name="q" value="{{ $filters['q'] ?? '' }}">
                                    <button class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white" type="submit">Ask Jimmy to write draft</button>
                                </form>
                            @endif
                            <form method="post" action="{{ route('admin.article-briefs.approve', $brief) }}">
                                @csrf
                                <input type="hidden" name="status" value="{{ $filters['status'] ?? '' }}">
                                <input type="hidden" name="q" value="{{ $filters['q'] ?? '' }}">
                                <button class="rounded-md bg-green-600 px-4 py-2 text-sm font-semibold text-white" type="submit">Approve for Jimmy</button>
                            </form>
                            <form method="post" action="{{ route('admin.article-briefs.reject', $brief) }}" class="flex flex-wrap gap-2">
                                @csrf
                                <input type="hidden" name="status" value="{{ $filters['status'] ?? '' }}">
                                <input type="hidden" name="q" value="{{ $filters['q'] ?? '' }}">
                                <input class="rounded-md border-gray-300 text-sm" name="rejection_reason" placeholder="Reason (optional)">
                                <button class="rounded-md bg-red-600 px-4 py-2 text-sm font-semibold text-white" type="submit" onclick="return confirm('Reject this brief?');">Reject</button>
                            </form>
                        </div>
                    </article>
                @empty
                    <div class="rounded-lg bg-white p-8 text-center text-gray-500 shadow-sm">
                        No briefs match this queue yet.
                    </div>
                @endforelse
            </div>

            <div>{{ $briefs->links() }}</div>
        </div>
    </div>
</x-app-layout>
