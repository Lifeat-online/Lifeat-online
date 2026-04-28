<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Wallet: {{ $wallet->user?->name }}</h2>
                <p class="mt-1 text-sm text-gray-500">{{ $wallet->user?->email }}</p>
            </div>
            <a href="{{ route('admin.wallet.index') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700">Back to wallets</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-5xl mx-auto space-y-6 sm:px-6 lg:px-8">
            <div class="grid gap-4 sm:grid-cols-3">
                <div class="rounded-lg bg-white p-6 shadow-sm text-center">
                    <p class="text-sm text-gray-500">Available</p>
                    <p class="mt-2 text-3xl font-bold text-green-700">{{ $wallet->currency }} {{ number_format($wallet->available_balance, 2) }}</p>
                </div>
                <div class="rounded-lg bg-white p-6 shadow-sm text-center">
                    <p class="text-sm text-gray-500">Pending</p>
                    <p class="mt-2 text-3xl font-bold text-amber-600">{{ $wallet->currency }} {{ number_format($wallet->pending_balance, 2) }}</p>
                </div>
                <div class="rounded-lg bg-white p-6 shadow-sm text-center">
                    <p class="text-sm text-gray-500">Paid out total</p>
                    <p class="mt-2 text-3xl font-bold text-gray-700">{{ $wallet->currency }} {{ number_format($wallet->paid_out_total, 2) }}</p>
                </div>
            </div>

            <div class="rounded-lg bg-white p-6 shadow-sm">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-900">Payout Requests</h3>
                    <a href="{{ route('admin.payout-requests.index', ['wallet' => $wallet->id]) }}" class="text-sm text-indigo-600">View all</a>
                </div>
                @if ($payoutRequests->isEmpty())
                    <p class="mt-3 text-sm text-gray-500">No payout requests.</p>
                @else
                    <table class="mt-4 min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50"><tr>
                            <th class="px-4 py-3 text-left">Amount</th>
                            <th class="px-4 py-3 text-left">Status</th>
                            <th class="px-4 py-3 text-left">Requested</th>
                            <th class="px-4 py-3 text-left">Actions</th>
                        </tr></thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($payoutRequests as $pr)
                                <tr>
                                    <td class="px-4 py-3 font-medium">{{ $pr->currency }} {{ number_format($pr->amount, 2) }}</td>
                                    <td class="px-4 py-3"><span class="{{ $pr->status === 'paid' ? 'text-green-700' : ($pr->status === 'rejected' ? 'text-red-600' : 'text-amber-700') }}">{{ ucfirst($pr->status) }}</span></td>
                                    <td class="px-4 py-3 text-gray-600">{{ optional($pr->requested_at)->format('j M Y') ?: '-' }}</td>
                                    <td class="px-4 py-3"><a class="text-indigo-600" href="{{ route('admin.payout-requests.show', $pr) }}">Review</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>

            <div class="rounded-lg bg-white p-6 shadow-sm">
                <h3 class="text-lg font-semibold text-gray-900">Commission Ledger</h3>
                @if ($ledgerEntries->isEmpty())
                    <p class="mt-3 text-sm text-gray-500">No ledger entries.</p>
                @else
                    <table class="mt-4 min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50"><tr>
                            <th class="px-4 py-3 text-left">Type</th>
                            <th class="px-4 py-3 text-left">Amount</th>
                            <th class="px-4 py-3 text-left">Description</th>
                            <th class="px-4 py-3 text-left">Date</th>
                        </tr></thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($ledgerEntries as $entry)
                                <tr>
                                    <td class="px-4 py-3"><span class="{{ $entry->entry_type === 'commission_credit' ? 'text-green-700' : 'text-red-600' }}">{{ ucwords(str_replace('_', ' ', $entry->entry_type)) }}</span></td>
                                    <td class="px-4 py-3 font-medium">{{ $entry->currency }} {{ number_format($entry->net_amount, 2) }}</td>
                                    <td class="px-4 py-3 text-gray-600">{{ $entry->description ?: '-' }}</td>
                                    <td class="px-4 py-3 text-gray-600">{{ $entry->recorded_at->format('j M Y H:i') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
