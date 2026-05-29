@extends('layouts.public')

@section('title', 'Mall Order '.$order->order_number)

@include('mall.partials.styles')

@section('content')
    <div class="mall-shell">
        @include('mall.vendor.partials.nav')
        <section class="mall-card">
            <h1>{{ $order->order_number }}</h1>
            <p class="mall-muted">{{ $order->user?->name }} - {{ $order->status }} - R {{ $order->total }}</p>
            @if ($order->fulfillment)
                <div class="mall-alert">
                    <strong>{{ $order->fulfillment->label }}</strong>
                    <p class="mall-muted" style="margin:.25rem 0 0;">{{ $order->fulfillment->delivery_address ?: 'No delivery address needed.' }}</p>
                    @if ($order->fulfillment->contact_phone)
                        <p class="mall-muted" style="margin:.25rem 0 0;">{{ $order->fulfillment->contact_phone }}</p>
                    @endif
                </div>
            @endif
            @foreach ($order->items as $item)
                <div class="mall-total-row">
                    <span>{{ $item->product_name }} x {{ $item->quantity }}</span>
                    <span>R {{ $item->line_total }}</span>
                </div>
            @endforeach
        </section>
        <form class="mall-card" method="post" action="{{ route('mall.vendor.orders.status', $order) }}">
            @csrf
            @method('PUT')
            <label>Status
                <select class="mall-select" name="status">
                    @foreach (['paid', 'processing', 'shipped', 'completed', 'cancelled'] as $status)
                        <option value="{{ $status }}" @selected($order->status === $status)>{{ ucfirst($status) }}</option>
                    @endforeach
                </select>
            </label>
            <button class="mall-button" type="submit">Update Status</button>
        </form>
    </div>
@endsection
