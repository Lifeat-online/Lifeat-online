@extends('layouts.public')

@section('title', 'Privacy Policy | Life Platform')

@section('content')
    <section class="section hero">
        <article class="card">
            <span class="badge">Legal</span>
            <h2>Privacy Policy</h2>
            <p class="muted">This page explains how Life Platform collects, stores, uses, and protects personal and business information across accounts, listings, payments, editorial workflows, and support operations.</p>
        </article>
        <article class="card">
            <h3>Your privacy matters</h3>
            <p class="muted">We are committed to handling your personal and business information responsibly. This policy explains what we collect, why we collect it, and how it is used to operate and improve the platform.</p>
        </article>
    </section>

    <section class="section">
        <div class="grid grid-2">
            <article class="card">
                <h3>1. Information We Collect</h3>
                <p class="muted">The platform may collect account details, contact information, listing content, article submissions, finance records, audit logs, and package-related transaction data necessary to operate the service.</p>
            </article>
            <article class="card">
                <h3>2. Why We Use It</h3>
                <p class="muted">Data is used to manage access, publish approved content, operate directory and event services, process orders and subscriptions, send invoices and account communications, and support customer or operational follow-up.</p>
            </article>
        </div>
    </section>

    <section class="section">
        <article class="card stack">
            <div>
                <h3>3. Payments and Billing Data</h3>
                <p class="muted">Payment-related records are used to confirm orders, activate entitlements, issue invoices, and maintain finance traceability. Payment handling follows the platform billing and compliance controls in place at the time of processing.</p>
            </div>

            <div>
                <h3>4. Listings, Editorial, and Campaign Data</h3>
                <p class="muted">Business listings, event submissions, articles, adverts, and supporting metadata may be reviewed, moderated, published, or retained to support platform operations, quality control, and future auditing.</p>
            </div>

            <div>
                <h3>5. Access Control and Auditability</h3>
                <p class="muted">Role-based access, operational logging, and audit records help protect sensitive data and support internal review, compliance, and dispute resolution where necessary.</p>
            </div>

            <div>
                <h3>6. Retention and Support</h3>
                <p class="muted">The platform may retain records for operational, billing, support, moderation, and compliance reasons. Retention periods depend on the service context, platform rules, and applicable legal obligations.</p>
            </div>

            <div>
                <h3>7. User Responsibilities</h3>
                <p class="muted">Users should provide accurate information, keep account credentials secure, and avoid submitting data they are not permitted to share through the platform.</p>
            </div>

            <div>
                <h3>8. Contact and Requests</h3>
                <p class="muted">If you have questions about how your data is handled, want to request access to your information, or need to report a privacy concern, please reach out via the <a href="{{ route('contact.index') }}">contact page</a>.</p>
            </div>
        </article>
    </section>

    <section class="section">
        <article class="card">
            <h3>Related Trust Pages</h3>
            <p class="muted">Review the related trust and purchase documents before continuing into any package or account workflow.</p>
            <div style="display:flex; gap:0.75rem; flex-wrap:wrap; margin-top:1rem;">
                <a class="button" href="{{ route('legal.terms') }}">Terms and conditions</a>
                <a class="button-link" href="{{ route('add-listing.index') }}">Start listing</a>
                <a class="button-link" href="{{ route('contact.index') }}">Contact support</a>
            </div>
        </article>
    </section>
@endsection
