<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Staff Wallets</h2>
            <a href="{{ route('admin.payout-requests.index') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700">Payout Requests</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto space-y-6 sm:px-6 lg:px-8">
            <div class="rounded-lg bg-white p-6 shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left">Staff member</th>
                                <th class="px-4 py-3 text-left">Available</th>
                                <th class="px-4 py-3 text-left">Pending</th>
                                <th class="px-4 py-3 text-left">Paid out total</th>
                                <th class="px-4 py-3 text-left">Active requests</th>
                                <th class="px-4 py-3 text-left">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($wallets as $wallet)
                                <tr>
                                    <td class="px-4 py-3">
                                        <p class="font-medium">{{ $wallet->user?->name ?: '-' }}</p>
                                        <p class="text-gray-500 text-xs">{{ $wallet->user?->email }}</p>
                                    </td>
                                    <td class="px-4 py-3 font-semibold {{ $wallet->available_balance > 0 ? 'text-green-700' : 'text-gray-500' }}">
                                        {{ $wallet->currency }} {{ number_format($wallet->available_balance, 2) }}
                                    </td>
                                    <td class="px-4 py-3 text-amber-600">
                                        {{ $wallet->currency }} {{ number_format($wallet->pending_balance, 2) }}
                                    </td>
                                    <td class="px-4 py-3 text-gray-600">
                                        {{ $wallet->currency }} {{ number_format($wallet->paid_out_total, 2) }}
                                    </td>
                                    <td class="px-4 py-3">
                                        @if ($wallet->payoutRequests->isNotEmpty())
                                            <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800">{{ $wallet->payoutRequests->count() }} active</span>
                                        @else
                                            <span class="text-gray-400 text-xs">None</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        <a class="text-indigo-600 text-sm" href="{{ route('admin.wallet.show', $wallet) }}">View</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-6 text-center text-gray-500">No staff wallets found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-6">{{ $wallets->links() }}</div>
            </div>
        </div>
    </div>
</x-app-layout>
