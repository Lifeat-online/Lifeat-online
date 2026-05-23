@extends('layouts.public')

@section('title', $pageTitle)

@section('content')
    <section class="section">
        <div class="section-head">
            <div>
                <h2>{{ $pageTitle }}</h2>
                <p class="section-subtitle">{{ $listing->title }}</p>
            </div>
            <div style="display:flex; gap:0.75rem; flex-wrap:wrap;">
                <a class="button-link" href="{{ route('account.listings.vouchers.index', $listing) }}">Back to vouchers</a>
                @if ($voucher->exists && $voucher->status === 'published' && $listing->status === 'published')
                    <a class="button-link" href="{{ route('vouchers.show', [$listing, $voucher]) }}">View public</a>
                @endif
            </div>
        </div>

        @if (session('status'))
            <div class="card">{{ session('status') }}</div>
        @endif

        <form method="post" action="{{ $formAction }}" class="card">
            @csrf
            @if ($formMethod !== 'POST')
                @method($formMethod)
            @endif

            <div class="grid grid-2">
                <div>
                    <label for="title">Title</label>
                    <input id="title" name="title" value="{{ old('title', $voucher->title) }}">
                    <x-input-error :messages="$errors->get('title')" class="mt-2" />
                </div>
                <div>
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        <option value="draft" @selected(old('status', $voucher->status) === 'draft')>Draft</option>
                        <option value="published" @selected(old('status', $voucher->status) === 'published')>Published</option>
                    </select>
                    <x-input-error :messages="$errors->get('status')" class="mt-2" />
                </div>
            </div>

            <div style="margin-top:1rem;">
                <label for="description">Description</label>
                <textarea id="description" name="description" rows="4">{{ old('description', $voucher->description) }}</textarea>
                <x-input-error :messages="$errors->get('description')" class="mt-2" />
            </div>

            <div class="grid grid-2" style="margin-top:1rem;">
                <div>
                    <label for="voucher_type">Type</label>
                    <select id="voucher_type" name="voucher_type">
                        <option value="{{ \App\Models\Voucher::TYPE_DISCOUNT_AMOUNT }}" @selected(old('voucher_type', $voucher->voucher_type) === \App\Models\Voucher::TYPE_DISCOUNT_AMOUNT)>Discount amount</option>
                        <option value="{{ \App\Models\Voucher::TYPE_DISCOUNT_PERCENT }}" @selected(old('voucher_type', $voucher->voucher_type) === \App\Models\Voucher::TYPE_DISCOUNT_PERCENT)>Discount percent</option>
                        <option value="{{ \App\Models\Voucher::TYPE_FIXED_PRICE }}" @selected(old('voucher_type', $voucher->voucher_type) === \App\Models\Voucher::TYPE_FIXED_PRICE)>Fixed price offer</option>
                        <option value="{{ \App\Models\Voucher::TYPE_PROMO_OFFER }}" @selected(old('voucher_type', $voucher->voucher_type) === \App\Models\Voucher::TYPE_PROMO_OFFER)>Promo offer</option>
                    </select>
                    <x-input-error :messages="$errors->get('voucher_type')" class="mt-2" />
                </div>
                <div>
                    <label for="usage_limit">Usage limit</label>
                    <input id="usage_limit" name="usage_limit" type="number" min="1" value="{{ old('usage_limit', $voucher->usage_limit) }}">
                    <x-input-error :messages="$errors->get('usage_limit')" class="mt-2" />
                </div>
            </div>

            <div class="grid grid-2" style="margin-top:1rem;">
                <div>
                    <label for="discount_amount">Amount / price</label>
                    <input id="discount_amount" name="discount_amount" type="number" step="0.01" min="0" value="{{ old('discount_amount', $voucher->discount_amount) }}">
                    <x-input-error :messages="$errors->get('discount_amount')" class="mt-2" />
                </div>
                <div>
                    <label for="discount_percent">Percent</label>
                    <input id="discount_percent" name="discount_percent" type="number" step="0.01" min="0" max="100" value="{{ old('discount_percent', $voucher->discount_percent) }}">
                    <x-input-error :messages="$errors->get('discount_percent')" class="mt-2" />
                </div>
            </div>

            <div style="margin-top:1rem;">
                @include('partials.ai-copy-assistant', [
                    'endpoint' => route('account.listings.ai.voucher-copy', $listing),
                    'mode' => 'voucher',
                    'heading' => 'AI Voucher Copy',
                    'description' => 'Draft a customer-friendly voucher title, description, and terms from your offer.',
                    'placeholder' => 'Example: R50 off first service, valid until month end, booking required.',
                ])
            </div>

            <div class="grid grid-2" style="margin-top:1rem;">
                <div>
                    <label for="start_at">Start date</label>
                    <input id="start_at" name="start_at" type="datetime-local" value="{{ old('start_at', $voucher->start_at ? $voucher->start_at->format('Y-m-d\\TH:i') : '') }}">
                    <x-input-error :messages="$errors->get('start_at')" class="mt-2" />
                </div>
                <div>
                    <label for="end_at">End date</label>
                    <input id="end_at" name="end_at" type="datetime-local" value="{{ old('end_at', $voucher->end_at ? $voucher->end_at->format('Y-m-d\\TH:i') : '') }}">
                    <x-input-error :messages="$errors->get('end_at')" class="mt-2" />
                </div>
            </div>

            <div style="margin-top:1rem;">
                <label for="terms">Terms and conditions</label>
                <textarea id="terms" name="terms" rows="5">{{ old('terms', $voucher->terms) }}</textarea>
                <x-input-error :messages="$errors->get('terms')" class="mt-2" />
            </div>

            <div style="margin-top:1rem;">
                <label for="category_ids">Categories</label>
                <select id="category_ids" name="category_ids[]" multiple>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected(in_array((int) $category->id, old('category_ids', $selectedCategoryIds), true))>{{ $category->name }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('category_ids')" class="mt-2" />
            </div>

            <div style="margin-top:1.25rem; display:flex; gap:0.75rem; flex-wrap:wrap;">
                <button class="button" type="submit">Save voucher</button>
            </div>
        </form>
    </section>
@endsection
