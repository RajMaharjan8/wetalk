{{-- App header: logo (left) + shared controls (right). Used across the
     user-facing pages. --}}
<header class="border-b border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
    <div class="mx-auto flex h-14 max-w-6xl items-center justify-between gap-4 px-4 sm:px-6">
        <a href="{{ route('landing') }}" wire:navigate class="flex items-center gap-2">
            <x-app-logo />
            <span class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ \App\Support\LandingContent::siteName() }}</span>
        </a>

        <x-header-controls />
    </div>
</header>
