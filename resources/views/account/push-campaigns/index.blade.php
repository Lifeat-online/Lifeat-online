@extends('layouts.public')

@section('title', 'Push Campaigns | '.$listing->title)

@section('content')
    <section class="section">
        <div class="section-head">
            <div>
                <h2>Push Campaigns for {{ $listing->title }}</h2>
                <p class="muted">Compose scheduled push campaigns tied to this business listing and manage the package handoff in one place.</p>
            </div>
            <div style="display:flex; gap:0.75rem; flex-wrap:wrap;">
                <a class="button" href="{{ route('account.listings.push-campaigns.create', $listing) }}">Create push campaign</a>
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
                        <p class="muted">{{ ucfirst($campaign->status) }} · {{ optional($campaign->schedule_at)->format('j M Y g:i A') ?: 'Schedule pending' }}</p>
                    </div>
                    <div style="display:flex; gap:0.75rem; flex-wrap:wrap;">
                        <a class="button-link" href="{{ route('account.listings.push-campaigns.edit', [$listing, $campaign]) }}">Edit push</a>
                        <a class="button-link" href="{{ route('checkout.index', ['push_campaign' => $campaign->slug]) }}">Buy push package</a>
                        <form method="post" action="{{ route('account.listings.push-campaigns.destroy', [$listing, $campaign]) }}" onsubmit="return confirm('Remove this push campaign?')">
                            @csrf
                            @method('DELETE')
                            <button class="button-link" type="submit">Delete</button>
                        </form>
                    </div>
                </div>
                <p class="muted">
                    @if ($campaign->sent_at)
                        Push campaign was delivered on {{ $campaign->sent_at->format('j M Y g:i A') }}.
                    @elseif ($campaign->isOperational())
                        Push campaign is scheduled or active with valid listing and push entitlements.
                    @elseif ($campaign->status === 'active' || $campaign->status === 'scheduled')
                        Push campaign status is set, but it still needs valid listing and push entitlements.
                    @else
                        Draft or ready campaign awaiting package purchase and activation.
                    @endif
                </p>
                <p class="muted">Audience: {{ $campaign->audienceSummary() }}</p>
                <p class="muted">
                    Push package:
                    @if ($campaign->activeSubscription)
                        {{ $campaign->activeSubscription->package?->name ?: 'Package' }} · {{ ucfirst($campaign->activeSubscription->status) }} · Ends {{ optional($campaign->activeSubscription->ends_at)->format('j M Y') ?: '-' }}
                    @else
                        No active push package yet.
                    @endif
                </p>
                <p class="muted">
                    Latest payment: {{ ucfirst($latestPayment?->status ?: 'none') }} · Latest invoice: {{ $latestInvoice?->invoice_number ?: '-' }} · Delivery logs: {{ $campaign->notificationLogs->where('channel', 'push')->count() }}
                </p>
                <div style="display:flex; gap:0.75rem; flex-wrap:wrap; margin-top:0.75rem;">
                    @if ($campaign->activeSubscription)
                        <a class="button-link" href="{{ route('checkout.subscriptions.renew', $campaign->activeSubscription) }}">Renew push package</a>
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
            <div class="empty-state">No push campaigns for this listing yet.</div>
        @endforelse

        <div style="margin-top:1rem;">{{ $campaigns->links() }}</div>
    </section>
@endsection
