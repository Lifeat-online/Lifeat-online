@extends('layouts.public')

@section('title', 'My Listings | Life Platform')

@section('content')
    <section class="section">
        <div class="section-head">
            <div>
                <h2>My Listings</h2>
                <p class="muted">Track business listing status, package progress, and renewal readiness from one place.</p>
            </div>
            <div style="display:flex; gap:0.75rem; flex-wrap:wrap;">
                <a class="button" href="{{ route('add-listing.index') }}">Start another listing</a>
                <a class="button-link" href="{{ route('account.index') }}">Back to account</a>
            </div>
        </div>
    </section>

    <section class="section">
        <form method="get" class="card form-grid">
            <div>
                <label for="status">Status</label>
                <select id="status" name="status">
                    <option value="">All statuses</option>
                    @foreach (['draft', 'published'] as $status)
                        <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ ucfirst($status) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <button class="button" type="submit">Filter</button>
                <a class="button-link" href="{{ route('account.listings.index') }}">Reset</a>
            </div>
        </form>
    </section>

    <section class="section">
        @forelse ($listings as $listing)
            @php
                $activeSubscription = $listing->activeSubscription;
                $expiresAt = $listing->package_expires_at ?: $activeSubscription?->ends_at;
                $daysUntilExpiry = $expiresAt ? (int) now()->startOfDay()->diffInDays($expiresAt->copy()->startOfDay(), false) : null;
                $expiresSoon = $activeSubscription && $daysUntilExpiry !== null && $daysUntilExpiry >= 0 && $daysUntilExpiry <= 30;
                $hasExpiredHistory = ! $activeSubscription && $listing->subscriptions->isNotEmpty();
            @endphp
            <article class="card" style="margin-bottom:1rem;">
                <div class="section-head" style="margin-bottom:0.75rem;">
                    <div>
                        <h3><a href="{{ route('account.listings.show', $listing) }}">{{ $listing->title }}</a></h3>
                        <p class="muted">{{ ucfirst($listing->status) }}{{ $listing->city ? ' · '.$listing->city : '' }}</p>
                    </div>
                    <div class="muted">
                        @if ($listing->activeSubscription)
                            Active package
                        @elseif ($listing->subscriptions->isNotEmpty())
                            Subscription history
                        @else
                            Starter only
                        @endif
                    </div>
                </div>
                <p class="muted">
                    @if ($expiresSoon)
                        Package expires soon: {{ $expiresAt->format('j M Y') }}. Renew early to avoid losing public visibility.
                    @elseif ($hasExpiredHistory)
                        Package expired or inactive. Renew to restore public listing visibility.
                    @elseif ($listing->hasActiveBusinessEntitlement())
                        Publicly visible with an active business entitlement.
                    @elseif ($listing->status === 'published')
                        Published status is set, but the listing still needs an active entitlement to stay public.
                    @else
                        Draft listing starter created. Continue package checkout to progress it toward activation.
                    @endif
                </p>
                <div style="display:flex; gap:0.75rem; flex-wrap:wrap;">
                    <a class="button-link" href="{{ route('account.listings.show', $listing) }}">Open listing workspace</a>
                    <a class="button-link" href="{{ route('account.listings.edit', $listing) }}">Edit profile</a>
                    @if ($activeSubscription)
                        <a class="button-link" href="{{ route('checkout.subscriptions.renew', $activeSubscription) }}">Renew package</a>
                    @elseif ($hasExpiredHistory)
                        <a class="button-link" href="{{ route('checkout.index', ['listing' => $listing->slug]) }}">Choose package</a>
                    @endif
                </div>
            </article>
        @empty
            <div class="empty-state">No listings match your current filters.</div>
        @endforelse

        <div style="margin-top:1rem;">{{ $listings->links() }}</div>
    </section>
@endsection
