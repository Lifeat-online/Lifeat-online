@extends('layouts.public')

@section('title', 'Mall Commissions')

@include('mall.partials.styles')

@section('content')
    <div class="mall-shell">
        @include('mall.admin.partials.nav')
        <h1>Mall Commissions</h1>
        <div class="mall-grid">
            <div class="mall-card"><span class="mall-muted">Paid orders</span><strong>{{ $totals['orders'] }}</strong></div>
            <div class="mall-card"><span class="mall-muted">Gross</span><strong>R {{ $totals['gross'] }}</strong></div>
            <div class="mall-card"><span class="mall-muted">Platform fee</span><strong>R {{ $totals['platform_fee'] }}</strong></div>
            <div class="mall-card"><span class="mall-muted">Vendor amount</span><strong>R {{ $totals['vendor_amount'] }}</strong></div>
        </div>
        <section class="mall-card">
            @forelse ($stores as $row)
                <div class="mall-total-row">
                    <span>{{ $row->store?->name ?? 'Deleted store' }} - {{ $row->orders_count }} orders</span>
                    <span>R {{ $row->platform_total }} platform fee</span>
                </div>
            @empty
                <p class="mall-muted">No paid mall orders yet.</p>
            @endforelse
        </section>
    </div>
@endsection
