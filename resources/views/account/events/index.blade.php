@extends('layouts.public')

@section('title', 'My Events | '.$listing->title)

@section('content')
    <section class="section">
        <div class="section-head">
            <div>
                <h2>Events for {{ $listing->title }}</h2>
                <p class="muted">Manage event drafts, publishable events, and event package handoff from your listing context.</p>
            </div>
            <div style="display:flex; gap:0.75rem; flex-wrap:wrap;">
                <a class="button" href="{{ route('account.listings.events.create', $listing) }}">Create event</a>
                <a class="button-link" href="{{ route('account.listings.show', $listing) }}">Back to listing workspace</a>
            </div>
        </div>
    </section>

    <section class="section">
        @forelse ($events as $event)
            @php
                $latestOrderItem = $event->orderItems->sortByDesc('id')->first();
                $latestOrder = $latestOrderItem?->order;
                $latestInvoice = $latestOrder?->latestInvoice();
                $latestPayment = $latestOrder?->latestPayment();
            @endphp
            <article class="card" style="margin-bottom:1rem;">
                <div class="section-head" style="margin-bottom:0.75rem;">
                    <div>
                        <h3>{{ $event->title }}</h3>
                        <p class="muted">{{ ucfirst($event->status) }} · {{ optional($event->start_at)->format('j M Y g:i A') ?: 'Date pending' }}</p>
                    </div>
                    <div style="display:flex; gap:0.75rem; flex-wrap:wrap;">
                        <a class="button-link" href="{{ route('account.listings.events.edit', [$listing, $event]) }}">Edit event</a>
                        <a class="button-link" href="{{ route('checkout.index', ['event' => $event->slug]) }}">Buy event package</a>
                    </div>
                </div>
                <p class="muted">
                    @if ($event->isPubliclyVisible())
                        Publicly visible with active business and event entitlements.
                    @elseif ($event->status === 'published')
                        Published status is set, but the event still needs active entitlement to appear publicly.
                    @else
                        Draft event. Complete details and buy an event package when ready.
                    @endif
                </p>
                <p class="muted">
                    Event package:
                    @if ($event->activeSubscription)
                        {{ $event->activeSubscription->package?->name ?: 'Package' }} · {{ ucfirst($event->activeSubscription->status) }} · Ends {{ optional($event->activeSubscription->ends_at)->format('j M Y') ?: '-' }}
                    @else
                        No active event package yet.
                    @endif
                </p>
                <p class="muted">
                    Latest payment: {{ ucfirst($latestPayment?->status ?: 'none') }} · Latest invoice: {{ $latestInvoice?->invoice_number ?: '-' }}
                </p>
                <div style="display:flex; gap:0.75rem; flex-wrap:wrap; margin-top:0.75rem;">
                    @if ($event->activeSubscription)
                        <a class="button-link" href="{{ route('checkout.subscriptions.renew', $event->activeSubscription) }}">Renew event package</a>
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
            <div class="empty-state">No events for this listing yet.</div>
        @endforelse

        <div style="margin-top:1rem;">{{ $events->links() }}</div>
    </section>
@endsection
