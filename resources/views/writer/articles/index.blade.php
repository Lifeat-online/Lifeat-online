<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">My Article Submissions</h2>
            <div class="flex gap-3">
                <a href="{{ route('writer.earnings.index') }}" class="rounded-md bg-gray-200 px-4 py-2 text-gray-800">View Earnings</a>
                <a href="{{ route('writer.articles.create') }}" class="rounded-md bg-indigo-600 px-4 py-2 text-white">New Submission</a>
            </div>
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
                                <th class="px-4 py-3 text-left font-medium text-gray-600">Word Count</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-600">Ledger</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-600">Latest Feedback</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-600">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($articles as $article)
                                <tr>
                                    <td class="px-4 py-3">{{ $article->title }}</td>
                                    <td class="px-4 py-3">{{ str_replace('_', ' ', ucfirst($article->status)) }}</td>
                                    <td class="px-4 py-3">{{ $article->wordCount() }}</td>
                                    <td class="px-4 py-3">
                                        @if ($article->wordLedger)
                                            {{ $article->wordLedger->status }} ({{ number_format((float) $article->wordLedger->gross_amount, 2) }})
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        {{ \Illuminate\Support\Str::limit($article->revisionNotes->first()?->note ?? '-', 60) }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <a class="text-indigo-600 hover:underline" href="{{ route('writer.articles.edit', $article) }}">Edit</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-6 text-center text-gray-500">No submissions yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-6">{{ $articles->links() }}</div>
            </div>
        </div>
    </div>
</x-app-layout>
