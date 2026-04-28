@extends('layouts.public')

@section('title', 'Contact Us | Life Platform')

@section('content')
    <section class="section hero">
        <article class="card">
            <span class="badge">Support</span>
            <h2>Contact Us</h2>
            <p class="muted">Get in touch about listings, package purchases, payments, article submissions, or general platform support across {{ $contact['region'] }}.</p>
            <div style="display:flex; gap:0.75rem; flex-wrap:wrap; margin-top:1rem;">
                <a class="button" href="mailto:{{ $contact['email'] }}">Email support</a>
                <a class="button-link" href="{{ route('add-listing.index') }}">Start listing</a>
            </div>
        </article>
        <article class="card">
            <h3>Support Hours</h3>
            <p class="muted">{{ $contact['hours'] }}</p>
            <p class="muted">For acquisition or package questions, start with the advertiser flow and contact support if you need help choosing the right option.</p>
        </article>
    </section>

    <section class="section">
        <div class="grid grid-3">
            <article class="card">
                <h3>Email</h3>
                <p class="muted">General platform support and customer questions.</p>
                <p><a href="mailto:{{ $contact['email'] }}">{{ $contact['email'] }}</a></p>
            </article>
            <article class="card">
                <h3>Phone</h3>
                <p class="muted">Sales, listings, and account support during business hours.</p>
                <p><a href="tel:{{ preg_replace('/\s+/', '', $contact['phone']) }}">{{ $contact['phone'] }}</a></p>
            </article>
            <article class="card">
                <h3>WhatsApp</h3>
                <p class="muted">Best for quick listing capture and staff-assisted acquisition.</p>
                <p><a href="https://wa.me/{{ preg_replace('/\D+/', '', $contact['whatsapp']) }}">{{ $contact['whatsapp'] }}</a></p>
            </article>
        </div>
    </section>

    <section class="section">
        <div class="grid grid-2">
            <article class="card">
                <h3>What We Can Help With</h3>
                <ul style="margin:0; padding-left:1.25rem;">
                    <li>Choosing the right directory package</li>
                    <li>Starting or managing a listing</li>
                    <li>Package checkout and invoice questions</li>
                    <li>Event promotion eligibility</li>
                    <li>Writer or editorial workflow questions</li>
                </ul>
            </article>
            <article class="card">
                <h3>Helpful Pages First</h3>
                <p class="muted">Most users can self-serve the next step from these pages before contacting support.</p>
                <p><a href="{{ route('advertise.index') }}">Advertise with us</a></p>
                <p><a href="{{ route('add-listing.index') }}">Add listing</a></p>
                @auth
                    <p><a href="{{ route('account.index') }}">My account</a></p>
                @else
                    <p><a href="{{ route('login') }}">Login to account</a></p>
                @endauth
                <p><a href="{{ route('legal.terms') }}">Terms and conditions</a></p>
                <p><a href="{{ route('legal.privacy') }}">Privacy policy</a></p>
            </article>
        </div>
    </section>
@endsection
