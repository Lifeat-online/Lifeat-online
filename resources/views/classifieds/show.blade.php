@extends('layouts.public')

@section('title', $classified->title.' | Classifieds')

@section('content')
    <section class="section detail-grid">
        <div class="stack">
            <article class="card">
                <div class="meta">
                    <span>{{ $classified->city ?: 'Location' }}</span>
                </div>
                @if ($classified->featured_image)
                    <img src="{{ \Illuminate\Support\Facades\Storage::url($classified->featured_image) }}" alt="{{ $classified->title }}" loading="lazy" decoding="async" style="width:100%; height:280px; object-fit:cover; border-radius:12px; margin:0 0 1rem;">
                @endif
                <h2>{{ $classified->title }}</h2>
                <p class="muted">
                    @if ($classified->contact_for_price)
                        Contact for price
                    @elseif (! is_null($classified->price))
                        {{ $classified->currency }} {{ number_format($classified->price, 2) }}
                    @endif
                </p>
                <div>{!! nl2br(e($classified->description ?: 'Details coming soon.')) !!}</div>
            </article>
        </div>
        <aside class="stack">
            <article class="card">
                <h3>Item details</h3>
                <p class="muted">Region: {{ $classified->region ?: '-' }}</p>
                <p class="muted">Country: {{ $classified->country ?: '-' }}</p>
            </article>
        </aside>
    </section>
@endsection
