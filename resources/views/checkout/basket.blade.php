@extends('layouts.public')

@section('title', 'Basket | Life Platform')

@section('content')
    <section class="section">
        <div class="section-head">
            <h2>Basket</h2>
        </div>
        <article class="card">
            @if ($package)
                <p><strong>Package:</strong> {{ $package->name }}</p>
                <p class="muted">{{ $package->description }}</p>
                @if ($listing)
                    <p><strong>Listing:</strong> {{ $listing->title }}</p>
                @endif
                @if ($event)
                    <p><strong>Event:</strong> {{ $event->title }}</p>
                @endif
                @if ($campaign)
                    <p><strong>Advert campaign:</strong> {{ $campaign->title }}</p>
                @endif
                @if ($pushCampaign)
                    <p><strong>Push campaign:</strong> {{ $pushCampaign->title }}</p>
                @endif
                <form method="get" action="{{ route('checkout.index') }}" style="margin-top: 1rem;">
                    <input type="hidden" name="package" value="{{ $package->slug }}">
                    @if ($listing)
                        <input type="hidden" name="listing" value="{{ $listing->slug }}">
                    @endif
                    @if ($event)
                        <input type="hidden" name="event" value="{{ $event->slug }}">
                    @endif
                    @if ($campaign)
                        <input type="hidden" name="campaign" value="{{ $campaign->slug }}">
                    @endif
                    @if ($pushCampaign)
                        <input type="hidden" name="push_campaign" value="{{ $pushCampaign->slug }}">
                    @endif
                    <button class="button" type="submit">Proceed to checkout</button>
                </form>
            @else
                <p class="muted">Your selected packages will appear here before checkout.</p>
                <p><a class="button" href="{{ route('checkout.index') }}">Choose a package</a></p>
            @endif
        </article>
    </section>
@endsection
