@extends('layouts.public')

@section('title', 'My Account | Life Platform')

@section('content')
    <section class="section hero">
        <article class="card">
            <span class="badge">My Account</span>
            <h2>Account Hub</h2>
            <p class="muted">Manage your profile, review package purchases, monitor subscriptions, and continue into the parts of the platform that matter to your role.</p>
        </article>
        <article class="card">
            <h3>{{ $user->name }}</h3>
            <p class="muted">{{ $user->email }}</p>
            <p class="muted">Role: {{ ucfirst(str_replace('_', ' ', $user->role ?: 'member')) }}</p>
        </article>
    </section>

    <section class="section">
        <div class="stats">
            <div class="card">
                <div class="stat-number">{{ $accountStats['orders'] }}</div>
                <div>Orders</div>
            </div>
            <div class="card">
                <div class="stat-number">{{ $accountStats['invoices'] }}</div>
                <div>Invoices</div>
            </div>
            <div class="card">
                <div class="stat-number">{{ $accountStats['subscriptions'] }}</div>
                <div>Subscriptions</div>
            </div>
            <div class="card">
                <div class="stat-number">{{ $accountStats['active_subscriptions'] }}</div>
                <div>Active subscriptions</div>
            </div>
            <div class="card">
                <div class="stat-number">{{ $accountStats['listings'] }}</div>
                <div>Listings</div>
            </div>
            <div class="card">
                <div class="stat-number">{{ $accountStats['articles'] }}</div>
                <div>Articles</div>
            </div>
            <div class="card">
                <div class="stat-number">{{ $accountStats['classifieds'] }}</div>
                <div>Classifieds</div>
            </div>
        </div>
    </section>

    <section class="section">
        <div class="section-head">
            <div>
                <h3>Quick Links</h3>
                <p class="muted">Use the fastest route into your current workflows.</p>
            </div>
        </div>
        <div class="grid grid-3">
            @foreach ($quickLinks as $link)
                <article class="card">
                    <h4>{{ $link['label'] }}</h4>
                    <p class="muted">{{ $link['description'] }}</p>
                    <p><a class="button-link" href="{{ route($link['route']) }}">Open</a></p>
                </article>
            @endforeach
        </div>
    </section>

    <section class="section">
        <article class="card">
            <h3>Trust and Privacy</h3>
            <p class="muted">Review the public trust pages that explain package rules, privacy handling, and purchase expectations.</p>
            <div style="display:flex; gap:0.75rem; flex-wrap:wrap;">
                <a class="button-link" href="{{ route('legal.terms') }}">Terms and conditions</a>
                <a class="button-link" href="{{ route('legal.privacy') }}">Privacy policy</a>
            </div>
        </article>
    </section>

    <section class="section">
        <div class="grid grid-2">
            <article class="card">
                <h3>Recent Orders</h3>
                @forelse ($orders as $order)
                    <div class="feature-list">
                        <div>
                            <strong>{{ $order->order_number }}</strong>
                            <div class="muted">{{ ucfirst($order->status) }} · {{ $order->currency }} {{ number_format((float) $order->total, 2) }}</div>
                            @if ($order->invoices->isNotEmpty())
                                <div class="muted">Invoice: {{ $order->invoices->first()->invoice_number }}</div>
                            @endif
                            <a href="{{ route('checkout.show', $order) }}">View order</a>
                        </div>
                        <div>{{ optional($order->created_at)->format('j M Y') }}</div>
                    </div>
                @empty
                    <div class="empty-state">No orders yet.</div>
                @endforelse
            </article>

            <article class="card">
                <h3>Recent Invoices</h3>
                @forelse ($invoices as $invoice)
                    <div class="feature-list">
                        <div>
                            <strong>{{ $invoice->invoice_number }}</strong>
                            <div class="muted">{{ ucfirst($invoice->status) }} · {{ $invoice->currency }} {{ number_format((float) $invoice->total, 2) }}</div>
                            <a href="{{ route('account.invoices.show', $invoice) }}">View invoice</a>
                        </div>
                        <div>{{ optional($invoice->issued_at ?: $invoice->created_at)->format('j M Y') }}</div>
                    </div>
                @empty
                    <div class="empty-state">No invoices yet.</div>
                @endforelse
            </article>

            <article class="card">
                <h3>Subscriptions</h3>
                @forelse ($subscriptions as $subscription)
                    <div class="feature-list">
                        <div>
                            <strong>{{ $subscription->package?->name ?: 'Package' }}</strong>
                            <div class="muted">{{ ucfirst($subscription->status) }} · Ends {{ optional($subscription->ends_at)->format('j M Y') ?: '-' }}</div>
                            @if ($subscription->subscribable)
                                <div class="muted">{{ class_basename($subscription->subscribable_type) }} linked</div>
                                <a href="{{ route('checkout.subscriptions.renew', $subscription) }}">Renew subscription</a>
                            @endif
                        </div>
                        <div>{{ $subscription->isActive() ? 'Active' : 'Inactive' }}</div>
                    </div>
                @empty
                    <div class="empty-state">No subscriptions yet.</div>
                @endforelse
            </article>
        </div>
    </section>

    <section class="section">
        <div class="grid grid-2">
            <article class="card">
                <h3>My Classifieds</h3>
                @forelse ($classifieds as $classified)
                    <div class="feature-list">
                        <div>
                            <strong>{{ $classified->title }}</strong>
                            <div class="muted">{{ ucfirst($classified->status) }}{{ $classified->city ? ' · '.$classified->city : '' }}</div>
                            @if ($classified->moderation_notes)
                                <div class="muted">{{ \Illuminate\Support\Str::limit($classified->moderation_notes, 80) }}</div>
                            @endif
                            @if ($classified->status !== \App\Models\Classified::STATUS_PUBLISHED)
                                <a href="{{ route('classifieds.manage.edit', $classified) }}">Edit classified</a>
                            @endif
                        </div>
                        <div>{{ optional($classified->updated_at)->format('j M Y') }}</div>
                    </div>
                @empty
                    <div class="empty-state">No classifieds yet. <a href="{{ route('classifieds.manage.create') }}">Post a classified</a>.</div>
                @endforelse
            </article>

            <article class="card">
                <h3>My Listings</h3>
                @forelse ($listings as $listing)
                    <div class="feature-list">
                        <div>
                            <strong>{{ $listing->title }}</strong>
                            <div class="muted">{{ ucfirst($listing->status) }}{{ $listing->city ? ' · '.$listing->city : '' }}</div>
                            <a href="{{ route('account.listings.show', $listing) }}">Open listing workspace</a>
                        </div>
                        <div>{{ optional($listing->package_expires_at)->format('j M Y') ?: 'No expiry' }}</div>
                    </div>
                @empty
                    <div class="empty-state">No listings yet. <a href="{{ route('add-listing.index') }}">Start a listing</a>.</div>
                @endforelse
            </article>

            <article class="card">
                <h3>My Articles</h3>
                @forelse ($articles as $article)
                    <div class="feature-list">
                        <div>
                            <strong>{{ $article->title }}</strong>
                            <div class="muted">{{ ucfirst($article->status) }}</div>
                        </div>
                        <div>{{ optional($article->updated_at)->format('j M Y') }}</div>
                    </div>
                @empty
                    <div class="empty-state">No article submissions yet.</div>
                @endforelse
            </article>
        </div>
    </section>
@endsection
