<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Finance Dashboard</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto space-y-6 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-3 text-sm text-green-700">{{ session('status') }}</div>
            @endif

            <div class="flex flex-wrap gap-3">
                @if (auth()->user()->hasRole('admin', 'editor'))
                    <a href="{{ route('admin.finance.export', 'orders') }}" class="rounded-md bg-gray-200 px-4 py-2 text-sm text-gray-800">Export Orders</a>
                    <a href="{{ route('admin.finance.export', 'payments') }}" class="rounded-md bg-gray-200 px-4 py-2 text-sm text-gray-800">Export Payments</a>
                    <a href="{{ route('admin.finance.export', 'invoices') }}" class="rounded-md bg-gray-200 px-4 py-2 text-sm text-gray-800">Export Invoices</a>
                    <a href="{{ route('admin.finance.export', 'subscriptions') }}" class="rounded-md bg-gray-200 px-4 py-2 text-sm text-gray-800">Export Subscriptions</a>
                @endif
                <a href="{{ route('admin.finance.orders.index') }}" class="rounded-md bg-indigo-50 px-4 py-2 text-sm text-indigo-700">View All Orders</a>
                <a href="{{ route('admin.finance.payments.index') }}" class="rounded-md bg-indigo-50 px-4 py-2 text-sm text-indigo-700">View All Payments</a>
                <a href="{{ route('admin.finance.subscriptions.index') }}" class="rounded-md bg-indigo-50 px-4 py-2 text-sm text-indigo-700">View All Subscriptions</a>
                <a href="{{ route('admin.finance.notifications.index') }}" class="rounded-md bg-indigo-50 px-4 py-2 text-sm text-indigo-700">View Notifications</a>
            </div>

            <form method="get" action="{{ route('admin.finance.index') }}" class="grid gap-4 rounded-lg bg-white p-4 shadow-sm md:grid-cols-4">
                <select class="rounded-md border-gray-300 text-sm" name="order_status">
                    <option value="">All order statuses</option>
                    @foreach (['pending_payment', 'paid', 'cancelled', 'refunded'] as $status)
                        <option value="{{ $status }}" @selected(($filters['orderStatus'] ?? '') === $status)>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
                    @endforeach
                </select>
                <select class="rounded-md border-gray-300 text-sm" name="payment_status">
                    <option value="">All payment statuses</option>
                    @foreach (['pending', 'paid', 'failed', 'refunded'] as $status)
                        <option value="{{ $status }}" @selected(($filters['paymentStatus'] ?? '') === $status)>{{ ucfirst($status) }}</option>
                    @endforeach
                </select>
                <select class="rounded-md border-gray-300 text-sm" name="subscription_status">
                    <option value="">All subscription statuses</option>
                    @foreach (['active', 'pending', 'suspended', 'expired'] as $status)
                        <option value="{{ $status }}" @selected(($filters['subscriptionStatus'] ?? '') === $status)>{{ ucfirst($status) }}</option>
                    @endforeach
                </select>
                <button class="rounded-md bg-indigo-600 px-4 py-2 text-sm text-white" type="submit">Apply Filters</button>
            </form>

            <div class="grid gap-4 md:grid-cols-4">
                <div class="rounded-lg bg-white p-6 shadow-sm">
                    <p class="text-sm font-medium text-gray-500">Pending Orders</p>
                    <p class="mt-2 text-2xl font-semibold text-gray-900">{{ $summary['pending_orders'] }}</p>
                </div>
                <div class="rounded-lg bg-white p-6 shadow-sm">
                    <p class="text-sm font-medium text-gray-500">Paid Orders</p>
                    <p class="mt-2 text-2xl font-semibold text-gray-900">{{ $summary['paid_orders'] }}</p>
                </div>
                <div class="rounded-lg bg-white p-6 shadow-sm">
                    <p class="text-sm font-medium text-gray-500">Paid Revenue</p>
                    <p class="mt-2 text-2xl font-semibold text-gray-900">ZAR {{ number_format($summary['paid_revenue'], 2) }}</p>
                </div>
                <div class="rounded-lg bg-white p-6 shadow-sm">
                    <p class="text-sm font-medium text-gray-500">Active Subscriptions</p>
                    <p class="mt-2 text-2xl font-semibold text-gray-900">{{ $summary['active_subscriptions'] }}</p>
                </div>
            </div>

            <div class="rounded-lg bg-white p-6 shadow-sm">
                <h3 class="mb-4 text-lg font-semibold text-gray-900">Recent Orders</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left">Order</th>
                                <th class="px-4 py-3 text-left">Customer</th>
                                <th class="px-4 py-3 text-left">Status</th>
                                <th class="px-4 py-3 text-left">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($orders as $order)
                                <tr>
                                    <td class="px-4 py-3">{{ $order->order_number }}</td>
                                    <td class="px-4 py-3">{{ $order->user?->name }}</td>
                                    <td class="px-4 py-3">{{ ucfirst($order->status) }}</td>
                                    <td class="px-4 py-3">{{ $order->currency }} {{ number_format((float) $order->total, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-2">
                <div class="rounded-lg bg-white p-6 shadow-sm">
                    <h3 class="mb-4 text-lg font-semibold text-gray-900">Recent Payments</h3>
                    <div class="space-y-3 text-sm">
                        @foreach ($payments as $payment)
                            <div class="rounded-md bg-gray-50 p-3">
                                <p class="font-medium">{{ $payment->order?->order_number }} · {{ ucfirst($payment->status) }}</p>
                                <p>{{ $payment->currency }} {{ number_format((float) $payment->amount, 2) }}</p>
                                <p class="text-gray-500">Attempts: {{ $payment->attempts->count() }}</p>
                                @if (auth()->user()->hasRole('admin', 'editor'))
                                    <div class="mt-3 flex flex-wrap gap-3">
                                        @if ($payment->status !== 'paid')
                                            <form method="post" action="{{ route('admin.finance.payments.mark-paid', $payment) }}">
                                                @csrf
                                                <button class="text-green-600" type="submit">Mark Paid</button>
                                            </form>
                                        @endif
                                        @if (! in_array($payment->status, ['failed', 'refunded'], true))
                                            <form method="post" action="{{ route('admin.finance.payments.mark-failed', $payment) }}">
                                                @csrf
                                                <button class="text-amber-600" type="submit">Mark Failed</button>
                                            </form>
                                        @endif
                                        @if (auth()->user()->hasRole('admin') && $payment->status === 'paid')
                                            <form method="post" action="{{ route('admin.finance.payments.refunds.store', $payment) }}" class="flex flex-wrap items-center gap-2">
                                                @csrf
                                                <input class="rounded-md border-gray-300 text-xs" type="number" name="refund_amount" min="0.01" step="0.01" placeholder="Amount">
                                                <input class="rounded-md border-gray-300 text-xs" type="text" name="refund_reason" placeholder="Reason">
                                                <button class="text-red-600" type="submit">Record Refund</button>
                                            </form>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="rounded-lg bg-white p-6 shadow-sm">
                    <h3 class="mb-4 text-lg font-semibold text-gray-900">Recent Invoices</h3>
                    <div class="space-y-3 text-sm">
                        @foreach ($invoices as $invoice)
                            <div class="rounded-md bg-gray-50 p-3">
                                <p class="font-medium">{{ $invoice->invoice_number }} · {{ ucfirst($invoice->status) }}</p>
                                <p>{{ $invoice->currency }} {{ number_format((float) $invoice->total, 2) }}</p>
                                <p class="text-gray-500">{{ $invoice->order?->user?->name }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="rounded-lg bg-white p-6 shadow-sm">
                <h3 class="mb-4 text-lg font-semibold text-gray-900">Recent Subscriptions</h3>
                <div class="space-y-3 text-sm">
                    @foreach ($subscriptions as $subscription)
                        <div class="rounded-md bg-gray-50 p-3">
                            <p class="font-medium">{{ $subscription->package?->name }} · {{ ucfirst($subscription->status) }}</p>
                            <p>{{ $subscription->user?->name }}</p>
                            <p class="text-gray-500">Ends: {{ optional($subscription->ends_at)->format('j M Y') ?: '-' }}</p>
                            @if (auth()->user()->hasRole('admin', 'editor'))
                                <div class="mt-3 flex flex-wrap gap-3">
                                    <form method="post" action="{{ route('admin.finance.subscriptions.extend', $subscription) }}" class="flex items-center gap-2">
                                        @csrf
                                        <input class="rounded-md border-gray-300 text-xs" type="number" name="extension_days" min="1" value="30">
                                        <button class="text-indigo-600" type="submit">Extend</button>
                                    </form>
                                    @if (auth()->user()->hasRole('admin') && $subscription->status !== 'suspended')
                                        <form method="post" action="{{ route('admin.finance.subscriptions.suspend', $subscription) }}">
                                            @csrf
                                            <button class="text-red-600" type="submit">Suspend</button>
                                        </form>
                                    @endif
                                    <form method="post" action="{{ route('admin.finance.subscriptions.reminder', $subscription) }}">
                                        @csrf
                                        <button class="text-amber-600" type="submit">Log Reminder</button>
                                    </form>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="rounded-lg bg-white p-6 shadow-sm">
                <h3 class="mb-4 text-lg font-semibold text-gray-900">Recent Refunds</h3>
                <div class="space-y-3 text-sm">
                    @forelse ($refunds as $refund)
                        <div class="rounded-md bg-gray-50 p-3">
                            <p class="font-medium">{{ $refund->payment?->order?->order_number }} · {{ ucfirst($refund->status) }}</p>
                            <p>{{ number_format((float) $refund->amount, 2) }}</p>
                            <p class="text-gray-500">{{ $refund->reason ?: 'No reason provided' }}</p>
                        </div>
                    @empty
                        <p class="text-gray-500">No refunds recorded yet.</p>
                    @endforelse
                </div>
            </div>

            <div class="rounded-lg bg-white p-6 shadow-sm">
                <h3 class="mb-4 text-lg font-semibold text-gray-900">Recent Subscription Reminders</h3>
                <div class="space-y-3 text-sm">
                    @forelse ($reminders as $reminder)
                        <div class="rounded-md bg-gray-50 p-3">
                            <p class="font-medium">{{ $reminder->subscription?->package?->name }} · {{ ucfirst(str_replace('_', ' ', $reminder->reminder_type)) }}</p>
                            <p>{{ $reminder->subscription?->user?->name }}</p>
                            <p class="text-gray-500">{{ optional($reminder->sent_at)->format('j M Y H:i') ?: '-' }}</p>
                        </div>
                    @empty
                        <p class="text-gray-500">No reminders logged yet.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
