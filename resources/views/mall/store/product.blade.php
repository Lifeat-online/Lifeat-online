@extends('layouts.public')

@section('title', $product->name.' | '.$store->name)

@include('mall.partials.styles')

@section('content')
    <div class="mall-shell">
        <a href="{{ route('mall.stores.index', $store) }}" class="mall-button secondary" style="width:max-content;">Back to {{ $store->name }}</a>

        <section class="mall-split">
            <div class="mall-card">
                <img src="{{ $product->main_image_url }}" alt="">
            </div>
            <div class="mall-sidebar">
                <h1 style="margin:0;">{{ $product->name }}</h1>
                @if ($product->short_description)
                    <p class="mall-muted">{{ $product->short_description }}</p>
                @endif
                <div class="mall-price">R {{ $product->price }}</div>
                @if ($product->isOnSale())
                    <p class="mall-muted">Was R {{ $product->compare_price }}</p>
                @endif
                <div class="mall-chip-row">
                    @foreach ($product->categories as $category)
                        <span class="mall-chip">{{ $category->name }}</span>
                    @endforeach
                </div>
                <form method="post" action="{{ route('mall.cart.items.store', [$store, $product]) }}" class="mall-form-row">
                    @csrf
                    <input class="mall-input" type="number" name="quantity" value="1" min="1" max="99" style="width:5rem;">
                    <button class="mall-button" type="submit" @disabled(! $product->isInStock())>
                        {{ $product->isInStock() ? 'Add to Basket' : 'Out of stock' }}
                    </button>
                </form>
                <a class="mall-button secondary" href="{{ route('mall.cart.show', $store) }}">Basket ({{ $cart->item_count }})</a>
            </div>
        </section>

        @if ($product->description)
            <section class="mall-card">
                <h2>Product details</h2>
                <p>{{ $product->description }}</p>
            </section>
        @endif
    </div>
@endsection
