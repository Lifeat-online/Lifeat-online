@extends('layouts.public')

@section('title', 'New Product Category')

@include('mall.partials.styles')

@section('content')
    <div class="mall-shell">
        @include('mall.vendor.partials.nav')
        <div class="mall-toolbar">
            <div>
                <h1>New Product Category</h1>
                <p class="mall-muted" style="margin:0;">{{ $store->name }}</p>
            </div>
            <a class="mall-button secondary" href="{{ route('mall.vendor.product-categories.index') }}">Back to Categories</a>
        </div>

        <form class="mall-card" method="post" action="{{ route('mall.vendor.product-categories.store') }}">
            @include('mall.vendor.product-categories._form')
        </form>
    </div>
@endsection
