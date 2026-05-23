<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Manage Listings</h2>
            <a href="{{ route('admin.listings.create') }}" class="rounded-md bg-indigo-600 px-4 py-2 text-sm text-white">Create Listing</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="overflow-hidden rounded-lg bg-white shadow-sm">
                <div class="p-6">
                    @if (session('status'))<div class="mb-4 rounded-md bg-green-50 p-3 text-sm text-green-700">{{ session('status') }}</div>@endif
                    <form method="get" action="{{ route('admin.listings.index') }}" class="mb-4 grid gap-3 rounded-lg bg-slate-50 p-4 md:grid-cols-5">
                        <input class="rounded-md border-gray-300 text-sm md:col-span-2" name="q" placeholder="Search title, city, email…" value="{{ $filters['q'] ?? '' }}">
                        <select class="rounded-md border-gray-300 text-sm" name="status">
                            <option value="">All statuses</option>
                            <option value="draft" @selected(($filters['status'] ?? '') === 'draft')>Draft</option>
                            <option value="published" @selected(($filters['status'] ?? '') === 'published')>Published</option>
                        </select>
                        <select class="rounded-md border-gray-300 text-sm" name="featured">
                            <option value="">Featured & unfeatured</option>
                            <option value="yes" @selected(($filters['featured'] ?? '') === 'yes')>Featured only</option>
                            <option value="no" @selected(($filters['featured'] ?? '') === 'no')>Unfeatured only</option>
                        </select>
                        <div class="flex gap-2">
                            <select class="w-full rounded-md border-gray-300 text-sm" name="sort">
                                <option value="newest" @selected(($filters['sort'] ?? '') === 'newest')>Newest</option>
                                <option value="oldest" @selected(($filters['sort'] ?? '') === 'oldest')>Oldest</option>
                                <option value="title_asc" @selected(($filters['sort'] ?? '') === 'title_asc')>Title A→Z</option>
                                <option value="title_desc" @selected(($filters['sort'] ?? '') === 'title_desc')>Title Z→A</option>
                            </select>
                            <button class="rounded-md bg-indigo-600 px-4 py-2 text-sm text-white" type="submit">Apply</button>
                        </div>
                    </form>

                    <form method="post" action="{{ route('admin.listings.bulk') }}">
                        @csrf
                        <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                            <div class="flex flex-wrap items-center gap-2">
                                <select class="rounded-md border-gray-300 text-sm" name="action" required>
                                    <option value="" selected disabled>Bulk action…</option>
                                    <option value="publish">Publish</option>
                                    <option value="unpublish">Unpublish</option>
                                    <option value="feature">Mark featured</option>
                                    <option value="unfeature">Unmark featured</option>
                                    <option value="delete">Delete</option>
                                </select>
                                <button class="rounded-md bg-slate-700 px-4 py-2 text-sm text-white" type="submit" onclick="return confirm('Apply this bulk action to the selected listings?');">Run</button>
                            </div>
                            <label class="flex items-center gap-2 text-sm text-gray-600">
                                <input id="select_all" type="checkbox" class="rounded border-gray-300">
                                Select all on this page
                            </label>
                        </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead><tr><th class="px-3 py-2 text-left"></th><th class="px-3 py-2 text-left">Title</th><th class="px-3 py-2 text-left">Status</th><th class="px-3 py-2 text-left">Quality</th><th class="px-3 py-2 text-left">City</th><th class="px-3 py-2 text-left">Actions</th></tr></thead>
                            <tbody class="divide-y divide-gray-100">@foreach ($listings as $listing)@php($quality = $qualityScores[$listing->id] ?? ['score' => 0, 'label' => 'Incomplete', 'missing' => []])<tr><td class="px-3 py-2"><input class="row_cb rounded border-gray-300" type="checkbox" name="ids[]" value="{{ $listing->slug }}"></td><td class="px-3 py-2">{{ $listing->title }}</td><td class="px-3 py-2">{{ ucfirst($listing->status) }}</td><td class="px-3 py-2"><span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-700">{{ $quality['score'] }} / {{ $quality['label'] }}</span>@if (! empty($quality['missing']))<p class="mt-1 text-xs text-gray-500">{{ implode(', ', array_slice($quality['missing'], 0, 3)) }}</p>@endif</td><td class="px-3 py-2">{{ $listing->city }}</td><td class="px-3 py-2"><div class="flex gap-3"><a href="{{ route('admin.listings.edit', $listing) }}" class="text-indigo-600">Edit</a><form method="post" action="{{ route('admin.listings.destroy', $listing) }}">@csrf @method('DELETE')<button class="text-red-600" type="submit" onclick="return confirm('Delete this listing?');">Delete</button></form></div></td></tr>@endforeach</tbody>
                        </table>
                    </div>
                    </form>
                    <div class="mt-4">{{ $listings->links() }}</div>
                </div>
            </div>
        </div>
    </div>

    <script>
        (() => {
            const all = document.getElementById('select_all');
            const cbs = Array.from(document.querySelectorAll('.row_cb'));
            if (!all || cbs.length === 0) return;
            all.addEventListener('change', () => cbs.forEach((cb) => cb.checked = all.checked));
        })();
    </script>
</x-app-layout>
