<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Payout Request #{{ $payout->id }}</h2>
                <p class="mt-1 text-sm text-gray-500">{{ $payout->requestedBy?->name }} — {{ $payout->currency }} {{ number_format($payout->amount, 2) }}</p>
            </div>
            <a href="{{ route('admin.payout-requests.index') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700">Back to list</a>
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

            <div class="grid gap-6 lg:grid-cols-3">
                {{-- Payout details ─────────────────────────────────────── --}}
                <div class="space-y-6 lg:col-span-2">
                    <div class="rounded-lg bg-white p-6 shadow-sm">
                        <h3 class="text-lg font-semibold text-gray-900">Payout Details</h3>
                        <div class="mt-4 grid gap-4 md:grid-cols-2 text-sm">
                            <div><p class="text-gray-500">Staff member</p><p class="font-medium">{{ $payout->requestedBy?->name }}</p></div>
                            <div><p class="text-gray-500">Email</p><p class="font-medium">{{ $payout->requestedBy?->email }}</p></div>
                            <div><p class="text-gray-500">Amount</p><p class="font-medium text-lg">{{ $payout->currency }} {{ number_format($payout->amount, 2) }}</p></div>
                            <div><p class="text-gray-500">Status</p>
                                <span class="inline-block rounded-full px-3 py-1 text-xs font-semibold
                                    @if($payout->status === 'paid') bg-green-100 text-green-800
                                    @elseif($payout->status === 'rejected') bg-red-100 text-red-800
                                    @elseif($payout->status === 'requested') bg-amber-100 text-amber-800
                                    @elseif($payout->status === 'approved') bg-indigo-100 text-indigo-800
                                    @else bg-gray-100 text-gray-600 @endif">
                                    {{ ucfirst($payout->status) }}
                                </span>
                            </div>
                            <div><p class="text-gray-500">Bank</p><p class="font-medium">{{ $payout->bank_name ?: '-' }}</p></div>
                            <div><p class="text-gray-500">Account holder</p><p class="font-medium">{{ $payout->account_holder ?: '-' }}</p></div>
                            <div><p class="text-gray-500">Account number</p><p class="font-medium">{{ $payout->account_number ?: '-' }}</p></div>
                            <div><p class="text-gray-500">Branch code</p><p class="font-medium">{{ $payout->branch_code ?: '-' }}</p></div>
                            @if ($payout->payment_reference)
                                <div class="md:col-span-2"><p class="text-gray-500">Payment reference</p><p class="font-medium">{{ $payout->payment_reference }}</p></div>
                            @endif
                            @if ($payout->notes)
                                <div class="md:col-span-2"><p class="text-gray-500">Notes</p><p class="whitespace-pre-line text-gray-700">{{ $payout->notes }}</p></div>
                            @endif
                            <div><p class="text-gray-500">Requested at</p><p class="font-medium">{{ optional($payout->requested_at)->format('j M Y H:i') ?: '-' }}</p></div>
                            <div><p class="text-gray-500">Reviewed at</p><p class="font-medium">{{ optional($payout->reviewed_at)->format('j M Y H:i') ?: '-' }}</p></div>
                            <div><p class="text-gray-500">Paid at</p><p class="font-medium">{{ optional($payout->paid_at)->format('j M Y H:i') ?: '-' }}</p></div>
                            <div><p class="text-gray-500">Reviewed by</p><p class="font-medium">{{ $payout->reviewedBy?->name ?: '-' }}</p></div>
                        </div>
                    </div>

                    @if ($payout->ledgerEntries->isNotEmpty())
                        <div class="rounded-lg bg-white p-6 shadow-sm">
                            <h3 class="text-lg font-semibold text-gray-900">Ledger Entries</h3>
                            <table class="mt-4 min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-gray-50"><tr>
                                    <th class="px-4 py-3 text-left">Type</th>
                                    <th class="px-4 py-3 text-left">Amount</th>
                                    <th class="px-4 py-3 text-left">Recorded</th>
                                </tr></thead>
                                <tbody class="divide-y divide-gray-100">
                                    @foreach ($payout->ledgerEntries as $entry)
                                        <tr>
                                            <td class="px-4 py-3">{{ ucwords(str_replace('_', ' ', $entry->entry_type)) }}</td>
                                            <td class="px-4 py-3">{{ $entry->currency }} {{ number_format($entry->net_amount, 2) }}</td>
                                            <td class="px-4 py-3 text-gray-600">{{ $entry->recorded_at->format('j M Y H:i') }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>

                {{-- Admin action sidebar ─────────────────────────────── --}}
                <div class="space-y-4">
                    @if ($payout->status === 'requested')
                        <div class="rounded-lg bg-white p-6 shadow-sm space-y-3">
                            <h3 class="text-lg font-semibold text-gray-900">Actions</h3>
                            <form method="post" action="{{ route('admin.payout-requests.approve', $payout) }}">
                                @csrf
                                <button class="w-full rounded-md bg-green-600 px-4 py-2 text-sm text-white">Approve</button>
                            </form>
                            <form method="post" action="{{ route('admin.payout-requests.reject', $payout) }}">
                                @csrf
                                <textarea name="notes" rows="2" placeholder="Reason for rejection (optional)" class="w-full rounded-md border-gray-300 text-sm mb-2">{{ old('notes') }}</textarea>
                                <button class="w-full rounded-md bg-red-600 px-4 py-2 text-sm text-white">Reject</button>
                            </form>
                        </div>
                    @elseif ($payout->status === 'approved')
                        <div class="rounded-lg bg-white p-6 shadow-sm space-y-3">
                            <h3 class="text-lg font-semibold text-gray-900">Mark as Paid</h3>
                            <p class="text-sm text-gray-500">Once payment has been made via EFT, enter the reference and confirm below.</p>
                            <form method="post" action="{{ route('admin.payout-requests.mark-paid', $payout) }}">
                                @csrf
                                <input type="text" name="payment_reference" placeholder="EFT / payment reference" class="w-full rounded-md border-gray-300 text-sm mb-3" value="{{ old('payment_reference') }}">
                                <button class="w-full rounded-md bg-indigo-600 px-4 py-2 text-sm text-white">Mark Paid & Debit Wallet</button>
                            </form>
                        </div>
                    @else
                        <div class="rounded-lg bg-white p-6 shadow-sm">
                            <p class="text-sm text-gray-500">No further actions — this request is <strong>{{ $payout->status }}</strong>.</p>
                        </div>
                    @endif

                    <div class="rounded-lg bg-white p-6 shadow-sm text-sm space-y-1">
                        <h3 class="font-semibold text-gray-900">Wallet Summary</h3>
                        <p class="text-gray-500">Available: <span class="font-medium text-green-700">{{ $payout->wallet?->currency }} {{ number_format($payout->wallet?->available_balance ?? 0, 2) }}</span></p>
                        <p class="text-gray-500">Paid out: {{ $payout->wallet?->currency }} {{ number_format($payout->wallet?->paid_out_total ?? 0, 2) }}</p>
                        <a href="{{ route('admin.wallet.show', $payout->wallet) }}" class="text-indigo-600">Full wallet detail →</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
