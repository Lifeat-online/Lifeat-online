@extends('layouts.public')

@section('title', $store->name.' | Life@ Mall')

@include('mall.partials.styles')

@section('content')
    <div class="mall-shell">
        <a href="{{ route('mall.index') }}" class="mall-button secondary" style="width:max-content;">Back to Mall</a>

        <section class="mall-storefront-body" style="--accent: {{ $store->primary_color }};">
            <img class="mall-banner" src="{{ $store->banner_url }}" alt="">
            <div class="mall-storefront-head">
                <img class="mall-logo" src="{{ $store->logo_url }}" alt="{{ $store->name }} logo">
                <div class="mall-storefront-copy">
                    <div>
                        <h1 class="mall-title">{{ $store->name }}</h1>
                        @if ($store->tagline)
                            <p class="mall-subtitle">{{ $store->tagline }}</p>
                        @endif
                    </div>
                    @if ($store->description)
                        <p class="mall-subtitle">{{ $store->description }}</p>
                    @endif
                    @if ($store->categories->isNotEmpty())
                        <div class="mall-chip-row">
                            @foreach ($store->categories as $category)
                                <span class="mall-chip">{{ $category->name }}</span>
                            @endforeach
                        </div>
                    @endif
                </div>
                <a class="mall-button" href="{{ route('mall.stores.index', $store) }}">Enter Store</a>
            </div>

            @if ($featuredProducts->isEmpty())
                <div class="mall-empty">No featured products are in the window yet.</div>
            @else
                <div class="mall-window-strip" aria-label="{{ $store->name }} window products">
                    @foreach ($featuredProducts as $product)
                        <a class="mall-window-product" href="{{ route('mall.stores.products.show', [$store, $product]) }}">
                            <img src="{{ $product->main_image_url }}" alt="">
                            <span>
                                <strong>{{ $product->name }}</strong>
                                <span class="mall-muted" style="display:block;">R {{ $product->price }}</span>
                            </span>
                        </a>
                    @endforeach
                </div>
            @endif
        </section>
    </div>
@endsection
