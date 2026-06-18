@php
    $isActive = $activeSection?->is($section);
    $excerpt = \Illuminate\Support\Str::limit(trim(strip_tags(\App\Support\SectionContent::toHtml($section->content))), 200);
@endphp

<li
    wire:key="section-{{ $section->id }}"
    wire:sort:item="{{ $section->id }}"
    data-card-key="{{ ($isFront ? 'front-' : 'sec-').$section->id }}"
    x-data="{ collapsed: false }"
    class="group overflow-hidden rounded-lg bg-white shadow-sm ring-1 transition-shadow {{ $isActive ? 'ring-indigo-300' : 'ring-gray-200' }} dark:bg-gray-800 {{ $isActive ? 'dark:ring-indigo-500' : 'dark:ring-gray-700' }}"
>
    {{-- Card header --}}
    <div class="flex items-center gap-2 px-3 py-2" :class="(!collapsed && @js($isActive)) ? 'border-b border-gray-100 dark:border-gray-700' : ''">
        <span wire:sort:handle class="cursor-grab select-none text-gray-300 hover:text-gray-500 dark:text-gray-600 dark:hover:text-gray-400" title="{{ __('Drag to reorder') }}">⠿</span>

        <button type="button" @click="collapsed = !collapsed" class="shrink-0 rounded p-0.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-700 dark:hover:text-gray-300" :title="collapsed ? @js(__('Expand')) : @js(__('Collapse'))" :aria-expanded="(!collapsed).toString()">
            <svg class="h-4 w-4 transition-transform duration-200" :class="collapsed ? '-rotate-90' : ''" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" /></svg>
        </button>

        @if ($isFront)
            <span class="inline-flex h-6 shrink-0 items-center rounded-md bg-amber-50 px-2 text-[11px] font-semibold text-amber-800 dark:bg-amber-900/40 dark:text-amber-300" title="{{ __('Front-matter page — shown before the contents') }}">{{ __('Front page') }}</span>
        @else
            <span class="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-md bg-indigo-50 text-xs font-semibold text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300" title="{{ __('Section number') }}">{{ $number }}</span>
        @endif

        <button type="button" @click="collapsed = false" wire:click="selectSection({{ $section->id }})" class="flex-1 truncate text-left text-sm font-semibold {{ $section->hidden ? 'text-gray-400 dark:text-gray-500' : 'text-gray-900 dark:text-gray-100' }}">
            {{ $section->title }}
            @if ($section->hidden)
                <span class="ml-1 rounded bg-gray-100 px-1.5 py-0.5 align-middle text-[10px] font-medium uppercase tracking-wide text-gray-500 dark:bg-gray-700 dark:text-gray-400">{{ __('Hidden') }}</span>
            @endif
        </button>

        <button type="button" wire:click="toggleVisibility({{ $section->id }})" class="shrink-0 rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-gray-700 dark:hover:text-gray-200" title="{{ $section->hidden ? __('Show in report') : __('Hide from report (keeps the content)') }}">
            @if ($section->hidden)
                {{-- eye-slash --}}
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" /></svg>
            @else
                {{-- eye --}}
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
            @endif
        </button>

        <button type="button" wire:click="deleteSection({{ $section->id }})" wire:confirm="{{ $isFront ? __('Delete this page?') : __('Delete this section?') }}" class="shrink-0 rounded p-1 text-gray-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-900/30 dark:hover:text-red-400" title="{{ __('Delete') }}">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" /></svg>
        </button>
    </div>

    {{-- Body: the full editor when active, a click-to-edit excerpt otherwise.
         Collapsible — hidden when the card is collapsed (the editor stays
         mounted, just visually hidden). --}}
    <div x-show="!collapsed" x-collapse.duration.200ms>
        @if ($isActive)
            @include('reports.partials.section-editor')
        @else
            <button type="button" wire:click="selectSection({{ $section->id }})" class="block w-full px-3 pb-3 text-left">
                <span class="line-clamp-3 text-sm leading-relaxed {{ $excerpt === '' ? 'text-gray-400 italic dark:text-gray-500' : 'text-gray-600 dark:text-gray-300' }}">
                    {{ $excerpt === '' ? __('Empty — click to write…') : $excerpt }}
                </span>
            </button>
        @endif
    </div>
</li>
