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
                            <input class="w-full rounded-md border-gray-300" name="title" value="{{ old('title', $listing->title) }}">
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium">Slug</label>
                            <input class="w-full rounded-md border-gray-300" name="slug" value="{{ old('slug', $listing->slug) }}">
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium">Website</label>
                            <input class="w-full rounded-md border-gray-300" name="website_url" value="{{ old('website_url', $listing->website_url) }}">
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium">Email</label>
                            <input class="w-full rounded-md border-gray-300" name="email" value="{{ old('email', $listing->email) }}">
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium">Phone</label>
                            <input class="w-full rounded-md border-gray-300" name="phone" value="{{ old('phone', $listing->phone) }}">
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium">City</label>
                            <input class="w-full rounded-md border-gray-300" name="city" value="{{ old('city', $listing->city) }}">
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium">Status</label>
                            <select class="w-full rounded-md border-gray-300" name="status">
                                <option value="draft" @selected(old('status', $listing->status ?: 'draft') === 'draft')>Draft</option>
                                <option value="published" @selected(old('status', $listing->status) === 'published')>Published</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium">Publish At</label>
                            <input class="w-full rounded-md border-gray-300" type="datetime-local" name="published_at" value="{{ old('published_at', optional($listing->published_at)->format('Y-m-d\TH:i')) }}">
                        </div>
                    </div>

                    <div class="grid gap-6 md:grid-cols-2">
                        <div class="space-y-3">
                            <div>
                                <label class="mb-1 block text-sm font-medium">Featured Image</label>
                                <input class="w-full rounded-md border-gray-300" type="file" name="featured_image_upload" accept="image/*">
                            </div>
                            @if ($listing->featured_image)
                                <img src="{{ Storage::url($listing->featured_image) }}" alt="" class="h-40 rounded-md border object-cover">
                                <label class="inline-flex items-center gap-2 text-sm">
                                    <input type="checkbox" name="remove_featured_image" value="1">
                                    <span>Remove featured image</span>
                                </label>
                            @endif
                        </div>
                        <div class="space-y-3">
                            <div>
                                <label class="mb-1 block text-sm font-medium">Logo</label>
                                <input class="w-full rounded-md border-gray-300" type="file" name="logo_upload" accept="image/*">
                            </div>
                            @if ($listing->logo_path)
                                <img src="{{ Storage::url($listing->logo_path) }}" alt="" class="h-32 rounded-md border object-contain bg-gray-50 p-2">
                                <label class="inline-flex items-center gap-2 text-sm">
                                    <input type="checkbox" name="remove_logo" value="1">
                                    <span>Remove logo</span>
                                </label>
                            @endif
                        </div>
                    </div>

                    <div class="grid gap-6 rounded-lg bg-gray-50 p-4 md:grid-cols-3">
                        <div>
                            <p class="text-sm font-medium text-gray-700">Source Channel</p>
                            <p class="text-sm text-gray-600">{{ $listing->source_channel ?: 'Not set' }}</p>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-700">Active Subscription</p>
                            <p class="text-sm text-gray-600">
                                @if ($listing->activeSubscription)
                                    {{ ucfirst($listing->activeSubscription->status) }}
                                @else
                                    None
                                @endif
                            </p>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-700">Package Expires</p>
                            <p class="text-sm text-gray-600">{{ optional($listing->package_expires_at)->format('j M Y') ?: '-' }}</p>
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
                        <label class="mb-1 block text-sm font-medium">Excerpt</label>
                        <textarea class="w-full rounded-md border-gray-300" name="excerpt" rows="3">{{ old('excerpt', $listing->excerpt) }}</textarea>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">Description</label>
                        <textarea class="w-full rounded-md border-gray-300" name="description" rows="8">{{ old('description', $listing->description) }}</textarea>
                    </div>

                    <label class="inline-flex items-center gap-2">
                        <input type="checkbox" name="is_featured" value="1" @checked(old('is_featured', $listing->is_featured))>
                        <span>Featured listing</span>
                    </label>

                    <div class="flex gap-3">
                        <button class="rounded-md bg-indigo-600 px-4 py-2 text-white" type="submit">Save Listing</button>
                        <a href="{{ route('admin.listings.index') }}" class="rounded-md bg-gray-200 px-4 py-2 text-gray-800">Back</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
