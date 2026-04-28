<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Customer Lookup</h2>
            <a href="{{ route('admin.customers.index') }}" class="btn-mgmt btn-soft">Reset Search</a>
        </div>
    </x-slot>

    <style>
        .dash-card {
            background: #ffffff;
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.05);
            border: 1px solid #f1f5f9;
        }
        .btn-mgmt {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.625rem 1.25rem;
            border-radius: 10px;
            font-size: 0.875rem;
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
        .premium-table { width: 100%; border-collapse: separate; border-spacing: 0; }
        .premium-table th { padding: 1rem; background: #f8fafc; color: #64748b; font-weight: 700; text-align: left; font-size: 0.75rem; text-transform: uppercase; }
        .premium-table td { padding: 1.25rem 1rem; border-bottom: 1px solid #f1f5f9; color: #334155; }
        .premium-table tr:hover td { background: #fdfdfd; }
        
        .customer-link { color: #4f46e5; font-weight: 700; text-decoration: none; font-size: 1rem; }
        .customer-link:hover { text-decoration: underline; }
        .customer-subtext { color: #64748b; font-size: 0.85rem; margin-top: 0.125rem; }
        
        .count-badge {
            display: inline-flex;
            padding: 0.25rem 0.625rem;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 700;
            background: #f1f5f9;
            color: #475569;
        }

        html[data-theme="dark"] .dash-card { background: #111827; border-color: #334155; }
        html[data-theme="dark"] .premium-table th { background: #1e293b; color: #94a3b8; }
        html[data-theme="dark"] .premium-table td { color: #e5eefb; border-color: #1f2937; }
        html[data-theme="dark"] .count-badge { background: #1e293b; color: #94a3b8; }
        html[data-theme="dark"] .btn-soft {
            background: #1e293b;
            color: #f1f5f9 !important;
            border-color: #334155;
        }
        html[data-theme="dark"] input {
            background: #0f172a !important;
            color: #f8fafc !important;
            border-color: #334155 !important;
        }
    </style>

    <div class="py-12">
        <div class="max-w-7xl mx-auto space-y-6 sm:px-6 lg:px-8">
            <div class="dash-card">
                <form method="get" action="{{ route('admin.customers.index') }}">
                    <div style="display: flex; gap: 0.75rem;">
                        <input
                            style="flex-grow: 1; border-radius: 10px; border: 1px solid #e2e8f0; padding: 0.625rem 1rem;"
                            type="text"
                            name="q"
                            value="{{ $filters['q'] }}"
                            placeholder="Search by name, email, phone, username..."
                        >
                        <button class="btn-mgmt btn-indigo" type="submit">Search Customer</button>
                    </div>
                </form>
            </div>

            <div class="dash-card">
                <div class="overflow-x-auto">
                    <table class="premium-table">
                        <thead>
                            <tr>
                                <th>Customer Information</th>
                                <th>Role</th>
                                <th style="text-align: center;">Orders</th>
                                <th style="text-align: center;">Subs</th>
                                <th style="text-align: center;">Listings</th>
                                <th>Last Seen</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($customers as $customer)
                                <tr>
                                    <td>
                                        <a class="customer-link" href="{{ route('admin.customers.show', $customer) }}">{{ $customer->name }}</a>
                                        <div class="customer-subtext">{{ $customer->email }}</div>
                                        @if ($customer->phone)
                                            <div class="customer-subtext">{{ $customer->phone }}</div>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="status-badge" style="background: #eef2f6; color: #475569; padding: 0.25rem 0.625rem; border-radius: 999px; font-size: 0.75rem; font-weight: 700;">
                                            {{ ucfirst(str_replace('_', ' ', $customer->role ?: 'member')) }}
                                        </span>
                                    </td>
                                    <td style="text-align: center;"><span class="count-badge">{{ $customer->orders_count }}</span></td>
                                    <td style="text-align: center;"><span class="count-badge">{{ $customer->subscriptions_count }}</span></td>
                                    <td style="text-align: center;"><span class="count-badge">{{ $customer->listings_count }}</span></td>
                                    <td style="font-size: 0.85rem; color: #64748b;">{{ optional($customer->updated_at)->format('j M Y H:i') ?: '-' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" style="padding: 3rem; text-align: center; color: #94a3b8;">No customers matched the current search.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-6">{{ $customers->links() }}</div>
            </div>
        </div>
    </div>
</x-app-layout>
