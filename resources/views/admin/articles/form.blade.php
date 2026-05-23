<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $pageTitle }}</h2>
    </x-slot>

    @php
        $supportedLocales = collect((array) config('localization.supported'));
        $translationTargets = $supportedLocales->keys()->reject(fn ($locale) => $locale === $article->sourceLocale())->values();
        $currentTranslations = $article->exists
            ? ($article->relationLoaded('contentTranslations') ? $article->contentTranslations : $article->contentTranslations()->get())
            : collect();
    @endphp

    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
            <div class="rounded-lg bg-white p-6 shadow-sm">
                @if ($errors->any())
                    <div class="mb-4 rounded-md bg-red-50 p-4 text-sm text-red-700">
                        <ul class="list-disc pl-5">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if (session('status'))
                    <div class="mb-4 rounded-md bg-green-50 p-3 text-sm text-green-700">{{ session('status') }}</div>
                @endif

                <form method="post" action="{{ $formAction }}" enctype="multipart/form-data" class="space-y-6">
                    @csrf
                    @if ($formMethod !== 'POST')
                        @method($formMethod)
                    @endif

                    <div class="grid gap-6 md:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-sm font-medium">Title</label>
                            <input class="w-full rounded-md border-gray-300" name="title" value="{{ old('title', $article->title) }}">
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium">Slug</label>
                            <input class="w-full rounded-md border-gray-300" name="slug" value="{{ old('slug', $article->slug) }}">
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium">Status</label>
                            <select class="w-full rounded-md border-gray-300" name="status">
                                <option value="draft" @selected(old('status', $article->status ?: 'draft') === 'draft')>Draft</option>
                                <option value="pending_review" @selected(old('status', $article->status) === 'pending_review')>Pending Review</option>
                                <option value="revision_requested" @selected(old('status', $article->status) === 'revision_requested')>Revision Requested</option>
                                <option value="published" @selected(old('status', $article->status) === 'published')>Published</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium">Original Language</label>
                            <select class="w-full rounded-md border-gray-300" name="source_locale">
                                @foreach (config('localization.supported') as $locale => $details)
                                    <option value="{{ $locale }}" @selected(old('source_locale', $article->source_locale ?: app()->getLocale()) === $locale)>{{ $details['name'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium">Publish At</label>
                            <input class="w-full rounded-md border-gray-300" type="datetime-local" name="published_at" value="{{ old('published_at', optional($article->published_at)->format('Y-m-d\TH:i')) }}">
                        </div>
                    </div>

                    <div class="grid gap-6 rounded-lg bg-gray-50 p-4 md:grid-cols-3">
                        <div>
                            <p class="text-sm font-medium text-gray-700">Writer</p>
                            <p class="text-sm text-gray-600">{{ $article->author?->name ?: 'Current admin user' }}</p>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-700">Word Count</p>
                            <p class="text-sm text-gray-600">{{ $article->wordCount() }}</p>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-700">Ledger Status</p>
                            <p class="text-sm text-gray-600">
                                @if ($article->wordLedger)
                                    {{ ucfirst($article->wordLedger->status) }} / {{ number_format((float) $article->wordLedger->gross_amount, 2) }}
                                @else
                                    Not generated
                                @endif
                            </p>
                        </div>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium">Revision / Editorial Note</label>
                        <textarea class="w-full rounded-md border-gray-300" name="revision_note" rows="3" placeholder="Add review feedback, revision instructions, or editorial context.">{{ old('revision_note') }}</textarea>
                    </div>

                    @if ($article->exists && $article->revisionNotes->isNotEmpty())
                        <div class="rounded-lg border border-gray-200 p-4">
                            <h3 class="mb-3 text-sm font-semibold text-gray-900">Revision History</h3>
                            <div class="space-y-3 text-sm text-gray-700">
                                @foreach ($article->revisionNotes as $note)
                                    <div class="rounded-md bg-gray-50 p-3">
                                        <p class="font-medium">{{ $note->author?->name }} · {{ $note->created_at?->format('j M Y H:i') }}</p>
                                        <p class="text-xs uppercase tracking-wide text-gray-500">{{ str_replace('_', ' ', $note->status ?: 'note') }}</p>
                                        <p class="mt-1 whitespace-pre-line">{{ $note->note }}</p>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <div class="space-y-3">
                        <div>
                            <label class="mb-1 block text-sm font-medium">Featured Image</label>
                            <input class="w-full rounded-md border-gray-300" type="file" name="featured_image_upload" accept="image/*">
                        </div>
                        @if ($article->featured_image)
                            <img src="{{ Storage::url($article->featured_image) }}" alt="" class="h-40 rounded-md border object-cover">
                            @if ($article->featured_image_is_ai_generated)
                                <p class="text-sm font-semibold text-indigo-700">AI-generated illustration</p>
                            @endif
                            <label class="inline-flex items-center gap-2 text-sm">
                                <input type="checkbox" name="remove_featured_image" value="1">
                                <span>Remove featured image</span>
                            </label>
                        @endif
                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-sm font-medium">Image caption</label>
                                <input class="w-full rounded-md border-gray-300" name="featured_image_caption" value="{{ old('featured_image_caption', $article->featured_image_caption) }}">
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium">Image credit</label>
                                <input class="w-full rounded-md border-gray-300" name="featured_image_credit" value="{{ old('featured_image_credit', $article->featured_image_credit) }}">
                            </div>
                        </div>
                        @if ($article->exists)
                            <div class="rounded-lg border border-fuchsia-100 bg-fuchsia-50 p-4" data-ai-image-panel data-endpoint="{{ route('admin.articles.ai-image', $article) }}">
                                <div class="grid gap-4 lg:grid-cols-[0.85fr,1.15fr]">
                                    <div>
                                        <p class="text-sm font-semibold text-fuchsia-950">Image Agent</p>
                                        <p class="mt-2 text-sm text-fuchsia-900">Generate a clearly labelled editorial illustration from this draft. It will replace the featured image only if you allow it.</p>
                                    </div>
                                    <div class="space-y-3">
                                        <label class="flex items-center gap-2 text-sm text-fuchsia-950">
                                            <input type="checkbox" data-ai-image-force @checked(! $article->featured_image)>
                                            <span>Replace existing featured image</span>
                                        </label>
                                        <div class="flex flex-wrap items-center gap-3">
                                            <button type="button" class="rounded-md bg-fuchsia-700 px-4 py-2 text-sm font-semibold text-white disabled:opacity-60" data-ai-image-submit>Generate illustration</button>
                                            <span class="text-sm text-fuchsia-900" data-ai-image-status>Uses the configured OpenAI/Gemini image provider.</span>
                                        </div>
                                        @if ($article->featured_image_prompt)
                                            <p class="rounded-md bg-white p-3 text-xs text-fuchsia-900"><strong>Prompt seed:</strong> {{ $article->featured_image_prompt }}</p>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @else
                            <div class="rounded-lg border border-fuchsia-100 bg-fuchsia-50 p-4 text-sm text-fuchsia-900">
                                Save the article before generating an AI illustration.
                            </div>
                        @endif
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium">Categories</label>
                        <select class="w-full rounded-md border-gray-300" name="category_ids[]" multiple size="5">
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}" @selected(in_array($category->id, old('category_ids', $selectedCategoryIds), true))>{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">Tags</label>
                        <select class="w-full rounded-md border-gray-300" name="tag_ids[]" multiple size="5">
                            @foreach ($tags as $tag)
                                <option value="{{ $tag->id }}" @selected(in_array($tag->id, old('tag_ids', $selectedTagIds), true))>{{ $tag->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">Locations</label>
                        <select class="w-full rounded-md border-gray-300" name="location_ids[]" multiple size="5">
                            @foreach ($locations as $location)
                                <option value="{{ $location->id }}" @selected(in_array($location->id, old('location_ids', $selectedLocationIds), true))>{{ $location->name }} ({{ $location->type }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">Excerpt</label>
                        <textarea class="w-full rounded-md border-gray-300" name="excerpt" rows="3" data-ai-article-excerpt>{{ old('excerpt', $article->excerpt) }}</textarea>
                    </div>

                    <div class="grid gap-6 rounded-lg border border-indigo-100 bg-indigo-50 p-4 lg:grid-cols-[0.7fr,1.3fr]" data-ai-article-panel data-endpoint="{{ route('admin.ai.article-seo') }}">
                        <div>
                            <p class="text-sm font-semibold text-indigo-950">AI SEO Assistant</p>
                            <p class="mt-2 text-sm text-indigo-900">Generate honest search metadata, an excerpt, a slug suggestion, and a push teaser from the article draft.</p>
                        </div>
                        <div class="space-y-3">
                            <div class="flex flex-wrap items-center gap-3">
                                <button type="button" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-60" data-ai-article-submit>Generate SEO draft</button>
                                <span class="text-sm text-indigo-900" data-ai-article-status>Review before saving.</span>
                            </div>
                            <div class="grid gap-3 md:grid-cols-2">
                                <div>
                                    <label class="mb-1 block text-sm font-medium text-indigo-950">SEO Title</label>
                                    <input class="w-full rounded-md border-indigo-200" name="seo_title" value="{{ old('seo_title', $article->seo_title) }}" data-ai-article-seo-title>
                                </div>
                                <div>
                                    <label class="mb-1 block text-sm font-medium text-indigo-950">SEO Description</label>
                                    <input class="w-full rounded-md border-indigo-200" name="seo_description" value="{{ old('seo_description', $article->seo_description) }}" data-ai-article-seo-description>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium">Body</label>
                        <textarea class="w-full rounded-md border-gray-300" name="body" rows="10">{{ old('body', $article->body) }}</textarea>
                    </div>

                    @if ($article->exists)
                        <div class="rounded-lg border border-emerald-100 bg-emerald-50 p-4" data-ai-article-translation-panel data-endpoint="{{ route('admin.ai.article-translation', $article) }}">
                            <div class="grid gap-4 lg:grid-cols-[0.85fr,1.15fr]">
                                <div>
                                    <p class="text-sm font-semibold text-emerald-950">AI Article Translation</p>
                                    <p class="mt-2 text-sm text-emerald-900">Save the article first, then generate an AI-assisted translation into the selected language. The translation is stored separately and never overwrites the original.</p>

                                    <div class="mt-3 space-y-2 text-sm text-emerald-900">
                                        <p><strong>Source:</strong> {{ $supportedLocales[$article->sourceLocale()]['name'] ?? strtoupper($article->sourceLocale()) }}</p>
                                        <p><strong>Saved translations:</strong>
                                            @forelse ($currentTranslations as $translation)
                                                <span class="rounded-full bg-white px-2 py-1 text-xs font-semibold text-emerald-800">
                                                    {{ $supportedLocales[$translation->locale]['name'] ?? strtoupper($translation->locale) }}
                                                    @if ($translation->provider)
                                                        · {{ $translation->provider }}
                                                    @endif
                                                </span>
                                            @empty
                                                None yet
                                            @endforelse
                                        </p>
                                    </div>
                                </div>

                                <div class="space-y-3">
                                    <div class="grid gap-3 md:grid-cols-2">
                                        <div>
                                            <label class="mb-1 block text-sm font-medium text-emerald-950">Target language</label>
                                            <select class="w-full rounded-md border-emerald-200" data-ai-translation-locale>
                                                @foreach ($translationTargets as $locale)
                                                    <option value="{{ $locale }}">{{ $supportedLocales[$locale]['name'] ?? strtoupper($locale) }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <label class="flex items-end gap-2 text-sm font-medium text-emerald-950">
                                            <input type="checkbox" value="1" data-ai-translation-force>
                                            <span>Regenerate even if current</span>
                                        </label>
                                    </div>

                                    <div class="flex flex-wrap items-center gap-3">
                                        <button type="button" class="rounded-md bg-emerald-700 px-4 py-2 text-sm font-semibold text-white disabled:opacity-60" data-ai-translation-submit>Generate translation</button>
                                        <span class="text-sm text-emerald-900" data-ai-translation-status>Ready.</span>
                                    </div>

                                    <textarea class="w-full rounded-md border-emerald-200 bg-white text-sm" rows="6" readonly data-ai-translation-preview placeholder="The translated title, excerpt, and body preview will appear here."></textarea>
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="rounded-lg border border-emerald-100 bg-emerald-50 p-4 text-sm text-emerald-900">
                            Save the article before generating AI translations.
                        </div>
                    @endif

                    <div class="flex gap-3">
                        <button class="rounded-md bg-indigo-600 px-4 py-2 text-white" type="submit">Save Article</button>
                        <a href="{{ route('admin.articles.index') }}" class="rounded-md bg-gray-200 px-4 py-2 text-gray-800">Back</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        (() => {
            const panel = document.querySelector('[data-ai-article-panel]');
            if (!panel) return;

            const form = panel.closest('form');
            const button = panel.querySelector('[data-ai-article-submit]');
            const status = panel.querySelector('[data-ai-article-status]');
            const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

            const selectedText = (name) => Array.from(form?.querySelectorAll(`[name="${name}[]"] option:checked`) || [])
                .map((option) => option.textContent.trim())
                .filter(Boolean);

            button?.addEventListener('click', async () => {
                button.disabled = true;
                if (status) status.textContent = 'Generating SEO draft...';

                try {
                    const response = await fetch(panel.dataset.endpoint, {
                        method: 'POST',
                        headers: {
                            Accept: 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': token,
                        },
                        body: JSON.stringify({
                            title: form?.querySelector('[name="title"]')?.value || '',
                            slug: form?.querySelector('[name="slug"]')?.value || '',
                            excerpt: form?.querySelector('[name="excerpt"]')?.value || '',
                            body: form?.querySelector('[name="body"]')?.value || '',
                            source_locale: form?.querySelector('[name="source_locale"]')?.value || 'en',
                            categories: selectedText('category_ids'),
                            tags: selectedText('tag_ids'),
                            locations: selectedText('location_ids'),
                        }),
                    });
                    const payload = await response.json().catch(() => ({}));
                    const suggestion = payload.suggestion || {};

                    if (response.ok && suggestion.seo_title) {
                        const target = form?.querySelector('[data-ai-article-seo-title]');
                        if (target) target.value = suggestion.seo_title;
                    }

                    if (response.ok && suggestion.seo_description) {
                        const target = form?.querySelector('[data-ai-article-seo-description]');
                        if (target) target.value = suggestion.seo_description;
                    }

                    if (response.ok && suggestion.excerpt) {
                        const target = form?.querySelector('[data-ai-article-excerpt]');
                        if (target) target.value = suggestion.excerpt;
                    }

                    if (response.ok && suggestion.suggested_slug) {
                        const slug = form?.querySelector('[name="slug"]');
                        if (slug && !slug.value) slug.value = suggestion.suggested_slug;
                    }

                    if (status) {
                        const keywords = Array.isArray(suggestion.focus_keywords) && suggestion.focus_keywords.length > 0
                            ? ` Keywords: ${suggestion.focus_keywords.join(', ')}.`
                            : '';
                        status.textContent = (payload.message || `Request finished with status ${response.status}.`) + keywords;
                    }
                } catch (error) {
                    if (status) status.textContent = error instanceof Error ? error.message : 'Unable to generate SEO draft.';
                } finally {
                    button.disabled = false;
                }
            });
        })();
    </script>
    @if ($article->exists)
        <script>
            (() => {
                const panel = document.querySelector('[data-ai-article-translation-panel]');
                if (!panel) return;

                const button = panel.querySelector('[data-ai-translation-submit]');
                const status = panel.querySelector('[data-ai-translation-status]');
                const locale = panel.querySelector('[data-ai-translation-locale]');
                const force = panel.querySelector('[data-ai-translation-force]');
                const preview = panel.querySelector('[data-ai-translation-preview]');
                const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

                const formatPreview = (content) => {
                    if (!content || typeof content !== 'object') return '';

                    return Object.entries(content)
                        .map(([field, value]) => `${field.toUpperCase()}\n${value}`)
                        .join("\n\n");
                };

                button?.addEventListener('click', async () => {
                    button.disabled = true;
                    if (status) status.textContent = 'Generating translation...';

                    try {
                        const response = await fetch(panel.dataset.endpoint, {
                            method: 'POST',
                            headers: {
                                Accept: 'application/json',
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': token,
                            },
                            body: JSON.stringify({
                                target_locale: locale?.value || 'af',
                                force: Boolean(force?.checked),
                            }),
                        });
                        const payload = await response.json().catch(() => ({}));

                        if (!response.ok || !payload.ok) {
                            throw new Error(payload.message || 'AI translation failed.');
                        }

                        if (preview) preview.value = formatPreview(payload.translation?.content || {});
                        if (status) status.textContent = payload.message || 'AI-assisted translation saved.';
                    } catch (error) {
                        if (status) status.textContent = error instanceof Error ? error.message : 'Unable to generate translation.';
                    } finally {
                        button.disabled = false;
                    }
                });
            })();
        </script>
    @endif
    @if ($article->exists)
        <script>
            (() => {
                const panel = document.querySelector('[data-ai-image-panel]');
                if (!panel) return;

                const button = panel.querySelector('[data-ai-image-submit]');
                const force = panel.querySelector('[data-ai-image-force]');
                const status = panel.querySelector('[data-ai-image-status]');
                const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

                button?.addEventListener('click', async () => {
                    button.disabled = true;
                    if (status) status.textContent = 'Generating illustration...';

                    try {
                        const response = await fetch(panel.dataset.endpoint, {
                            method: 'POST',
                            headers: {
                                Accept: 'application/json',
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': token,
                            },
                            body: JSON.stringify({ force: Boolean(force?.checked) }),
                        });
                        const payload = await response.json().catch(() => ({}));

                        if (!response.ok || !payload.ok) {
                            throw new Error(payload.message || 'Image generation failed.');
                        }

                        if (status) status.textContent = payload.message || 'Image Agent illustration generated. Refresh to review it.';
                    } catch (error) {
                        if (status) status.textContent = error instanceof Error ? error.message : 'Unable to generate illustration.';
                    } finally {
                        button.disabled = false;
                    }
                });
            })();
        </script>
    @endif
</x-app-layout>
