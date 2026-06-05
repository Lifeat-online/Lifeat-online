@extends('layouts.public')

@section('title', 'Mall Product Categories')

@include('mall.partials.styles')

@section('content')
    <div class="mall-shell">
        @include('mall.admin.partials.nav')
        <div class="mall-toolbar">
            <h1>Product Categories</h1>
            <div class="mall-form-row">
                <form method="get" class="mall-form-row">
                    <input class="mall-input" type="search" name="q" value="{{ request('q') }}" placeholder="Search categories">
                    <select class="mall-select" name="store_id">
                        <option value="">All stores</option>
                        @foreach ($stores as $store)
                            <option value="{{ $store->id }}" @selected((string) request('store_id') === (string) $store->id)>{{ $store->name }}</option>
                        @endforeach
                    </select>
                    <button class="mall-button" type="submit">Filter</button>
                </form>
                <a class="mall-button secondary" href="{{ route('mall.admin.product-categories.create', request('store_id') ? ['store_id' => request('store_id')] : []) }}">New Category</a>
            </div>
        </div>

        @if (session('status')) <div class="mall-alert">{{ session('status') }}</div> @endif

        <section class="mall-card">
            @forelse ($categories as $category)
                <div class="mall-total-row">
                    <div>
                        <a href="{{ route('mall.admin.product-categories.edit', $category->id) }}">{{ $category->name }}</a>
                        <div class="mall-muted">{{ $category->store?->name }} - {{ $category->slug }} - sort {{ $category->sort_order }}</div>
                    </div>
                    <span>{{ $category->products_count }} products</span>
                </div>
            @empty
                <p class="mall-muted">No mall product categories match this view.</p>
            @endforelse
        </section>

        {{ $categories->links() }}
    </div>
@endsection
