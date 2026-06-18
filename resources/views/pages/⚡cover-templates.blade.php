<?php

use App\Models\CoverTemplate;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public ?int $editingId = null;

    public string $name = '';

    public string $html = '';

    public $image;

    public function mount(): void
    {
        $this->startNew();
    }

    /** @return \Illuminate\Support\Collection<int, CoverTemplate> */
    public function getTemplatesProperty()
    {
        return CoverTemplate::where('user_id', auth()->id())->latest()->get();
    }

    public function getAtLimitProperty(): bool
    {
        return $this->templates->count() >= CoverTemplate::MAX_PER_USER;
    }

    protected function starterHtml(): string
    {
        return '<h1>Project Title</h1><p>Your Name</p><p>Your Institution</p><p>Month, Year</p>';
    }

    public function startNew(): void
    {
        $this->editingId = null;
        $this->name = '';
        $this->html = $this->starterHtml();
        $this->resetErrorBag();
        $this->dispatch('cover-loaded', html: $this->html);
    }

    public function edit(int $id): void
    {
        $template = CoverTemplate::where('user_id', auth()->id())->findOrFail($id);

        $this->editingId = $template->id;
        $this->name = $template->name;
        $this->html = (string) $template->html;
        $this->resetErrorBag();
        $this->dispatch('cover-loaded', html: $this->html);
    }

    public function saveCover(string $html): void
    {
        $this->html = $html;

        $this->validate([
            'name' => 'required|string|max:60',
            'html' => 'required|string|max:100000',
        ]);

        if ($this->editingId) {
            CoverTemplate::where('user_id', auth()->id())
                ->findOrFail($this->editingId)
                ->update(['name' => $this->name, 'html' => $this->html]);
        } else {
            if ($this->atLimit) {
                $this->addError('name', __('You can save up to :max covers. Delete one to add another.', ['max' => CoverTemplate::MAX_PER_USER]));

                return;
            }

            $template = CoverTemplate::create([
                'user_id' => auth()->id(),
                'name' => $this->name,
                'html' => $this->html,
            ]);

            $this->editingId = $template->id;
        }

        session()->flash('cover-tpl-saved', __('Saved “:name”.', ['name' => $this->name]));
    }

    public function delete(int $id): void
    {
        CoverTemplate::where('user_id', auth()->id())->whereKey($id)->delete();

        if ($this->editingId === $id) {
            $this->startNew();
        }
    }

    public function updatedImage(): void
    {
        $this->validate(['image' => 'image|max:4096']);

        $path = $this->image->store('cover-images', 'public');
        $this->image = null;

        $this->dispatch('cover-image', url: Storage::disk('public')->url($path));
    }
}; ?>

@push('head')
    <link rel="stylesheet" href="{{ asset('css/report.css') }}?v={{ filemtime(public_path('css/report.css')) }}">
@endpush

<div class="min-h-screen bg-gray-100 dark:bg-gray-900">
    <x-app-header />
    <header class="border-b border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
        <div class="mx-auto flex max-w-7xl flex-col gap-2 px-4 py-3 sm:flex-row sm:items-center sm:justify-between sm:px-6">
            <div>
                <a href="{{ route('reports.index') }}" wire:navigate class="text-xs font-medium text-indigo-600 hover:text-indigo-500">&larr; {{ __('Back to reports') }}</a>
                <h1 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('Cover Designer') }}</h1>
            </div>
            <button type="button" wire:click="startNew" class="self-start rounded-md bg-white px-3 py-1.5 text-sm font-semibold text-gray-900 ring-1 ring-gray-300 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-100 dark:ring-gray-600 dark:hover:bg-gray-700 sm:self-auto">{{ __('+ New cover') }}</button>
        </div>
    </header>

    <div class="mx-auto flex max-w-7xl flex-col gap-6 px-4 py-6 sm:px-6 lg:flex-row">
        {{-- Builder --}}
        <main class="min-w-0 flex-1"
            x-data="{
                cmd(c, v = null) { this.$refs.canvas.focus(); document.execCommand(c, false, v); },
                block(tag) { this.$refs.canvas.focus(); document.execCommand('formatBlock', false, tag); },
            }"
            x-init="$nextTick(() => { document.execCommand('styleWithCSS', false, true); $refs.canvas.innerHTML = @js($html); })"
            @cover-loaded.window="$refs.canvas.innerHTML = $event.detail.html"
            @cover-image.window="$refs.canvas.focus(); document.execCommand('insertImage', false, $event.detail.url)"
        >
            <div class="rounded-xl bg-white shadow-sm ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-gray-700">
                {{-- Name + save --}}
                <div class="flex flex-wrap items-center gap-2 border-b border-gray-200 px-4 py-2 dark:border-gray-700">
                    <input type="text" wire:model="name" placeholder="{{ __('Name this cover (e.g. My TU Cover)') }}" class="min-w-50 flex-1 rounded-md px-2 py-1.5 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-gray-900 dark:text-gray-100 dark:ring-gray-600">
                    <button type="button" x-on:click="$wire.saveCover($refs.canvas.innerHTML)" class="rounded-md bg-indigo-600 px-4 py-1.5 text-sm font-semibold text-white hover:bg-indigo-500">
                        {{ $editingId ? __('Update cover') : __('Save cover') }}
                    </button>
                </div>
                @error('name') <p class="px-4 pt-2 text-xs text-red-600">{{ $message }}</p> @enderror
                @error('image') <p class="px-4 pt-2 text-xs text-red-600">{{ $message }}</p> @enderror
                @if (session('cover-tpl-saved'))
                    <p class="px-4 pt-2 text-xs font-medium text-green-700">{{ session('cover-tpl-saved') }}</p>
                @endif

                {{-- Toolbar --}}
                <div class="se-toolbar">
                    <button type="button" x-on:mousedown.prevent x-on:click="block('h1')" class="toolbar-btn font-semibold" title="{{ __('Big title') }}">{{ __('Title') }}</button>
                    <button type="button" x-on:mousedown.prevent x-on:click="block('h2')" class="toolbar-btn font-semibold" title="{{ __('Subtitle') }}">{{ __('Subtitle') }}</button>
                    <button type="button" x-on:mousedown.prevent x-on:click="block('p')" class="toolbar-btn" title="{{ __('Normal text') }}">{{ __('Normal') }}</button>
                    <span class="toolbar-divider"></span>
                    <button type="button" x-on:mousedown.prevent x-on:click="cmd('bold')" class="toolbar-btn font-bold">B</button>
                    <button type="button" x-on:mousedown.prevent x-on:click="cmd('italic')" class="toolbar-btn italic">I</button>
                    <button type="button" x-on:mousedown.prevent x-on:click="cmd('underline')" class="toolbar-btn underline">U</button>
                    <span class="toolbar-divider"></span>
                    <button type="button" x-on:mousedown.prevent x-on:click="cmd('justifyLeft')" class="toolbar-btn">{{ __('Left') }}</button>
                    <button type="button" x-on:mousedown.prevent x-on:click="cmd('justifyCenter')" class="toolbar-btn">{{ __('Center') }}</button>
                    <button type="button" x-on:mousedown.prevent x-on:click="cmd('justifyRight')" class="toolbar-btn">{{ __('Right') }}</button>
                    <span class="toolbar-divider"></span>
                    <label class="toolbar-btn cursor-pointer" title="{{ __('Insert an image') }}">
                        {{ __('+ Image') }}
                        <input type="file" wire:model="image" accept="image/*" class="hidden">
                    </label>
                    <span wire:loading wire:target="image" class="se-group-label">{{ __('Uploading…') }}</span>
                </div>

                {{-- Canvas --}}
                <p class="px-4 pt-3 text-xs text-gray-500 dark:text-gray-400">{!! __('Click into the page below and type. Select text, then use the buttons above. Add images with <strong>+ Image</strong>.') !!}</p>
                <div class="overflow-x-auto px-4 py-4">
                    <div wire:ignore x-ref="canvas" contenteditable="true" spellcheck="false"
                        class="cover-sheet-custom cover-custom mx-auto shadow ring-1 ring-gray-200 focus:outline-none focus:ring-2 focus:ring-indigo-400 dark:ring-gray-700">
                    </div>
                </div>
            </div>
        </main>

        {{-- Saved covers --}}
        <aside class="w-full space-y-3 lg:w-72 lg:shrink-0">
            <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-gray-700">
                <h2 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Your saved covers') }}</h2>
                <p class="mt-1 text-[11px] text-gray-400 dark:text-gray-400">{{ __('Up to :max. Open one on a report from its cover page.', ['max' => \App\Models\CoverTemplate::MAX_PER_USER]) }}</p>

                <ul class="mt-3 space-y-2">
                    @forelse ($this->templates as $template)
                        <li class="flex items-center justify-between gap-2 rounded-md px-2 py-1.5 text-sm {{ $editingId === $template->id ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300' : 'text-gray-700 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-700' }}">
                            <button type="button" wire:click="edit({{ $template->id }})" class="flex-1 truncate text-left font-medium">{{ $template->name }}</button>
                            <button type="button" wire:click="delete({{ $template->id }})" wire:confirm="{{ __('Delete this saved cover?') }}" class="text-xs text-red-600 hover:text-red-800">{{ __('Delete') }}</button>
                        </li>
                    @empty
                        <li class="px-2 py-1.5 text-xs text-gray-500 dark:text-gray-400">{{ __('No saved covers yet — design one and click Save.') }}</li>
                    @endforelse
                </ul>

                @if ($this->atLimit && ! $editingId)
                    <p class="mt-3 rounded-md bg-amber-50 px-2 py-1.5 text-[11px] text-amber-800 dark:bg-amber-500/10 dark:text-amber-300">{{ __('You\'ve saved :max covers. Delete one to create another.', ['max' => \App\Models\CoverTemplate::MAX_PER_USER]) }}</p>
                @endif
            </div>
        </aside>
    </div>
</div>
