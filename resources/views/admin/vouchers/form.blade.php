<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $pageTitle }}</h2>
            <a href="{{ route('admin.vouchers.index') }}" class="rounded-md bg-slate-700 px-4 py-2 text-sm text-white">Back to vouchers</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="overflow-hidden rounded-lg bg-white shadow-sm">
                <div class="p-6">
                    @if (session('status'))<div class="mb-4 rounded-md bg-green-50 p-3 text-sm text-green-700">{{ session('status') }}</div>@endif
                    @if ($errors->any())<div class="mb-4 rounded-md bg-red-50 p-3 text-sm text-red-700">Please correct the highlighted fields.</div>@endif

                    <form method="post" action="{{ $formAction }}" class="space-y-6">
                        @csrf
                        @if ($formMethod !== 'POST') @method($formMethod) @endif

                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700">Listing</label>
                                <select name="listing_id" class="mt-1 w-full rounded-md border-gray-300">
                                    @foreach ($listings as $listing)
                                        <option value="{{ $listing->id }}" @selected((int) old('listing_id', $voucher->listing_id) === (int) $listing->id)>{{ $listing->title }}</option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('listing_id')" class="mt-2" />
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700">Status</label>
                                <select name="status" class="mt-1 w-full rounded-md border-gray-300">
                                    <option value="draft" @selected(old('status', $voucher->status ?: 'draft') === 'draft')>Draft</option>
                                    <option value="published" @selected(old('status', $voucher->status) === 'published')>Published</option>
                                </select>
                                <x-input-error :messages="$errors->get('status')" class="mt-2" />
                            </div>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700">Title</label>
                                <input name="title" value="{{ old('title', $voucher->title) }}" class="mt-1 w-full rounded-md border-gray-300">
                                <x-input-error :messages="$errors->get('title')" class="mt-2" />
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700">Type</label>
                                <select name="voucher_type" class="mt-1 w-full rounded-md border-gray-300">
                                    <option value="discount_amount" @selected(old('voucher_type', $voucher->voucher_type ?: 'discount_amount') === 'discount_amount')>Discount amount</option>
                                    <option value="discount_percent" @selected(old('voucher_type', $voucher->voucher_type) === 'discount_percent')>Discount percent</option>
                                    <option value="fixed_price" @selected(old('voucher_type', $voucher->voucher_type) === 'fixed_price')>Fixed price</option>
                                    <option value="promo_offer" @selected(old('voucher_type', $voucher->voucher_type) === 'promo_offer')>Promo offer</option>
                                </select>
                                <x-input-error :messages="$errors->get('voucher_type')" class="mt-2" />
                            </div>
                        </div>

                        <div class="grid gap-4 md:grid-cols-3">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700">Discount Amount</label>
                                <input name="discount_amount" value="{{ old('discount_amount', $voucher->discount_amount) }}" class="mt-1 w-full rounded-md border-gray-300" inputmode="decimal">
                                <x-input-error :messages="$errors->get('discount_amount')" class="mt-2" />
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700">Discount Percent</label>
                                <input name="discount_percent" value="{{ old('discount_percent', $voucher->discount_percent) }}" class="mt-1 w-full rounded-md border-gray-300" inputmode="decimal">
                                <x-input-error :messages="$errors->get('discount_percent')" class="mt-2" />
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700">Currency</label>
                                <input name="currency" value="{{ old('currency', $voucher->currency ?: 'ZAR') }}" class="mt-1 w-full rounded-md border-gray-300">
                                <x-input-error :messages="$errors->get('currency')" class="mt-2" />
                            </div>
                        </div>

                        <div class="grid gap-4 md:grid-cols-3">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700">Usage Limit</label>
                                <input name="usage_limit" value="{{ old('usage_limit', $voucher->usage_limit ?: 1) }}" class="mt-1 w-full rounded-md border-gray-300" inputmode="numeric">
                                <x-input-error :messages="$errors->get('usage_limit')" class="mt-2" />
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700">Start At</label>
                                <input type="datetime-local" name="start_at" value="{{ old('start_at', optional($voucher->start_at)->format('Y-m-d\TH:i')) }}" class="mt-1 w-full rounded-md border-gray-300">
                                <x-input-error :messages="$errors->get('start_at')" class="mt-2" />
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700">End At</label>
                                <input type="datetime-local" name="end_at" value="{{ old('end_at', optional($voucher->end_at)->format('Y-m-d\TH:i')) }}" class="mt-1 w-full rounded-md border-gray-300">
                                <x-input-error :messages="$errors->get('end_at')" class="mt-2" />
                            </div>
                        </div>

                        <div>
                            @include('partials.ai-copy-assistant', [
                                'endpoint' => route('admin.ai.voucher-copy'),
                                'mode' => 'voucher',
                                'heading' => 'AI Voucher Copy',
                                'description' => 'Draft a clearer voucher title, description, terms, and redemption instruction from this offer.',
                                'placeholder' => 'Example: 10% off first haircut, valid weekdays, first-time customers only.',
                            ])
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700">Description</label>
                            <textarea name="description" rows="3" class="mt-1 w-full rounded-md border-gray-300">{{ old('description', $voucher->description) }}</textarea>
                            <x-input-error :messages="$errors->get('description')" class="mt-2" />
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700">Terms</label>
                            <textarea name="terms" rows="6" class="mt-1 w-full rounded-md border-gray-300">{{ old('terms', $voucher->terms) }}</textarea>
                            <x-input-error :messages="$errors->get('terms')" class="mt-2" />
                        </div>

                        <div class="flex flex-wrap gap-3">
                            <button class="rounded-md bg-indigo-600 px-6 py-2 text-sm text-white" type="submit">Save</button>
                        </div>
                    </form>
                    @if ($voucher->exists)
                        <form method="post" action="{{ route('admin.vouchers.destroy', $voucher->id) }}" class="mt-4">
                            @csrf
                            @method('DELETE')
                            <button class="rounded-md bg-red-600 px-6 py-2 text-sm text-white" type="submit" onclick="return confirm('Delete this voucher?');">Delete</button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
