@extends('layouts.public')

@section('title', 'Mall Admin Order '.$order->order_number)

@include('mall.partials.styles')

@section('content')
    <div class="mall-shell">
        @include('mall.admin.partials.nav')
        <section class="mall-card">
            <h1>{{ $order->order_number }}</h1>
            <p class="mall-muted">{{ $order->store?->name }} - {{ $order->user?->name }} - {{ $order->status }}</p>
            <div class="mall-total-row"><span>Subtotal</span><span>R {{ $order->subtotal }}</span></div>
            @if ($order->fulfillment)
                <div class="mall-total-row"><span>Delivery</span><span>{{ $order->fulfillment->label }} - R {{ $order->fulfillment->delivery_fee }}</span></div>
                <div class="mall-total-row"><span>Delivery provider amount</span><span>R {{ $order->fulfillment->provider_amount }}</span></div>
            @endif
            <div class="mall-total-row"><span>Platform fee</span><span>R {{ $order->platform_fee }}</span></div>
            <div class="mall-total-row"><span>Vendor amount</span><span>R {{ $order->vendor_amount }}</span></div>
            <div class="mall-total-row"><span>Total</span><span>R {{ $order->total }}</span></div>
        </section>
        <section class="mall-card">
            <h2>Items</h2>
            @foreach ($order->items as $item)
                <div class="mall-total-row">
                    <span>{{ $item->product_name }} x {{ $item->quantity }}</span>
                    <span>R {{ $item->line_total }}</span>
                </div>
            @endforeach
        </section>
        <section class="mall-card">
            <h2>Payments</h2>
            @foreach ($order->payments as $payment)
                <div class="mall-total-row">
                    <span>{{ $payment->m_payment_id }} - {{ $payment->status }}</span>
                    <span>R {{ $payment->amount }}</span>
                </div>
            @endforeach
        </section>
    </div>
@endsection
