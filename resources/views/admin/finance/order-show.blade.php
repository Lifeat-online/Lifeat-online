<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Order {{ $order->order_number }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto space-y-6 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-3 text-sm text-green-700">{{ session('status') }}</div>
            @endif
            <div class="rounded-lg bg-white p-6 shadow-sm">
                <p><strong>Customer:</strong> {{ $order->user?->name }}</p>
                <p><strong>Status:</strong> {{ ucfirst($order->status) }}</p>
                <p><strong>Total:</strong> {{ $order->currency }} {{ number_format((float) $order->total, 2) }}</p>
                @if ($order->renewedSubscription)
                    <p><strong>Renewal For:</strong> Subscription {{ $order->renewedSubscription->id }}</p>
                @endif
            </div>

            {{-- Commission attribution ──────────────────────────────────────── --}}
            <div class="rounded-lg bg-white p-6 shadow-sm">
                <h3 class="text-base font-semibold text-gray-900">Commission Attribution</h3>
                <p class="mt-1 text-sm text-gray-500">Link a staff member to this order to credit them commission when payment is (or was) received.</p>
                <div class="mt-3 flex items-center gap-3 text-sm">
                    <span class="font-medium">Currently attributed to:</span>
                    @if ($order->referredBy)
                        <span class="rounded-full bg-indigo-100 px-3 py-1 text-indigo-800 text-xs font-semibold">{{ $order->referredBy->name }} ({{ $order->referredBy->email }})</span>
                    @else
                        <span class="text-gray-400">None</span>
                    @endif
                </div>
                @can('role:admin,editor')
                @endcan
                <form method="post" action="{{ route('admin.finance.orders.attribution', $order) }}" class="mt-4 flex flex-wrap items-end gap-3">
                    @csrf
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Staff member</label>
                        <select name="referred_by_user_id" class="rounded-md border-gray-300 text-sm">
                            <option value="">— No attribution —</option>
                            @foreach ($staffUsers as $staffUser)
                                <option value="{{ $staffUser->id }}" @selected($order->referred_by_user_id === $staffUser->id)>
                                    {{ $staffUser->name }} ({{ $staffUser->email }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm text-white">Save attribution</button>
                </form>
            </div>

            <div class="grid gap-6 lg:grid-cols-2">
                <div class="rounded-lg bg-white p-6 shadow-sm">
                    <h3 class="mb-4 text-lg font-semibold text-gray-900">Items</h3>
                    <div class="space-y-3 text-sm">
                        @foreach ($order->items as $item)
                            <div class="rounded-md bg-gray-50 p-3">
                                <p class="font-medium">{{ $item->name_snapshot }}</p>
                                <p>{{ $order->currency }} {{ number_format((float) $item->unit_price, 2) }} · Qty {{ $item->quantity }}</p>
                                <p class="text-gray-500">{{ class_basename($item->purchasable_type) }} #{{ $item->purchasable_id }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="rounded-lg bg-white p-6 shadow-sm">
                    <h3 class="mb-4 text-lg font-semibold text-gray-900">Timeline</h3>
                    <div class="space-y-3 text-sm">
                        @foreach ($order->payments as $payment)
                            <div class="rounded-md bg-gray-50 p-3">
                                <p class="font-medium">Payment {{ $payment->id }} · {{ ucfirst($payment->status) }}</p>
                                <p>{{ $payment->currency }} {{ number_format((float) $payment->amount, 2) }}</p>
                            </div>
                        @endforeach
                        @foreach ($order->invoices as $invoice)
                            <div class="rounded-md bg-gray-50 p-3">
                                <p class="font-medium">Invoice {{ $invoice->invoice_number }} · {{ ucfirst($invoice->status) }}</p>
                            </div>
                        @endforeach
                        @foreach ($notifications as $notification)
                            <div class="rounded-md bg-gray-50 p-3">
                                <p class="font-medium">{{ ucfirst(str_replace('_', ' ', $notification->notification_type)) }}</p>
                                <p class="text-gray-500">{{ $notification->recipient ?: 'No recipient' }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="rounded-lg bg-white p-6 shadow-sm">
                <h3 class="mb-4 text-lg font-semibold text-gray-900">Audit Timeline</h3>
                <div class="mb-4 flex flex-wrap gap-2 text-sm">
                    <a class="rounded-md bg-gray-100 px-3 py-1 text-gray-700" href="{{ route('admin.finance.orders.show', $order) }}">All</a>
                    <a class="rounded-md bg-gray-100 px-3 py-1 text-gray-700" href="{{ route('admin.finance.orders.show', [$order, 'timeline_source' => 'payment']) }}">Payments</a>
                    <a class="rounded-md bg-gray-100 px-3 py-1 text-gray-700" href="{{ route('admin.finance.orders.show', [$order, 'timeline_source' => 'notification']) }}">Notifications</a>
                    <a class="rounded-md bg-gray-100 px-3 py-1 text-gray-700" href="{{ route('admin.finance.orders.show', [$order, 'timeline_source' => 'audit']) }}">Admin Actions</a>
                </div>
                <form method="get" action="{{ route('admin.finance.orders.show', $order) }}" class="mb-4 grid gap-3 md:grid-cols-3">
                    <select class="rounded-md border-gray-300 text-sm" name="timeline_source">
                        <option value="">All sources</option>
                        @foreach (['lifecycle', 'payment', 'attempt', 'refund', 'invoice', 'notification', 'audit'] as $source)
                            <option value="{{ $source }}" @selected(($timelineFilters['timeline_source'] ?? '') === $source)>{{ ucfirst($source) }}</option>
                        @endforeach
                    </select>
                    <input class="rounded-md border-gray-300 text-sm" name="timeline_type" placeholder="Type" value="{{ $timelineFilters['timeline_type'] ?? '' }}">
                    <button class="rounded-md bg-indigo-600 px-4 py-2 text-sm text-white" type="submit">Filter Timeline</button>
                </form>
                <div class="space-y-3 text-sm">
                    @foreach ($timeline as $entry)
                        <div class="rounded-md bg-gray-50 p-3">
                            <p class="font-medium">{{ $entry['title'] }} <span class="text-xs text-gray-500">[{{ $entry['badge'] }}]</span></p>
                            <p>{{ $entry['detail'] ?: '-' }}</p>
                            <p class="text-gray-500">{{ optional($entry['occurred_at'])->format('j M Y H:i') ?: '-' }}</p>
                        </div>
                    @endforeach
                </div>
                <div class="mt-6">{{ $timeline->links() }}</div>
            </div>
        </div>
    </div>
</x-app-layout>
