@extends('layouts.public')

@section('title', 'Life@ Mall')

@include('mall.partials.styles')

@section('content')
    <div class="mall-shell">
        <section class="mall-hero">
            <div class="mall-hero-grid">
                <div>
                    <h1 class="mall-title">Life@ Mall</h1>
                    <p class="mall-subtitle">Walk the mall corridor, window shop the storefronts, then enter one store at a time.</p>
                </div>
                <form class="mall-form-row" method="get" action="{{ route('mall.index') }}">
                    <input class="mall-input" type="search" name="q" value="{{ request('q') }}" placeholder="Search stores">
                    <button class="mall-button" type="submit">Search</button>
                </form>
            </div>
            <div class="mall-chip-row">
                <a class="mall-chip" href="{{ route('mall.index') }}">All stores</a>
                @foreach ($categories as $category)
                    <a class="mall-chip" href="{{ route('mall.index', ['category' => $category->slug]) }}">
                        {{ $category->name }} ({{ $category->stores_count }})
                    </a>
                @endforeach
            </div>
        </section>

        @if (session('status') || session('info') || session('error'))
            <div class="mall-alert">{{ session('status') ?? session('info') ?? session('error') }}</div>
        @endif

        @if ($stores->isEmpty())
            <div class="mall-empty">
                <strong>No mall stores are open yet.</strong>
                <p class="mall-muted">Approved stores will appear here as soon as they are active.</p>
            </div>
        @else
            <section class="mall-window-grid" aria-label="Shop windows">
                @foreach ($stores as $store)
                    @php
                        $windowProducts = $store->products->take(6);
                    @endphp
                    <article class="mall-window" style="--accent: {{ $store->primary_color }};">
                        <div class="mall-window-sign">
                            <img class="mall-logo" src="{{ $store->logo_url }}" alt="{{ $store->name }} logo">
                            <div>
                                <h2 class="mall-window-name">{{ $store->name }}</h2>
                                @if ($store->tagline)
                                    <p class="mall-muted" style="margin:.15rem 0;">{{ $store->tagline }}</p>
                                @endif
                            </div>
                            <a class="mall-button" href="{{ route('mall.stores.index', $store) }}">Enter Store</a>
                        </div>

                        <div class="mall-window-glass mall-window-shelf">
                            @forelse ($windowProducts as $product)
                                <a class="mall-window-product" href="{{ route('mall.stores.products.show', [$store, $product]) }}">
                                    <img src="{{ $product->main_image_url }}" alt="">
                                    <span>
                                        <strong>{{ $product->name }}</strong>
                                        <span class="mall-muted" style="display:block;">R {{ $product->price }}</span>
                                    </span>
                                </a>
                            @empty
                                <div class="mall-empty" style="grid-column:1 / -1;">
                                    <strong>Window display coming soon.</strong>
                                    <p class="mall-muted">This store is open, but has not featured products in the window yet.</p>
                                </div>
                            @endforelse
                        </div>

                        <div class="mall-window-footer">
                            <div>
                                <strong>{{ $store->products_count }} products inside</strong>
                                <div class="mall-chip-row" style="margin-top:.45rem;">
                                    @foreach ($store->categories as $category)
                                        <span class="mall-chip">{{ $category->name }}</span>
                                    @endforeach
                                </div>
                            </div>
                            <a class="mall-button secondary" href="{{ route('mall.stores.index', $store) }}">Shop at {{ $store->name }}</a>
                        </div>
                    </article>
                @endforeach
            </section>

            {{ $stores->links() }}
        @endif
    </div>
@endsection
