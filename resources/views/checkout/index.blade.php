@extends('layouts.public')

@section('title', 'Checkout | Life Platform')

@section('content')
    <section class="section">
        <div class="section-head">
            <h2>Checkout</h2>
        </div>
        <article class="card">
            <p class="muted">
                {{ $packageType === 'event_package'
                    ? 'Choose an event package for the selected event. Event packages require an active linked business listing.'
                    : ($packageType === 'advert_package'
                        ? 'Choose an advert package for the selected campaign. Advert packages require an active linked business listing.'
                        : ($packageType === 'push_campaign'
                            ? 'Choose a push package for the selected push campaign. Push packages require an active linked business listing.'
                            : 'Choose a business directory package to begin the paid listing flow.')) }}
            </p>
            <p class="muted">Terms and package rules are available before purchase. <a href="{{ route('legal.terms') }}">Read the terms and conditions</a> and <a href="{{ route('legal.privacy') }}">privacy policy</a>.</p>
            @if ($selectedListing)
                <p><strong>Selected listing:</strong> {{ $selectedListing->title }}</p>
            @endif
            @if ($selectedEvent)
                <p><strong>Selected event:</strong> {{ $selectedEvent->title }}</p>
            @endif
            @if ($selectedCampaign)
                <p><strong>Selected advert campaign:</strong> {{ $selectedCampaign->title }}</p>
            @endif
            @if ($selectedPushCampaign)
                <p><strong>Selected push campaign:</strong> {{ $selectedPushCampaign->title }}</p>
            @endif
            @guest
                <p class="muted">Login is required before an order and invoice can be created.</p>
                <p><a class="button" href="{{ route('login') }}">Login to continue</a></p>
            @endguest
        </article>
    </section>

    <section class="section">
        <div class="grid grid-2">
            @forelse ($packages as $package)
                @php($price = $package->currentPrice())
                <article class="card">
                    <h3>{{ $package->name }}</h3>
                    <p class="muted">{{ $package->description }}</p>
                    <p><strong>{{ $price ? $price->currency.' '.number_format((float) $price->amount, 2) : 'Price pending' }}</strong></p>
                    <p class="muted">{{ ucfirst(str_replace('_', ' ', $package->billing_model)) }} / {{ $package->duration_days }} days</p>
                    <p class="muted">{{ $package->is_self_service ? 'Self-service' : 'Staff-assisted / managed' }}</p>
                    @auth
                        @if ($selectedListing)
                            <form method="post" action="{{ route('checkout.start') }}">
                                @csrf
                                <input type="hidden" name="package_slug" value="{{ $package->slug }}">
                                <input type="hidden" name="listing_slug" value="{{ $selectedListing->slug }}">
                                @if ($selectedEvent)
                                    <input type="hidden" name="event_slug" value="{{ $selectedEvent->slug }}">
                                @endif
                                @if ($selectedCampaign)
                                    <input type="hidden" name="campaign_slug" value="{{ $selectedCampaign->slug }}">
                                @endif
                                @if ($selectedPushCampaign)
                                    <input type="hidden" name="push_campaign_slug" value="{{ $selectedPushCampaign->slug }}">
                                @endif
                                <button class="button" type="submit">Create Order</button>
                            </form>
                        @else
                            <p class="muted">Select a listing first to start checkout.</p>
                        @endif
                    @else
                        <p>
                            <a class="button" href="{{ route('basket.index', array_filter(['package' => $package->slug, 'listing' => $selectedListing?->slug, 'event' => $selectedEvent?->slug, 'campaign' => $selectedCampaign?->slug, 'push_campaign' => $selectedPushCampaign?->slug])) }}">
                                Choose Package
                            </a>
                        </p>
                    @endauth
                </article>
            @empty
                <div class="empty-state">No active packages are available yet.</div>
            @endforelse
        </div>
    </section>
@endsection
