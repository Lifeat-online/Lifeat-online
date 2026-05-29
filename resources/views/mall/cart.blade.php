@extends('layouts.public')

@section('title', 'Basket at '.$store->name)

@include('mall.partials.styles')

@section('content')
    <div class="mall-shell">
        <div class="mall-toolbar">
            <div>
                <h1>Your basket at {{ $store->name }}</h1>
                <p class="mall-muted">This basket belongs only to this store.</p>
            </div>
            <a href="{{ route('mall.stores.index', $store) }}" class="mall-button secondary">Continue Shopping</a>
        </div>

        @if (session('status') || session('info') || session('error'))
            <div class="mall-alert">{{ session('status') ?? session('info') ?? session('error') }}</div>
        @endif
        @if ($errors->any())
            <div class="mall-alert">{{ $errors->first() }}</div>
        @endif

        @if ($cart->isEmpty())
            <div class="mall-empty">
                <strong>Your basket is empty.</strong>
                <p><a href="{{ route('mall.stores.index', $store) }}">Go back to {{ $store->name }}</a></p>
            </div>
        @else
            <div class="mall-split">
                <section class="mall-card">
                    @foreach ($cart->items as $item)
                        <div class="mall-line-item">
                            <img src="{{ $item->product?->main_image_url }}" alt="">
                            <div>
                                <strong>{{ $item->product?->name ?? 'Deleted product' }}</strong>
                                <div class="mall-muted">R {{ $item->unit_price }} each</div>
                                <div class="mall-muted">Line total: R {{ $item->line_total }}</div>
                            </div>
                            <div class="mall-line-actions">
                                <form method="post" action="{{ route('mall.cart.items.update', [$store, $item]) }}" class="mall-form-row">
                                    @csrf
                                    @method('PATCH')
                                    <input class="mall-input" type="number" name="quantity" value="{{ $item->quantity }}" min="1" max="99" style="width:5rem;">
                                    <button class="mall-button secondary" type="submit">Update</button>
                                </form>
                                <form method="post" action="{{ route('mall.cart.items.destroy', [$store, $item]) }}" style="margin-top:.5rem;">
                                    @csrf
                                    @method('DELETE')
                                    <button class="mall-button danger" type="submit">Remove</button>
                                </form>
                            </div>
                        </div>
                    @endforeach
                </section>

                <aside class="mall-sidebar">
                    <strong>Order summary</strong>
                    <div class="mall-total-row"><span>Items</span><span>{{ $cart->item_count }}</span></div>
                    <div class="mall-total-row"><span>Subtotal</span><span>R {{ $cart->total }}</span></div>
                    <div class="mall-total-row"><span>Total</span><span>R {{ $cart->total }}</span></div>
                    <a class="mall-button" href="{{ route('mall.checkout.show', $store) }}">Proceed to Checkout</a>
                </aside>
            </div>
        @endif
    </div>
@endsection
