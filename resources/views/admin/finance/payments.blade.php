<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Finance Payments</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto space-y-6 sm:px-6 lg:px-8">
            <form method="get" action="{{ route('admin.finance.payments.index') }}" class="grid gap-4 rounded-lg bg-white p-4 shadow-sm md:grid-cols-3">
                <select class="rounded-md border-gray-300 text-sm" name="status">
                    <option value="">All statuses</option>
                    @foreach (['pending', 'paid', 'failed', 'refunded'] as $status)
                        <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ ucfirst($status) }}</option>
                    @endforeach
                </select>
                <div class="flex gap-2">
                    <button class="rounded-md bg-indigo-600 px-4 py-2 text-sm text-white" type="submit">Apply Filters</button>
                    <a class="rounded-md bg-gray-100 px-4 py-2 text-sm text-gray-700" href="{{ route('admin.finance.payments.index') }}">Reset</a>
                </div>
            </form>

            <div class="rounded-lg bg-white p-6 shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left">Order</th>
                                <th class="px-4 py-3 text-left">Customer</th>
                                <th class="px-4 py-3 text-left">Status</th>
                                <th class="px-4 py-3 text-left">Amount</th>
                                <th class="px-4 py-3 text-left">Attempts</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($payments as $payment)
                                <tr>
                                    <td class="px-4 py-3"><a class="text-indigo-600" href="{{ route('admin.finance.payments.show', $payment) }}">{{ $payment->order?->order_number }}</a></td>
                                    <td class="px-4 py-3">
                                        @if ($payment->user)
                                            <a class="text-indigo-600" href="{{ route('admin.customers.show', $payment->user) }}">{{ $payment->user->name }}</a>
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">{{ ucfirst($payment->status) }}</td>
                                    <td class="px-4 py-3">{{ $payment->currency }} {{ number_format((float) $payment->amount, 2) }}</td>
                                    <td class="px-4 py-3">{{ $payment->attempts->count() }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-6">{{ $payments->links() }}</div>
            </div>
        </div>
    </div>
</x-app-layout>
