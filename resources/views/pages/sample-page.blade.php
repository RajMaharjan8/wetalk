<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @php
        use App\Support\LandingContent;
        $metaTitle = $feature->meta_title ?: ($feature->title.' — '.LandingContent::siteName());
        $metaDescription = $feature->meta_description ?: $feature->excerpt ?: strip_tags($feature->description ?? '');
    @endphp

    <title>{{ $metaTitle }}</title>
    <meta name="description" content="{{ $metaDescription }}">
    @if ($feature->meta_keywords)
        <meta name="keywords" content="{{ $feature->meta_keywords }}">
    @endif

    <meta property="og:type" content="article">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:title" content="{{ $metaTitle }}">
    <meta property="og:description" content="{{ $metaDescription }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $metaTitle }}">
    <meta name="twitter:description" content="{{ $metaDescription }}">

    @include('partials.pwa-head')
    @include('partials.theme-head')

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @livewireStyles
</head>
<body class="bg-white text-gray-900 antialiased dark:bg-gray-950 dark:text-gray-100">

    <x-app-header />

    <main class="py-16">
        <div class="mx-auto max-w-3xl px-4 sm:px-6">
            <a href="{{ route('samples.index') }}" wire:navigate class="inline-flex items-center gap-1 text-sm font-medium text-indigo-600 hover:text-indigo-500">
                &larr; {{ __('All sample reports') }}
            </a>

            <h1 class="mt-4 text-3xl font-bold font-display tracking-tight text-gray-900 dark:text-gray-100">{{ $feature->title }}</h1>

            @if ($feature->excerpt)
                <p class="mt-3 text-lg text-gray-500 dark:text-gray-400">{{ $feature->excerpt }}</p>
            @endif

            {{-- View the actual sample: live report (link field) and/or PDF. --}}
            <div class="mt-6 flex flex-wrap gap-3">
                @if ($feature->link)
                    <a href="{{ $feature->link }}" target="_blank" rel="noopener"
                       class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                        {{ __('View sample') }} &rarr;
                    </a>
                @endif
                @if ($feature->pdfUrl())
                    <a href="{{ $feature->pdfUrl() }}" target="_blank" rel="noopener"
                       class="inline-flex items-center gap-1.5 rounded-lg px-5 py-2.5 text-sm font-semibold text-indigo-600 ring-1 ring-indigo-200 hover:bg-indigo-50 dark:ring-indigo-500/40 dark:hover:bg-indigo-500/10">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" /></svg>
                        {{ __('View PDF') }}
                    </a>
                @endif
            </div>

            @if ($feature->description)
                <div class="tiptap-content mt-8 max-w-none text-gray-700 dark:text-gray-300">{!! $feature->description !!}</div>
            @endif

            {{-- Embedded PDF --}}
            @if ($feature->pdfUrl())
                <div class="mt-10">
                    <div class="mb-3 flex items-center justify-between">
                        <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('Sample PDF') }}</h2>
                        <a href="{{ $feature->pdfUrl() }}" target="_blank" rel="noopener" class="text-sm font-semibold text-indigo-600 hover:text-indigo-500">{{ __('Open in new tab') }} &rarr;</a>
                    </div>
                    <iframe src="{{ $feature->pdfUrl() }}" class="h-[80vh] w-full rounded-lg border border-gray-200 bg-gray-50 dark:border-gray-700" title="{{ $feature->title }} PDF"></iframe>
                </div>
            @endif
        </div>

        {{-- Related samples (3 others) --}}
        @if ($related->isNotEmpty())
            <div class="mx-auto mt-16 max-w-6xl px-4 sm:px-6">
                <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100">{{ __('More sample reports') }}</h2>
                <div class="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($related as $other)
                        <a href="{{ $other->sampleUrl() ?: '#' }}" wire:navigate class="group flex flex-col overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm transition hover:border-indigo-300 hover:shadow-md dark:border-gray-700 dark:bg-gray-800 dark:hover:border-indigo-500">
                            @if ($other->iconUrl())
                                <img src="{{ $other->iconUrl() }}" alt="{{ $other->title }}" class="aspect-8/3 w-full object-cover">
                            @else
                                <div class="flex aspect-8/3 w-full items-center justify-center bg-indigo-50 text-indigo-600 dark:bg-indigo-500/15">
                                    <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke-width="1.4" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m.75 12l3 3m0 0l3-3m-3 3v-6m-1.5-9H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" /></svg>
                                </div>
                            @endif
                            <div class="flex flex-1 flex-col p-6">
                                <h3 class="text-base font-semibold text-gray-900 group-hover:text-indigo-700 dark:text-gray-100">{{ $other->title }}</h3>
                                <p class="mt-1.5 text-sm leading-relaxed text-gray-500 dark:text-gray-400">{{ $other->excerpt ?: $other->description }}</p>
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif
    </main>

    @livewireScripts
</body>
</html>
