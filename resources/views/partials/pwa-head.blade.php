{{-- Progressive Web App: makes the project installable on desktop & mobile. --}}
<link rel="manifest" href="/manifest.webmanifest">
<meta name="theme-color" content="#4f46e5">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="Report Generator">

{{-- Favicon: admin-uploaded icon (Settings) when set, otherwise the default. --}}
@php($favicon = \App\Support\LandingContent::imageUrl('site_favicon'))
@if ($favicon)
    <link rel="icon" href="{{ $favicon }}">
    <link rel="apple-touch-icon" href="{{ $favicon }}">
@else
    <link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
@endif
