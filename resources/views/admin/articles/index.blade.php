<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Manage Articles</h2>
            <a href="{{ route('admin.articles.create') }}" class="rounded-md bg-indigo-600 px-4 py-2 text-sm text-white">Create Article</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="overflow-hidden rounded-lg bg-white shadow-sm">
                <div class="p-6">
                    @if (session('status'))
                        <div class="mb-4 rounded-md bg-green-50 p-3 text-sm text-green-700">{{ session('status') }}</div>
                    @endif

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead>
                                <tr>
                                    <th class="px-3 py-2 text-left">Title</th>
                                    <th class="px-3 py-2 text-left">Author</th>
                                    <th class="px-3 py-2 text-left">Status</th>
                                    <th class="px-3 py-2 text-left">Words</th>
                                    <th class="px-3 py-2 text-left">Ledger</th>
                                    <th class="px-3 py-2 text-left">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($articles as $article)
                                    <tr>
                                        <td class="px-3 py-2">{{ $article->title }}</td>
                                        <td class="px-3 py-2">{{ $article->author?->name }}</td>
                                        <td class="px-3 py-2">{{ str_replace('_', ' ', ucfirst($article->status)) }}</td>
                                        <td class="px-3 py-2">{{ $article->wordCount() }}</td>
                                        <td class="px-3 py-2">
                                            @if ($article->wordLedger)
                                                {{ ucfirst($article->wordLedger->status) }} ({{ number_format((float) $article->wordLedger->gross_amount, 2) }})
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td class="px-3 py-2">
                                            <div class="flex gap-3">
                                                <a href="{{ route('admin.articles.edit', $article) }}" class="text-indigo-600">Edit</a>
                                                <form method="post" action="{{ route('admin.articles.destroy', $article) }}">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button class="text-red-600" type="submit">Delete</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-4">{{ $articles->links() }}</div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
