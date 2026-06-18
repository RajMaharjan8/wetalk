<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">

        <title>{{ $title ?? config('app.name') }}</title>

        @include('partials.theme-head')

        @include('partials.pwa-head')

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        @stack('head')

        @livewireStyles
    </head>
    <body class="bg-gray-50 text-gray-900 dark:bg-gray-900 dark:text-gray-100">
        {{ $slot }}

        @auth
            <livewire:feedback />
        @endauth

        @livewireScripts
    </body>
</html>
