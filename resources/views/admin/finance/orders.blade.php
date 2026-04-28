<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Finance Orders</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="rounded-lg bg-white p-6 shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left">Order</th>
                                <th class="px-4 py-3 text-left">Customer</th>
                                <th class="px-4 py-3 text-left">Status</th>
                                <th class="px-4 py-3 text-left">Total</th>
                                <th class="px-4 py-3 text-left">Created</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($orders as $order)
                                <tr>
                                    <td class="px-4 py-3"><a class="text-indigo-600" href="{{ route('admin.finance.orders.show', $order) }}">{{ $order->order_number }}</a></td>
                                    <td class="px-4 py-3">
                                        @if ($order->user)
                                            <a class="text-indigo-600" href="{{ route('admin.customers.show', $order->user) }}">{{ $order->user->name }}</a>
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">{{ ucfirst($order->status) }}</td>
                                    <td class="px-4 py-3">{{ $order->currency }} {{ number_format((float) $order->total, 2) }}</td>
                                    <td class="px-4 py-3">{{ $order->created_at?->format('j M Y H:i') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-6">{{ $orders->links() }}</div>
            </div>
        </div>
    </div>
</x-app-layout>
