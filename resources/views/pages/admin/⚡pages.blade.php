<?php

use App\Models\CustomPage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::admin')] class extends Component
{
    /** The page being edited (null = create form is closed). */
    public ?int $editingId = null;

    public bool $formOpen = false;

    // Form fields
    public string $title = '';

    public string $slug = '';

    public string $content = '';

    public string $meta_title = '';

    public string $meta_description = '';

    public bool $published = false;

    public bool $show_in_footer = false;

    /** @return \Illuminate\Support\Collection<int, CustomPage> */
    public function pages()
    {
        return CustomPage::orderBy('order')->orderBy('title')->get();
    }

    /** Keep the slug in sync with the title until the user edits the slug. */
    public function updatedTitle(string $value): void
    {
        if ($this->editingId === null) {
            $this->slug = Str::slug($value);
        }
    }

    public function newPage(): void
    {
        $this->reset(['editingId', 'title', 'slug', 'content', 'meta_title', 'meta_description', 'published', 'show_in_footer']);
        $this->resetValidation();
        $this->formOpen = true;
    }

    public function edit(int $id): void
    {
        $page = CustomPage::findOrFail($id);

        $this->editingId = $page->id;
        $this->title = $page->title;
        $this->slug = $page->slug;
        $this->content = (string) $page->content;
        $this->meta_title = (string) $page->meta_title;
        $this->meta_description = (string) $page->meta_description;
        $this->published = $page->published;
        $this->show_in_footer = $page->show_in_footer;
        $this->resetValidation();
        $this->formOpen = true;
    }

    public function cancel(): void
    {
        $this->reset(['editingId', 'title', 'slug', 'content', 'meta_title', 'meta_description', 'published', 'show_in_footer', 'formOpen']);
        $this->resetValidation();
    }

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        return [
            'title' => 'required|string|max:160',
            'slug' => [
                'required', 'string', 'max:160', 'alpha_dash',
                Rule::unique('custom_pages', 'slug')->ignore($this->editingId),
                function ($attribute, $value, $fail) {
                    if (CustomPage::isReservedSlug($value)) {
                        $fail('That address is reserved by the app — pick another slug.');
                    }
                },
            ],
            'content' => 'nullable|string',
            'meta_title' => 'nullable|string|max:160',
            'meta_description' => 'nullable|string|max:300',
            'published' => 'boolean',
            'show_in_footer' => 'boolean',
        ];
    }

    public function save(): void
    {
        $data = $this->validate();

        CustomPage::updateOrCreate(
            ['id' => $this->editingId],
            $data,
        );

        $this->cancel();
        session()->flash('pages-saved', 'Page saved.');
    }

    public function togglePublished(int $id): void
    {
        $page = CustomPage::findOrFail($id);
        $page->update(['published' => ! $page->published]);
    }

    public function delete(int $id): void
    {
        CustomPage::find($id)?->delete();

        if ($this->editingId === $id) {
            $this->cancel();
        }

        session()->flash('pages-saved', 'Page deleted.');
    }
}; ?>

@php($title = 'Pages')

<div class="max-w-4xl space-y-6">
    <x-validation-popup />

    @if (session('pages-saved'))
        <div x-data="{ show: true }" x-show="show" x-transition x-init="setTimeout(() => show = false, 4000)"
             class="flex items-center gap-2 rounded-lg bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 ring-1 ring-emerald-200">
            <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
            {{ session('pages-saved') }}
        </div>
    @endif

    <div class="flex items-center justify-between gap-4">
        <div>
            <h1 class="text-lg font-semibold text-slate-900">Pages</h1>
            <p class="mt-1 text-sm text-slate-500">Build custom pages like Privacy Policy or Terms. Each lives at <code class="rounded bg-slate-100 px-1 text-xs">/your-slug</code>.</p>
        </div>
        @unless ($formOpen)
            <button type="button" wire:click="newPage" class="shrink-0 rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">+ New page</button>
        @endunless
    </div>

    {{-- ============ Editor form ============ --}}
    @if ($formOpen)
        <form wire:submit="save" class="space-y-5 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between">
                <h2 class="text-base font-semibold text-slate-900">{{ $editingId ? 'Edit page' : 'New page' }}</h2>
                <button type="button" wire:click="cancel" class="text-sm font-medium text-slate-500 hover:text-slate-800">Cancel</button>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium text-slate-700">Title</label>
                    <input type="text" wire:model.live="title" class="mt-1 block w-full rounded-md px-3 py-2 text-sm ring-1 ring-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    @error('title') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">URL slug</label>
                    <div class="mt-1 flex rounded-md ring-1 ring-slate-300 focus-within:ring-2 focus-within:ring-indigo-500">
                        <span class="inline-flex items-center rounded-l-md border-r border-slate-200 bg-slate-50 px-2 text-xs text-slate-500">/</span>
                        <input type="text" wire:model="slug" class="block w-full rounded-r-md px-3 py-2 text-sm focus:outline-none">
                    </div>
                    @error('slug') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            {{-- Rich-text content editor --}}
            <div wire:ignore>
                <label class="block text-sm font-medium text-slate-700">Content</label>
                <div x-data="pageEditor(@js($content))" class="mt-1 overflow-hidden rounded-md ring-1 ring-slate-300 focus-within:ring-2 focus-within:ring-indigo-500">
                    <div class="flex flex-wrap items-center gap-0.5 border-b border-slate-200 bg-slate-50 px-2 py-1.5 text-xs">
                        <button type="button" x-on:mousedown.prevent x-on:click="cmd('formatBlock','<p>')" class="pe-btn">Normal</button>
                        <button type="button" x-on:mousedown.prevent x-on:click="cmd('formatBlock','<h2>')" class="pe-btn font-semibold">H2</button>
                        <button type="button" x-on:mousedown.prevent x-on:click="cmd('formatBlock','<h3>')" class="pe-btn font-semibold">H3</button>
                        <span class="mx-1 h-4 w-px bg-slate-300"></span>
                        <button type="button" x-on:mousedown.prevent x-on:click="cmd('bold')" class="pe-btn font-bold">B</button>
                        <button type="button" x-on:mousedown.prevent x-on:click="cmd('italic')" class="pe-btn italic">I</button>
                        <button type="button" x-on:mousedown.prevent x-on:click="cmd('underline')" class="pe-btn underline">U</button>
                        <span class="mx-1 h-4 w-px bg-slate-300"></span>
                        <button type="button" x-on:mousedown.prevent x-on:click="cmd('insertUnorderedList')" class="pe-btn">&bull; List</button>
                        <button type="button" x-on:mousedown.prevent x-on:click="cmd('insertOrderedList')" class="pe-btn">1. List</button>
                        <button type="button" x-on:mousedown.prevent x-on:click="addLink()" class="pe-btn text-indigo-600">Link</button>
                    </div>
                    <div x-ref="editor" contenteditable="true" x-on:input="sync()" x-on:blur="sync()"
                         class="page-content-editor min-h-[260px] max-w-none px-4 py-3 text-sm leading-relaxed focus:outline-none"></div>
                </div>
            </div>
            <input type="hidden" wire:model="content">
            @error('content') <p class="text-xs text-red-600">{{ $message }}</p> @enderror

            {{-- SEO --}}
            <div class="grid grid-cols-1 gap-4 border-t border-slate-100 pt-4 sm:grid-cols-2">
                <div class="sm:col-span-2"><p class="text-xs font-semibold uppercase tracking-wide text-slate-500">SEO (optional)</p></div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Meta title</label>
                    <input type="text" wire:model="meta_title" placeholder="Defaults to the page title" class="mt-1 block w-full rounded-md px-3 py-2 text-sm ring-1 ring-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Meta description</label>
                    <input type="text" wire:model="meta_description" class="mt-1 block w-full rounded-md px-3 py-2 text-sm ring-1 ring-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-5 border-t border-slate-100 pt-4">
                <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" wire:model="published" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                    Published <span class="text-xs text-slate-400">(off = draft, returns 404 publicly)</span>
                </label>
                <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" wire:model="show_in_footer" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                    Show in landing footer
                </label>
                <button type="submit" class="ml-auto rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">Save page</button>
            </div>
        </form>
    @endif

    {{-- ============ Pages list ============ --}}
    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        @forelse ($this->pages() as $page)
            <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-3 last:border-b-0">
                <div class="min-w-0">
                    <p class="flex items-center gap-2 text-sm font-semibold text-slate-900">
                        {{ $page->title }}
                        @if ($page->published)
                            <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">Live</span>
                        @else
                            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-500">Draft</span>
                        @endif
                    </p>
                    <a href="{{ $page->url() }}" target="_blank" class="text-xs text-slate-400 hover:text-indigo-600">/{{ $page->slug }} ↗</a>
                </div>
                <div class="flex shrink-0 items-center gap-2">
                    <button type="button" wire:click="togglePublished({{ $page->id }})" class="rounded-md px-2.5 py-1.5 text-xs font-semibold {{ $page->published ? 'text-slate-500 hover:bg-slate-50' : 'text-emerald-600 hover:bg-emerald-50' }}">{{ $page->published ? 'Unpublish' : 'Publish' }}</button>
                    <button type="button" wire:click="edit({{ $page->id }})" class="rounded-md px-2.5 py-1.5 text-xs font-semibold text-indigo-600 hover:bg-indigo-50">Edit</button>
                    <button type="button" wire:click="delete({{ $page->id }})" wire:confirm="Delete this page?" class="rounded-md px-2.5 py-1.5 text-xs font-semibold text-red-600 hover:bg-red-50">Delete</button>
                </div>
            </div>
        @empty
            <div class="px-5 py-10 text-center text-sm text-slate-400">No pages yet. Create one to add a Privacy Policy, Terms, or any custom page.</div>
        @endforelse
    </div>
</div>

@push('head')
    <style>
        .pe-btn { padding: 3px 7px; border-radius: 5px; color: #334155; }
        .pe-btn:hover { background: #e2e8f0; }
        .page-content-editor h2 { font-size: 1.05rem; font-weight: 700; margin: 0.6rem 0 0.3rem; }
        .page-content-editor h3 { font-size: 0.98rem; font-weight: 700; margin: 0.5rem 0 0.25rem; }
        .page-content-editor p { margin: 0 0 0.6rem; }
        .page-content-editor ul { list-style: disc; padding-left: 1.4rem; margin: 0 0 0.6rem; }
        .page-content-editor ol { list-style: decimal; padding-left: 1.4rem; margin: 0 0 0.6rem; }
        .page-content-editor a { color: #4f46e5; text-decoration: underline; }
    </style>
    <script>
        function pageEditor(initial) {
            return {
                init() {
                    this.$refs.editor.innerHTML = initial || '<p></p>';
                },
                cmd(command, value = null) {
                    this.$refs.editor.focus();
                    document.execCommand(command, false, value);
                    this.sync();
                },
                addLink() {
                    var url = window.prompt('Link URL (https://…)');
                    if (url) { this.cmd('createLink', url); }
                },
                sync() {
                    this.$wire.set('content', this.$refs.editor.innerHTML, false);
                },
            };
        }
    </script>
@endpush
