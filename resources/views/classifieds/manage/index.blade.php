<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">My Classifieds</h2>
            <a href="{{ route('classifieds.manage.create') }}" class="rounded-md bg-indigo-600 px-4 py-2 text-sm text-white">Post Classified</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="rounded-lg bg-white p-6 shadow-sm">
                @if (session('status'))
                    <div class="mb-4 rounded-md bg-green-50 p-3 text-sm text-green-700">{{ session('status') }}</div>
                @endif

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left font-medium text-gray-600">Title</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-600">Status</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-600">Location</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-600">Moderation</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-600">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($classifieds as $classified)
                                <tr>
                                    <td class="px-4 py-3">{{ $classified->title }}</td>
                                    <td class="px-4 py-3">{{ ucfirst($classified->status) }}</td>
                                    <td class="px-4 py-3">{{ $classified->city ?: '-' }}</td>
                                    <td class="px-4 py-3">{{ $classified->moderation_notes ? \Illuminate\Support\Str::limit($classified->moderation_notes, 80) : '-' }}</td>
                                    <td class="px-4 py-3">
                                        @if ($classified->status !== \App\Models\Classified::STATUS_PUBLISHED)
                                            <a class="text-indigo-600 hover:underline" href="{{ route('classifieds.manage.edit', $classified) }}">Edit</a>
                                        @else
                                            <a class="text-indigo-600 hover:underline" href="{{ route('classifieds.show', $classified) }}">View</a>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-6 text-center text-gray-500">No classifieds yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-6">{{ $classifieds->links() }}</div>
            </div>
        </div>
    </div>
</x-app-layout>
