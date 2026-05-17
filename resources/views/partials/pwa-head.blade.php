<meta name="application-name" content="{{ config('app.name', 'Life Platform') }}">
<meta name="apple-mobile-web-app-title" content="{{ config('app.name', 'Life Platform') }}">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="mobile-web-app-capable" content="yes">
<meta name="theme-color" content="#f3251e" media="(prefers-color-scheme: light)">
<meta name="theme-color" content="#111827" media="(prefers-color-scheme: dark)">
@php($webPushPublicKey = config('services.webpush.public_key') ?: \App\Models\Setting::getValue('webpush.vapid_public_key'))
@if ($webPushPublicKey)
<meta name="webpush-vapid-public-key" content="{{ $webPushPublicKey }}">
@endif
<link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
<link rel="apple-touch-icon" sizes="180x180" href="{{ asset('pwa/apple-touch-icon.png') }}">
<link rel="icon" type="image/png" sizes="32x32" href="{{ asset('pwa/favicon-32x32.png') }}">
<link rel="icon" type="image/png" sizes="16x16" href="{{ asset('pwa/favicon-16x16.png') }}">
