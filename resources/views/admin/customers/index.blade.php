<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Customer Lookup</h2>
            <a href="{{ route('admin.customers.index') }}" class="rounded-md bg-slate-100 px-4 py-2 text-sm text-slate-700">Reset</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto space-y-6 sm:px-6 lg:px-8">
            <form method="get" action="{{ route('admin.customers.index') }}" class="rounded-lg bg-white p-6 shadow-sm">
                <div class="grid gap-4 md:grid-cols-[1fr_auto]">
                    <input
                        class="rounded-md border-gray-300 text-sm"
                        type="text"
                        name="q"
                        value="{{ $filters['q'] }}"
                        placeholder="Search by name, email, phone, username, order number, or transaction reference"
                    >
                    <button class="rounded-md bg-indigo-600 px-4 py-2 text-sm text-white" type="submit">Search</button>
                </div>
            </form>

            <div class="rounded-lg bg-white p-6 shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left">Customer</th>
                                <th class="px-4 py-3 text-left">Role</th>
                                <th class="px-4 py-3 text-left">Orders</th>
                                <th class="px-4 py-3 text-left">Subscriptions</th>
                                <th class="px-4 py-3 text-left">Listings</th>
                                <th class="px-4 py-3 text-left">Last Seen</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($customers as $customer)
                                <tr>
                                    <td class="px-4 py-3">
                                        <a class="font-medium text-indigo-600" href="{{ route('admin.customers.show', $customer) }}">{{ $customer->name }}</a>
                                        <div class="text-gray-500">{{ $customer->email }}</div>
                                        @if ($customer->phone)
                                            <div class="text-gray-500">{{ $customer->phone }}</div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">{{ ucfirst(str_replace('_', ' ', $customer->role ?: 'member')) }}</td>
                                    <td class="px-4 py-3">{{ $customer->orders_count }}</td>
                                    <td class="px-4 py-3">{{ $customer->subscriptions_count }}</td>
                                    <td class="px-4 py-3">{{ $customer->listings_count }}</td>
                                    <td class="px-4 py-3">{{ optional($customer->updated_at)->format('j M Y H:i') ?: '-' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-8 text-center text-gray-500">No customers matched the current search.</td>
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
