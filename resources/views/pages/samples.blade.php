<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @php
        use App\Support\LandingContent;
        $metaTitle = LandingContent::get('landing_samples_heading').' — '.LandingContent::siteName();
        $metaDescription = LandingContent::get('landing_samples_subheading');
    @endphp

    <title>{{ $metaTitle }}</title>
    <meta name="description" content="{{ $metaDescription }}">

    @include('partials.pwa-head')
    @include('partials.theme-head')

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @livewireStyles
</head>
<body class="bg-white text-gray-900 antialiased dark:bg-gray-950 dark:text-gray-100">

    <x-app-header />

    <main
        class="py-16"
        x-data="{ preview: null, open(url) { this.preview = url; document.body.style.overflow = 'hidden'; }, close() { this.preview = null; document.body.style.overflow = ''; } }"
    >
        <div class="mx-auto max-w-6xl px-4 sm:px-6">
            <p class="text-center text-xs font-semibold uppercase tracking-[0.18em] text-indigo-600">{{ LandingContent::get('landing_samples_eyebrow') }}</p>
            <h1 class="mt-3 text-center text-3xl font-bold font-display tracking-tight text-gray-900 dark:text-gray-100">{{ LandingContent::get('landing_samples_heading') }}</h1>
            <p class="mx-auto mt-3 max-w-2xl text-center text-sm text-gray-500 dark:text-gray-400">{{ LandingContent::get('landing_samples_subheading') }}</p>

            @php
                $samples = \App\Models\LandingFeature::section('samples')->visible()->get();
            @endphp

            @if ($samples->isEmpty())
                <p class="mt-16 text-center text-sm text-gray-400 dark:text-gray-500">No sample reports are available yet.</p>
            @endif

            <div class="mt-12 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($samples as $sample)
                    @php
                        $detailUrl = $sample->sampleUrl();
                    @endphp
                    <a href="{{ $detailUrl ?: '#' }}" wire:navigate class="group flex flex-col overflow-hidden rounded-xl border border-gray-200 bg-white text-left shadow-sm transition hover:border-indigo-300 hover:shadow-md dark:border-gray-700 dark:bg-gray-800 dark:hover:border-indigo-500">
                        @if ($sample->iconUrl())
                            <img src="{{ $sample->iconUrl() }}" alt="{{ $sample->title }}" class="aspect-8/3 w-full object-cover">
                        @else
                            <div class="flex aspect-8/3 w-full items-center justify-center bg-indigo-50 text-indigo-600 dark:bg-indigo-500/15">
                                <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke-width="1.4" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m.75 12l3 3m0 0l3-3m-3 3v-6m-1.5-9H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" /></svg>
                            </div>
                        @endif
                        <div class="flex flex-1 flex-col p-6">
                            <h3 class="text-base font-semibold text-gray-900 group-hover:text-indigo-700 dark:text-gray-100">{{ $sample->title }}</h3>
                            <p class="mt-1.5 text-sm leading-relaxed text-gray-500 dark:text-gray-400">{{ $sample->excerpt ?: $sample->description }}</p>
                            <span class="mt-4 inline-flex items-center gap-1 text-sm font-semibold text-indigo-600">{{ __('View sample report') }} &rarr;</span>
                        </div>
                    </a>
                @endforeach
            </div>
        </div>

        {{-- Sample preview modal: renders the live, paginated report in an iframe --}}
        <div
            x-show="preview"
            x-cloak
            x-transition.opacity
            @keydown.escape.window="close()"
            class="fixed inset-0 z-100 flex items-center justify-center bg-gray-900/70 p-4 backdrop-blur-sm"
            style="display:none"
        >
            <div class="flex h-full max-h-[92vh] w-full max-w-5xl flex-col overflow-hidden rounded-xl bg-white shadow-2xl dark:bg-gray-900" @click.outside="close()">
                <div class="flex items-center justify-between border-b border-gray-200 px-4 py-3 dark:border-gray-700">
                    <span class="text-sm font-semibold text-gray-700 dark:text-gray-200">{{ __('Sample report preview') }}</span>
                    <button type="button" @click="close()" class="rounded-md p-1.5 text-gray-400 transition hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-gray-800 dark:hover:text-gray-200" aria-label="{{ __('Close') }}">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                    </button>
                </div>
                <iframe x-bind:src="preview" class="h-full w-full flex-1 bg-white" title="{{ __('Sample report') }}"></iframe>
            </div>
        </div>
    </main>

    @livewireScripts
</body>
</html>
