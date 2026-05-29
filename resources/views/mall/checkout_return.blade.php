@extends('layouts.public')

@section('title', 'Payment Processing')

@include('mall.partials.styles')

@section('content')
    <div class="mall-shell">
        <section class="mall-hero">
            <h1 class="mall-title">Payment processing</h1>
            <p class="mall-subtitle">{{ $message }}</p>
            <a class="mall-button secondary" href="{{ route('mall.stores.index', $store) }}">Back to {{ $store->name }}</a>
        </section>
    </div>
@endsection
