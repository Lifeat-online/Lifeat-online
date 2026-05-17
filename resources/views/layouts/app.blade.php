<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>
        @include('partials.pwa-head')
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
            .app-shell-bg {
                background: #f3f4f6;
                color: #111827;
            }
            .app-nav {
                background: #ffffff;
                border-color: #e5e7eb;
            }
            .app-header-surface {
                background: #ffffff;
            }
            .theme-toggle-admin {
                box-shadow: 0 1px 2px rgba(15, 23, 42, 0.06);
            }
            html[data-theme="dark"] .app-shell-bg {
                background: #0b1220;
                color: #e5eefb;
            }
            html[data-theme="dark"] .app-nav {
                background: #111827;
                border-color: #1f2937;
            }
            html[data-theme="dark"] .app-header-surface {
                background: #111827;
                color: #e5eefb;
            }
            html[data-theme="dark"] .app-header-surface h2,
            html[data-theme="dark"] .app-header-surface div,
            html[data-theme="dark"] .app-header-surface span {
                color: inherit;
            }
            html[data-theme="dark"] .theme-toggle-admin {
                border-color: #334155;
                background: #0f172a;
                color: #e5eefb;
            }

            /* Global Dark Mode Overrides */
            html[data-theme="dark"] body { color: #e2e8f0; }
            html[data-theme="dark"] .bg-white { background-color: #111827 !important; color: #e2e8f0 !important; }
            html[data-theme="dark"] .text-gray-800 { color: #f8fafc !important; }
            html[data-theme="dark"] .text-gray-700 { color: #cbd5e1 !important; }
            html[data-theme="dark"] .text-gray-600 { color: #94a3b8 !important; }
            html[data-theme="dark"] .text-gray-900 { color: #ffffff !important; }
            
            html[data-theme="dark"] input:not([type="submit"]):not([type="button"]), 
            html[data-theme="dark"] select, 
            html[data-theme="dark"] textarea {
                background-color: #0f172a !important;
                color: #ffffff !important;
                border-color: #334155 !important;
            }
            html[data-theme="dark"] input::placeholder { color: #475569 !important; }
            
            html[data-theme="dark"] .shadow, 
            html[data-theme="dark"] .shadow-sm { 
                box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.2), 0 2px 4px -2px rgba(0, 0, 0, 0.2) !important;
                border: 1px solid #1f2937;
            }
        </style>

        <!-- Favicon -->
        <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700|inter:400,500,600,700&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @stack('styles')
    </head>
    <body class="font-sans antialiased">
        <div class="min-h-screen app-shell-bg">
            @include('layouts.navigation')

            <!-- Page Heading -->
            @isset($header)
                <header class="shadow app-header-surface">
                    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endisset

            <!-- Page Content -->
            <main>
                {{ $slot }}
            </main>
        </div>
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
