@extends('layouts.public')

@section('title', 'Classifieds | Life Platform')

@section('content')
    <section class="section">
        <div class="section-head">
            <div>
                <h2>Classifieds</h2>
                <p class="muted">Browse free product and service listings.</p>
            </div>
            <div style="display:flex; gap:0.75rem; flex-wrap:wrap;">
                @auth
                    <a class="button" href="{{ route('classifieds.manage.create') }}">Post a classified</a>
                    <a class="button-link" href="{{ route('classifieds.manage.index') }}">My classifieds</a>
                @else
                    <a class="button" href="{{ route('login') }}">Login to post</a>
                @endauth
            </div>
        </div>
        <form method="get" class="card form-grid">
            <div>
                <label for="q">Search</label>
                <input id="q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search items or services">
            </div>
            <div>
                <button class="button" type="submit">Filter</button>
            </div>
        </form>
    </section>

    <section class="section">
        @forelse ($classifieds as $item)
            @if ($loop->first)<div class="grid grid-2">@endif
            <article class="card">
                <h3><a href="{{ route('classifieds.show', $item) }}">{{ $item->localizedValue('title') }}</a></h3>
                <p class="muted">{{ $item->localizedValue('city') ?: 'Location' }}</p>
                <p>
                    @if ($item->contact_for_price)
                        Contact for price
                    @elseif (! is_null($item->price))
                        {{ $item->currency }} {{ number_format($item->price, 2) }}
                    @else
                        -
                    @endif
                </p>
            </article>
            @if ($loop->last)</div>@endif
        @empty
            <div class="empty-state">No classifieds match your filters.</div>
        @endforelse

        <div style="margin-top: 1rem;">{{ $classifieds->links() }}</div>
    </section>
@endsection
