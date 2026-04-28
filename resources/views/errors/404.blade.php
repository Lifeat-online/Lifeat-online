@extends('layouts.public')

@section('title', 'Page not found | Life@')

@section('content')
    <section class="section hero">
        <article class="card" style="text-align:center;">
            <span class="badge">404</span>
            <h2>Page not found</h2>
            <p class="muted">The page you're looking for doesn't exist, may have moved, or the address was typed incorrectly.</p>
            <div style="display:flex; gap:0.75rem; flex-wrap:wrap; justify-content:center; margin-top:1.25rem;">
                <a class="button" href="{{ route('home') }}">Back to home</a>
                <a class="button-link" href="{{ route('search.index') }}">Search the platform</a>
            </div>
        </article>
    </section>

    <section class="section">
        <div class="grid grid-3">
            <article class="card">
                <h3>Browse the directory</h3>
                <p class="muted">Find local businesses across the {{ config('app.name') }} region.</p>
                <a href="{{ route('directory.index') }}">Business directory</a>
            </article>
            <article class="card">
                <h3>Upcoming events</h3>
                <p class="muted">See what's happening locally — markets, shows, and community events.</p>
                <a href="{{ route('events.index') }}">Events calendar</a>
            </article>
            <article class="card">
                <h3>Latest articles</h3>
                <p class="muted">Read local editorial coverage, community stories, and regional news.</p>
                <a href="{{ route('articles.index') }}">Latest articles</a>
            </article>
        </div>
    </section>
@endsection
