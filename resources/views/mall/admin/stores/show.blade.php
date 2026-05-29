@extends('layouts.public')

@section('title', 'Mall Admin '.$store->name)

@include('mall.partials.styles')

@section('content')
    <div class="mall-shell">
        @include('mall.admin.partials.nav')
        <section class="mall-hero">
            <h1 class="mall-title">{{ $store->name }}</h1>
            <p class="mall-subtitle">{{ $store->owner?->name }} - {{ $store->status }}</p>
            <div class="mall-form-row">
                <a class="mall-button secondary" href="{{ route('mall.admin.stores.edit', $store) }}">Edit Store</a>
                <form method="post" action="{{ route('mall.admin.stores.approve', $store) }}">
                    @csrf
                    <button class="mall-button" type="submit">Approve</button>
                </form>
                <form method="post" action="{{ route('mall.admin.stores.suspend', $store) }}">
                    @csrf
                    <button class="mall-button danger" type="submit">Suspend</button>
                </form>
            </div>
        </section>
        <div class="mall-grid">
            <div class="mall-card"><span class="mall-muted">Products</span><strong>{{ $store->products->count() }}</strong></div>
            <div class="mall-card"><span class="mall-muted">Orders</span><strong>{{ $store->orders->count() }}</strong></div>
            <div class="mall-card"><span class="mall-muted">PayFast split</span><strong>{{ $store->hasPayFastSplit() ? 'Ready' : 'Manual payout' }}</strong></div>
            <div class="mall-card"><span class="mall-muted">Pickup point</span><strong>{{ $store->pickup_address ?: 'Not configured' }}</strong></div>
        </div>
    </div>
@endsection
