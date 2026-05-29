@extends('layouts.public')

@section('title', 'Mall Order '.$order->order_number)

@include('mall.partials.styles')

@section('content')
    <div class="mall-shell">
        <a class="mall-button secondary" href="{{ route('mall.account.orders.index') }}" style="width:max-content;">Back to Orders</a>
        <section class="mall-card">
            <h1>{{ $order->order_number }}</h1>
            <p class="mall-muted">{{ $order->store?->name }} - {{ $order->status }}</p>
            @if ($order->fulfillment)
                <div class="mall-alert">
                    <strong>{{ $order->fulfillment->label }}</strong>
                    <p class="mall-muted" style="margin:.25rem 0 0;">Delivery fee: R {{ $order->fulfillment->delivery_fee }}</p>
                </div>
            @endif
            @foreach ($order->items as $item)
                <div class="mall-total-row">
                    <span>{{ $item->product_name }} x {{ $item->quantity }}</span>
                    <span>R {{ $item->line_total }}</span>
                </div>
            @endforeach
            <div class="mall-total-row"><span>Total</span><span>R {{ $order->total }}</span></div>
        </section>
    </div>
@endsection
