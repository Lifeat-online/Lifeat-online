@extends('layouts.public')

@section('title', 'Mall Store Pending')

@include('mall.partials.styles')

@section('content')
    <section class="mall-hero">
        <h1 class="mall-title">{{ $store->name }}</h1>
        <p class="mall-subtitle">Your mall store is currently {{ $store->status }}.</p>
        <a class="mall-button secondary" href="{{ route('mall.index') }}">Back to Mall</a>
    </section>
@endsection
