<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Life Platform')</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    @stack('head')
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
    <script>
        (() => {
            const key = 'life-theme';
            const stored = localStorage.getItem(key);
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            const theme = stored === 'dark' || stored === 'light' ? stored : (prefersDark ? 'dark' : 'light');
            document.documentElement.dataset.theme = theme;
            document.documentElement.style.colorScheme = theme;
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body class="lp">
    <a class="lp-skip-link" href="#main">Skip to content</a>
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
            <a href="{{ route('home') }}" class="brand-link" aria-label="Life Platform home">
                <img
                    src="{{ asset('branding/life-logo-light.svg') }}"
                    data-theme-logo
                    data-logo-light="{{ asset('branding/life-logo-light.svg') }}"
                    data-logo-dark="{{ asset('branding/life-logo-dark.svg') }}"
                    alt="Life Platform"
                    class="brand-logo"
                >
            </a>
            <p class="page-copy">A fast, clean local front door for editorial content, trusted businesses, upcoming events, and advertising opportunities across the Eastern Freestate.</p>
            <nav class="nav" aria-label="Primary navigation">
                <a href="{{ route('home') }}" class="{{ request()->routeIs('home') ? 'active' : '' }}">Home</a>
                <a href="{{ route('directory.index') }}" class="{{ request()->routeIs('directory.*') ? 'active' : '' }}">Directory</a>
                <a href="{{ route('vouchers.index') }}" class="{{ request()->routeIs('vouchers.*') ? 'active' : '' }}">Vouchers</a>
                <a href="{{ route('events.index') }}" class="{{ request()->routeIs('events.*') ? 'active' : '' }}">Events</a>
                <a href="{{ route('articles.index') }}" class="{{ request()->routeIs('articles.*') ? 'active' : '' }}">Articles</a>
                <a href="{{ route('classifieds.index') }}" class="{{ request()->routeIs('classifieds.*') ? 'active' : '' }}">Classifieds</a>
                <a href="{{ route('advertise.index') }}" class="{{ request()->routeIs('advertise.*') ? 'active' : '' }}">Advertise</a>
                <a href="{{ route('search.index') }}" class="{{ request()->routeIs('search.*') ? 'active' : '' }}">Search</a>
                <a href="{{ route('faults.index') }}" class="{{ request()->routeIs('faults.*') ? 'active' : '' }}">Faults</a>
                <a href="{{ route('about.index') }}" class="{{ request()->routeIs('about.*') ? 'active' : '' }}">About</a>
                <span class="nav-spacer"></span>
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
            </nav>
        </div>
    </header>

    <main id="main" class="container" data-reveal>
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
