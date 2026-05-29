@extends('layouts.public')

@section('title', 'Mall Admin Stores')

@include('mall.partials.styles')

@section('content')
    <div class="mall-shell">
        @include('mall.admin.partials.nav')
        <div class="mall-toolbar">
            <h1>Mall Stores</h1>
            <form method="get" class="mall-form-row">
                <select class="mall-select" name="status">
                    <option value="">All statuses</option>
                    @foreach (['pending', 'active', 'suspended', 'closed'] as $status)
                        <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
                    @endforeach
                </select>
                <button class="mall-button" type="submit">Filter</button>
            </form>
        </div>
        @if (session('status')) <div class="mall-alert">{{ session('status') }}</div> @endif
        <section class="mall-card">
            @forelse ($stores as $store)
                <div class="mall-total-row">
                    <a href="{{ route('mall.admin.stores.show', $store) }}">{{ $store->name }}</a>
                    <span>{{ $store->status }} - {{ $store->products_count }} products - {{ $store->orders_count }} orders</span>
                </div>
            @empty
                <p class="mall-muted">No mall stores yet.</p>
            @endforelse
        </section>
        {{ $stores->links() }}
    </div>
@endsection
