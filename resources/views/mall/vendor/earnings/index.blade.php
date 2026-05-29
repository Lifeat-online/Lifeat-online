@extends('layouts.public')

@section('title', 'Mall Earnings')

@include('mall.partials.styles')

@section('content')
    <div class="mall-shell">
        @include('mall.vendor.partials.nav')
        <h1>Earnings</h1>
        <div class="mall-grid">
            <div class="mall-card"><span class="mall-muted">Gross</span><strong>R {{ $totals['gross'] }}</strong></div>
            <div class="mall-card"><span class="mall-muted">Platform fee</span><strong>R {{ $totals['platform_fee'] }}</strong></div>
            <div class="mall-card"><span class="mall-muted">Vendor amount</span><strong>R {{ $totals['vendor_amount'] }}</strong></div>
        </div>
        <section class="mall-card">
            @forelse ($orders as $order)
                <div class="mall-total-row">
                    <span>{{ $order->order_number }}</span>
                    <span>R {{ $order->vendor_amount }}</span>
                </div>
            @empty
                <p class="mall-muted">No paid orders yet.</p>
            @endforelse
        </section>
        {{ $orders->links() }}
    </div>
@endsection
