@extends('layouts.public')

@section('title', 'Add Listing | Life Platform')

@section('content')
    <section class="section platform-hero">
        <div class="platform-hero-inner">
            <div>
                <span class="badge">Add Listing</span>
                <h1 class="platform-title">Launch a polished local business listing in minutes.</h1>
                <p class="platform-lede">Choose a 6-month directory package, create your listing starter, and unlock the advertiser gateway for events, banner campaigns, article placements, and push visibility.</p>
                <div class="hero-actions">
                    <a class="button" href="#start-listing">Start your listing <x-icon name="arrow-right" class="w-4 h-4" /></a>
                    <a class="button-link btn-soft" href="{{ route('advertise.index') }}">Compare ad packages</a>
                </div>
            </div>

            <aside class="metric-grid" aria-label="Directory package prices">
                <div class="metric-card">
                    <strong>R{{ number_format($pricing['directory_standard_6m'], 0) }}</strong>
                    <span>Staff-assisted listing for 6 months</span>
                </div>
                <div class="metric-card">
                    <strong>R{{ number_format($pricing['directory_self_service_6m'], 0) }}</strong>
                    <span>Self-service listing for 6 months</span>
                </div>
            </aside>
        </div>
    </section>

    <section class="section">
        <div class="section-head">
            <div>
                <h3>Choose Your Listing Path</h3>
                <p class="muted">Both options unlock the same advertiser gateway. The difference is who captures and manages the listing content.</p>
            </div>
        </div>
        <div class="choice-grid">
            @foreach ($directoryPackages as $package)
                @php($price = $package['current_price'] ?? null)
                <article class="choice-card {{ $package['is_self_service'] ? '' : 'choice-card-featured' }}">
                    <div class="icon-chip">
                        <x-icon name="{{ $package['is_self_service'] ? 'building' : 'sparkles' }}" class="w-5 h-5" />
                    </div>
                    <div>
                        <h4 style="margin:0;">{{ $package['name'] }}</h4>
                        <p class="muted" style="margin:0.5rem 0 0;">{{ $package['description'] }}</p>
                    </div>
                    <p class="stat-number" style="font-size:1.9rem; margin:0;">{{ $price ? $price['currency'].' '.number_format((float) $price['amount'], 2) : 'Price pending' }}</p>
                    <ul class="check-list">
                        @if ($package['is_self_service'])
                            <li>You manage your own listing content.</li>
                            <li>Best for owners who want direct control.</li>
                        @else
                            <li>Staff can capture content by visit, WhatsApp, phone, or form.</li>
                            <li>Best for assisted setup and managed onboarding.</li>
                        @endif
                        <li>Listing runs for {{ $package['duration_days'] }} days.</li>
                    </ul>
                </article>
            @endforeach
        </div>
    </section>

    <section class="section" id="start-listing">
        <article class="card">
            <div class="section-head">
                <div>
                    <h3>Start Your Listing</h3>
                    <p class="muted">Create a starter now, then continue straight into package selection and checkout.</p>
                </div>
            </div>
            @auth
                <form method="post" action="{{ route('add-listing.start') }}" class="form-grid">
                    @csrf
                    <div class="lp-field">
                        <label class="lp-label" for="title">Business name</label>
                        <input class="lp-input" id="title" name="title" value="{{ old('title') }}" placeholder="Your trading name">
                    </div>
                    <div class="lp-field">
                        <label class="lp-label" for="city">Town or city</label>
                        <input class="lp-input" id="city" name="city" value="{{ old('city') }}" placeholder="Bethlehem, Harrismith, Clarens...">
                    </div>
                    <div class="lp-field">
                        <label class="lp-label" for="package_slug">Directory package</label>
                        <select class="lp-select" id="package_slug" name="package_slug">
                            @foreach ($directoryPackages as $package)
                                <option value="{{ $package['slug'] }}" @selected(old('package_slug') === $package['slug'])>{{ $package['name'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <button class="button" type="submit">Create listing starter</button>
                    </div>
                </form>
            @else
                <p class="muted">Login or create an account first, then you can start your listing and continue into checkout.</p>
                <div style="display:flex; gap:0.75rem; flex-wrap:wrap; margin-top:1rem;">
                    <a class="button" href="{{ route('register') }}">Create account</a>
                    <a class="button-link" href="{{ route('login') }}">Login</a>
                </div>
            @endauth
        </article>
    </section>

    <section class="section">
        <div class="choice-grid">
            <article class="choice-card">
                <span class="eyebrow">What happens next</span>
                <ul class="check-list">
                    <li>Create a starter business listing.</li>
                    <li>Choose the right 6-month directory package.</li>
                    <li>Complete checkout and activate the listing journey.</li>
                    <li>Expand into event and campaign products later.</li>
                </ul>
            </article>
            <article class="choice-card">
                <span class="eyebrow">Before checkout</span>
                <p class="muted mb-0">Read the <a href="{{ route('legal.terms') }}">terms and conditions</a>. Once the directory package is active, your business can move into event promotion, banner campaigns, and other visibility products.</p>
            </article>
        </div>
    </section>
@endsection
