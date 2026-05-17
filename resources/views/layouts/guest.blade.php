<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }} - Authentication</title>
        @include('partials.pwa-head')

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700|inter:400,500,600,700&display=swap" rel="stylesheet" />

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
    </head>
    <body class="lp min-h-screen">
        <div class="min-h-screen grid place-items-center px-4 py-12">
            <div class="w-full max-w-md">
                <div class="card" data-reveal>
                    <div class="flex justify-center mb-8">
                        <a href="{{ route('home') }}" aria-label="Life Platform home">
                            <x-application-logo class="h-12 w-auto" />
                        </a>
                    </div>
                    {{ $slot }}
                </div>
                <div class="mt-6 text-center text-sm muted">
                    <button type="button" class="theme-toggle" data-theme-toggle aria-label="Toggle dark and light mode" title="Toggle dark and light mode">
                        <svg data-theme-icon-sun xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 3v2.25M12 18.75V21M4.97 4.97l1.59 1.59M17.44 17.44l1.59 1.59M3 12h2.25M18.75 12H21M4.97 19.03l1.59-1.59M17.44 6.56l1.59-1.59M15.75 12A3.75 3.75 0 1112 8.25 3.75 3.75 0 0115.75 12z" />
                        </svg>
                        <svg data-theme-icon-moon xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="display:none;">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M21 12.79A9 9 0 1111.21 3c-.02.25-.03.5-.03.75a9 9 0 009.07 9.04c.25 0 .5-.01.75-.03z" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </body>
</html>
