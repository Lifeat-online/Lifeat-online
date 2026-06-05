@extends('layouts.public')

@section('title', 'Edit Product Category')

@include('mall.partials.styles')

@section('content')
    <div class="mall-shell">
        @include('mall.vendor.partials.nav')
        <div class="mall-toolbar">
            <div>
                <h1>Edit {{ $category->name }}</h1>
                <p class="mall-muted" style="margin:0;">{{ $store->name }} - {{ $category->products_count }} products</p>
            </div>
            <a class="mall-button secondary" href="{{ route('mall.vendor.product-categories.index') }}">Back to Categories</a>
        </div>

        <form class="mall-card" method="post" action="{{ route('mall.vendor.product-categories.update', $category->id) }}">
            @include('mall.vendor.product-categories._form')
        </form>

        <form method="post" action="{{ route('mall.vendor.product-categories.destroy', $category->id) }}" onsubmit="return confirm('Delete this product category?');">
            @csrf
            @method('DELETE')
            <button class="mall-button danger" type="submit" @disabled($category->products_count > 0)>Delete Category</button>
        </form>
    </div>
@endsection
