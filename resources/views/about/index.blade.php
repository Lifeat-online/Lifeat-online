@extends('layouts.public')

@section('title', 'About Life@ | Eastern Freestate Local Guide')

@section('content')
    <section class="section hero">
        <article class="card">
            <span class="badge">About</span>
            <h2>About Life@</h2>
            <p class="muted">Life@ News is a local digital platform serving the {{ $contact['region'] }} region — blending editorial news coverage with a business directory, event calendar, community classifieds, and direct advertising opportunities.</p>
            <div style="display:flex; gap:0.75rem; flex-wrap:wrap; margin-top:1rem;">
                <a class="button" href="{{ route('directory.index') }}">Browse the directory</a>
                <a class="button-link" href="{{ route('advertise.index') }}">Advertise with us</a>
            </div>
        </article>
        <article class="card">
            <h3>Platform by the numbers</h3>
            <p class="muted">A live snapshot of the content and coverage on the platform today.</p>
            <div style="display:flex; gap:2rem; margin-top:1rem; flex-wrap:wrap;">
                <div>
                    <p style="font-size:2rem; font-weight:700; margin:0;">{{ number_format($stats['listings']) }}</p>
                    <p class="muted" style="margin:0;">Business listings</p>
                </div>
                <div>
                    <p style="font-size:2rem; font-weight:700; margin:0;">{{ number_format($stats['events']) }}</p>
                    <p class="muted" style="margin:0;">Published events</p>
                </div>
                <div>
                    <p style="font-size:2rem; font-weight:700; margin:0;">{{ number_format($stats['articles']) }}</p>
                    <p class="muted" style="margin:0;">Articles published</p>
                </div>
            </div>
        </article>
    </section>

    <section class="section">
        <div class="grid grid-2">
            <article class="card">
                <h3>What we cover</h3>
                <p class="muted">Life@ News exists to be the digital front door for the {{ $contact['region'] }}: local editorial journalism, curated business discovery, community events, and practical promotion paths for local businesses.</p>
                <ul style="margin:0.75rem 0 0; padding-left:1.25rem;">
                    <li>Local news, features, and editorial coverage</li>
                    <li>A searchable business directory for the region</li>
                    <li>A community events calendar</li>
                    <li>Community classifieds for buying and selling</li>
                    <li>Direct advertising packages for local businesses</li>
                </ul>
            </article>
            <article class="card">
                <h3>Who it is for</h3>
                <p class="muted">The platform serves three overlapping audiences in the {{ $contact['region'] }} region.</p>
                <ul style="margin:0.75rem 0 0; padding-left:1.25rem;">
                    <li><strong>Residents</strong> — local news, event discovery, and community classifieds</li>
                    <li><strong>Businesses</strong> — directory listings, event promotion, and ad campaigns</li>
                    <li><strong>Writers and contributors</strong> — an editorial workflow for local content creation</li>
                </ul>
            </article>
        </div>
    </section>

    <section class="section">
        <div class="grid grid-3">
            <article class="card">
                <h3>Get listed</h3>
                <p class="muted">Add your business to the directory and choose the package that fits your growth goals — from a basic listing through to featured placement and ad campaigns.</p>
                <a href="{{ route('add-listing.index') }}">Start here</a>
            </article>
            <article class="card">
                <h3>Advertise</h3>
                <p class="muted">Reach a local audience through targeted ad placements and push notifications. Learn about advertising options and get in touch to start a campaign.</p>
                <a href="{{ route('advertise.index') }}">See ad options</a>
            </article>
            <article class="card">
                <h3>Write with us</h3>
                <p class="muted">We welcome local journalists, community contributors, and specialist writers. Apply to join the editorial team and start contributing local coverage.</p>
                <a href="{{ route('staff-signup.create') }}">Apply as writer</a>
            </article>
        </div>
    </section>

    <section class="section">
        <article class="card">
            <h3>Get in touch</h3>
            <p class="muted">For listing help, package questions, advertising enquiries, or editorial matters, reach the team directly.</p>
            <div style="display:flex; gap:0.75rem; flex-wrap:wrap; margin-top:0.75rem;">
                <a class="button" href="{{ route('contact.index') }}">Contact us</a>
                <a class="button-link" href="mailto:{{ $contact['email'] }}">{{ $contact['email'] }}</a>
            </div>
        </article>
    </section>
@endsection
