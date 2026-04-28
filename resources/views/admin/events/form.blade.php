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
                            <input class="w-full rounded-md border-gray-300" name="title" value="{{ old('title', $event->title) }}">
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium">Slug</label>
                            <input class="w-full rounded-md border-gray-300" name="slug" value="{{ old('slug', $event->slug) }}">
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium">Listing</label>
                            <select class="w-full rounded-md border-gray-300" name="listing_id">
                                <option value="">No linked listing</option>
                                @foreach ($listings as $listing)
                                    <option value="{{ $listing->id }}" @selected((string) old('listing_id', $event->listing_id) === (string) $listing->id)>{{ $listing->title }}</option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-xs text-gray-500">Published events require a linked listing with an active business package.</p>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium">Venue</label>
                            <input class="w-full rounded-md border-gray-300" name="venue_name" value="{{ old('venue_name', $event->venue_name) }}">
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium">City</label>
                            <input class="w-full rounded-md border-gray-300" name="city" value="{{ old('city', $event->city) }}">
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium">Status</label>
                            <select class="w-full rounded-md border-gray-300" name="status">
                                <option value="draft" @selected(old('status', $event->status ?: 'draft') === 'draft')>Draft</option>
                                <option value="published" @selected(old('status', $event->status) === 'published')>Published</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium">Start</label>
                            <input class="w-full rounded-md border-gray-300" type="datetime-local" name="start_at" value="{{ old('start_at', optional($event->start_at)->format('Y-m-d\TH:i')) }}">
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium">End</label>
                            <input class="w-full rounded-md border-gray-300" type="datetime-local" name="end_at" value="{{ old('end_at', optional($event->end_at)->format('Y-m-d\TH:i')) }}">
                        </div>
                    </div>

                    <div class="space-y-3">
                        <div>
                            <label class="mb-1 block text-sm font-medium">Featured Image</label>
                            <input class="w-full rounded-md border-gray-300" type="file" name="featured_image_upload" accept="image/*">
                        </div>
                        @if ($event->featured_image)
                            <img src="{{ Storage::url($event->featured_image) }}" alt="" class="h-40 rounded-md border object-cover">
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
                        <label class="mb-1 block text-sm font-medium">Excerpt</label>
                        <textarea class="w-full rounded-md border-gray-300" name="excerpt" rows="3">{{ old('excerpt', $event->excerpt) }}</textarea>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">Description</label>
                        <textarea class="w-full rounded-md border-gray-300" name="description" rows="8">{{ old('description', $event->description) }}</textarea>
                    </div>

                    <label class="inline-flex items-center gap-2">
                        <input type="checkbox" name="is_all_day" value="1" @checked(old('is_all_day', $event->is_all_day))>
                        <span>All day event</span>
                    </label>

                    <div class="flex gap-3">
                        <button class="rounded-md bg-indigo-600 px-4 py-2 text-white" type="submit">Save Event</button>
                        <a href="{{ route('admin.events.index') }}" class="rounded-md bg-gray-200 px-4 py-2 text-gray-800">Back</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
