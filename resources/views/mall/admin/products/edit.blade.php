@extends('layouts.public')

@section('title', 'Edit Mall Product')

@include('mall.partials.styles')

@section('content')
    <div class="mall-shell">
        @include('mall.admin.partials.nav')
        <div class="mall-toolbar">
            <div>
                <h1>Edit {{ $product->name }}</h1>
                <p class="mall-muted" style="margin:0;">{{ $product->store?->name }}</p>
            </div>
            <a class="mall-button secondary" href="{{ route('mall.admin.products.index') }}">Back to Products</a>
        </div>

        <form class="mall-card" method="post" action="{{ route('mall.admin.products.update', $product->id) }}">
            @csrf
            @method('PUT')
            <div class="mall-grid">
                <label>Name <input class="mall-input" name="name" value="{{ old('name', $product->name) }}" required></label>
                <label>Price <input class="mall-input" name="price" value="{{ old('price', $product->price) }}" required></label>
                <label>Compare price <input class="mall-input" name="compare_price" value="{{ old('compare_price', $product->compare_price) }}"></label>
                <label>SKU <input class="mall-input" name="sku" value="{{ old('sku', $product->sku) }}"></label>
                <label>Stock <input class="mall-input" type="number" min="0" name="stock_qty" value="{{ old('stock_qty', $product->stock_qty ?? 0) }}" required></label>
                <label>Parcel kg estimate <input class="mall-input" type="number" min="0.001" max="999999" step="0.001" name="parcel_weight_kg" value="{{ old('parcel_weight_kg', $product->parcel_weight_kg) }}"></label>
            </div>
            <label>Short description <input class="mall-input" name="short_description" value="{{ old('short_description', $product->short_description) }}"></label>
            <label>Description <textarea class="mall-textarea" name="description">{{ old('description', $product->description) }}</textarea></label>
            <input type="hidden" name="manage_stock" value="0">
            <input type="hidden" name="is_featured" value="0">
            <input type="hidden" name="is_active" value="0">
            <div class="mall-chip-row">
                <label class="mall-chip"><input type="checkbox" name="manage_stock" value="1" @checked(old('manage_stock', $product->manage_stock))> Manage stock</label>
                <label class="mall-chip"><input type="checkbox" name="is_featured" value="1" @checked(old('is_featured', $product->is_featured))> Featured in windows</label>
                <label class="mall-chip"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $product->is_active))> Active</label>
            </div>
            <div class="mall-chip-row">
                @foreach ($categories as $category)
                    <label class="mall-chip">
                        <input type="checkbox" name="category_ids[]" value="{{ $category->id }}" @checked(in_array($category->id, old('category_ids', $product->categories->pluck('id')->all())))>
                        {{ $category->name }}
                    </label>
                @endforeach
            </div>
            @if ($errors->any()) <div class="mall-alert">{{ $errors->first() }}</div> @endif
            <button class="mall-button" type="submit">Save Product</button>
        </form>
    </div>
@endsection
