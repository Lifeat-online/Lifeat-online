<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">My Commission Wallet</h2>
        <div class="mt-2">
            <a href="{{ route('account.wallet.statement.pdf') }}" target="_blank" rel="noopener" class="inline-flex items-center text-sm text-indigo-600 hover:text-indigo-800">
                Download PDF statement
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-5xl mx-auto space-y-6 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-3 text-sm text-green-700">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="rounded-md bg-red-50 p-3 text-sm text-red-700">
                    <ul class="list-disc pl-5 space-y-1">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                </div>
            @endif

            {{-- Balance summary ─────────────────────────────────────────── --}}
            <div class="grid gap-4 sm:grid-cols-3">
                <div class="rounded-lg bg-white p-6 shadow-sm text-center">
                    <p class="text-sm text-gray-500">Available balance</p>
                    <p class="mt-2 text-3xl font-bold text-green-700">{{ $wallet->currency }} {{ number_format($wallet->available_balance, 2) }}</p>
                </div>
                <div class="rounded-lg bg-white p-6 shadow-sm text-center">
                    <p class="text-sm text-gray-500">Pending</p>
                    <p class="mt-2 text-3xl font-bold text-amber-600">{{ $wallet->currency }} {{ number_format($wallet->pending_balance, 2) }}</p>
                </div>
                <div class="rounded-lg bg-white p-6 shadow-sm text-center">
                    <p class="text-sm text-gray-500">Total paid out</p>
                    <p class="mt-2 text-3xl font-bold text-gray-700">{{ $wallet->currency }} {{ number_format($wallet->paid_out_total, 2) }}</p>
                </div>
            </div>

            {{-- Payout request form ─────────────────────────────────────── --}}
            <div class="rounded-lg bg-white p-6 shadow-sm">
                <h3 class="text-lg font-semibold text-gray-900">Request a Payout</h3>
                @if ($pendingRequest)
                    <div class="mt-3 rounded-md bg-amber-50 p-3 text-sm text-amber-800">
                        You have an active payout request for <strong>{{ $wallet->currency }} {{ number_format($pendingRequest->amount, 2) }}</strong>
                        (status: <strong>{{ ucfirst($pendingRequest->status) }}</strong>).
                        @if ($pendingRequest->status === 'requested')
                            <form method="post" action="{{ route('account.wallet.payout-requests.cancel', $pendingRequest) }}" class="inline ml-2">
                                @csrf @method('DELETE')
                                <button class="underline text-red-700 text-sm">Cancel request</button>
                            </form>
                        @endif
                    </div>
                @elseif ($wallet->available_balance > 0)
                    <form method="post" action="{{ route('account.wallet.payout-requests.store') }}" class="mt-4 grid gap-4 md:grid-cols-2">
                        @csrf
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Amount ({{ $wallet->currency }})</label>
                            <input type="number" step="0.01" min="1" max="{{ $wallet->available_balance }}" name="amount" value="{{ old('amount', $wallet->available_balance) }}" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Bank name</label>
                            <input type="text" name="bank_name" value="{{ old('bank_name') }}" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Account holder name</label>
                            <input type="text" name="account_holder" value="{{ old('account_holder') }}" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Account number</label>
                            <input type="text" name="account_number" value="{{ old('account_number') }}" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Branch code</label>
                            <input type="text" name="branch_code" value="{{ old('branch_code') }}" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700">Notes (optional)</label>
                            <textarea name="notes" rows="2" class="mt-1 w-full rounded-md border-gray-300 text-sm">{{ old('notes') }}</textarea>
                        </div>
                        <div class="md:col-span-2">
                            <button class="rounded-md bg-indigo-600 px-5 py-2 text-sm text-white">Submit payout request</button>
                        </div>
                    </form>
                @else
                    <p class="mt-3 text-sm text-gray-500">No available balance to request a payout yet.</p>
                @endif
            </div>

            {{-- Payout history ─────────────────────────────────────────── --}}
            <div class="rounded-lg bg-white p-6 shadow-sm">
                <h3 class="text-lg font-semibold text-gray-900">Payout History</h3>
                @if ($payoutRequests->isEmpty())
                    <p class="mt-3 text-sm text-gray-500">No payout requests yet.</p>
                @else
                    <table class="mt-4 min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50"><tr>
                            <th class="px-4 py-3 text-left">Amount</th>
                            <th class="px-4 py-3 text-left">Status</th>
                            <th class="px-4 py-3 text-left">Requested</th>
                            <th class="px-4 py-3 text-left">Paid</th>
                        </tr></thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($payoutRequests as $pr)
                                <tr>
                                    <td class="px-4 py-3 font-medium">{{ $pr->currency }} {{ number_format($pr->amount, 2) }}</td>
                                    <td class="px-4 py-3"><span class="{{ $pr->status === 'paid' ? 'text-green-700' : ($pr->status === 'rejected' ? 'text-red-600' : 'text-amber-700') }}">{{ ucfirst($pr->status) }}</span></td>
                                    <td class="px-4 py-3 text-gray-600">{{ optional($pr->requested_at)->format('j M Y') ?: '-' }}</td>
                                    <td class="px-4 py-3 text-gray-600">{{ optional($pr->paid_at)->format('j M Y') ?: '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>

            {{-- Commission ledger ─────────────────────────────────────── --}}
            <div class="rounded-lg bg-white p-6 shadow-sm">
                <h3 class="text-lg font-semibold text-gray-900">Commission Ledger</h3>
                @if ($ledgerEntries->isEmpty())
                    <p class="mt-3 text-sm text-gray-500">No commission entries yet.</p>
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
                                    <td class="px-4 py-3 text-gray-600">{{ $entry->recorded_at->format('j M Y') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
