@extends('layouts.public')

@section('title', 'Advertise | Life Platform')

@php
    $pack = fn ($packages) => $packages->map(function ($package) {
        $price = $package->currentPrice();

        return [
            'slug' => $package->slug,
            'name' => $package->name,
            'description' => $package->description,
            'amount' => $price ? (float) $price->amount : 0,
            'currency' => $price?->currency ?: 'ZAR',
            'duration_days' => $package->duration_days,
            'billing_model' => $package->billing_model,
            'is_self_service' => (bool) $package->is_self_service,
            'settings' => $package->settings_json ?: [],
        ];
    })->values();

    $directoryOptions = $pack($directoryPackages);
    $eventOptions = $pack($eventPackages);
    $advertOptions = $pack($advertPackages);
    $pushOptions = $pack($pushPackages);
@endphp

@push('styles')
    <style>
        .bundle-shell { display:grid; gap:1.25rem; grid-template-columns:minmax(0, 1.4fr) minmax(320px, 0.8fr); align-items:start; }
        .bundle-builder { display:grid; gap:1rem; }
        .bundle-panel { border:1px solid rgb(var(--border-rgb) / 0.9); border-radius:16px; background:rgb(var(--surface-rgb) / 0.94); padding:1rem; box-shadow:var(--shadow-soft); }
        .bundle-panel-head { display:flex; align-items:flex-start; justify-content:space-between; gap:1rem; }
        .bundle-panel-title { display:flex; align-items:flex-start; gap:0.8rem; }
        .bundle-step { width:2.1rem; height:2.1rem; flex:0 0 auto; border-radius:999px; display:inline-flex; align-items:center; justify-content:center; background:rgb(var(--brand-rgb)); color:#fff; font-weight:900; }
        .bundle-toggle { display:inline-flex; align-items:center; gap:0.5rem; font-weight:850; color:rgb(var(--text-rgb) / 0.9); }
        .bundle-toggle input { width:1.15rem; height:1.15rem; }
        .bundle-options { display:grid; gap:0.75rem; margin-top:1rem; }
        .bundle-option { display:grid; gap:0.4rem; border:1px solid rgb(var(--border-rgb) / 0.9); border-radius:14px; padding:0.85rem; background:rgb(var(--border-rgb) / 0.20); cursor:pointer; }
        .bundle-option input { margin-right:0.5rem; }
        .bundle-option-line { display:flex; align-items:center; justify-content:space-between; gap:1rem; flex-wrap:wrap; }
        .bundle-price { font-weight:900; color:rgb(var(--text-rgb) / 0.95); }
        .bundle-summary { position:sticky; top:1rem; }
        .summary-row { display:flex; align-items:flex-start; justify-content:space-between; gap:1rem; padding:0.65rem 0; border-bottom:1px solid rgb(var(--border-rgb) / 0.75); }
        .summary-row:last-child { border-bottom:0; }
        .summary-total { font-size:2rem; line-height:1; font-weight:950; letter-spacing:-0.02em; }
        .bundle-mini-grid { display:grid; gap:0.75rem; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); }
        .bundle-mini { border:1px solid rgb(var(--border-rgb) / 0.9); border-radius:14px; padding:0.9rem; background:rgb(var(--surface-rgb) / 0.72); }
        .bundle-mini strong { display:block; margin-bottom:0.3rem; }
        .mission-hero {
            display:grid;
            gap:1rem;
            grid-template-columns:minmax(0, 1.3fr) minmax(280px, 0.7fr);
            align-items:stretch;
        }
        .mission-hero-panel {
            border-radius:24px;
            padding:1.75rem;
            border:1px solid rgb(var(--border-rgb) / 0.9);
            background:
                linear-gradient(135deg, rgb(var(--accent-rgb) / 0.13), rgb(var(--brand-rgb) / 0.05)),
                rgb(var(--surface-rgb) / 0.94);
            box-shadow:var(--shadow-soft);
        }
        .price-logic {
            display:grid;
            gap:0.75rem;
            margin-top:1rem;
        }
        .price-logic-item {
            display:grid;
            gap:0.25rem;
            padding:0.85rem;
            border-radius:14px;
            border:1px solid rgb(var(--border-rgb) / 0.9);
            background:rgb(var(--surface-rgb) / 0.76);
        }
        .price-logic-item strong { color:rgb(var(--text-rgb) / 0.96); }
        [x-cloak] { display:none !important; }
        @media (max-width: 920px) {
            .bundle-shell { grid-template-columns:1fr; }
            .mission-hero { grid-template-columns:1fr; }
            .bundle-summary { position:static; }
        }
    </style>
@endpush

@section('content')
    <section class="section mission-hero">
        <article class="mission-hero-panel">
            <span class="badge">Advertise and create work</span>
            <h2 class="h2-tight">Put your business on the platform that is built to employ local people.</h2>
            <p class="muted mb-0">Life@ is a job-creation platform as much as it is a local media and directory platform. A business listing starts your advertising journey and helps fund the people who capture listings, support clients, write local stories, and run campaigns.</p>
            <div style="display:flex; gap:0.75rem; flex-wrap:wrap; margin-top:1rem;">
                <a class="button" href="#build-package">Build your package</a>
                <a class="button-link" href="{{ route('staff-signup.create') }}">Apply to work with us</a>
                <a class="button-link" href="{{ route('directory.index') }}">View directory</a>
            </div>
        </article>
        <aside class="card">
            <span class="eyebrow">Why self-service costs more</span>
            <div class="price-logic">
                <div class="price-logic-item">
                    <strong>Staff assisted is cheaper because it creates a job.</strong>
                    <span class="muted">A local staff member earns by helping capture and onboard the business.</span>
                </div>
                <div class="price-logic-item">
                    <strong>Self service is for owners who want direct control.</strong>
                    <span class="muted">It costs more because it bypasses the assisted work opportunity and helps protect the employment model.</span>
                </div>
                <div class="price-logic-item">
                    <strong>Listing first, then everything else.</strong>
                    <span class="muted">Events, article placements, banners, and push campaigns all require an active business listing.</span>
                </div>
            </div>
        </aside>
    </section>

    <section class="section">
        <div class="bundle-mini-grid">
            <div class="bundle-mini">
                <strong>Staff assisted</strong>
                <span class="muted">Lower price because a local person is paid to help capture and support the business.</span>
            </div>
            <div class="bundle-mini">
                <strong>Self service</strong>
                <span class="muted">Higher price for direct owner control while still contributing to the job-creation model.</span>
            </div>
            <div class="bundle-mini">
                <strong>Public impact</strong>
                <span class="muted">Advertising revenue helps fund local stories, sales support, onboarding, and community discovery.</span>
            </div>
        </div>
    </section>

    <section
        id="build-package"
        class="section bundle-shell"
        x-data="advertisingBundle({
            directories: @js($directoryOptions),
            events: @js($eventOptions),
            adverts: @js($advertOptions),
            pushes: @js($pushOptions),
        })"
    >
        <div class="bundle-builder">
            <article class="bundle-panel">
                <div class="bundle-panel-head">
                    <div class="bundle-panel-title">
                        <span class="bundle-step">1</span>
                        <div>
                            <h3 class="h3-tight">Business listing</h3>
                            <p class="muted mb-0">Required gateway package. Choose the setup model that matches your role in local job creation.</p>
                        </div>
                    </div>
                    <span class="badge">Required</span>
                </div>

                <div class="bundle-options">
                    <template x-for="option in directories" :key="option.slug">
                        <label class="bundle-option">
                            <div class="bundle-option-line">
                                <span>
                                    <input type="radio" name="preview_listing_package" :value="option.slug" x-model="listingPackage">
                                    <strong x-text="option.name"></strong>
                                </span>
                                <span class="bundle-price" x-text="money(option.amount)"></span>
                            </div>
                            <span class="muted" x-text="option.description"></span>
                            <span class="mini-meta" x-text="option.is_self_service ? 'Higher-price owner-managed path that helps sustain the employment model' : 'Lower-price assisted path because a staff member earns from onboarding'"></span>
                        </label>
                    </template>
                </div>
            </article>

            <article class="bundle-panel">
                <div class="bundle-panel-head">
                    <div class="bundle-panel-title">
                        <span class="bundle-step">2</span>
                        <div>
                            <h3 class="h3-tight">Event promotion</h3>
                            <p class="muted mb-0">Use when the business wants an event listed and promoted.</p>
                        </div>
                    </div>
                    <label class="bundle-toggle">
                        <input type="checkbox" x-model="eventEnabled">
                        <span x-text="eventEnabled ? 'On' : 'Off'"></span>
                    </label>
                </div>

                <div class="bundle-options" x-show="eventEnabled" x-cloak>
                    <label>
                        <span class="lp-label">Event package</span>
                        <select class="lp-select" x-model="eventPackage">
                            <template x-for="option in events" :key="option.slug">
                                <option :value="option.slug" x-text="`${option.name} - ${money(option.amount)}`"></option>
                            </template>
                        </select>
                    </label>
                    <label>
                        <span class="lp-label">Event title placeholder</span>
                        <input class="lp-input" type="text" name="preview_event_title" x-model="eventTitle" placeholder="Example: Winter market launch">
                    </label>
                </div>
            </article>

            <article class="bundle-panel">
                <div class="bundle-panel-head">
                    <div class="bundle-panel-title">
                        <span class="bundle-step">3</span>
                        <div>
                            <h3 class="h3-tight">Advert placements</h3>
                            <p class="muted mb-0">Choose one or more placements. Sitewide banner is priced highest because it appears across the platform.</p>
                        </div>
                    </div>
                    <label class="bundle-toggle">
                        <input type="checkbox" x-model="advertsEnabled">
                        <span x-text="advertsEnabled ? 'On' : 'Off'"></span>
                    </label>
                </div>

                <div class="bundle-options" x-show="advertsEnabled" x-cloak>
                    <template x-for="option in adverts" :key="option.slug">
                        <label class="bundle-option">
                            <div class="bundle-option-line">
                                <span>
                                    <input type="checkbox" :value="option.slug" x-model="advertPackages">
                                    <strong x-text="option.name"></strong>
                                </span>
                                <span class="bundle-price" x-text="money(option.amount)"></span>
                            </div>
                            <span class="muted" x-text="option.description"></span>
                            <span class="mini-meta" x-text="placementCopy(option)"></span>
                        </label>
                    </template>
                </div>
            </article>

            <article class="bundle-panel">
                <div class="bundle-panel-head">
                    <div class="bundle-panel-title">
                        <span class="bundle-step">4</span>
                        <div>
                            <h3 class="h3-tight">Push notification</h3>
                            <p class="muted mb-0">Premium direct reach. Choose city-level or regional targeting.</p>
                        </div>
                    </div>
                    <label class="bundle-toggle">
                        <input type="checkbox" x-model="pushEnabled">
                        <span x-text="pushEnabled ? 'On' : 'Off'"></span>
                    </label>
                </div>

                <div class="bundle-options" x-show="pushEnabled" x-cloak>
                    <label>
                        <span class="lp-label">Push package</span>
                        <select class="lp-select" x-model="pushPackage">
                            <template x-for="option in pushes" :key="option.slug">
                                <option :value="option.slug" x-text="`${option.name} - ${money(option.amount)}`"></option>
                            </template>
                        </select>
                    </label>
                </div>
            </article>
        </div>

        <aside class="card bundle-summary">
            <h3 class="h3-tight">Package Summary</h3>
            <p class="muted">This estimate is VAT-inclusive where package prices are configured that way.</p>

            <div style="margin-top:1rem;">
                <template x-for="line in lines" :key="line.key">
                    <div class="summary-row">
                        <span>
                            <strong x-text="line.name"></strong><br>
                            <span class="mini-meta" x-text="line.detail"></span>
                        </span>
                        <span class="bundle-price" x-text="money(line.amount)"></span>
                    </div>
                </template>
            </div>

            <div style="margin-top:1.25rem;">
                <div class="mini-meta">Estimated total</div>
                <div class="summary-total" x-text="money(total)"></div>
            </div>

            @auth
                <form method="post" action="{{ route('advertise.start') }}" style="display:grid; gap:0.8rem; margin-top:1.25rem;">
                    @csrf
                    <input type="hidden" name="listing_package_slug" :value="listingPackage">
                    <input type="hidden" name="event_package_slug" :value="eventEnabled ? eventPackage : ''">
                    <input type="hidden" name="event_title" :value="eventTitle">
                    <input type="hidden" name="push_package_slug" :value="pushEnabled ? pushPackage : ''">
                    <template x-for="slug in (advertsEnabled ? advertPackages : [])" :key="slug">
                        <input type="hidden" name="advert_package_slugs[]" :value="slug">
                    </template>

                    <label>
                        <span class="lp-label">Business name</span>
                        <input class="lp-input" name="business_name" value="{{ old('business_name') }}" placeholder="Trading name" required>
                    </label>
                    <label>
                        <span class="lp-label">Town or city</span>
                        <input class="lp-input" name="city" value="{{ old('city') }}" placeholder="Bethlehem, Harrismith, Clarens...">
                    </label>

                    @if ($errors->any())
                        <div class="empty-state" style="color:#b91c1c;">{{ implode(' ', $errors->all()) }}</div>
                    @endif

                    <button class="button" type="submit">Create bundle and checkout</button>
                    <a class="button-link" href="{{ route('account.advertising.index') }}">Open self-service dashboard</a>
                </form>
            @else
                <div style="display:grid; gap:0.75rem; margin-top:1.25rem;">
                    <a class="button" href="{{ route('register') }}">Create account to start</a>
                    <a class="button-link" href="{{ route('login') }}">Login</a>
                </div>
            @endauth
        </aside>
    </section>
@endsection

@push('scripts')
    <script>
        function advertisingBundle(config) {
            return {
                directories: config.directories || [],
                events: config.events || [],
                adverts: config.adverts || [],
                pushes: config.pushes || [],
                listingPackage: (config.directories || [])[0]?.slug || '',
                eventEnabled: false,
                eventPackage: (config.events || [])[0]?.slug || '',
                eventTitle: '',
                advertsEnabled: false,
                advertPackages: [],
                pushEnabled: false,
                pushPackage: (config.pushes || [])[0]?.slug || '',
                money(amount) {
                    return new Intl.NumberFormat('en-ZA', { style: 'currency', currency: 'ZAR' }).format(Number(amount || 0));
                },
                find(items, slug) {
                    return (items || []).find((item) => item.slug === slug) || null;
                },
                placementCopy(option) {
                    const reach = option.settings?.reach || 'selected inventory';
                    const placement = String(option.settings?.placement || 'advert').replaceAll('_', ' ');
                    return `${placement} · ${reach.replaceAll('_', ' ')}`;
                },
                get lines() {
                    const lines = [];
                    const listing = this.find(this.directories, this.listingPackage);
                    if (listing) lines.push({ key: 'listing', name: listing.name, detail: 'Required business listing', amount: listing.amount });

                    const event = this.eventEnabled ? this.find(this.events, this.eventPackage) : null;
                    if (event) lines.push({ key: 'event', name: event.name, detail: 'Event promotion add-on', amount: event.amount });

                    if (this.advertsEnabled) {
                        this.advertPackages.forEach((slug) => {
                            const advert = this.find(this.adverts, slug);
                            if (advert) lines.push({ key: advert.slug, name: advert.name, detail: this.placementCopy(advert), amount: advert.amount });
                        });
                    }

                    const push = this.pushEnabled ? this.find(this.pushes, this.pushPackage) : null;
                    if (push) lines.push({ key: 'push', name: push.name, detail: 'Push notification add-on', amount: push.amount });

                    return lines;
                },
                get total() {
                    return this.lines.reduce((sum, line) => sum + Number(line.amount || 0), 0);
                },
            };
        }
    </script>
@endpush
