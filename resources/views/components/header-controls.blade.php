{{-- Shared right-hand header cluster: language switch, theme toggle, and (when
     signed in) a profile dropdown with feedback + sign out. Reused by the full
     <x-app-header> and the sections editor's own header. --}}
<div class="flex items-center gap-1.5">
    {{-- Language switch --}}
    <div class="flex items-center rounded-md ring-1 ring-gray-200 dark:ring-gray-700">
        @foreach (['en' => 'EN', 'ne' => 'ने'] as $code => $label)
            <a href="{{ route('locale.switch', $code) }}"
               class="rounded-md px-2 py-1 text-xs font-semibold {{ app()->getLocale() === $code ? 'bg-indigo-600 text-white' : 'text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    {{-- Theme toggle --}}
    <button type="button" x-on:click="$store.theme.toggle()" :title="$store.theme.dark ? '{{ __('Light') }}' : '{{ __('Dark') }}'"
            class="rounded-md p-1.5 text-gray-500 ring-1 ring-gray-200 hover:text-gray-800 dark:text-gray-400 dark:ring-gray-700 dark:hover:text-gray-200">
        {{-- moon (shown in light mode) --}}
        <svg x-show="!$store.theme.dark" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.718 9.718 0 0118 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 003 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 009.002-5.998z" /></svg>
        {{-- sun (shown in dark mode) --}}
        <svg x-show="$store.theme.dark" x-cloak class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386l-1.591 1.591M21 12h-2.25m-.386 6.364l-1.591-1.591M12 18.75V21m-4.773-4.227l-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z" /></svg>
    </button>

    @auth
        {{-- Profile dropdown --}}
        <div x-data="{ open: false }" class="relative">
            <button type="button" x-on:click="open = !open" class="flex items-center gap-2 rounded-md p-1 pr-2 ring-1 ring-gray-200 hover:bg-gray-50 dark:ring-gray-700 dark:hover:bg-gray-800">
                <span class="flex h-7 w-7 items-center justify-center rounded-full bg-indigo-100 text-xs font-semibold text-indigo-700 dark:bg-indigo-500/20 dark:text-indigo-300">{{ auth()->user()->initials() }}</span>
                <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" /></svg>
            </button>

            <div x-show="open" x-cloak x-transition x-on:click.outside="open = false"
                 class="absolute right-0 z-50 mt-2 w-56 overflow-hidden rounded-lg bg-white py-1 shadow-lg ring-1 ring-black/5 dark:bg-gray-800 dark:ring-white/10">
                <div class="border-b border-gray-100 px-4 py-2 dark:border-gray-700">
                    <p class="truncate text-sm font-medium text-gray-900 dark:text-gray-100">{{ auth()->user()->name }}</p>
                    <p class="truncate text-xs text-gray-400">{{ auth()->user()->email }}</p>
                </div>

                <button type="button" x-on:click="open = false; $dispatch('open-feedback')" class="flex w-full items-center gap-2 px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-gray-700">
                    <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.129.166 2.27.293 3.423.379.35.026.67.21.865.501L12 21l2.755-4.133a1.14 1.14 0 01.865-.501 48.172 48.172 0 003.423-.379c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z" /></svg>
                    {{ __('Send feedback') }}
                </button>

                @if (auth()->user()->isAdmin())
                    <a href="{{ route('admin.dashboard') }}" wire:navigate class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-gray-700">
                        <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" /></svg>
                        {{ __('Admin panel') }}
                    </a>
                @endif

                <form method="POST" action="{{ route('logout') }}" class="border-t border-gray-100 dark:border-gray-700">
                    @csrf
                    <button type="submit" class="flex w-full items-center gap-2 px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-gray-700">
                        <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75" /></svg>
                        {{ __('Sign out') }}
                    </button>
                </form>
            </div>
        </div>
    @endauth
</div>
