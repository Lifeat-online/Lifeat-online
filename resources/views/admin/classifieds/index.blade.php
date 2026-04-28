<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Classified Moderation</h2>
            <a href="{{ route('classifieds.index') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700">View Public Classifieds</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto space-y-6 sm:px-6 lg:px-8">
            <form method="get" action="{{ route('admin.classifieds.index') }}" class="rounded-lg bg-white p-4 shadow-sm">
                <div class="flex flex-wrap gap-3">
                    <select class="rounded-md border-gray-300 text-sm" name="status">
                        @foreach (['pending', 'published', 'hidden', 'flagged', 'rejected', 'all'] as $status)
                            <option value="{{ $status }}" @selected(($filters['status'] ?? 'pending') === $status)>{{ ucfirst($status) }}</option>
                        @endforeach
                    </select>
                    <button class="rounded-md bg-indigo-600 px-4 py-2 text-sm text-white" type="submit">Apply</button>
                </div>
            </form>

            <div class="rounded-lg bg-white p-6 shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left">Title</th>
                                <th class="px-4 py-3 text-left">Owner</th>
                                <th class="px-4 py-3 text-left">Status</th>
                                <th class="px-4 py-3 text-left">Submitted</th>
                                <th class="px-4 py-3 text-left">Reviewed</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($classifieds as $classified)
                                <tr>
                                    <td class="px-4 py-3">
                                        <a class="font-medium text-indigo-600" href="{{ route('admin.classifieds.show', $classified) }}">{{ $classified->title }}</a>
                                        <div class="text-gray-500">{{ $classified->city ?: 'No location' }}</div>
                                    </td>
                                    <td class="px-4 py-3">{{ $classified->user?->name ?: 'Guest' }}</td>
                                    <td class="px-4 py-3">{{ ucfirst($classified->status) }}</td>
                                    <td class="px-4 py-3">{{ optional($classified->submitted_at)->format('j M Y H:i') ?: '-' }}</td>
                                    <td class="px-4 py-3">{{ optional($classified->reviewed_at)->format('j M Y H:i') ?: '-' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-6 text-center text-gray-500">No classifieds in this moderation state.</td>
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
