<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name', 'Life Platform'))</title>
    @include('partials.pwa-head')
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="preload" as="image" href="{{ asset('branding/life-logo-light.svg') }}">
    <link rel="preload" as="image" href="{{ asset('branding/life-logo-dark.svg') }}">
    @stack('head')
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700|inter:400,500,600,700&display=swap" rel="stylesheet" />
    <script>
        (() => {
            const key = 'life-theme';
            let stored = null;
            try {
                stored = localStorage.getItem(key);
            } catch (_) {}
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            const theme = stored === 'dark' || stored === 'light' ? stored : (prefersDark ? 'dark' : 'light');
            document.documentElement.dataset.theme = theme;
            document.documentElement.style.colorScheme = theme;
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('partials.filter-dropdown-assets')
    @stack('styles')
</head>
<body class="lp">
    <a class="lp-skip-link" href="#main">Skip to content</a>
    @php
        $navLinks = [
            ['label' => 'Home', 'icon' => 'sparkles', 'url' => route('home'), 'active' => request()->routeIs('home')],
            ['label' => 'Directory', 'icon' => 'building', 'url' => route('directory.index'), 'active' => request()->routeIs('directory.*')],
            ['label' => 'Vouchers', 'icon' => 'ticket', 'url' => route('vouchers.index'), 'active' => request()->routeIs('vouchers.*')],
            ['label' => 'Events', 'icon' => 'calendar', 'url' => route('events.index'), 'active' => request()->routeIs('events.*')],
            ['label' => 'Articles', 'icon' => 'newspaper', 'url' => route('articles.index'), 'active' => request()->routeIs('articles.*')],
            ['label' => 'Classifieds', 'icon' => 'tag', 'url' => route('classifieds.index'), 'active' => request()->routeIs('classifieds.*')],
            ['label' => 'Advertise', 'icon' => 'megaphone', 'url' => route('advertise.index'), 'active' => request()->routeIs('advertise.*')],
            ['label' => 'Taxi / Delivery', 'icon' => 'taxi', 'url' => route('transport.index'), 'active' => request()->routeIs('transport.*')],
            ['label' => 'Search', 'icon' => 'search', 'url' => route('search.index'), 'active' => request()->routeIs('search.*')],
            ['label' => 'Faults', 'icon' => 'map-pin', 'url' => route('faults.index'), 'active' => request()->routeIs('faults.*')],
            ['label' => 'About', 'icon' => 'heart', 'url' => route('about.index'), 'active' => request()->routeIs('about.*')],
        ];

        $routeName = optional(request()->route())->getName();
        $crumbs = [
            ['label' => 'Home', 'url' => route('home')],
        ];

        $pushCrumb = function (string $label, ?string $url = null) use (&$crumbs) {
            $crumbs[] = ['label' => $label, 'url' => $url];
        };

        switch ($routeName) {
            case 'home':
                $crumbs = [];
                break;
            case 'directory.index':
                $pushCrumb('Directory', route('directory.index'));
                break;
            case 'directory.show':
                $pushCrumb('Directory', route('directory.index'));
                $listing = request()->route('listing');
                $pushCrumb($listing?->localizedValue('title') ?: 'Listing', null);
                break;
            case 'vouchers.index':
                $pushCrumb('Vouchers', route('vouchers.index'));
                break;
            case 'vouchers.show':
                $pushCrumb('Vouchers', route('vouchers.index'));
                $voucher = request()->route('voucher');
                $pushCrumb($voucher?->localizedValue('title') ?: 'Voucher', null);
                break;
            case 'events.index':
                $pushCrumb('Events', route('events.index'));
                break;
            case 'events.show':
                $pushCrumb('Events', route('events.index'));
                $event = request()->route('event');
                $pushCrumb($event?->localizedValue('title') ?: 'Event', null);
                break;
            case 'articles.index':
            case 'articles.categories.show':
            case 'articles.tags.show':
            case 'articles.locations.show':
            case 'articles.authors.show':
                $pushCrumb('Articles', route('articles.index'));
                break;
            case 'articles.show':
                $pushCrumb('Articles', route('articles.index'));
                $article = request()->route('article');
                $pushCrumb($article && method_exists($article, 'localizedTitle') ? $article->localizedTitle() : ($article?->title ?: 'Article'), null);
                break;
            case 'classifieds.index':
                $pushCrumb('Classifieds', route('classifieds.index'));
                break;
            case 'classifieds.show':
                $pushCrumb('Classifieds', route('classifieds.index'));
                $classified = request()->route('classified');
                $pushCrumb($classified?->localizedValue('title') ?: 'Classified', null);
                break;
            case 'advertise.index':
                $pushCrumb('Advertise', route('advertise.index'));
                break;
            case 'transport.index':
                $pushCrumb('Taxi / Delivery', route('transport.index'));
                break;
            case 'transport.requests.create':
                $pushCrumb('Taxi / Delivery', route('transport.index'));
                $pushCrumb('Request transport', null);
                break;
            case 'transport.requests.show':
                $pushCrumb('Taxi / Delivery', route('transport.index'));
                $pushCrumb('Transport request', null);
                break;
            case 'search.index':
                $pushCrumb('Search', route('search.index'));
                break;
            case 'faults.index':
                $pushCrumb('Faults', route('faults.index'));
                break;
            case 'faults.report.create':
                $pushCrumb('Faults', route('faults.index'));
                $pushCrumb('Report a fault', null);
                break;
            case 'add-listing.index':
                $pushCrumb('Add listing', route('add-listing.index'));
                break;
            case 'contact.index':
                $pushCrumb('Contact', route('contact.index'));
                break;
            case 'legal.privacy':
                $pushCrumb('Privacy', route('legal.privacy'));
                break;
            case 'legal.terms':
                $pushCrumb('Terms', route('legal.terms'));
                break;
            case 'about.index':
                $pushCrumb('About', route('about.index'));
                break;
            default:
                if (request()->routeIs('account.*')) {
                    $pushCrumb('Account', route('account.index'));
                }
                break;
        }
    @endphp
    <header class="site-header">
        <div class="topbar">
            <div class="container topbar-inner">
                @if (request()->routeIs('faults.*'))
                    <div class="da-brand">
                        <div class="da-logo" aria-hidden="true"><span>DA</span></div>
                        <p class="topbar-copy">Civic infrastructure fault reporting — potholes, burst pipes, streetlights, sidewalks, and more.</p>
                        <span class="da-tag">Powered by DA</span>
                    </div>
                @else
                    <p class="topbar-copy">Eastern Freestate local news, business discovery, events, and community opportunities.</p>
                @endif
                <div>{{ now()->format('D j M Y') }}</div>
            </div>
        </div>
        <div class="container">
            <a href="{{ route('home') }}" class="brand-link" aria-label="{{ config('app.name', 'Life Platform') }} home">
                <img
                    src="{{ asset('branding/life-logo-light.svg') }}"
                    data-theme-logo
                    data-logo-light="{{ asset('branding/life-logo-light.svg') }}"
                    data-logo-dark="{{ asset('branding/life-logo-dark.svg') }}"
                    alt="{{ config('app.name', 'Life Platform') }}"
                    class="brand-logo"
                    width="240"
                    height="56"
                >
            </a>
            <p class="page-copy">A fast, clean local front door for editorial content, trusted businesses, upcoming events, and advertising opportunities across the Eastern Freestate.</p>
            <div class="lp-visual-strip" aria-hidden="true">
                <img src="{{ asset('illustrations/community-mosaic.svg') }}" alt="" width="1200" height="260" loading="eager" decoding="async">
            </div>
            <nav class="lp-nav" aria-label="Primary navigation" data-nav-root>
                <button type="button" class="lp-nav-toggle" data-nav-toggle aria-controls="lp-nav-drawer" aria-expanded="false" aria-label="Open menu">
                    <x-icon name="menu" class="w-6 h-6" />
                </button>

                <div class="lp-nav-desktop" data-nav-desktop>
                    <ul class="lp-nav-list">
                        @foreach ($navLinks as $link)
                            <li class="lp-nav-item">
                                <a href="{{ $link['url'] }}" class="lp-nav-link {{ $link['active'] ? 'active' : '' }}" data-nav-link>
                                    <span class="lp-nav-icon"><x-icon name="{{ $link['icon'] }}" class="w-4 h-4" /></span>
                                    <span>{{ $link['label'] }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                    <div class="lp-nav-tools">
                        @include('partials.language-switcher')
                        <button type="button" class="theme-toggle" data-theme-toggle aria-label="Toggle dark and light mode" title="Toggle dark and light mode">
                            <svg data-theme-icon-sun xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 3v2.25M12 18.75V21M4.97 4.97l1.59 1.59M17.44 17.44l1.59 1.59M3 12h2.25M18.75 12H21M4.97 19.03l1.59-1.59M17.44 6.56l1.59-1.59M15.75 12A3.75 3.75 0 1112 8.25 3.75 3.75 0 0115.75 12z" />
                            </svg>
                            <svg data-theme-icon-moon xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="display:none;">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M21 12.79A9 9 0 1111.21 3c-.02.25-.03.5-.03.75a9 9 0 009.07 9.04c.25 0 .5-.01.75-.03z" />
                            </svg>
                        </button>
                        @auth
                            <a href="{{ route('dashboard') }}" class="button-link">Dashboard</a>
                            <form method="post" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="button-link">Logout</button>
                            </form>
                        @else
                            <a href="{{ route('login') }}" class="button-link">Login</a>
                            <a href="{{ route('register') }}" class="button-link">Register</a>
                        @endauth
                        <button type="button" class="button-link push-toggle" data-push-toggle hidden>Enable alerts</button>
                    </div>
                </div>

                <div class="lp-nav-overlay" data-nav-overlay hidden></div>
                <div class="lp-nav-drawer" id="lp-nav-drawer" data-nav-drawer hidden role="dialog" aria-modal="true" aria-label="Menu">
                    <div class="lp-drawer-head">
                        <div class="lp-drawer-brand">
                            <img
                                src="{{ asset('branding/life-logo-light.svg') }}"
                                data-theme-logo
                                data-logo-light="{{ asset('branding/life-logo-light.svg') }}"
                                data-logo-dark="{{ asset('branding/life-logo-dark.svg') }}"
                                alt="Life Platform"
                                class="lp-drawer-logo"
                                width="180"
                                height="42"
                                decoding="async"
                            >
                        </div>
                        <div class="lp-drawer-actions">
                            @include('partials.language-switcher')
                            <button type="button" class="theme-toggle" data-theme-toggle aria-label="Toggle dark and light mode" title="Toggle dark and light mode">
                                <svg data-theme-icon-sun xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 3v2.25M12 18.75V21M4.97 4.97l1.59 1.59M17.44 17.44l1.59 1.59M3 12h2.25M18.75 12H21M4.97 19.03l1.59-1.59M17.44 6.56l1.59-1.59M15.75 12A3.75 3.75 0 1112 8.25 3.75 3.75 0 0115.75 12z" />
                                </svg>
                                <svg data-theme-icon-moon xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="display:none;">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M21 12.79A9 9 0 1111.21 3c-.02.25-.03.5-.03.75a9 9 0 009.07 9.04c.25 0 .5-.01.75-.03z" />
                                </svg>
                            </button>
                            <button type="button" class="lp-nav-close" data-nav-close aria-label="Close menu">
                                <x-icon name="x" class="w-6 h-6" />
                            </button>
                        </div>
                    </div>
                    <ul class="lp-nav-mobile-list">
                        @foreach ($navLinks as $link)
                            <li>
                                <a href="{{ $link['url'] }}" class="lp-nav-mobile-link {{ $link['active'] ? 'active' : '' }}" data-nav-link>
                                    <span class="lp-nav-mobile-label">
                                        <span class="lp-nav-icon"><x-icon name="{{ $link['icon'] }}" class="w-4 h-4" /></span>
                                        <span>{{ $link['label'] }}</span>
                                    </span>
                                    <x-icon name="arrow-right" class="w-4 h-4" />
                                </a>
                            </li>
                        @endforeach
                    </ul>
                    <div class="lp-drawer-footer">
                        @include('partials.language-switcher')
                        @auth
                            <a href="{{ route('dashboard') }}" class="button-link w-full">Dashboard</a>
                            <form method="post" action="{{ route('logout') }}" class="w-full">
                                @csrf
                                <button type="submit" class="button-link w-full">Logout</button>
                            </form>
                        @else
                            <a href="{{ route('login') }}" class="button-link w-full">Login</a>
                            <a href="{{ route('register') }}" class="button-link w-full">Register</a>
                        @endauth
                        <button type="button" class="button-link w-full push-toggle" data-push-toggle hidden>Enable alerts</button>
                    </div>
                </div>
            </nav>
        </div>
    </header>

    <main id="main" class="container" data-reveal>
        @if (!empty($crumbs) && count($crumbs) > 1)
            <nav class="lp-breadcrumb" aria-label="Breadcrumb" data-reveal>
                <ol class="lp-breadcrumb-list">
                    @foreach ($crumbs as $crumb)
                        @php $isLast = $loop->last; @endphp
                        <li class="lp-breadcrumb-item">
                            @if (! $isLast && ! empty($crumb['url']))
                                <a class="lp-breadcrumb-link" href="{{ $crumb['url'] }}">{{ $crumb['label'] }}</a>
                            @else
                                <span class="lp-breadcrumb-current" aria-current="page">{{ $crumb['label'] }}</span>
                            @endif
                        </li>
                    @endforeach
                </ol>
            </nav>
        @endif
        @yield('content')
    </main>

    <footer class="container footer">
        <div class="footer-grid" data-reveal>
            <div>
                <strong>Eastern Freestate</strong>
                <p>Life@ News blends local editorial coverage with business discovery, event visibility, and commercial promotion paths for the region.</p>
            </div>
            <div>
                <strong>Explore</strong>
                <p><a href="{{ route('articles.index') }}">Latest Articles</a></p>
                <p><a href="{{ route('directory.index') }}">Business Directory</a></p>
                <p><a href="{{ route('vouchers.index') }}">Vouchers</a></p>
                <p><a href="{{ route('events.index') }}">Upcoming Events</a></p>
            </div>
            <div>
                <strong>Grow</strong>
                <p><a href="{{ route('advertise.index') }}">Advertise With Us</a></p>
                <p><a href="{{ route('add-listing.index') }}">Add Listing</a></p>
                <p><a href="{{ route('transport.index') }}">Taxi / Delivery</a></p>
                <p><a href="{{ route('classifieds.index') }}">Community Classifieds</a></p>
                <p><a href="{{ route('staff-signup.create') }}">Join As Writer Or Staff</a></p>
                <p><a href="{{ route('contact.index') }}">Contact Us</a></p>
                <p><a href="{{ route('search.index') }}">Search The Platform</a></p>
                <p><a href="{{ route('legal.terms') }}">Terms & Conditions</a></p>
                <p><a href="{{ route('legal.privacy') }}">Privacy Policy</a></p>
                <p><a href="{{ route('about.index') }}">About Life@</a></p>
            </div>
        </div>
        @if (request()->routeIs('faults.*'))
            <div style="margin-top: 1.25rem; padding-top: 1rem; border-top: 1px solid var(--border);">
                <strong>Powered by DA</strong>
                <p class="mb-0">This civic infrastructure fault reporting interface is sponsored by the Democratic Alliance (DA).</p>
            </div>
        @endif
    </footer>
    @stack('scripts')
</body>
</html>
