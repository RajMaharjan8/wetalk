<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>{{ $page->meta_title ?: $page->title }} — {{ \App\Support\LandingContent::siteName() }}</title>
    @if ($page->meta_description)
        <meta name="description" content="{{ $page->meta_description }}">
    @endif
    <meta property="og:title" content="{{ $page->meta_title ?: $page->title }}">
    @if ($page->meta_description)
        <meta property="og:description" content="{{ $page->meta_description }}">
    @endif

    @include('partials.theme-head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-white text-gray-900 antialiased dark:bg-gray-950 dark:text-gray-100">

    {{-- Header --}}
    <header class="border-b border-gray-200 dark:border-gray-800">
        <div class="mx-auto flex h-14 max-w-3xl items-center justify-between gap-4 px-4 sm:px-6">
            <a href="{{ route('home') }}" class="flex items-center gap-2">
                <x-app-logo />
                <span class="text-sm font-semibold">{{ \App\Support\LandingContent::siteName() }}</span>
            </a>
            <a href="{{ route('home') }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-500">&larr; {{ __('Back to home') }}</a>
        </div>
    </header>

    {{-- Content --}}
    <main class="mx-auto max-w-3xl px-4 py-12 sm:px-6">
        <h1 class="text-3xl font-bold font-display tracking-tight text-gray-900 dark:text-gray-100">{{ $page->title }}</h1>
        <p class="mt-2 text-xs text-gray-400">{{ __('Last updated') }} {{ $page->updated_at->format('F j, Y') }}</p>

        <div class="custom-page-content mt-8 text-gray-700 dark:text-gray-300">
            {!! $page->content !!}
        </div>
    </main>

    {{-- Footer --}}
    <footer class="mt-12 border-t border-gray-200 py-8 dark:border-gray-800">
        <div class="mx-auto max-w-3xl px-4 text-xs text-gray-400 sm:px-6">
            <p>{!! \App\Support\LandingContent::copyrightHtml() !!}</p>
        </div>
    </footer>

    @livewireScripts
</body>
</html>
