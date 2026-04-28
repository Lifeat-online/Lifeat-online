@extends('layouts.public')

@section('title', 'Advertise | Life Platform')

@section('content')
    <section class="section hero">
        <article class="card">
            <span class="badge">Advertiser Growth</span>
            <h2>Advertise With Us</h2>
            <p class="muted">Start with a business directory package, then unlock event promotion, in-article placements, banners, and geo-aware push campaigns.</p>
            <div class="stats">
                <div class="card">
                    <div class="stat-number">R{{ number_format($pricing['directory_standard_6m'], 0) }}</div>
                    <div>Staff-assisted directory package for 6 months</div>
                </div>
                <div class="card">
                    <div class="stat-number">R{{ number_format($pricing['directory_self_service_6m'], 0) }}</div>
                    <div>Self-service directory package for 6 months</div>
                </div>
                <div class="card">
                    <div class="stat-number">R{{ number_format($pricing['event_one_off'], 0) }}</div>
                    <div>One-off event upsell once your directory package is active</div>
                </div>
            </div>
            <div style="display:flex; gap:0.75rem; flex-wrap:wrap; margin-top:1.25rem;">
                <a class="button" href="{{ route('add-listing.index') }}">Start with a directory package</a>
                <a class="button-link" href="{{ route('directory.index') }}">See live business listings</a>
            </div>
        </article>
        <article class="card">
            <h3>How the monetisation ladder works</h3>
            <ol style="margin: 0; padding-left: 1.25rem;">
                <li>Buy a Business Directory package first.</li>
                <li>Publish a stronger local business profile.</li>
                <li>Add event promotion for eligible listings.</li>
                <li>Layer in banner, in-article, and push visibility.</li>
            </ol>
            <p class="muted" style="margin-top: 1rem;">Starting with a directory package establishes your business presence on the platform and unlocks access to all other advertising products.</p>
        </article>
    </section>

    <section class="section">
        <div class="section-head">
            <div>
                <h3>Directory Packages</h3>
                <p class="muted">These packages are the mandatory first step for advertisers and business promotion.</p>
            </div>
        </div>
        <div class="grid grid-2">
            @forelse ($directoryPackages as $package)
                @php($price = $package->currentPrice())
                <article class="card">
                    <h4>{{ $package->name }}</h4>
                    <p class="muted">{{ $package->description }}</p>
                    <p><strong>{{ $price ? $price->currency.' '.number_format((float) $price->amount, 2) : 'Price pending' }}</strong></p>
                    <p class="muted">{{ $package->is_self_service ? 'Self-service workflow' : 'Staff-assisted sales workflow' }}</p>
                    <p class="muted">{{ $package->duration_days }} days of listing visibility and advertiser eligibility</p>
                    <p><a class="button" href="{{ route('checkout.index', ['package' => $package->slug]) }}">Choose this package</a></p>
                </article>
            @empty
                <div class="empty-state">Directory package setup is still being finalised.</div>
            @endforelse
        </div>
    </section>

    <section class="section">
        <div class="section-head">
            <div>
                <h3>Event Add-Ons</h3>
                <p class="muted">Once your directory package is active, you can promote business-linked events through dedicated event packages.</p>
            </div>
        </div>
        <div class="grid grid-2">
            @forelse ($eventPackages as $package)
                @php($price = $package->currentPrice())
                <article class="card">
                    <h4>{{ $package->name }}</h4>
                    <p class="muted">{{ $package->description }}</p>
                    <p><strong>{{ $price ? $price->currency.' '.number_format((float) $price->amount, 2) : 'Price pending' }}</strong></p>
                    <p class="muted">{{ ucfirst(str_replace('_', ' ', $package->billing_model)) }}</p>
                    <p class="muted">Requires an active linked business listing before checkout.</p>
                    <p><a class="button-link" href="{{ route('events.index') }}">See event examples</a></p>
                </article>
            @empty
                <div class="empty-state">Event add-on packages are not active yet.</div>
            @endforelse
        </div>
    </section>

    <section class="section">
        <div class="section-head">
            <div>
                <h3>Campaign Expansion</h3>
                <p class="muted">After the business package is live, advertisers can expand into higher-visibility products.</p>
            </div>
        </div>
        <div class="grid grid-3">
            <article class="card">
                <h4>In-Article Placements</h4>
                <p class="muted">Promote inside editorial content with paragraph-one, mid-article, and end-of-article inventory.</p>
            </article>
            <article class="card">
                <h4>Banner Campaigns</h4>
                <p class="muted">Use homepage, sidebar, mobile sticky, and other display inventory to keep local brands visible.</p>
            </article>
            <article class="card">
                <h4>Push Campaigns</h4>
                <p class="muted">
                    Premium geo-targeted notification promotion
                    @if ($pricing['push_notification'] > 0)
                        from <strong>R{{ number_format($pricing['push_notification'], 2) }}</strong>.
                    @else
                        with admin-managed pricing.
                    @endif
                </p>
            </article>
        </div>
    </section>

    <section class="section">
        <article class="card">
            <h3>Recommended Next Step</h3>
            <p class="muted">If you want visibility on the platform, begin with the directory package that matches how you want your listing managed, then continue into events and campaigns from there.</p>
            <div style="display:flex; gap:0.75rem; flex-wrap:wrap; margin-top:1rem;">
                <a class="button" href="{{ route('add-listing.index') }}">Start your listing</a>
                <a class="button-link" href="{{ route('staff-signup.create') }}">Talk to staff / join the sales flow</a>
                <a class="button-link" href="{{ route('contact.index') }}">Contact support</a>
            </div>
        </article>
    </section>
@endsection
