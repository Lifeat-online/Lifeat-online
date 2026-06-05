<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $pageTitle }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
            <div class="rounded-lg bg-white p-6 shadow-sm">
                @if ($errors->any())
                    <div class="mb-4 rounded-md bg-red-50 p-4 text-sm text-red-700">
                        <ul class="list-disc pl-5">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if (session('status'))
                    <div class="mb-4 rounded-md bg-green-50 p-3 text-sm text-green-700">{{ session('status') }}</div>
                @endif

                <form method="post" action="{{ $formAction }}" class="space-y-6">
                    @csrf
                    @if ($formMethod !== 'POST')
                        @method($formMethod)
                    @endif

                    <div class="grid gap-6 md:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-sm font-medium">Package Type</label>
                            <select class="w-full rounded-md border-gray-300" name="package_type_id">
                                @foreach ($packageTypes as $type)
                                    <option value="{{ $type->id }}" @selected(old('package_type_id', $package->package_type_id) == $type->id)>{{ $type->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium">Status</label>
                            <select class="w-full rounded-md border-gray-300" name="status">
                                <option value="active" @selected(old('status', $package->status ?: 'active') === 'active')>Active</option>
                                <option value="inactive" @selected(old('status', $package->status) === 'inactive')>Inactive</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid gap-6 md:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-sm font-medium">Name</label>
                            <input class="w-full rounded-md border-gray-300" name="name" value="{{ old('name', $package->name) }}">
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium">Slug</label>
                            <input class="w-full rounded-md border-gray-300" name="slug" value="{{ old('slug', $package->slug) }}">
                        </div>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium">Description</label>
                        <textarea class="w-full rounded-md border-gray-300" name="description" rows="4">{{ old('description', $package->description) }}</textarea>
                    </div>

                    <div class="grid gap-6 md:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-sm font-medium">Billing Model</label>
                            <select class="w-full rounded-md border-gray-300" name="billing_model">
                                @foreach (['once_off' => 'Once Off', 'monthly' => 'Monthly', 'six_monthly' => 'Six Monthly'] as $value => $label)
                                    <option value="{{ $value }}" @selected(old('billing_model', $package->billing_model ?: 'six_monthly') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium">Duration Days</label>
                            <input class="w-full rounded-md border-gray-300" type="number" min="1" name="duration_days" value="{{ old('duration_days', $package->duration_days ?: 180) }}">
                        </div>
                    </div>

                    <div class="grid gap-6 md:grid-cols-3">
                        <div>
                            <label class="mb-1 block text-sm font-medium">Amount</label>
                            <input class="w-full rounded-md border-gray-300" type="number" step="0.01" min="0" name="amount" value="{{ old('amount', $packagePrice->amount) }}">
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium">Currency</label>
                            <input class="w-full rounded-md border-gray-300" name="currency" value="{{ old('currency', $packagePrice->currency ?: 'ZAR') }}">
                        </div>
                        <div class="flex items-end">
                            <label class="inline-flex items-center gap-2">
                                <input type="checkbox" name="vat_inclusive" value="1" @checked(old('vat_inclusive', $packagePrice->vat_inclusive ?? true))>
                                <span>VAT inclusive</span>
                            </label>
                        </div>
                    </div>

                    @if ($package->exists)
                        <div class="rounded-md border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                            <p class="font-semibold">Pricing authority note</p>
                            <p class="mt-1">Changing amount, currency, or VAT creates a new effective price version. Existing orders keep their saved price snapshots.</p>
                            <label class="mt-3 block text-sm font-medium" for="price_change_note">Required when changing price</label>
                            <textarea
                                id="price_change_note"
                                class="mt-1 w-full rounded-md border-amber-200"
                                name="price_change_note"
                                rows="3"
                                placeholder="Example: Approved 2026 Q3 package price update"
                            >{{ old('price_change_note') }}</textarea>
                        </div>

                        <div class="rounded-md border border-gray-200 p-4">
                            <h3 class="text-sm font-semibold text-gray-900">Price history</h3>
                            <div class="mt-3 overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200 text-sm">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-3 py-2 text-left">Amount</th>
                                            <th class="px-3 py-2 text-left">VAT</th>
                                            <th class="px-3 py-2 text-left">Effective From</th>
                                            <th class="px-3 py-2 text-left">Effective To</th>
                                            <th class="px-3 py-2 text-left">Created By</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        @foreach ($package->prices->sortByDesc('effective_from')->values() as $historyPrice)
                                            <tr>
                                                <td class="px-3 py-2">{{ $historyPrice->currency }} {{ number_format((float) $historyPrice->amount, 2) }}</td>
                                                <td class="px-3 py-2">{{ $historyPrice->vat_inclusive ? 'Inclusive' : 'Exclusive' }}</td>
                                                <td class="px-3 py-2">{{ $historyPrice->effective_from?->format('Y-m-d H:i') ?: '-' }}</td>
                                                <td class="px-3 py-2">{{ $historyPrice->effective_to?->format('Y-m-d H:i') ?: 'Current' }}</td>
                                                <td class="px-3 py-2">{{ $historyPrice->creator?->email ?: '-' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif

                    <label class="inline-flex items-center gap-2">
                        <input type="checkbox" name="is_self_service" value="1" @checked(old('is_self_service', $package->is_self_service))>
                        <span>Self-service package</span>
                    </label>

                    <div class="flex gap-3">
                        <button class="rounded-md bg-indigo-600 px-4 py-2 text-white" type="submit">Save Package</button>
                        <a href="{{ route('admin.packages.index') }}" class="rounded-md bg-gray-200 px-4 py-2 text-gray-800">Back</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
