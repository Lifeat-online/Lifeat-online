@extends('layouts.public')

@section('title', 'My Mall Orders')

@include('mall.partials.styles')

@section('content')
    <div class="mall-shell">
        <h1>My Mall Orders</h1>
        <section class="mall-card">
            @forelse ($orders as $order)
                <div class="mall-total-row">
                    <a href="{{ route('mall.account.orders.show', $order) }}">{{ $order->order_number }}</a>
                    <span>{{ $order->store?->name }} - {{ $order->status }} - R {{ $order->total }}</span>
                </div>
            @empty
                <p class="mall-muted">No mall orders yet.</p>
            @endforelse
        </section>
        {{ $orders->links() }}
    </div>
@endsection
