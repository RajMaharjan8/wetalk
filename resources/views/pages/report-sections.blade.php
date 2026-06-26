<?php

use App\Models\Report;
use App\Models\Section;
use App\Support\CitationFormatter;
use App\Support\ReportCompiler;
use App\Support\SectionContent;
use Livewire\Component;

new class extends Component
{
    public Report $report;

    public ?Section $activeSection = null;

    public string $newSectionTitle = '';

    public string $newFrontPageTitle = '';

    public string $newBackPageTitle = '';

    public string $editTitle = '';

    public function mount(Report $report): void
    {
        $this->authorize('update', $report);

        $this->report = $report;

        $requestedId = (int) request()->query('section');

        $this->activeSection = ($requestedId
            ? $report->sections()->whereKey($requestedId)->first()
            : null) ?? $report->sections()->first();

        $this->syncEditorFromActive();
    }

    /**
     * Custom front-matter pages, shown before the contents.
     */
    public function getFrontPagesProperty()
    {
        return $this->report->sections()->where('placement', 'front')->orderBy('order')->get();
    }

    /**
     * Numbered body sections (1, 2, 3 …).
     */
    public function getBodySectionsProperty()
    {
        return $this->report->sections()->where('placement', 'body')->orderBy('order')->get();
    }

    /**
     * Unnumbered back-matter pages (References, Appendix), shown after the body.
     */
    public function getBackPagesProperty()
    {
        return $this->report->sections()->where('placement', 'back')->orderBy('order')->get();
    }

    /**
     * The compiled report (front matter + numbered sections, citations and
     * figure/table numbers resolved) used to render the live preview pane.
     */
    public function getPreviewProperty(): ReportCompiler
    {
        return ReportCompiler::for($this->report->load('sections'));
    }

    /**
     * The compiler id of the active section, so the preview can mark which
     * block to mirror live as the user types ('sec-<id>' or 'front-<id>').
     */
    public function getActivePreviewIdProperty(): ?string
    {
        if (! $this->activeSection) {
            return null;
        }

        $prefix = match (true) {
            $this->activeSection->isFrontPage() => 'front-',
            $this->activeSection->isBackPage() => 'back-',
            default => 'sec-',
        };

        return $prefix.$this->activeSection->id;
    }

    /**
     * The 1-based position of the active body section, used as its heading
     * number. Front-matter pages are unnumbered and return 0.
     */
    public function getActiveSectionNumberProperty(): int
    {
        if (! $this->activeSection || $this->activeSection->isFrontPage()) {
            return 0;
        }

        return (int) $this->report->sections()
            ->where('placement', 'body')
            ->where('hidden', false)
            ->orderBy('order')
            ->pluck('id')
            ->search($this->activeSection->id) + 1;
    }

    /**
     * How many figures and tables appear in the body sections before the
     * active one, so the editor can continue the count instead of restarting
     * at 1 in every section (matching the compiled report).
     *
     * @return array{figures: int, tables: int}
     */
    public function getFigureTableOffsetProperty(): array
    {
        if (! $this->activeSection || $this->activeSection->isFrontPage()) {
            return ['figures' => 0, 'tables' => 0];
        }

        $figures = 0;
        $tables = 0;

        $prior = $this->report->sections()
            ->where('placement', 'body')
            ->where('hidden', false)
            ->where('order', '<', $this->activeSection->order)
            ->orderBy('order')
            ->get();

        foreach ($prior as $section) {
            [$f, $t] = $this->countFiguresTables(SectionContent::toHtml($section->content));
            $figures += $f;
            $tables += $t;
        }

        return ['figures' => $figures, 'tables' => $tables];
    }

    /**
     * Count figures (a <figure> containing an <img>) and tables in a chunk of
     * HTML — the same rule the report compiler uses for numbering.
     *
     * @return array{0: int, 1: int}
     */
    protected function countFiguresTables(string $html): array
    {
        if (trim($html) === '') {
            return [0, 0];
        }

        $document = new \DOMDocument;
        libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8"><div>'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $figures = 0;

        foreach ($document->getElementsByTagName('figure') as $figure) {
            if ($figure->getElementsByTagName('img')->length > 0) {
                $figures++;
            }
        }

        return [$figures, $document->getElementsByTagName('table')->length];
    }

    public function addSection(): void
    {
        if ($this->createPage($this->newSectionTitle, 'body')) {
            $this->newSectionTitle = '';
        }
    }

    public function addFrontPage(): void
    {
        if ($this->createPage($this->newFrontPageTitle, 'front')) {
            $this->newFrontPageTitle = '';
        }
    }

    public function addBackPage(): void
    {
        if ($this->createPage($this->newBackPageTitle, 'back')) {
            $this->newBackPageTitle = '';
        }
    }

    /**
     * Create a body section or front-matter page, then open it in the editor.
     */
    protected function createPage(string $title, string $placement): bool
    {
        $title = trim($title);

        if ($title === '') {
            return false;
        }

        $order = ($this->report->sections()->max('order') ?? -1) + 1;

        $section = $this->report->sections()->create([
            'placement' => $placement,
            'title' => $title,
            'order' => $order,
            'content' => null,
        ]);

        $this->selectSection($section->id);

        return true;
    }

    public function selectSection(int $sectionId): void
    {
        $section = $this->report->sections()->whereKey($sectionId)->first();

        if (! $section) {
            return;
        }

        $this->activeSection = $section;
        $this->syncEditorFromActive();
    }

    /**
     * Toggle whether a section is hidden from the compiled report. The content
     * is kept — hidden sections simply don't render in the preview or output.
     */
    public function toggleVisibility(int $sectionId): void
    {
        $section = $this->report->sections()->whereKey($sectionId)->first();

        if ($section) {
            $section->update(['hidden' => ! $section->hidden]);
        }
    }

    public function deleteSection(int $sectionId): void
    {
        $section = $this->report->sections()->whereKey($sectionId)->first();

        if (! $section) {
            return;
        }

        $wasActive = $this->activeSection && $this->activeSection->is($section);

        $section->delete();

        if ($wasActive) {
            $this->activeSection = $this->report->sections()->first();
            $this->syncEditorFromActive();
        }
    }

    /**
     * Move a section to a new position after a drag-and-drop sort.
     *
     * Livewire's wire:sort calls this with the dragged item's key and its
     * new index; every section's `order` is then rewritten 0..n.
     */
    public function reorder(mixed $item, int $position = 0): void
    {
        $itemId = (int) $item;

        $moved = $this->report->sections()->whereKey($itemId)->first();

        if (! $moved) {
            return;
        }

        $front = $this->report->sections()->where('placement', 'front')->orderBy('order')->pluck('id')->all();
        $body = $this->report->sections()->where('placement', 'body')->orderBy('order')->pluck('id')->all();

        $isFront = $moved->isFrontPage();
        $ids = $isFront ? $front : $body;
        $ids = array_values(array_filter($ids, fn ($id) => (int) $id !== $itemId));

        array_splice($ids, $position, 0, [$itemId]);

        if ($isFront) {
            $front = $ids;
        } else {
            $body = $ids;
        }

        foreach (array_merge($front, $body) as $order => $id) {
            Section::whereKey($id)
                ->where('report_id', $this->report->id)
                ->update(['order' => $order]);
        }
    }

    /**
     * Build a JSON-friendly map of every reference belonging to this report,
     * with the inline citation pre-rendered in each supported format. Handed
     * to the editor so the citation picker can render the correct inline text
     * without a server round-trip.
     *
     * @return list<array{id: int, type: string, label: string, inline: array<string, string>}>
     */
    public function getReferencesPayloadProperty(): array
    {
        $references = $this->report->references()->get();
        $payload = [];

        foreach (CitationFormatter::FORMATS as $format) {
            $formatter = new CitationFormatter($format, $references);

            foreach ($references as $reference) {
                $payload[$reference->id]['id'] = (int) $reference->id;
                $payload[$reference->id]['type'] = $reference->type;
                $payload[$reference->id]['label'] = $this->referenceLabel($reference);
                $payload[$reference->id]['inline'][$format] = $formatter->inline($reference);
            }
        }

        return array_values($payload);
    }

    protected function referenceLabel(\App\Models\Reference $reference): string
    {
        $authors = trim((string) $reference->field('authors', ''));
        $year = trim((string) $reference->field('year', ''));
        $title = trim((string) $reference->field('title', $reference->field('site_name', '')));

        $head = trim(($authors !== '' ? $authors : '').($year !== '' ? " ({$year})" : ''));

        return $head === '' ? ($title !== '' ? $title : 'Untitled reference') : "{$head} — {$title}";
    }

    public function save(?string $content = null)
    {
        if (! $this->activeSection) {
            return null;
        }

        $title = trim($this->editTitle);

        $this->activeSection->update([
            'title' => $title === '' ? $this->activeSection->title : $title,
            'content' => $content,
        ]);

        // Reload the page so the saved title and content are shown back to the user.
        return $this->redirectRoute('reports.sections', [
            'report' => $this->report,
            'section' => $this->activeSection->id,
        ], navigate: true);
    }

    protected function syncEditorFromActive(): void
    {
        $this->editTitle = $this->activeSection?->title ?? '';
    }
}; ?>

<div
    x-data="{ editorOpen: false }"
    x-init="$store.preview.html = @js($activeSection ? \App\Support\SectionContent::toHtml($activeSection->content) : ''); $store.preview.title = @js($activeSection?->title ?? '')"
    class="flex min-h-screen flex-col bg-gray-100 lg:h-screen lg:overflow-hidden"
>
    <header class="z-20 border-b border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 dark:text-gray-100">
        <div class="flex items-center justify-between gap-3 px-4 py-3 sm:px-6">
            <div class="min-w-0">
                <a href="{{ route('reports.cover', ['report' => $report]) }}" class="text-xs font-medium text-indigo-600 hover:text-indigo-500">&larr; {{ __('Back to cover') }}</a>
                <h1 class="truncate text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $report->module_code }} &middot; {{ $report->module_title }}</h1>
            </div>
            <div class="flex shrink-0 flex-wrap items-center gap-2">
                <button type="button" @click="editorOpen = true" class="inline-flex items-center gap-1.5 rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-500 lg:hidden">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z" /></svg>
                    {{ __('Edit chapters') }}
                </button>
                <livewire:manage-references :report="$report" />
                <a href="{{ route('reports.output', ['report' => $report]) }}"
                   class="group view-report-cta relative inline-flex items-center gap-1.5 rounded-md bg-indigo-600 px-3.5 py-1.5 text-xs font-semibold text-white shadow-sm ring-1 ring-indigo-500/30 transition hover:bg-indigo-500 hover:shadow-md focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-400">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                    {{ __('View full report') }}
                    <svg class="h-3.5 w-3.5 transition-transform duration-200 group-hover:translate-x-0.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" /></svg>
                </a>
                <x-header-controls />
            </div>
        </div>
    </header>

    <div x-data="scrollSync()" data-active-key="{{ $this->activePreviewId }}" @preview-goto="goTo($event.detail.key)" @resize.window="onScroll()" class="relative flex flex-1 min-h-0 lg:overflow-hidden">
        {{-- Mobile backdrop behind the off-canvas editor --}}
        <div x-show="editorOpen" x-transition.opacity @click="editorOpen = false" class="fixed inset-0 z-30 bg-black/40 lg:hidden" x-cloak></div>

        {{-- LEFT: the editor column. On mobile it is an off-canvas drawer; on
             large screens it is a static split pane beside the preview. --}}
        <aside
            x-ref="editor"
            :class="editorOpen ? 'translate-x-0' : '-translate-x-full'"
            class="fixed inset-y-0 left-0 z-40 flex w-[92%] max-w-md -translate-x-full flex-col gap-4 overflow-y-auto bg-gray-100 p-4 shadow-xl transition-transform duration-300 lg:static lg:z-auto lg:min-h-0 lg:w-[48%] lg:max-w-none lg:translate-x-0 lg:border-r lg:border-gray-200 lg:shadow-none dark:bg-gray-900 dark:lg:border-gray-800"
        >
            <div class="flex items-start justify-between">
                <div>
                    <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('Content') }}</h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Chapters & sources — cite with') }} <code class="rounded bg-gray-200 px-1 text-[11px] dark:bg-gray-800 dark:text-gray-200">[[key]]</code></p>
                </div>
                <button type="button" @click="editorOpen = false" class="rounded-md p-1.5 text-gray-400 hover:bg-gray-200 lg:hidden dark:hover:bg-gray-800" title="{{ __('Close') }}">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            </div>

            {{-- Front-matter pages — shown after the cover, before the contents --}}
            <section>
                <h3 class="px-1 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Preliminary pages') }}</h3>
                <p class="mt-0.5 px-1 text-[11px] text-gray-400 dark:text-gray-500">{{ __('Pages before the chapters (e.g. Acknowledgements, Abstract).') }}</p>
                <ul wire:sort="reorder" class="mt-2 space-y-3">
                    @forelse ($this->frontPages as $section)
                        @include('reports.partials.section-card', ['section' => $section, 'isFront' => true, 'number' => null])
                    @empty
                        <li class="rounded-lg bg-white px-3 py-3 text-center text-xs text-gray-400 ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-gray-700">{{ __('No preliminary pages yet') }}</li>
                    @endforelse
                </ul>
                <form wire:submit="addFrontPage" class="mt-3 flex gap-1">
                    <input type="text" wire:model="newFrontPageTitle" placeholder="{{ __('Add a preliminary page — e.g. Acknowledgements') }}" class="block w-full rounded-md px-2 py-1.5 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-gray-900 dark:text-gray-100 dark:ring-gray-600">
                    <button type="submit" class="shrink-0 rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-indigo-500">{{ __('Add') }}</button>
                </form>
            </section>

            {{-- Numbered body sections --}}
            <section>
                <h3 class="px-1 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Sections') }}</h3>
                <ul wire:sort="reorder" class="mt-2 space-y-3">
                    @forelse ($this->bodySections as $section)
                        @include('reports.partials.section-card', ['section' => $section, 'isFront' => false, 'number' => $loop->iteration])
                    @empty
                        <li class="rounded-lg bg-white px-3 py-3 text-center text-xs text-gray-400 ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-gray-700">{{ __('No sections yet') }}</li>
                    @endforelse
                </ul>
                <form wire:submit="addSection" class="mt-3 flex gap-1">
                    <input type="text" wire:model="newSectionTitle" placeholder="{{ __('Add section — e.g. Introduction') }}" class="block w-full rounded-md px-2 py-1.5 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-gray-900 dark:text-gray-100 dark:ring-gray-600">
                    <button type="submit" class="shrink-0 rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-indigo-500">{{ __('Add') }}</button>
                </form>
            </section>

            <section class="mt-5">
                <h3 class="px-1 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('End pages') }}</h3>
                <p class="mt-0.5 px-1 text-[11px] text-gray-400 dark:text-gray-500">{{ __('Unnumbered pages after the chapters (References, Appendix).') }}</p>
                <ul class="mt-2 space-y-3">
                    @forelse ($this->backPages as $section)
                        @include('reports.partials.section-card', ['section' => $section, 'isFront' => false, 'number' => null])
                    @empty
                        <li class="rounded-lg bg-white px-3 py-3 text-center text-xs text-gray-400 ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-gray-700">{{ __('No end pages yet') }}</li>
                    @endforelse
                </ul>
                <form wire:submit="addBackPage" class="mt-3 flex gap-1">
                    <input type="text" wire:model="newBackPageTitle" placeholder="{{ __('Add an end page — e.g. Appendix 1') }}" class="block w-full rounded-md px-2 py-1.5 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-gray-900 dark:text-gray-100 dark:ring-gray-600">
                    <button type="submit" class="shrink-0 rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-indigo-500">{{ __('Add') }}</button>
                </form>
            </section>
        </aside>

        {{-- RIGHT: live preview of the compiled report --}}
        <main x-ref="preview" @scroll="onScroll()" class="min-w-0 min-h-0 flex-1 overflow-y-auto bg-gray-200/70 dark:bg-gray-950">
            @php
                $activeId = $this->activePreviewId;
                // Mirror the report's chosen heading alignment + case in the
                // editor preview so it matches the full report exactly.
                $headingStyle = 'text-align: '.$this->report->headingAlign().'; text-transform: '.($this->report->heading_uppercase ? 'uppercase' : 'none').';';
            @endphp
            <div class="report-preview px-4 py-8 sm:px-8">
                @forelse ($this->preview->frontMatter() as $page)
                    <section class="preview-page" wire:key="preview-{{ $page['id'] }}" data-page-key="{{ $page['id'] }}">
                        @if ($page['id'] === $activeId)
                            <h2 class="preview-heading" style="{{ $headingStyle }}" x-text="$store.preview.title || @js($page['title'])"></h2>
                            <div class="report-content" x-html="$store.preview.html"></div>
                        @else
                            <h2 class="preview-heading" style="{{ $headingStyle }}">{{ $page['title'] }}</h2>
                            <div class="report-content">{!! $page['html'] !!}</div>
                        @endif
                    </section>
                @empty
                @endforelse

                @forelse ($this->preview->sections() as $sec)
                    <section class="preview-page" wire:key="preview-{{ $sec['id'] }}" data-page-key="{{ $sec['id'] }}">
                        @if ($sec['id'] === $activeId)
                            <h2 class="preview-heading" style="{{ $headingStyle }}">{{ $sec['marker'] }} <span x-text="$store.preview.title || @js($sec['title'])"></span></h2>
                            <div class="report-content" x-html="$store.preview.html"></div>
                        @else
                            <h2 class="preview-heading" style="{{ $headingStyle }}">{{ $sec['marker'] }} {{ $sec['title'] }}</h2>
                            <div class="report-content">{!! $sec['html'] !!}</div>
                        @endif
                    </section>
                @empty
                    @unless ($this->preview->hasFrontMatter())
                        <div class="py-24 text-center text-sm text-gray-400">
                            {{ __('Your report preview appears here. Add a section to start writing.') }}
                        </div>
                    @endunless
                @endforelse

                @foreach ($this->preview->backMatter() as $back)
                    <section class="preview-page" wire:key="preview-{{ $back['id'] }}" data-page-key="{{ $back['id'] }}">
                        @if ($back['id'] === $activeId)
                            <h2 class="preview-heading" style="{{ $headingStyle }}" x-text="$store.preview.title || @js($back['title'])"></h2>
                            <div class="report-content" x-html="$store.preview.html"></div>
                        @else
                            <h2 class="preview-heading" style="{{ $headingStyle }}">{{ $back['title'] }}</h2>
                            <div class="report-content">{!! $back['html'] !!}</div>
                        @endif
                    </section>
                @endforeach
            </div>
        </main>
    </div>
</div>
