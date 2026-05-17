<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $pageTitle }}</h2>
    </x-slot>

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

                <form method="post" action="{{ $formAction }}" class="space-y-6">
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
                            <label class="mb-1 block text-sm font-medium">Original Language</label>
                            <select class="w-full rounded-md border-gray-300" name="source_locale">
                                @foreach (config('localization.supported') as $locale => $details)
                                    <option value="{{ $locale }}" @selected(old('source_locale', $article->source_locale ?: app()->getLocale()) === $locale)>{{ $details['name'] }}</option>
                                @endforeach
                            </select>
                        </div>
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
                        <textarea class="w-full rounded-md border-gray-300" name="excerpt" rows="3">{{ old('excerpt', $article->excerpt) }}</textarea>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium">Body</label>
                        <textarea class="w-full rounded-md border-gray-300" name="body" rows="12">{{ old('body', $article->body) }}</textarea>
                    </div>

                    <div class="rounded-md bg-gray-50 p-4 text-sm text-gray-700">
                        <p>Current status: <strong>{{ str_replace('_', ' ', ucfirst($article->status ?: 'draft')) }}</strong></p>
                        <p>Estimated word count: <strong>{{ old('body') || old('excerpt') ? str_word_count(strip_tags((string) (old('body') ?: old('excerpt')))) : $article->wordCount() }}</strong></p>
                    </div>

                    @if ($article->exists && $article->revisionNotes->isNotEmpty())
                        <div class="rounded-md bg-amber-50 p-4">
                            <h3 class="mb-2 text-sm font-semibold text-amber-900">Revision Feedback</h3>
                            <div class="space-y-3 text-sm text-amber-950">
                                @foreach ($article->revisionNotes as $note)
                                    <div class="rounded-md bg-white/70 p-3">
                                        <p class="font-medium">{{ $note->author?->name }} · {{ $note->created_at?->format('j M Y H:i') }}</p>
                                        <p class="text-xs uppercase tracking-wide text-amber-700">{{ str_replace('_', ' ', $note->status ?: 'note') }}</p>
                                        <p class="mt-1 whitespace-pre-line">{{ $note->note }}</p>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <label class="inline-flex items-center gap-2">
                        <input type="checkbox" name="submit_for_review" value="1" @checked(old('submit_for_review', $article->status === 'pending_review'))>
                        <span>Submit for review</span>
                    </label>

                    <div class="flex gap-3">
                        <button class="rounded-md bg-indigo-600 px-4 py-2 text-white" type="submit">Save Submission</button>
                        <a href="{{ route('writer.articles.index') }}" class="rounded-md bg-gray-200 px-4 py-2 text-gray-800">Back</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
