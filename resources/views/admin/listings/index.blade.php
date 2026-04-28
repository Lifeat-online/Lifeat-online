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
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead><tr><th class="px-3 py-2 text-left">Title</th><th class="px-3 py-2 text-left">Status</th><th class="px-3 py-2 text-left">City</th><th class="px-3 py-2 text-left">Actions</th></tr></thead>
                            <tbody class="divide-y divide-gray-100">@foreach ($listings as $listing)<tr><td class="px-3 py-2">{{ $listing->title }}</td><td class="px-3 py-2">{{ ucfirst($listing->status) }}</td><td class="px-3 py-2">{{ $listing->city }}</td><td class="px-3 py-2"><div class="flex gap-3"><a href="{{ route('admin.listings.edit', $listing) }}" class="text-indigo-600">Edit</a><form method="post" action="{{ route('admin.listings.destroy', $listing) }}">@csrf @method('DELETE')<button class="text-red-600" type="submit">Delete</button></form></div></td></tr>@endforeach</tbody>
                        </table>
                    </div>
                    <div class="mt-4">{{ $listings->links() }}</div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>