<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">My Earnings</h2>
            <a href="{{ route('writer.articles.index') }}" class="rounded-md bg-gray-200 px-4 py-2 text-gray-800">Back to Submissions</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto space-y-6 sm:px-6 lg:px-8">
            <div class="grid gap-4 md:grid-cols-3">
                <div class="rounded-lg bg-white p-6 shadow-sm">
                    <p class="text-sm font-medium text-gray-500">Pending</p>
                    <p class="mt-2 text-2xl font-semibold text-gray-900">{{ number_format($summary['pending'], 2) }}</p>
                </div>
                <div class="rounded-lg bg-white p-6 shadow-sm">
                    <p class="text-sm font-medium text-gray-500">Batched</p>
                    <p class="mt-2 text-2xl font-semibold text-gray-900">{{ number_format($summary['batched'], 2) }}</p>
                </div>
                <div class="rounded-lg bg-white p-6 shadow-sm">
                    <p class="text-sm font-medium text-gray-500">Paid</p>
                    <p class="mt-2 text-2xl font-semibold text-gray-900">{{ number_format($summary['paid'], 2) }}</p>
                </div>
            </div>

            <div class="rounded-lg bg-white p-6 shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left">Article</th>
                                <th class="px-4 py-3 text-left">Status</th>
                                <th class="px-4 py-3 text-left">Words</th>
                                <th class="px-4 py-3 text-left">Rate</th>
                                <th class="px-4 py-3 text-left">Gross</th>
                                <th class="px-4 py-3 text-left">Approved</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($ledgers as $ledger)
                                <tr>
                                    <td class="px-4 py-3">{{ $ledger->article?->title }}</td>
                                    <td class="px-4 py-3">{{ ucfirst($ledger->status) }}</td>
                                    <td class="px-4 py-3">{{ $ledger->word_count }}</td>
                                    <td class="px-4 py-3">{{ number_format((float) $ledger->rate_per_word, 2) }}</td>
                                    <td class="px-4 py-3">{{ number_format((float) $ledger->gross_amount, 2) }}</td>
                                    <td class="px-4 py-3">{{ optional($ledger->approved_at)->format('j M Y H:i') ?: '-' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-6 text-center text-gray-500">No earnings recorded yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-6">{{ $ledgers->links() }}</div>
            </div>
        </div>
    </div>
</x-app-layout>
