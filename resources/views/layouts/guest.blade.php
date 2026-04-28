<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }} - Authentication</title>

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
                --bg: #f5f7fb;
                --surface: #ffffff;
                --border: #d9e1ec;
                --text: #1f2937;
                --muted: #6b7280;
                --primary: #1d4ed8;
                --shadow: 0 16px 44px rgba(2, 6, 23, 0.08);
                --ring: 0 0 0 4px rgba(29, 78, 216, 0.18);
            }
            html[data-theme="dark"] {
                --bg: #070f1b;
                --surface: #0b1220;
                --border: #253247;
                --text: #e5eefb;
                --muted: #94a3b8;
                --primary: #60a5fa;
                --shadow: 0 18px 56px rgba(0, 0, 0, 0.45);
            }
            body {
                margin: 0;
                font-family: 'Figtree', sans-serif;
                background: var(--bg);
                color: var(--text);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                position: relative;
                overflow-x: hidden;
            }
            .auth-background {
                position: absolute;
                top: 0; left: 0; right: 0; bottom: 0;
                z-index: -1;
                background: 
                    radial-gradient(circle at 15% 15%, rgba(29, 78, 216, 0.1), transparent 40%),
                    radial-gradient(circle at 85% 85%, rgba(147, 197, 253, 0.15), transparent 40%),
                    linear-gradient(135deg, var(--bg), var(--bg) 50%, rgba(29, 78, 216, 0.02));
            }
            .auth-card {
                background: var(--surface);
                border: 1px solid var(--border);
                border-radius: 24px;
                width: min(440px, calc(100% - 2rem));
                padding: 2.5rem;
                box-shadow: var(--shadow);
                backdrop-filter: blur(8px);
                animation: slideUp 0.6s cubic-bezier(0.16, 1, 0.3, 1);
            }
            @keyframes slideUp {
                from { opacity: 0; transform: translateY(20px); }
                to { opacity: 1; transform: translateY(0); }
            }
            .logo-wrap {
                text-align: center;
                margin-bottom: 2rem;
            }
            .logo-wrap img {
                height: 60px;
                width: auto;
                filter: drop-shadow(0 4px 12px rgba(2, 6, 23, 0.1));
            }
            /* Custom Form Styles to avoid Tailwind dependency for now */
            .auth-form-group { margin-bottom: 1.25rem; }
            .auth-label { display: block; font-weight: 600; font-size: 0.9rem; margin-bottom: 0.5rem; color: var(--text); }
            .auth-input {
                width: 100%;
                padding: 0.75rem 1rem;
                border-radius: 12px;
                border: 1px solid var(--border);
                background: var(--surface);
                color: var(--text);
                font-family: inherit;
                transition: all 0.2s;
            }
            .auth-input:focus { border-color: var(--primary); outline: none; box-shadow: var(--ring); }
            .auth-btn {
                width: 100%;
                padding: 0.85rem;
                border-radius: 12px;
                border: 0;
                background: var(--primary);
                color: #fff;
                font-weight: 700;
                cursor: pointer;
                transition: transform 0.1s, filter 0.2s;
                margin-top: 1rem;
            }
            .auth-btn:hover { filter: brightness(1.1); }
            .auth-btn:active { transform: scale(0.98); }
            .auth-links { margin-top: 1.5rem; text-align: center; font-size: 0.9rem; color: var(--muted); }
            .auth-links a { color: var(--primary); text-decoration: none; font-weight: 600; }
            .auth-links a:hover { text-decoration: underline; }
            .error-list { color: #ef4444; font-size: 0.85rem; margin-top: 0.5rem; list-style: none; padding: 0; }
        </style>
    </head>
    <body>
        <div class="auth-background"></div>
        <div class="auth-card">
            <div class="logo-wrap">
                <a href="/">
                    <img
                        src="{{ asset('branding/life-logo-light.svg') }}"
                        data-theme-logo
                        data-logo-light="{{ asset('branding/life-logo-light.svg') }}"
                        data-logo-dark="{{ asset('branding/life-logo-dark.svg') }}"
                        alt="Life Platform"
                    >
                </a>
            </div>

            {{ $slot }}
        </div>

        <script>
            (() => {
                const theme = document.documentElement.dataset.theme;
                document.querySelectorAll('[data-theme-logo]').forEach((logo) => {
                    logo.src = theme === 'dark' ? logo.dataset.logoDark : logo.dataset.logoLight;
                });
            })();
        </script>
    </body>
</html>
