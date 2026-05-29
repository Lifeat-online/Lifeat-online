@extends('layouts.public')

@section('title', 'Mall Orders')

@include('mall.partials.styles')

@section('content')
    <div class="mall-shell">
        @include('mall.vendor.partials.nav')
        <h1>Orders</h1>
        <section class="mall-card">
            @forelse ($orders as $order)
                <div class="mall-total-row">
                    <a href="{{ route('mall.vendor.orders.show', $order) }}">{{ $order->order_number }}</a>
                    <span>{{ $order->status }} - R {{ $order->total }}</span>
                </div>
            @empty
                <p class="mall-muted">No orders yet.</p>
            @endforelse
        </section>
        {{ $orders->links() }}
    </div>
@endsection
