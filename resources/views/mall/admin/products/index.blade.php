@extends('layouts.public')

@section('title', 'Mall Admin Products')

@include('mall.partials.styles')

@section('content')
    <div class="mall-shell">
        @include('mall.admin.partials.nav')
        <div class="mall-toolbar">
            <h1>Mall Products</h1>
            <form method="get" class="mall-form-row">
                <input class="mall-input" type="search" name="q" value="{{ request('q') }}" placeholder="Search products">
                <select class="mall-select" name="store_id">
                    <option value="">All stores</option>
                    @foreach ($stores as $store)
                        <option value="{{ $store->id }}" @selected((string) request('store_id') === (string) $store->id)>{{ $store->name }}</option>
                    @endforeach
                </select>
                <select class="mall-select" name="status">
                    <option value="">All statuses</option>
                    <option value="active" @selected(request('status') === 'active')>Active</option>
                    <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
                </select>
                <label class="mall-chip"><input type="checkbox" name="featured" value="1" @checked(request()->boolean('featured'))> Featured</label>
                <button class="mall-button" type="submit">Filter</button>
            </form>
        </div>
        @if (session('status')) <div class="mall-alert">{{ session('status') }}</div> @endif
        <section class="mall-card">
            @forelse ($products as $product)
                <div class="mall-line-item">
                    <img src="{{ $product->main_image_url }}" alt="">
                    <div>
                        <a href="{{ route('mall.admin.products.edit', $product->id) }}">{{ $product->name }}</a>
                        <div class="mall-muted">{{ $product->store?->name }} - R {{ $product->price }} - stock {{ $product->stock_qty }} - {{ $product->parcel_weight_kg ? $product->parcel_weight_kg.' kg' : 'parcel kg not set' }}</div>
                    </div>
                    <div class="mall-chip-row">
                        @if ($product->is_featured) <span class="mall-chip">Featured</span> @endif
                        <span class="mall-chip">{{ $product->is_active ? 'Active' : 'Inactive' }}</span>
                    </div>
                </div>
            @empty
                <p class="mall-muted">No mall products match this view.</p>
            @endforelse
        </section>
        {{ $products->links() }}
    </div>
@endsection
