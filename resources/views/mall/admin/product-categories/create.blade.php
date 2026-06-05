@extends('layouts.public')

@section('title', 'New Mall Product Category')

@include('mall.partials.styles')

@section('content')
    <div class="mall-shell">
        @include('mall.admin.partials.nav')
        <div class="mall-toolbar">
            <h1>New Product Category</h1>
            <a class="mall-button secondary" href="{{ route('mall.admin.product-categories.index') }}">Back to Categories</a>
        </div>

        <form class="mall-card" method="post" action="{{ route('mall.admin.product-categories.store') }}">
            @include('mall.admin.product-categories._form')
        </form>
    </div>
@endsection
