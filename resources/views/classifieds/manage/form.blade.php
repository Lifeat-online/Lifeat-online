<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div class="min-w-0">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $pageTitle }}</h2>
                <p class="mt-1 text-sm text-gray-500">All new and updated classifieds return to moderation before they appear publicly.</p>
            </div>
            <div class="flex shrink-0 items-center gap-3">
                <a href="{{ route('classifieds.manage.index') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700">Back to my classifieds</a>
                <button
                    type="submit"
                    form="classified-form"
                    class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white"
                >
                    Save
                </button>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
            <div class="rounded-lg bg-white p-6 shadow-sm">
                <form id="classified-form" method="post" action="{{ $formAction }}" enctype="multipart/form-data" class="space-y-6">
                    @csrf
                    @if ($formMethod !== 'POST')
                        @method($formMethod)
                    @endif

                    <div class="grid gap-6 md:grid-cols-2">
                        <div>
                            <label for="title" class="mb-1 block text-sm font-medium text-gray-700">Title</label>
                            <input id="title" name="title" class="w-full rounded-md border-gray-300 text-sm" value="{{ old('title', $classified->title) }}">
                            @error('title') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="price" class="mb-1 block text-sm font-medium text-gray-700">Price</label>
                            <input id="price" name="price" type="number" min="0" step="0.01" class="w-full rounded-md border-gray-300 text-sm" value="{{ old('price', $classified->price) }}">
                            @error('price') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div>
                        <label for="description" class="mb-1 block text-sm font-medium text-gray-700">Description</label>
                        <textarea id="description" name="description" rows="8" class="w-full rounded-md border-gray-300 text-sm">{{ old('description', $classified->description) }}</textarea>
                        @error('description') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid gap-6 md:grid-cols-3">
                        <div>
                            <label for="city" class="mb-1 block text-sm font-medium text-gray-700">City</label>
                            <input id="city" name="city" class="w-full rounded-md border-gray-300 text-sm" value="{{ old('city', $classified->city) }}">
                            @error('city') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="region" class="mb-1 block text-sm font-medium text-gray-700">Region</label>
                            <input id="region" name="region" class="w-full rounded-md border-gray-300 text-sm" value="{{ old('region', $classified->region) }}">
                            @error('region') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="country" class="mb-1 block text-sm font-medium text-gray-700">Country</label>
                            <input id="country" name="country" class="w-full rounded-md border-gray-300 text-sm" value="{{ old('country', $classified->country ?: 'South Africa') }}">
                            @error('country') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="grid gap-6 md:grid-cols-2">
                        <div>
                            <label for="currency" class="mb-1 block text-sm font-medium text-gray-700">Currency</label>
                            <input id="currency" name="currency" class="w-full rounded-md border-gray-300 text-sm" value="{{ old('currency', $classified->currency ?: 'ZAR') }}">
                            @error('currency') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="featured_image" class="mb-1 block text-sm font-medium text-gray-700">Featured image</label>
                            <input id="featured_image" name="featured_image" type="file" class="w-full rounded-md border-gray-300 text-sm">
                            @error('featured_image') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        <input id="contact_for_price" name="contact_for_price" type="checkbox" value="1" @checked(old('contact_for_price', $classified->contact_for_price))>
                        <label for="contact_for_price" class="text-sm font-medium text-gray-700">Contact for price</label>
                    </div>

                    @if ($classified->moderation_notes)
                        <div class="rounded-md bg-amber-50 p-4 text-sm text-amber-800">
                            <strong>Moderation notes:</strong> {{ $classified->moderation_notes }}
                        </div>
                    @endif

                    <div class="sticky bottom-4 z-10">
                        <div class="rounded-lg border border-gray-200 bg-white/95 p-3 shadow-sm backdrop-blur">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <p class="text-sm text-gray-600">Ready to submit? Your classified will return to moderation after saving.</p>
                                <div class="flex items-center gap-3">
                                    <a href="{{ route('classifieds.manage.index') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700">Cancel</a>
                                    <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white">Save classified</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
