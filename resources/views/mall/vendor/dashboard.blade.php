@extends('layouts.public')

@section('title', 'Mall Vendor Dashboard')

@include('mall.partials.styles')

@section('content')
    <div class="mall-shell">
        @include('mall.vendor.partials.nav')
        <section class="mall-hero">
            <h1 class="mall-title">{{ $store->name }}</h1>
            <p class="mall-subtitle">Vendor dashboard</p>
        </section>
        <div class="mall-grid">
            @foreach ($stats as $label => $value)
                <div class="mall-card">
                    <span class="mall-muted">{{ str_replace('_', ' ', ucfirst($label)) }}</span>
                    <strong style="font-size:1.6rem;">{{ is_numeric($value) ? $value : $value }}</strong>
                </div>
            @endforeach
        </div>
        <section class="mall-card">
            <h2>Recent orders</h2>
            @forelse ($recentOrders as $order)
                <div class="mall-total-row">
                    <a href="{{ route('mall.vendor.orders.show', $order) }}">{{ $order->order_number }}</a>
                    <span>R {{ $order->total }} - {{ $order->status }}</span>
                </div>
            @empty
                <p class="mall-muted">No orders yet.</p>
            @endforelse
        </section>
    </div>
@endsection
