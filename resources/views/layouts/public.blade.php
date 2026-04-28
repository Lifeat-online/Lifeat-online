<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Life Platform')</title>
    @stack('head')
    <!-- Fonts -->
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
    <style>
        :root {
            color-scheme: light;
            --bg: #f5f7fb;
            --surface: #ffffff;
            --border: #d9e1ec;
            --text: #1f2937;
            --muted: #6b7280;
            --primary: #1d4ed8;
            --primary-dark: #1e3a8a;
            --shadow: 0 16px 44px rgba(2, 6, 23, 0.08);
            --shadow-soft: 0 10px 30px rgba(2, 6, 23, 0.06);
            --ring: 0 0 0 4px rgba(29, 78, 216, 0.18);
        }
        html[data-theme="dark"] {
            color-scheme: dark;
            --bg: #070f1b;
            --surface: #0b1220;
            --border: #253247;
            --text: #e5eefb;
            --muted: #94a3b8;
            --primary: #60a5fa;
            --primary-dark: #1d4ed8;
            --shadow: 0 18px 56px rgba(0, 0, 0, 0.45);
            --shadow-soft: 0 12px 38px rgba(0, 0, 0, 0.35);
            --ring: 0 0 0 4px rgba(96, 165, 250, 0.22);
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: Figtree, ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, Arial, "Apple Color Emoji", "Segoe UI Emoji";
            background: var(--bg);
            color: var(--text);
            line-height: 1.55;
        }
        a { color: var(--primary); text-decoration: none; }
        a:hover { text-decoration: underline; }
        a:focus-visible, button:focus-visible, input:focus-visible, select:focus-visible, textarea:focus-visible { outline: none; box-shadow: var(--ring); }

        .site-header {
            background:
                radial-gradient(1100px circle at 15% 0%, rgba(167, 243, 208, 0.25), transparent 45%),
                radial-gradient(900px circle at 85% 0%, rgba(147, 197, 253, 0.28), transparent 40%),
                linear-gradient(135deg, #0b1220, #1d4ed8 58%, #1e3a8a);
            color: #fff;
            padding: 0;
            margin-bottom: 2rem;
        }
        html[data-theme="dark"] .site-header {
            background:
                radial-gradient(1100px circle at 20% 0%, rgba(96, 165, 250, 0.16), transparent 45%),
                radial-gradient(1100px circle at 82% 0%, rgba(168, 85, 247, 0.12), transparent 45%),
                linear-gradient(135deg, #020617, #0b1220 60%, #1e3a8a);
        }
        .topbar { border-bottom: 1px solid rgba(255, 255, 255, 0.14); }
        .topbar-inner { display: flex; gap: 1rem; align-items: center; justify-content: space-between; padding: 0.75rem 0; font-size: 0.95rem; color: rgba(219, 234, 254, 0.95); }
        .topbar-copy { margin: 0; }
        .container { width: min(1120px, calc(100% - 2rem)); margin: 0 auto; }
        .nav {
            display: flex;
            gap: 0.35rem;
            flex-wrap: wrap;
            padding: 1rem 0 1.4rem;
            align-items: center;
        }
        .nav a {
            color: rgba(219, 234, 254, 0.96);
            padding: 0.55rem 0.85rem;
            border-radius: 999px;
            border: 1px solid transparent;
            font-weight: 600;
        }
        .nav a.active, .nav a:hover {
            background: rgba(255, 255, 255, 0.12);
            border-color: rgba(255, 255, 255, 0.16);
            text-decoration: none;
        }
        .nav-spacer { flex: 1 1 auto; }
        .brand-link { display: inline-flex; align-items: center; }
        .brand-logo {
            display: block;
            width: min(280px, 100%);
            height: auto;
            max-height: 64px;
            border-radius: 16px;
            box-shadow: 0 16px 44px rgba(2, 6, 23, 0.30);
        }
        .page-copy { color: rgba(219, 234, 254, 0.92); max-width: 56rem; margin: 0.8rem 0 0 0; }
        .grid { display: grid; gap: 1rem; }
        .grid-2 { grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); }
        .grid-3 { grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); }
        .card { background: var(--surface); border: 1px solid var(--border); border-radius: 16px; padding: 1.25rem; box-shadow: var(--shadow-soft); }
        .hero { display: grid; gap: 1.5rem; grid-template-columns: 2fr 1fr; align-items: start; margin-bottom: 2rem; }
        .badge { display: inline-block; background: #dbeafe; color: var(--primary-dark); border-radius: 999px; padding: 0.2rem 0.65rem; font-size: 0.85rem; margin-right: 0.35rem; margin-bottom: 0.35rem; }
        html[data-theme="dark"] .badge { background: #172554; color: #bfdbfe; }
        .muted { color: var(--muted); }
        .stats { display: grid; gap: 1rem; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); margin-top: 1rem; }
        .stat-number { font-size: 2rem; font-weight: 700; color: var(--primary-dark); }
        .section { margin-bottom: 2rem; }
        .section-head { display: flex; justify-content: space-between; gap: 1rem; align-items: baseline; margin-bottom: 1rem; }
        .meta { display: flex; flex-wrap: wrap; gap: 0.75rem; color: var(--muted); font-size: 0.95rem; }
        .h2-tight { margin: 0.35rem 0 0; }
        .h2-block { margin: 0.45rem 0 0.75rem; }
        .h3-tight { margin: 0; }
        .h3-block { margin: 0.35rem 0 0.6rem; }
        .h3-card { margin: 0.4rem 0; }
        .lead-title { font-size: 1.9rem; margin: 0.4rem 0 0.8rem; }
        .mt-08 { margin-top: 0.8rem; }
        .mt-09 { margin-top: 0.9rem; }
        .mt-10 { margin-top: 1rem; }
        .mt-05 { margin-top: 0.5rem; }
        .mt-065 { margin-top: 0.65rem; }
        .mb-0 { margin-bottom: 0; }
        .h3-cat { margin: 0.35rem 0 0.4rem; }
        .h3-cta { margin: 0.35rem 0 0.5rem; }
        .pad-10 { padding: 1rem; }
        .lh-17 { line-height: 1.7; }
        .btn-soft {
            background: rgba(29, 78, 216, 0.10);
            color: var(--primary-dark);
            border: 1px solid rgba(29, 78, 216, 0.14);
        }
        html[data-theme="dark"] .btn-soft {
            background: rgba(96, 165, 250, 0.12);
            color: var(--text);
            border-color: rgba(96, 165, 250, 0.18);
        }
        .media-220 { height: 220px; }
        .list-spaced { margin: 0.9rem 0 0; padding-left: 1.1rem; }
        .form-grid { display: grid; gap: 1rem; grid-template-columns: 2fr 1fr auto; align-items: end; }
        label { display: block; font-size: 0.92rem; font-weight: 700; margin-bottom: 0.35rem; }
        input, select, textarea {
            width: 100%;
            padding: 0.85rem 0.95rem;
            border-radius: 12px;
            border: 1px solid var(--border);
            background: #fff;
            font: inherit;
            box-shadow: 0 1px 0 rgba(2, 6, 23, 0.03);
        }
        html[data-theme="dark"] input,
        html[data-theme="dark"] select,
        html[data-theme="dark"] textarea { background: #0b1220; color: var(--text); }
        .button-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.16);
            color: #fff;
            padding: 0.6rem 0.95rem;
            font-weight: 700;
        }
        .button-link:hover { background: rgba(255, 255, 255, 0.18); text-decoration: none; }
        .button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 0;
            border-radius: 12px;
            background: var(--primary);
            color: #fff;
            padding: 0.9rem 1.05rem;
            font-weight: 800;
            cursor: pointer;
            box-shadow: 0 14px 34px rgba(29, 78, 216, 0.18);
        }
        html[data-theme="dark"] .button { box-shadow: 0 16px 40px rgba(96, 165, 250, 0.14); }
        .button:hover { text-decoration: none; filter: brightness(1.02); }
        .empty-state { padding: 2rem; text-align: center; border: 1px dashed var(--border); border-radius: 16px; background: var(--surface); color: var(--muted); }
        .detail-grid { display: grid; gap: 1.5rem; grid-template-columns: 2fr 1fr; }
        .stack > * + * { margin-top: 1rem; }
        .footer { color: var(--muted); font-size: 0.9rem; padding: 0 0 2rem; }
        .footer-grid { display: grid; gap: 1rem; grid-template-columns: 2fr 1fr 1fr; margin-top: 2rem; }
        .theme-toggle {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 2.75rem;
            height: 2.75rem;
            border: 0;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.12);
            color: #fff;
            cursor: pointer;
            border: 1px solid rgba(255, 255, 255, 0.16);
        }
        .theme-toggle:hover { background: rgba(255, 255, 255, 0.20); }
        .theme-toggle svg { width: 1.2rem; height: 1.2rem; }
        @media (max-width: 840px) {
            .hero, .detail-grid, .form-grid, .footer-grid { grid-template-columns: 1fr; }
            .topbar-inner { flex-direction: column; align-items: flex-start; }
            .brand-logo { max-height: 56px; }
        }
    </style>
    @stack('styles')
</head>
<body>
    <header class="site-header">
        <div class="topbar">
            <div class="container topbar-inner">
                <p class="topbar-copy">Eastern Freestate local news, business discovery, events, and community opportunities.</p>
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
            <p class="page-copy">A fast, clean, modern-looking local front door for editorial content, featured businesses, upcoming events, and advertising opportunities across the Eastern Freestate.</p>
            <nav class="nav">
                <a href="{{ route('home') }}" class="{{ request()->routeIs('home') ? 'active' : '' }}">Home</a>
                <a href="{{ route('directory.index') }}" class="{{ request()->routeIs('directory.*') ? 'active' : '' }}">Directory</a>
                <a href="{{ route('events.index') }}" class="{{ request()->routeIs('events.*') ? 'active' : '' }}">Events</a>
                <a href="{{ route('articles.index') }}" class="{{ request()->routeIs('articles.*') ? 'active' : '' }}">Articles</a>
                <a href="{{ route('classifieds.index') }}" class="{{ request()->routeIs('classifieds.*') ? 'active' : '' }}">Classifieds</a>
                <a href="{{ route('advertise.index') }}" class="{{ request()->routeIs('advertise.*') ? 'active' : '' }}">Advertise</a>
                <a href="{{ route('search.index') }}" class="{{ request()->routeIs('search.*') ? 'active' : '' }}">Search</a>
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
                        <button type="submit" class="button-link" style="border:0; cursor:pointer;">Logout</button>
                    </form>
                @else
                    <a href="{{ route('login') }}" class="button-link">Login</a>
                    <a href="{{ route('register') }}" class="button-link">Register</a>
                @endauth
            </nav>
        </div>
    </header>

    <main class="container">
        @yield('content')
    </main>

    <footer class="container footer">
        <div class="footer-grid">
            <div>
                <strong>Eastern Freestate</strong>
                <p>Life@ News blends local editorial coverage with business discovery, event visibility, and commercial promotion paths for the region.</p>
            </div>
            <div>
                <strong>Explore</strong>
                <p><a href="{{ route('articles.index') }}">Latest Articles</a></p>
                <p><a href="{{ route('directory.index') }}">Business Directory</a></p>
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
    </footer>
    <script>
        (() => {
            const key = 'life-theme';
            const root = document.documentElement;

            const applyTheme = (theme) => {
                root.dataset.theme = theme;
                root.style.colorScheme = theme;
                localStorage.setItem(key, theme);

                document.querySelectorAll('[data-theme-logo]').forEach((logo) => {
                    logo.src = theme === 'dark' ? logo.dataset.logoDark : logo.dataset.logoLight;
                });

                document.querySelectorAll('[data-theme-icon-sun]').forEach((icon) => {
                    icon.style.display = theme === 'dark' ? 'none' : 'block';
                });

                document.querySelectorAll('[data-theme-icon-moon]').forEach((icon) => {
                    icon.style.display = theme === 'dark' ? 'block' : 'none';
                });
            };

            const toggleTheme = () => applyTheme(root.dataset.theme === 'dark' ? 'light' : 'dark');

            applyTheme(root.dataset.theme === 'dark' ? 'dark' : 'light');

            document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
                button.addEventListener('click', toggleTheme);
            });
        })();
    </script>
    @stack('scripts')
</body>
</html>
