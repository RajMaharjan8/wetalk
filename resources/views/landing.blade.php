<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @php
        use App\Support\LandingContent;
        $metaTitle = LandingContent::get('landing_meta_title');
        $metaDescription = LandingContent::get('landing_meta_description');
        $metaKeywords = LandingContent::get('landing_meta_keywords');
        $ogImage = LandingContent::imageUrl('landing_og_image');
        $twitterCard = LandingContent::get('landing_twitter_card') ?: 'summary_large_image';
        $heroImage = LandingContent::imageUrl('landing_hero_image');
    @endphp

    <title>{{ $metaTitle }}</title>
    <meta name="description" content="{{ $metaDescription }}">
    @if ($metaKeywords)
        <meta name="keywords" content="{{ $metaKeywords }}">
    @endif

    {{-- Open Graph / Facebook --}}
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:title" content="{{ $metaTitle }}">
    <meta property="og:description" content="{{ $metaDescription }}">
    @if ($ogImage)
        <meta property="og:image" content="{{ $ogImage }}">
    @endif

    {{-- Twitter --}}
    <meta name="twitter:card" content="{{ $twitterCard }}">
    <meta name="twitter:title" content="{{ $metaTitle }}">
    <meta name="twitter:description" content="{{ $metaDescription }}">
    @if ($ogImage)
        <meta name="twitter:image" content="{{ $ogImage }}">
    @endif

    @include('partials.pwa-head')

    @include('partials.theme-head')

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @livewireStyles
</head>
<body class="bg-white text-gray-900 antialiased dark:bg-gray-950 dark:text-gray-100">

    {{-- ============ Nav ============ --}}
    <header class="absolute inset-x-0 top-0 z-30">
        <nav class="mx-auto flex h-16 max-w-6xl items-center justify-between px-4 sm:px-6">
            <a href="{{ route('home') }}" class="flex items-center gap-2 text-white">
                <x-app-logo badge="bg-white/15 text-white ring-1 ring-white/25 backdrop-blur" />
                <span class="text-sm font-semibold">{{ LandingContent::siteName() }}</span>
            </a>
            <div class="hidden items-center gap-7 text-sm font-medium text-white/80 md:flex">
                <a href="#features" class="hover:text-white">Features</a>
                <a href="#how" class="hover:text-white">How it works</a>
                <a href="#formats" class="hover:text-white">Formats</a>
                <a href="#samples" class="hover:text-white">Samples</a>
                <a href="#faq" class="hover:text-white">FAQ</a>
            </div>
            <div class="flex items-center gap-3">
                {{-- Theme toggle. Self-contained so it never depends on the global
                     Alpine store's registration order: it reads the real <html>
                     class and writes the class + cookie + localStorage directly. --}}
                <button type="button"
                        x-data="{
                            dark: document.documentElement.classList.contains('dark'),
                            toggle() {
                                this.dark = !this.dark;
                                document.documentElement.classList.toggle('dark', this.dark);
                                var v = this.dark ? 'dark' : 'light';
                                try { localStorage.setItem('theme', v); } catch (e) {}
                                document.cookie = 'theme=' + v + ';path=/;max-age=31536000;samesite=lax';
                                if (window.Alpine && Alpine.store('theme')) { Alpine.store('theme').dark = this.dark; }
                            }
                        }"
                        x-on:click="toggle()" :title="dark ? 'Light' : 'Dark'"
                        class="rounded-md p-2 text-white/80 ring-1 ring-white/25 hover:bg-white/10 hover:text-white">
                    {{-- moon (shown in light mode) --}}
                    <svg x-show="!dark" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.718 9.718 0 0118 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 003 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 009.002-5.998z" /></svg>
                    {{-- sun (shown in dark mode) --}}
                    <svg x-show="dark" x-cloak class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386l-1.591 1.591M21 12h-2.25m-.386 6.364l-1.591-1.591M12 18.75V21m-4.773-4.227l-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z" /></svg>
                </button>
                <a href="{{ route('login') }}" class="rounded-md bg-white px-4 py-2 text-sm font-semibold text-indigo-700 shadow-sm hover:bg-gray-100">Open the generator</a>
            </div>
        </nav>
    </header>

    {{-- ============ Banner — full viewport, background image, text on the left ============ --}}
    <section class="relative flex min-h-[100dvh] items-center overflow-hidden">
        {{-- Background image + brand gradient overlay. Swap the url() for a real
             photo at public/images/hero.jpg to use your own image. --}}
        <div class="absolute inset-0 -z-10 bg-[linear-gradient(115deg,#1e3a8a_0%,#1d4ed8_45%,#2563eb_100%)] dark:bg-[linear-gradient(115deg,#0b1220_0%,#111c3a_45%,#16245c_100%)]"></div>
        <div class="absolute inset-0 -z-10 bg-[url('/images/hero.jpg')] bg-cover bg-center opacity-20 dark:opacity-10"></div>
        <div class="absolute inset-0 -z-10 bg-gradient-to-r from-black/40 via-black/10 to-transparent dark:from-black/70 dark:via-black/40"></div>

        <div class="mx-auto grid w-full max-w-6xl items-center gap-12 px-4 py-24 sm:px-6 {{ $heroImage ? 'lg:grid-cols-2' : '' }}">
            <div class="max-w-2xl text-left text-white">
                <p class="inline-block text-xs font-semibold uppercase tracking-[0.18em] text-white/70">
                    {{ LandingContent::get('landing_hero_eyebrow') }}
                    <span class="mt-2 block h-0.5 w-1/2 rounded-full bg-white/50"></span>
                </p>
                <h1 class="mt-4 text-4xl font-bold leading-tight sm:text-5xl lg:text-6xl">
                    {{ LandingContent::get('landing_hero_title') }}
                </h1>
                <p class="mt-5 max-w-xl text-base leading-relaxed text-white/85 sm:text-lg">
                    {{ LandingContent::get('landing_hero_subtitle') }}
                </p>
                <div class="mt-8 flex flex-wrap items-center gap-3">
                    <a href="{{ route('login') }}" class="rounded-md bg-white px-5 py-3 text-sm font-semibold text-indigo-700 shadow-sm hover:bg-gray-100">{{ LandingContent::get('landing_hero_primary_label') }}</a>
                    <a href="#how" class="rounded-md px-5 py-3 text-sm font-semibold text-white ring-1 ring-white/40 hover:bg-white/10">{{ LandingContent::get('landing_hero_secondary_label') }}</a>
                </div>
                <div class="mt-8 flex flex-wrap gap-x-6 gap-y-2 text-sm text-white/75">
                    @foreach (['landing_hero_badge_1', 'landing_hero_badge_2', 'landing_hero_badge_3'] as $badgeKey)
                        @if ($badge = LandingContent::getRaw($badgeKey))
                            <span>✓ {{ $badge }}</span>
                        @endif
                    @endforeach
                </div>
            </div>

            @if ($heroImage)
                <div class="hidden lg:block">
                    <img src="{{ $heroImage }}" alt="" class="ml-auto w-full max-w-lg rounded-2xl shadow-2xl ring-1 ring-white/20">
                </div>
            @endif
        </div>
    </section>

    {{-- ============ Features ============ --}}
    <section id="features" class="bg-gray-50 py-20 dark:bg-gray-900">
        <div class="mx-auto max-w-6xl px-4 sm:px-6">
            <p class="text-center text-xs font-semibold uppercase tracking-[0.18em] text-indigo-600">{{ LandingContent::get('landing_features_eyebrow') }}</p>
            <h2 class="mt-3 text-center text-3xl font-bold font-display tracking-tight text-gray-900 dark:text-gray-100">{{ LandingContent::get('landing_features_heading') }}</h2>
            <p class="mx-auto mt-3 max-w-2xl text-center text-sm text-gray-500 dark:text-gray-400">{{ LandingContent::get('landing_features_subheading') }}</p>

            <div class="mt-12 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach (\App\Models\LandingFeature::section('features')->visible()->get() as $feature)
                    <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                        <span class="flex h-10 w-10 items-center justify-center overflow-hidden rounded-lg bg-indigo-50 text-indigo-600 dark:bg-indigo-500/15">
                            @if ($feature->iconUrl())
                                <img src="{{ $feature->iconUrl() }}" alt="" class="h-full w-full object-contain">
                            @else
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                            @endif
                        </span>
                        <h3 class="mt-4 text-base font-semibold text-gray-900 dark:text-gray-100">{{ $feature->title }}</h3>
                        <p class="mt-1.5 text-sm leading-relaxed text-gray-500 dark:text-gray-400">{{ $feature->description }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ============ How it works ============ --}}
    <section id="how" class="py-20">
        <div class="mx-auto max-w-6xl px-4 sm:px-6">
            <p class="text-center text-xs font-semibold uppercase tracking-[0.18em] text-indigo-600">Three steps, one block</p>
            <h2 class="mt-3 text-center text-3xl font-bold font-display tracking-tight text-gray-900 dark:text-gray-100">From blank page to bound report</h2>
            <p class="mx-auto mt-3 max-w-xl text-center text-sm text-gray-500 dark:text-gray-400">No setup, no template wrangling. The whole flow lives in a single, focused workspace.</p>

            <div class="mt-12 grid gap-8 sm:grid-cols-3">
                @php
                    $steps = [
                        ['1', 'Add your details', 'Enter the title, author, guide and department once. Your title page and front matter build themselves.'],
                        ['2', 'Write & cite', 'Write your chapters, add your sources, and reference them inline with [[key]]. The preview updates live.'],
                        ['3', 'Export the PDF', 'Pick IEEE or APA, choose your margins, and download a polished, submission-ready document.'],
                    ];
                @endphp
                @foreach ($steps as [$n, $title, $body])
                    <div>
                        <span class="text-3xl font-bold text-indigo-600">{{ $n }}</span>
                        <h3 class="mt-2 text-base font-semibold text-gray-900 dark:text-gray-100">{{ $title }}</h3>
                        <p class="mt-1.5 text-sm leading-relaxed text-gray-500 dark:text-gray-400">{{ $body }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ============ University formats ============ --}}
    <section id="formats" class="bg-gray-50 py-20 dark:bg-gray-900">
        <div class="mx-auto max-w-6xl px-4 sm:px-6">
            <p class="text-center text-xs font-semibold uppercase tracking-[0.18em] text-indigo-600">{{ LandingContent::get('landing_formats_eyebrow') }}</p>
            <h2 class="mt-3 text-center text-3xl font-bold font-display tracking-tight text-gray-900 dark:text-gray-100">{{ LandingContent::get('landing_formats_heading') }}</h2>
            <p class="mx-auto mt-3 max-w-2xl text-center text-sm text-gray-500 dark:text-gray-400">{{ LandingContent::get('landing_formats_subheading') }}</p>

            <div class="mt-12 grid gap-5 lg:grid-cols-3">
                @foreach (\App\Models\LandingFeature::section('formats')->visible()->get() as $format)
                    <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                        @if ($format->iconUrl())
                            <img src="{{ $format->iconUrl() }}" alt="" class="h-9 w-9 rounded-lg object-contain">
                        @else
                            <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-indigo-600 text-sm font-bold text-white">{{ $format->badgeText() }}</span>
                        @endif
                        <h3 class="mt-4 text-base font-semibold text-gray-900 dark:text-gray-100">{{ $format->title }}</h3>
                        <p class="mt-1.5 text-sm leading-relaxed text-gray-500 dark:text-gray-400">{{ $format->description }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ============ Sample reports ============ --}}
    <section id="samples" class="py-20" x-data="{ preview: null, open(url) { this.preview = url; document.body.style.overflow = 'hidden'; }, close() { this.preview = null; document.body.style.overflow = ''; } }">
        <div class="mx-auto max-w-6xl px-4 sm:px-6">
            <p class="text-center text-xs font-semibold uppercase tracking-[0.18em] text-indigo-600">{{ LandingContent::get('landing_samples_eyebrow') }}</p>
            <h2 class="mt-3 text-center text-3xl font-bold font-display tracking-tight text-gray-900 dark:text-gray-100">{{ LandingContent::get('landing_samples_heading') }}</h2>
            <p class="mx-auto mt-3 max-w-2xl text-center text-sm text-gray-500 dark:text-gray-400">{{ LandingContent::get('landing_samples_subheading') }}</p>

            <div class="mt-12 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach (\App\Models\LandingFeature::section('samples')->visible()->get() as $sample)
                    @php
                        $previewUrl = $sample->link ?: null;
                        $clickAttr = $previewUrl ? "open('".e($previewUrl)."')" : '';
                    @endphp
                    <button
                        type="button"
                        x-on:click="{{ $clickAttr }}"
                        class="group flex flex-col overflow-hidden rounded-xl border border-gray-200 bg-white text-left shadow-sm transition hover:border-indigo-300 hover:shadow-md dark:border-gray-700 dark:bg-gray-800 dark:hover:border-indigo-500"
                    >
                        {{-- 400×150 banner image --}}
                        @if ($sample->iconUrl())
                            <img src="{{ $sample->iconUrl() }}" alt="{{ $sample->title }}" class="aspect-[8/3] w-full object-cover">
                        @else
                            <div class="flex aspect-[8/3] w-full items-center justify-center bg-indigo-50 text-indigo-600 dark:bg-indigo-500/15">
                                <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke-width="1.4" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m.75 12l3 3m0 0l3-3m-3 3v-6m-1.5-9H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" /></svg>
                            </div>
                        @endif
                        <div class="flex flex-1 flex-col p-6">
                            <h3 class="text-base font-semibold text-gray-900 group-hover:text-indigo-700 dark:text-gray-100">{{ $sample->title }}</h3>
                            <p class="mt-1.5 text-sm leading-relaxed text-gray-500 dark:text-gray-400">{{ $sample->description }}</p>
                            <span class="mt-4 inline-flex items-center gap-1 text-sm font-semibold text-indigo-600">View sample report &rarr;</span>
                        </div>
                    </button>
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
                    <span class="text-sm font-semibold text-gray-700 dark:text-gray-200">Sample report preview</span>
                    <button type="button" @click="close()" class="rounded-md p-1.5 text-gray-400 transition hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-gray-800 dark:hover:text-gray-200" aria-label="Close">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                    </button>
                </div>
                <iframe x-bind:src="preview" class="h-full w-full flex-1 bg-white" title="Sample report"></iframe>
            </div>
        </div>
    </section>

    {{-- ============ FAQ ============ --}}
    <section id="faq" class="bg-gray-50 py-20 dark:bg-gray-900" x-data="{ open: 1 }">
        <div class="mx-auto max-w-3xl px-4 sm:px-6">
            <p class="text-center text-xs font-semibold uppercase tracking-[0.18em] text-indigo-600">Questions</p>
            <h2 class="mt-3 text-center text-3xl font-bold font-display tracking-tight text-gray-900 dark:text-gray-100">Frequently asked</h2>

            <div class="mt-10 divide-y divide-gray-200 overflow-hidden rounded-xl border border-gray-200 bg-white dark:divide-gray-700 dark:border-gray-700 dark:bg-gray-800">
                @foreach (\App\Models\LandingFeature::section('faqs')->visible()->get() as $i => $faq)
                    <div>
                        <button type="button" x-on:click="open = (open === {{ $i + 1 }} ? null : {{ $i + 1 }})" class="flex w-full items-center justify-between gap-4 px-5 py-4 text-left">
                            <span class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $faq->title }}</span>
                            <svg class="h-4 w-4 shrink-0 text-gray-400 transition-transform" :class="open === {{ $i + 1 }} ? 'rotate-45' : ''" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                        </button>
                        <div x-show="open === {{ $i + 1 }}" x-collapse>
                            <p class="px-5 pb-4 text-sm leading-relaxed text-gray-500 dark:text-gray-400">{{ $faq->description }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ============ Footer ============ --}}
    <footer class="border-t border-gray-200 py-8 dark:border-gray-800">
        <div class="mx-auto flex max-w-6xl flex-col items-center justify-between gap-3 px-4 text-sm sm:flex-row sm:px-6">
            <div class="flex items-center gap-2 text-gray-900 dark:text-gray-100">
                <x-app-logo size="h-7 w-7" text="text-xs" />
                <span class="font-semibold">{{ LandingContent::siteName() }}</span>
            </div>
            <div class="flex flex-wrap items-center gap-x-6 gap-y-1 text-gray-500 dark:text-gray-400">
                <a href="#features" class="hover:text-gray-900 dark:hover:text-gray-200">Features</a>
                <a href="#how" class="hover:text-gray-900 dark:hover:text-gray-200">How it works</a>
                <a href="#formats" class="hover:text-gray-900 dark:hover:text-gray-200">Formats</a>
                <a href="#faq" class="hover:text-gray-900 dark:hover:text-gray-200">FAQ</a>
                <a href="{{ route('login') }}" class="font-semibold text-indigo-600 hover:text-indigo-500">Open the generator</a>
            </div>
        </div>
        <div class="mx-auto mt-4 max-w-6xl px-4 text-xs text-gray-400 sm:px-6">
            <p>{!! LandingContent::copyrightHtml() !!}</p>
            @if ($tagline = LandingContent::get('landing_footer_tagline'))
                <p class="mt-1">{{ $tagline }}</p>
            @endif
        </div>
    </footer>

    @livewireScripts
</body>
</html>
