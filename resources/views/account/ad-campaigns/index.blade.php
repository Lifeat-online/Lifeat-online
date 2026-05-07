@extends('layouts.public')

@section('title', 'Advert Campaigns | '.$listing->title)

@section('content')
    <section class="section">
        <div class="section-head">
            <div>
                <h2>Advert Campaigns for {{ $listing->title }}</h2>
                <p class="muted">Create and track owner-managed advert campaigns tied to this active business listing.</p>
            </div>
            <div style="display:flex; gap:0.75rem; flex-wrap:wrap;">
                <a class="button" href="{{ route('account.listings.ad-campaigns.create', $listing) }}">Create advert campaign</a>
                <a class="button-link" href="{{ route('account.listings.show', $listing) }}">Back to listing workspace</a>
            </div>
        </div>
    </section>

    <section class="section">
        @forelse ($campaigns as $campaign)
            @php
                $latestOrderItem = $campaign->orderItems->sortByDesc('id')->first();
                $latestOrder = $latestOrderItem?->order;
                $latestInvoice = $latestOrder?->latestInvoice();
                $latestPayment = $latestOrder?->latestPayment();
            @endphp
            <article class="card" style="margin-bottom:1rem;">
                <div class="section-head" style="margin-bottom:0.75rem;">
                    <div>
                        <h3>{{ $campaign->title }}</h3>
                        <p class="muted">{{ ucfirst($campaign->status) }} · {{ optional($campaign->start_at)->format('j M Y') ?: 'Start pending' }}</p>
                    </div>
                    <div style="display:flex; gap:0.75rem; flex-wrap:wrap;">
                        <a class="button-link" href="{{ route('account.listings.ad-campaigns.edit', [$listing, $campaign]) }}">Edit campaign</a>
                        <a class="button-link" href="{{ route('checkout.index', ['campaign' => $campaign->slug]) }}">Buy advert package</a>
                        <form method="post" action="{{ route('account.listings.ad-campaigns.destroy', [$listing, $campaign]) }}" onsubmit="return confirm('Remove this advert campaign?')">
                            @csrf
                            @method('DELETE')
                            <button class="button-link" type="submit">Delete</button>
                        </form>
                    </div>
                </div>
                <p class="muted">
                    @if ($campaign->isOperational())
                        Campaign is operational with active listing and advert entitlements.
                    @elseif ($campaign->status === 'active')
                        Campaign is marked active, but it still needs valid listing and advert entitlements to operate.
                    @else
                        Draft or ready campaign awaiting package purchase and activation.
                    @endif
                </p>
                <p class="muted">
                    Advert package:
                    @if ($campaign->activeSubscription)
                        {{ $campaign->activeSubscription->package?->name ?: 'Package' }} · {{ ucfirst($campaign->activeSubscription->status) }} · Ends {{ optional($campaign->activeSubscription->ends_at)->format('j M Y') ?: '-' }}
                    @else
                        No active advert package yet.
                    @endif
                </p>
                <p class="muted">
                    Latest payment: {{ ucfirst($latestPayment?->status ?: 'none') }} · Latest invoice: {{ $latestInvoice?->invoice_number ?: '-' }}
                </p>
                <div style="display:flex; gap:0.75rem; flex-wrap:wrap; margin-top:0.75rem;">
                    @if ($campaign->activeSubscription)
                        <a class="button-link" href="{{ route('checkout.subscriptions.renew', $campaign->activeSubscription) }}">Renew advert package</a>
                    @endif
                    @if ($latestOrder)
                        <a class="button-link" href="{{ route('checkout.show', $latestOrder) }}">Open latest order</a>
                    @endif
                    @if ($latestInvoice)
                        <a class="button-link" href="{{ route('account.invoices.show', $latestInvoice) }}">Open latest invoice</a>
                    @endif
                </div>
            </article>
        @empty
            <div class="empty-state">No advert campaigns for this listing yet.</div>
        @endforelse

        <div style="margin-top:1rem;">{{ $campaigns->links() }}</div>
    </section>
@endsection
