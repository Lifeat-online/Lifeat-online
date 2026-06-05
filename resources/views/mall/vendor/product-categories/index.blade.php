@extends('layouts.public')

@section('title', 'Product Categories')

@include('mall.partials.styles')

@section('content')
    <div class="mall-shell">
        @include('mall.vendor.partials.nav')
        <div class="mall-toolbar">
            <div>
                <h1>Product Categories</h1>
                <p class="mall-muted" style="margin:0;">{{ $store->name }}</p>
            </div>
            <div class="mall-form-row">
                <form method="get" class="mall-form-row">
                    <input class="mall-input" type="search" name="q" value="{{ request('q') }}" placeholder="Search categories">
                    <button class="mall-button" type="submit">Filter</button>
                </form>
                <a class="mall-button secondary" href="{{ route('mall.vendor.product-categories.create') }}">New Category</a>
            </div>
        </div>

        @if (session('status')) <div class="mall-alert">{{ session('status') }}</div> @endif

        <section class="mall-card">
            @forelse ($categories as $category)
                <div class="mall-total-row">
                    <div>
                        <a href="{{ route('mall.vendor.product-categories.edit', $category->id) }}">{{ $category->name }}</a>
                        <div class="mall-muted">{{ $category->slug }} - sort {{ $category->sort_order }}</div>
                    </div>
                    <span>{{ $category->products_count }} products</span>
                </div>
            @empty
                <p class="mall-muted">No product categories yet.</p>
            @endforelse
        </section>

        {{ $categories->links() }}
    </div>
@endsection
