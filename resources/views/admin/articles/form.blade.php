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
                            <label class="inline-flex items-center gap-2 text-sm">
                                <input type="checkbox" name="remove_featured_image" value="1">
                                <span>Remove featured image</span>
                            </label>
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
                        <textarea class="w-full rounded-md border-gray-300" name="excerpt" rows="3">{{ old('excerpt', $article->excerpt) }}</textarea>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">Body</label>
                        <textarea class="w-full rounded-md border-gray-300" name="body" rows="10">{{ old('body', $article->body) }}</textarea>
                    </div>

                    <div class="flex gap-3">
                        <button class="rounded-md bg-indigo-600 px-4 py-2 text-white" type="submit">Save Article</button>
                        <a href="{{ route('admin.articles.index') }}" class="rounded-md bg-gray-200 px-4 py-2 text-gray-800">Back</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
