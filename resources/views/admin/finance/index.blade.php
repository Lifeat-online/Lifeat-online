<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Finance Dashboard</h2>
    </x-slot>

    <style>
        .dash-card {
            background: #ffffff;
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.05);
            border: 1px solid #f1f5f9;
        }
        .section-title {
            font-size: 1.125rem;
            font-weight: 800;
            color: #1e293b;
            margin-bottom: 1.25rem;
            letter-spacing: -0.02em;
        }
        .btn-mgmt {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.6rem 1.15rem;
            border-radius: 10px;
            font-size: 0.85rem;
            font-weight: 700;
            text-decoration: none;
            transition: all 0.2s ease;
            border: 0;
            cursor: pointer;
        }
        .btn-slate {
            background: linear-gradient(135deg, #475569, #1e293b);
            color: #ffffff !important;
        }
        .btn-indigo {
            background: linear-gradient(135deg, #6366f1, #4f46e5);
            color: #ffffff !important;
        }
        .btn-soft {
            background: #f1f5f9;
            color: #475569 !important;
            border: 1px solid #e2e8f0;
        }
        .stat-grid {
            display: grid;
            gap: 1.5rem;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        }
        .stat-card {
            background: #ffffff;
            padding: 1.5rem;
            border-radius: 16px;
            border: 1px solid #f1f5f9;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02);
            transition: transform 0.2s;
        }
        .stat-card:hover { transform: translateY(-2px); }
        .stat-label { color: #64748b; font-size: 0.85rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.025em; }
        .stat-value { color: #0f172a; font-size: 1.75rem; font-weight: 800; margin-top: 0.5rem; }
        .stat-value.highlight { color: #059669; }

        .finance-table { width: 100%; border-collapse: separate; border-spacing: 0; }
        .finance-table th { padding: 1rem; background: #f8fafc; color: #64748b; font-weight: 700; text-align: left; font-size: 0.75rem; text-transform: uppercase; }
        .finance-table td { padding: 1rem; border-bottom: 1px solid #f1f5f9; color: #334155; }
        .finance-table tr:hover td { background: #fdfdfd; }

        .item-row {
            background: #f8fafc;
            border-radius: 12px;
            padding: 1.25rem;
            border: 1px solid #eef2f6;
            margin-bottom: 1rem;
        }
        .item-title { font-weight: 700; color: #1e293b; font-size: 0.95rem; }
        .item-meta { color: #64748b; font-size: 0.85rem; margin-top: 0.25rem; }
        .status-badge {
            display: inline-flex;
            padding: 0.25rem 0.625rem;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 700;
            background: #e2e8f0;
            color: #475569;
        }
        .status-paid { background: #dcfce7; color: #166534; }
        .status-pending { background: #fef9c3; color: #854d0e; }

        html[data-theme="dark"] .dash-card,
        html[data-theme="dark"] .stat-card,
        html[data-theme="dark"] .item-row {
            background: #111827;
            border-color: #334155;
        }
        html[data-theme="dark"] .finance-table th { background: #1e293b; color: #94a3b8; }
        html[data-theme="dark"] .finance-table td { color: #e2e8f0; border-color: #1f2937; }
        html[data-theme="dark"] .section-title,
        html[data-theme="dark"] .item-title,
        html[data-theme="dark"] .stat-value { color: #f8fafc; }
        html[data-theme="dark"] .btn-soft {
            background: #1e293b;
            color: #f1f5f9 !important;
            border-color: #334155;
        }
        html[data-theme="dark"] select, 
        html[data-theme="dark"] input {
            background: #0f172a !important;
            color: #f8fafc !important;
            border-color: #334155 !important;
        }
    </style>

    <div class="py-12">
        <div class="max-w-7xl mx-auto space-y-8 sm:px-6 lg:px-8">
            @if (session('status'))
                <div style="background: #dcfce7; color: #166534; padding: 1rem; border-radius: 12px; font-weight: 600;">{{ session('status') }}</div>
            @endif

            <div class="flex flex-wrap gap-3">
                @if (auth()->user()->hasRole('admin', 'editor'))
                    <a href="{{ route('admin.finance.export', 'orders') }}" class="btn-mgmt btn-soft">Export Orders</a>
                    <a href="{{ route('admin.finance.export', 'payments') }}" class="btn-mgmt btn-soft">Export Payments</a>
                    <a href="{{ route('admin.finance.export', 'subscriptions') }}" class="btn-mgmt btn-soft">Export Subscriptions</a>
                @endif
                <div style="flex-grow: 1;"></div>
                <a href="{{ route('admin.finance.orders.index') }}" class="btn-mgmt btn-indigo">View All Orders</a>
                <a href="{{ route('admin.finance.payments.index') }}" class="btn-mgmt btn-indigo">View All Payments</a>
                <a href="{{ route('admin.finance.subscriptions.index') }}" class="btn-mgmt btn-indigo">View All Subscriptions</a>
            </div>

            <div class="stat-grid">
                <div class="stat-card">
                    <p class="stat-label">Paid Revenue</p>
                    <p class="stat-value highlight">ZAR {{ number_format($summary['paid_revenue'], 2) }}</p>
                </div>
                <div class="stat-card">
                    <p class="stat-label">Paid Orders</p>
                    <p class="stat-value">{{ $summary['paid_orders'] }}</p>
                </div>
                <div class="stat-card">
                    <p class="stat-label">Pending Orders</p>
                    <p class="stat-value" style="color: #d97706;">{{ $summary['pending_orders'] }}</p>
                </div>
                <div class="stat-card">
                    <p class="stat-label">Active Subscriptions</p>
                    <p class="stat-value">{{ $summary['active_subscriptions'] }}</p>
                </div>
            </div>

            <div class="dash-card">
                <h3 class="section-title">Filters</h3>
                <form method="get" action="{{ route('admin.finance.index') }}" class="grid gap-4 md:grid-cols-4">
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
                    <button class="btn-mgmt btn-slate" type="submit">Apply Filters</button>
                </form>
            </div>

            <div class="dash-card">
                <h3 class="section-title">Recent Orders</h3>
                <div class="overflow-x-auto">
                    <table class="finance-table">
                        <thead>
                            <tr>
                                <th>Order #</th>
                                <th>Customer</th>
                                <th>Status</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($orders as $order)
                                <tr>
                                    <td style="font-weight: 700;">{{ $order->order_number }}</td>
                                    <td>{{ $order->user?->name }}</td>
                                    <td>
                                        <span class="status-badge {{ $order->status === 'paid' ? 'status-paid' : ($order->status === 'pending_payment' ? 'status-pending' : '') }}">
                                            {{ ucfirst(str_replace('_', ' ', $order->status)) }}
                                        </span>
                                    </td>
                                    <td style="font-weight: 700; color: #1e293b;">{{ $order->currency }} {{ number_format((float) $order->total, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="grid gap-8 lg:grid-cols-2">
                <div class="dash-card">
                    <h3 class="section-title">Recent Payments</h3>
                    <div class="space-y-4">
                        @foreach ($payments as $payment)
                            <div class="item-row">
                                <div class="flex items-center justify-between">
                                    <p class="item-title">{{ $payment->order?->order_number }}</p>
                                    <span class="status-badge {{ $payment->status === 'paid' ? 'status-paid' : '' }}">{{ ucfirst($payment->status) }}</span>
                                </div>
                                <p class="item-meta">{{ $payment->currency }} {{ number_format((float) $payment->amount, 2) }}</p>
                                @if (auth()->user()->hasRole('admin', 'editor'))
                                    <div class="mt-4 flex flex-wrap gap-3">
                                        @if ($payment->status !== 'paid')
                                            <form method="post" action="{{ route('admin.finance.payments.mark-paid', $payment) }}">
                                                @csrf
                                                <button class="text-sm font-bold text-green-600 hover:underline" type="submit">Mark Paid</button>
                                            </form>
                                        @endif
                                        @if (! in_array($payment->status, ['failed', 'refunded'], true))
                                            <form method="post" action="{{ route('admin.finance.payments.mark-failed', $payment) }}">
                                                @csrf
                                                <button class="text-sm font-bold text-amber-600 hover:underline" type="submit">Mark Failed</button>
                                            </form>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="dash-card">
                    <h3 class="section-title">Recent Subscriptions</h3>
                    <div class="space-y-4">
                        @foreach ($subscriptions as $subscription)
                            <div class="item-row">
                                <div class="flex items-center justify-between">
                                    <p class="item-title">{{ $subscription->package?->name }}</p>
                                    <span class="status-badge {{ $subscription->status === 'active' ? 'status-paid' : '' }}">{{ ucfirst($subscription->status) }}</span>
                                </div>
                                <p class="item-meta">Customer: {{ $subscription->user?->name }}</p>
                                <p class="item-meta">Ends: {{ optional($subscription->ends_at)->format('j M Y') ?: '-' }}</p>
                                @if (auth()->user()->hasRole('admin', 'editor'))
                                    <div class="mt-4 flex flex-wrap gap-3">
                                        <form method="post" action="{{ route('admin.finance.subscriptions.extend', $subscription) }}" class="flex items-center gap-2">
                                            @csrf
                                            <input class="w-16 rounded-md border-gray-300 text-xs" type="number" name="extension_days" min="1" value="30">
                                            <button class="text-sm font-bold text-indigo-600 hover:underline" type="submit">Extend</button>
                                        </form>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
