@extends('layouts.public')

@section('title', $store->name.' Products | Life@ Mall')

@include('mall.partials.styles')

@section('content')
    <div class="mall-shell">
        <div class="mall-toolbar">
            <div>
                <a href="{{ route('mall.stores.window', $store) }}" class="mall-button secondary">Back to {{ $store->name }} Entrance</a>
                <h1 style="margin:.8rem 0 0;">{{ $store->name }}</h1>
                @if ($store->tagline)
                    <p class="mall-muted">{{ $store->tagline }}</p>
                @endif
            </div>
            <a class="mall-button" href="{{ route('mall.cart.show', $store) }}">Basket ({{ $cart->item_count }})</a>
        </div>

        @if (session('status') || session('info') || session('error'))
            <div class="mall-alert">{{ session('status') ?? session('info') ?? session('error') }}</div>
        @endif
        @if ($errors->any())
            <div class="mall-alert">{{ $errors->first() }}</div>
        @endif

        <div class="mall-split">
            <section>
                <form class="mall-toolbar" method="get" action="{{ route('mall.stores.index', $store) }}">
                    <div class="mall-form-row">
                        <input class="mall-input" type="search" name="q" value="{{ request('q') }}" placeholder="Search products">
                        <select class="mall-select" name="category">
                            <option value="">All categories</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->slug }}" @selected(request('category') === $category->slug)>{{ $category->name }}</option>
                            @endforeach
                        </select>
                        <select class="mall-select" name="sort">
                            <option value="">Featured</option>
                            <option value="price_asc" @selected(request('sort') === 'price_asc')>Price low to high</option>
                            <option value="price_desc" @selected(request('sort') === 'price_desc')>Price high to low</option>
                        </select>
                    </div>
                    <button class="mall-button" type="submit">Apply</button>
                </form>

                @if ($products->isEmpty())
                    <div class="mall-empty">No products match this view.</div>
                @else
                    <div class="mall-grid">
                        @foreach ($products as $product)
                            <article class="mall-card">
                                <a href="{{ route('mall.stores.products.show', [$store, $product]) }}">
                                    <img src="{{ $product->main_image_url }}" alt="">
                                </a>
                                <div>
                                    <h2 style="margin:0;"><a href="{{ route('mall.stores.products.show', [$store, $product]) }}">{{ $product->name }}</a></h2>
                                    @if ($product->short_description)
                                        <p class="mall-muted">{{ $product->short_description }}</p>
                                    @endif
                                </div>
                                <div class="mall-price">R {{ $product->price }}</div>
                                <form method="post" action="{{ route('mall.cart.items.store', [$store, $product]) }}" class="mall-form-row">
                                    @csrf
                                    <input class="mall-input" type="number" name="quantity" value="1" min="1" max="99" style="width:5rem;">
                                    <button class="mall-button" type="submit" @disabled(! $product->isInStock())>
                                        {{ $product->isInStock() ? 'Add' : 'Out of stock' }}
                                    </button>
                                </form>
                            </article>
                        @endforeach
                    </div>

                    {{ $products->links() }}
                @endif
            </section>

            <aside class="mall-sidebar">
                <strong>Your basket at {{ $store->name }}</strong>
                <div class="mall-total-row"><span>Items</span><span>{{ $cart->item_count }}</span></div>
                <div class="mall-total-row"><span>Total</span><span>R {{ $cart->total }}</span></div>
                <a class="mall-button" href="{{ route('mall.cart.show', $store) }}">View Basket</a>
                @if (! $cart->isEmpty())
                    <a class="mall-button secondary" href="{{ route('mall.checkout.show', $store) }}">Go to Checkout</a>
                @endif
            </aside>
        </div>
    </div>
@endsection
