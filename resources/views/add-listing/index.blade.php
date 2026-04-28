@extends('layouts.public')

@section('title', 'Add Listing | Life Platform')

@section('content')
    <section class="section hero">
        <article class="card">
            <span class="badge">Add Listing</span>
            <h2>Launch your business listing the right way</h2>
            <p class="muted">This is the first step for advertisers on the platform. Choose the directory package that matches how you want your listing managed, then continue into checkout.</p>
            <div class="stats">
                <div class="card">
                    <div class="stat-number">R{{ number_format($pricing['directory_standard_6m'], 0) }}</div>
                    <div>Staff-assisted listing for 6 months</div>
                </div>
                <div class="card">
                    <div class="stat-number">R{{ number_format($pricing['directory_self_service_6m'], 0) }}</div>
                    <div>Self-service listing for 6 months</div>
                </div>
            </div>
            <p class="muted" style="margin-top:1rem;">Once the directory package is active, your business can move into event promotion, banner campaigns, and other visibility products.</p>
            <p class="muted">Read the <a href="{{ route('legal.terms') }}">terms and conditions</a> before starting checkout.</p>
        </article>
        <article class="card">
            <h3>What happens next</h3>
            <ol style="margin:0; padding-left:1.25rem;">
                <li>Create a starter business listing.</li>
                <li>Choose the right 6-month directory package.</li>
                <li>Complete checkout and activate the listing journey.</li>
                <li>Expand into event and campaign products later.</li>
            </ol>
        </article>
    </section>

    <section class="section">
        <div class="section-head">
            <div>
                <h3>Choose Your Listing Path</h3>
                <p class="muted">Both options unlock the same advertiser gateway. The difference is who captures and manages the listing content.</p>
            </div>
        </div>
        <div class="grid grid-2">
            @foreach ($directoryPackages as $package)
                @php($price = $package->currentPrice())
                <article class="card">
                    <h4>{{ $package->name }}</h4>
                    <p class="muted">{{ $package->description }}</p>
                    <p><strong>{{ $price ? $price->currency.' '.number_format((float) $price->amount, 2) : 'Price pending' }}</strong></p>
                    <ul style="padding-left:1.25rem; margin:0.75rem 0 0;">
                        @if ($package->is_self_service)
                            <li>You manage your own listing content.</li>
                            <li>Best for owners who want direct control.</li>
                        @else
                            <li>Staff can capture content by visit, WhatsApp, phone, or form.</li>
                            <li>Best for assisted setup and managed onboarding.</li>
                        @endif
                        <li>Listing runs for {{ $package->duration_days }} days.</li>
                    </ul>
                </article>
            @endforeach
        </div>
    </section>

    <section class="section">
        <article class="card">
            <h3>Start Your Listing</h3>
            @auth
                <p class="muted">Create a draft listing starter now, then continue straight into package selection and checkout.</p>
                <form method="post" action="{{ route('add-listing.start') }}" class="form-grid">
                    @csrf
                    <div>
                        <label for="title">Business name</label>
                        <input id="title" name="title" value="{{ old('title') }}" placeholder="Your trading name">
                    </div>
                    <div>
                        <label for="city">Town or city</label>
                        <input id="city" name="city" value="{{ old('city') }}" placeholder="Bethlehem, Harrismith, Clarens...">
                    </div>
                    <div>
                        <label for="package_slug">Directory package</label>
                        <select id="package_slug" name="package_slug">
                            @foreach ($directoryPackages as $package)
                                <option value="{{ $package->slug }}" @selected(old('package_slug') === $package->slug)>{{ $package->name }}</option>
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
@endsection
