@extends('layouts.public')

@section('title', 'Terms and Conditions | Life Platform')

@section('content')
    <section class="section hero">
        <article class="card">
            <span class="badge">Legal</span>
            <h2>Terms and Conditions</h2>
            <p class="muted">These terms explain the basic platform rules for directory packages, advertising eligibility, payments, invoices, user responsibilities, and account access on Life Platform.</p>
        </article>
        <article class="card">
            <h3>Before you buy</h3>
            <p class="muted">A business directory package is required before you can access event promotion, advertising, or push campaign products. Please read these terms in full before proceeding to checkout.</p>
        </article>
    </section>

    <section class="section">
        <div class="grid grid-2">
            <article class="card">
                <h3>1. Platform Scope</h3>
                <p class="muted">Life Platform combines local editorial content, business discovery, events, classifieds, and advertising opportunities for the Eastern Freestate region.</p>
            </article>
            <article class="card">
                <h3>2. Directory-First Rule</h3>
                <p class="muted">Any business that wants to advertise must first hold an active business directory package. Event packages, advert placements, and push campaigns depend on that active entitlement.</p>
            </article>
        </div>
    </section>

    <section class="section">
        <article class="card stack">
            <div>
                <h3>3. Packages and Pricing</h3>
                <p class="muted">Business directory packages may be staff-assisted or self-service. Package pricing is managed through platform settings and may change over time. Active package visibility, eligibility, and duration follow the package selected at checkout.</p>
            </div>

            <div>
                <h3>4. Payments and Invoices</h3>
                <p class="muted">Checkout, order creation, invoice generation, and payment processing follow the current package catalogue and billing settings. Payment confirmation is required before protected package entitlements become active.</p>
            </div>

            <div>
                <h3>5. Event and Campaign Eligibility</h3>
                <p class="muted">Event promotion and future campaign products are only available where the linked business listing remains eligible under an active directory entitlement and any related package rules.</p>
            </div>

            <div>
                <h3>6. Listing and Content Responsibility</h3>
                <p class="muted">Users remain responsible for the accuracy, legality, and appropriateness of the listing, article, event, or campaign information they submit. The platform may moderate, reject, suspend, or remove material that violates platform rules or legal obligations.</p>
            </div>

            <div>
                <h3>7. Account Access and Security</h3>
                <p class="muted">Users are responsible for keeping their account credentials secure. Access to protected operational areas depends on role-based permissions and platform policies.</p>
            </div>

            <div>
                <h3>8. Refunds, Overrides, and Support</h3>
                <p class="muted">Refunds, package extensions, and finance overrides may be handled through administrative review and platform finance controls. Operational outcomes depend on the payment state, package state, and audit history recorded by the platform.</p>
            </div>

            <div>
                <h3>9. Privacy and Compliance</h3>
                <p class="muted">The platform is expected to maintain privacy, auditability, payment-scope discipline, and access controls. Read the <a href="{{ route('legal.privacy') }}">privacy policy</a> for the current privacy overview.</p>
            </div>
        </article>
    </section>

    <section class="section">
        <article class="card">
            <h3>Questions Before Purchase?</h3>
            <p class="muted">If you need help choosing the correct package path, start with the advertiser journey pages below before continuing to checkout.</p>
            <div style="display:flex; gap:0.75rem; flex-wrap:wrap; margin-top:1rem;">
                <a class="button" href="{{ route('add-listing.index') }}">Start listing</a>
                <a class="button-link" href="{{ route('advertise.index') }}">Advertise with us</a>
                <a class="button-link" href="{{ route('contact.index') }}">Contact support</a>
            </div>
        </article>
    </section>
@endsection
