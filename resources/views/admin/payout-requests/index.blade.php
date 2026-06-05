<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Payout Requests
                @if ($pendingCount > 0)
                    <span class="ml-2 rounded-full bg-amber-100 px-2 py-0.5 text-sm font-semibold text-amber-800">{{ $pendingCount }} pending</span>
                @endif
            </h2>
            <div class="flex flex-wrap items-center gap-3">
                @can('export', App\Models\PayoutRequest::class)
                    <a href="{{ route('admin.payout-requests.export', request()->only(['status', 'wallet'])) }}" class="rounded-md bg-indigo-600 px-4 py-2 text-sm text-white">Export CSV</a>
                @endcan
                <a href="{{ route('admin.wallet.index') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700">Staff Wallets</a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto space-y-6 sm:px-6 lg:px-8">
            <form method="get" class="flex flex-wrap gap-4 rounded-lg bg-white p-4 shadow-sm">
                @if ($selectedWalletId)
                    <input type="hidden" name="wallet" value="{{ $selectedWalletId }}">
                @endif
                <select name="status" class="rounded-md border-gray-300 text-sm">
                    <option value="">All statuses</option>
                    @foreach ($statusOptions as $opt)
                        <option value="{{ $opt }}" @selected($selectedStatus === $opt)>{{ ucfirst($opt) }}</option>
                    @endforeach
                </select>
                <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm text-white">Filter</button>
                @if ($selectedStatus || $selectedWalletId)
                    <a href="{{ route('admin.payout-requests.index') }}" class="text-sm text-gray-600 self-center">Clear</a>
                @endif
                @if ($selectedWalletId)
                    <span class="self-center rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">Wallet #{{ $selectedWalletId }}</span>
                @endif
            </form>

            <div class="rounded-lg bg-white p-6 shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left">Staff member</th>
                                <th class="px-4 py-3 text-left">Amount</th>
                                <th class="px-4 py-3 text-left">Status</th>
                                <th class="px-4 py-3 text-left">Bank</th>
                                <th class="px-4 py-3 text-left">Requested</th>
                                <th class="px-4 py-3 text-left">Reviewed</th>
                                <th class="px-4 py-3 text-left">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($requests as $payout)
                                <tr>
                                    <td class="px-4 py-3">
                                        <p class="font-medium">{{ $payout->wallet?->user?->name ?: '-' }}</p>
                                        <p class="text-xs text-gray-500">{{ $payout->wallet?->user?->email }}</p>
                                    </td>
                                    <td class="px-4 py-3 font-semibold">{{ $payout->currency }} {{ number_format($payout->amount, 2) }}</td>
                                    <td class="px-4 py-3">
                                        <span class="@if($payout->status === 'paid') text-green-700 @elseif($payout->status === 'rejected') text-red-600 @elseif($payout->status === 'requested') text-amber-700 font-semibold @else text-gray-500 @endif">
                                            {{ ucfirst($payout->status) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-gray-600">{{ $payout->bank_name ?: '-' }}</td>
                                    <td class="px-4 py-3 text-gray-600">{{ optional($payout->requested_at)->format('j M Y') ?: '-' }}</td>
                                    <td class="px-4 py-3 text-gray-600">{{ optional($payout->reviewed_at)->format('j M Y') ?: '-' }}</td>
                                    <td class="px-4 py-3"><a class="text-indigo-600" href="{{ route('admin.payout-requests.show', $payout) }}">Review</a></td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-6 text-center text-gray-500">No payout requests found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-6">{{ $requests->links() }}</div>
            </div>
        </div>
    </div>
</x-app-layout>
