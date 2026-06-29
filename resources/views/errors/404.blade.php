<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>{{ __('Page not found') }} — {{ \App\Support\LandingContent::siteName() }}</title>

    @include('partials.pwa-head')
    @include('partials.theme-head')

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-white text-gray-900 antialiased dark:bg-gray-950 dark:text-gray-100">
    <div class="flex min-h-screen flex-col">
        {{-- Brand --}}
        <header class="px-6 py-5">
            <a href="{{ route('home') }}" class="inline-flex items-center gap-2">
                <x-app-logo />
                <span class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ \App\Support\LandingContent::siteName() }}</span>
            </a>
        </header>

        {{-- Content --}}
        <main class="flex flex-1 items-center justify-center px-6 py-12">
            <div class="w-full max-w-md text-center">
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-indigo-600">{{ __('Error 404') }}</p>

                <h1 class="mt-4 font-display text-7xl font-bold tracking-tight text-gray-900 dark:text-gray-100 sm:text-8xl">404</h1>

                <h2 class="mt-4 text-xl font-semibold text-gray-900 dark:text-gray-100">{{ __('This page could not be found') }}</h2>
                <p class="mt-2 text-sm leading-relaxed text-gray-500 dark:text-gray-400">
                    {{ __('The page you’re looking for doesn’t exist, may have been moved, or the link is broken.') }}
                </p>

                <div class="mt-8 flex flex-col items-center justify-center gap-3 sm:flex-row">
                    <a href="{{ route('home') }}" class="inline-flex w-full items-center justify-center gap-1.5 rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500 sm:w-auto">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75" /></svg>
                        {{ __('Back to home') }}
                    </a>
                    <a href="{{ route('samples.index') }}" class="inline-flex w-full items-center justify-center rounded-lg px-5 py-2.5 text-sm font-semibold text-gray-700 ring-1 ring-gray-300 transition hover:bg-gray-50 dark:text-gray-200 dark:ring-gray-700 dark:hover:bg-gray-800 sm:w-auto">
                        {{ __('Browse sample reports') }}
                    </a>
                </div>
            </div>
        </main>

        {{-- Footer --}}
        <footer class="px-6 py-6 text-center text-xs text-gray-400 dark:text-gray-600">
            {!! \App\Support\LandingContent::copyrightHtml() !!}
        </footer>
    </div>
</body>
</html>
