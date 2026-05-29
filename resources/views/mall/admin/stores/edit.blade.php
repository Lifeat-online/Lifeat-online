@extends('layouts.public')

@section('title', 'Edit Mall Store')

@include('mall.partials.styles')
@include('mall.partials.address-autocomplete')

@section('content')
    <div class="mall-shell">
        @include('mall.admin.partials.nav')
        <div class="mall-toolbar">
            <h1>Edit {{ $store->name }}</h1>
            <a class="mall-button secondary" href="{{ route('mall.admin.stores.show', $store) }}">Back to Store</a>
        </div>

        <form class="mall-card" method="post" action="{{ route('mall.admin.stores.update', $store) }}">
            @csrf
            @method('PUT')
            <div class="mall-grid">
                <label>Name <input class="mall-input" name="name" value="{{ old('name', $store->name) }}" required></label>
                <label>Brand color <input class="mall-input" name="primary_color" value="{{ old('primary_color', $store->primary_color) }}" required></label>
                <label>Status
                    <select class="mall-select" name="status">
                        @foreach (['pending', 'active', 'suspended', 'closed'] as $status)
                            <option value="{{ $status }}" @selected(old('status', $store->status) === $status)>{{ ucfirst($status) }}</option>
                        @endforeach
                    </select>
                </label>
                <label>PayFast merchant ID <input class="mall-input" name="payfast_merchant_id" value="{{ old('payfast_merchant_id', $store->payfast_merchant_id) }}"></label>
                <label>PayFast merchant key <input class="mall-input" name="payfast_merchant_key" value="{{ old('payfast_merchant_key', $store->payfast_merchant_key) }}"></label>
            </div>
            <label>Tagline <input class="mall-input" name="tagline" value="{{ old('tagline', $store->tagline) }}"></label>
            <label>Description <textarea class="mall-textarea" name="description">{{ old('description', $store->description) }}</textarea></label>
            <fieldset style="display:grid; gap:.75rem; margin:0; padding:0; border:0;">
                <strong>Pickup Point</strong>
                <label>
                    <span class="mall-muted">Pickup address</span>
                    <div class="mall-address-wrap">
                        <input class="mall-input" id="admin_pickup_address" name="pickup_address" value="{{ old('pickup_address', $store->pickup_address) }}" maxlength="500" autocomplete="off" data-mall-address-autocomplete data-latitude-target="admin_pickup_latitude" data-longitude-target="admin_pickup_longitude" data-status-target="admin_pickup_status">
                        <div class="mall-address-suggestions" id="admin_pickup_address_suggestions" role="listbox"></div>
                    </div>
                    <span class="mall-address-status" id="admin_pickup_status" aria-live="polite"></span>
                </label>
                <div class="mall-grid">
                    <label>
                        <span class="mall-muted">Pickup latitude</span>
                        <input class="mall-input" id="admin_pickup_latitude" type="number" step="0.0000001" min="-35" max="-22" name="pickup_latitude" value="{{ old('pickup_latitude', $store->pickup_latitude) }}">
                    </label>
                    <label>
                        <span class="mall-muted">Pickup longitude</span>
                        <input class="mall-input" id="admin_pickup_longitude" type="number" step="0.0000001" min="16" max="33" name="pickup_longitude" value="{{ old('pickup_longitude', $store->pickup_longitude) }}">
                    </label>
                </div>
                <p class="mall-muted" style="margin:0;">This is the pickup point used when mall orders are handed to taxi delivery drivers.</p>
            </fieldset>
            <input type="hidden" name="is_featured" value="0">
            <div class="mall-chip-row">
                <label class="mall-chip"><input type="checkbox" name="is_featured" value="1" @checked(old('is_featured', $store->is_featured))> Featured on mall home</label>
            </div>
            <div class="mall-chip-row">
                @foreach ($categories as $category)
                    <label class="mall-chip">
                        <input type="checkbox" name="category_ids[]" value="{{ $category->id }}" @checked(in_array($category->id, old('category_ids', $store->categories->pluck('id')->all())))>
                        {{ $category->name }}
                    </label>
                @endforeach
            </div>
            @if ($errors->any()) <div class="mall-alert">{{ $errors->first() }}</div> @endif
            <button class="mall-button" type="submit">Save Store</button>
        </form>
    </div>
@endsection
