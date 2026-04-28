<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Writer Payments</h2>
            <form method="post" action="{{ route('admin.writer-payments.batches.store') }}">
                @csrf
                <button class="rounded-md bg-indigo-600 px-4 py-2 text-white" type="submit">Create Batch From Pending</button>
            </form>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto space-y-6 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-3 text-sm text-green-700">{{ session('status') }}</div>
            @endif

            <div class="rounded-lg bg-white p-6 shadow-sm">
                <h3 class="mb-4 text-lg font-semibold text-gray-900">Pending Ledger Entries</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left">Writer</th>
                                <th class="px-4 py-3 text-left">Article</th>
                                <th class="px-4 py-3 text-left">Words</th>
                                <th class="px-4 py-3 text-left">Rate</th>
                                <th class="px-4 py-3 text-left">Gross</th>
                                <th class="px-4 py-3 text-left">Approved</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($pendingLedgers as $ledger)
                                <tr>
                                    <td class="px-4 py-3">{{ $ledger->writer?->name }}</td>
                                    <td class="px-4 py-3">{{ $ledger->article?->title }}</td>
                                    <td class="px-4 py-3">{{ $ledger->word_count }}</td>
                                    <td class="px-4 py-3">{{ number_format((float) $ledger->rate_per_word, 2) }}</td>
                                    <td class="px-4 py-3">{{ number_format((float) $ledger->gross_amount, 2) }}</td>
                                    <td class="px-4 py-3">{{ optional($ledger->approved_at)->format('j M Y H:i') ?: '-' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-6 text-center text-gray-500">No pending ledger entries.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="rounded-lg bg-white p-6 shadow-sm">
                <h3 class="mb-4 text-lg font-semibold text-gray-900">Payment Batches</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left">Reference</th>
                                <th class="px-4 py-3 text-left">Status</th>
                                <th class="px-4 py-3 text-left">Items</th>
                                <th class="px-4 py-3 text-left">Gross</th>
                                <th class="px-4 py-3 text-left">Created By</th>
                                <th class="px-4 py-3 text-left">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($batches as $batch)
                                <tr>
                                    <td class="px-4 py-3">{{ $batch->reference }}</td>
                                    <td class="px-4 py-3">{{ ucfirst($batch->status) }}</td>
                                    <td class="px-4 py-3">{{ $batch->item_count }}</td>
                                    <td class="px-4 py-3">{{ number_format((float) $batch->gross_amount, 2) }}</td>
                                    <td class="px-4 py-3">{{ $batch->creator?->name }}</td>
                                    <td class="px-4 py-3">
                                        <div class="flex flex-wrap gap-3">
                                            <a href="{{ route('admin.writer-payments.batches.export', $batch) }}" class="text-indigo-600">Export CSV</a>
                                            @if ($batch->status !== 'paid')
                                                <form method="post" action="{{ route('admin.writer-payments.batches.mark-paid', $batch) }}">
                                                    @csrf
                                                    <button class="text-green-600" type="submit">Mark Paid</button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-6 text-center text-gray-500">No payment batches created yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-6">{{ $batches->links() }}</div>
            </div>
        </div>
    </div>
</x-app-layout>
