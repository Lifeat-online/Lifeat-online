@extends('layouts.public')

@section('title', 'Mall Products')

@include('mall.partials.styles')

@section('content')
    <div class="mall-shell">
        @include('mall.vendor.partials.nav')
        <div class="mall-toolbar">
            <h1>Products</h1>
            <a class="mall-button" href="{{ route('mall.vendor.products.create') }}">New Product</a>
        </div>
        @if (session('status')) <div class="mall-alert">{{ session('status') }}</div> @endif
        <section class="mall-card">
            @forelse ($products as $product)
                <div class="mall-total-row">
                    <span>{{ $product->name }} - R {{ $product->price }} - stock {{ $product->stock_qty }} - {{ $product->parcel_weight_kg ? $product->parcel_weight_kg.' kg' : 'parcel kg not set' }}</span>
                    <span>
                        <a href="{{ route('mall.vendor.products.edit', $product) }}">Edit</a>
                    </span>
                </div>
            @empty
                <p class="mall-muted">No products yet.</p>
            @endforelse
        </section>
        {{ $products->links() }}
    </div>
@endsection
