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

                    <form method="get" action="{{ route('admin.articles.index') }}" class="mb-4 grid gap-3 rounded-lg bg-slate-50 p-4 md:grid-cols-5">
                        <input class="rounded-md border-gray-300 text-sm md:col-span-2" name="q" placeholder="Search title or author…" value="{{ $filters['q'] ?? '' }}">
                        <select class="rounded-md border-gray-300 text-sm" name="status">
                            <option value="">All statuses</option>
                            @foreach (($statusOptions ?? []) as $option)
                                <option value="{{ $option }}" @selected(($filters['status'] ?? '') === $option)>{{ str_replace('_', ' ', ucfirst($option)) }}</option>
                            @endforeach
                        </select>
                        <select class="rounded-md border-gray-300 text-sm" name="sort">
                            <option value="newest" @selected(($filters['sort'] ?? '') === 'newest')>Newest</option>
                            <option value="oldest" @selected(($filters['sort'] ?? '') === 'oldest')>Oldest</option>
                        </select>
                        <button class="rounded-md bg-indigo-600 px-4 py-2 text-sm text-white" type="submit">Apply</button>
                    </form>

                    <form method="post" action="{{ route('admin.articles.bulk') }}">
                        @csrf
                        <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                            <div class="flex flex-wrap items-center gap-2">
                                <select class="rounded-md border-gray-300 text-sm" name="action" required>
                                    <option value="" selected disabled>Bulk action…</option>
                                    <option value="set_status">Set status</option>
                                    <option value="delete">Delete</option>
                                </select>
                                <select class="rounded-md border-gray-300 text-sm" name="status">
                                    <option value="">Choose status…</option>
                                    @foreach (($statusOptions ?? []) as $option)
                                        <option value="{{ $option }}">{{ str_replace('_', ' ', ucfirst($option)) }}</option>
                                    @endforeach
                                </select>
                                <button class="rounded-md bg-slate-700 px-4 py-2 text-sm text-white" type="submit" onclick="return confirm('Apply this bulk action to the selected articles?');">Run</button>
                            </div>
                            <label class="flex items-center gap-2 text-sm text-gray-600">
                                <input id="select_all" type="checkbox" class="rounded border-gray-300">
                                Select all on this page
                            </label>
                        </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead>
                                <tr>
                                    <th class="px-3 py-2 text-left"></th>
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
                                        <td class="px-3 py-2"><input class="row_cb rounded border-gray-300" type="checkbox" name="ids[]" value="{{ $article->slug }}"></td>
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
                                                    <button class="text-red-600" type="submit" onclick="return confirm('Delete this article?');">Delete</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    </form>
                    <div class="mt-4">{{ $articles->links() }}</div>
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
