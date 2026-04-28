<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Packages</h2>
            <a href="{{ route('admin.packages.create') }}" class="rounded-md bg-indigo-600 px-4 py-2 text-white">Create Package</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="rounded-lg bg-white p-6 shadow-sm">
                @if (session('status'))
                    <div class="mb-4 rounded-md bg-green-50 p-3 text-sm text-green-700">{{ session('status') }}</div>
                @endif

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left">Name</th>
                                <th class="px-4 py-3 text-left">Type</th>
                                <th class="px-4 py-3 text-left">Billing</th>
                                <th class="px-4 py-3 text-left">Price</th>
                                <th class="px-4 py-3 text-left">Status</th>
                                <th class="px-4 py-3 text-left">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($packages as $package)
                                @php($price = $package->currentPrice())
                                <tr>
                                    <td class="px-4 py-3">{{ $package->name }}</td>
                                    <td class="px-4 py-3">{{ $package->type?->name }}</td>
                                    <td class="px-4 py-3">{{ ucfirst(str_replace('_', ' ', $package->billing_model)) }}</td>
                                    <td class="px-4 py-3">{{ $price ? $price->currency.' '.number_format((float) $price->amount, 2) : '-' }}</td>
                                    <td class="px-4 py-3">{{ ucfirst($package->status) }}</td>
                                    <td class="px-4 py-3">
                                        <a href="{{ route('admin.packages.edit', $package) }}" class="text-indigo-600">Edit</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-6 text-center text-gray-500">No packages created yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-6">{{ $packages->links() }}</div>
            </div>
        </div>
    </div>
</x-app-layout>
