@extends('layouts.public')

@section('title', 'Open a Mall Store')

@include('mall.partials.styles')

@section('content')
    <div class="mall-shell">
        <section class="mall-hero">
            <h1 class="mall-title">Open a Mall Store</h1>
            <p class="mall-subtitle">Submit the store details for admin approval.</p>
        </section>

        @if (session('status'))
            <div class="mall-alert">{{ session('status') }}</div>
        @endif

        <form method="post" action="{{ route('mall.vendor.register.store') }}" class="mall-card">
            @csrf
            <div class="mall-grid">
                <label>Store name <input class="mall-input" name="name" value="{{ old('name') }}" required></label>
                <label>Tagline <input class="mall-input" name="tagline" value="{{ old('tagline') }}"></label>
                <label>Primary color <input class="mall-input" name="primary_color" value="{{ old('primary_color', '#3B82F6') }}"></label>
                <label>PayFast merchant ID <input class="mall-input" name="payfast_merchant_id" value="{{ old('payfast_merchant_id') }}"></label>
                <label>PayFast merchant key <input class="mall-input" name="payfast_merchant_key" value="{{ old('payfast_merchant_key') }}"></label>
                <label>Contact name <input class="mall-input" name="contact_name" value="{{ old('contact_name', auth()->user()->name) }}" required></label>
                <label>Contact email <input class="mall-input" name="contact_email" type="email" value="{{ old('contact_email', auth()->user()->email) }}" required></label>
                <label>Contact phone <input class="mall-input" name="contact_phone" value="{{ old('contact_phone') }}"></label>
                <label>Business registration <input class="mall-input" name="business_reg" value="{{ old('business_reg') }}"></label>
                <label>Bank name <input class="mall-input" name="bank_name" value="{{ old('bank_name') }}"></label>
                <label>Bank account <input class="mall-input" name="bank_account" value="{{ old('bank_account') }}"></label>
                <label>Branch code <input class="mall-input" name="bank_branch_code" value="{{ old('bank_branch_code') }}"></label>
            </div>
            <label>Description <textarea class="mall-textarea" name="description">{{ old('description') }}</textarea></label>
            <div class="mall-chip-row">
                @foreach ($categories as $category)
                    <label class="mall-chip">
                        <input type="checkbox" name="category_ids[]" value="{{ $category->id }}" @checked(in_array($category->id, old('category_ids', [])))>
                        {{ $category->name }}
                    </label>
                @endforeach
            </div>
            @if ($errors->any())
                <div class="mall-alert">{{ $errors->first() }}</div>
            @endif
            <button class="mall-button" type="submit">Submit Store</button>
        </form>
    </div>
@endsection
