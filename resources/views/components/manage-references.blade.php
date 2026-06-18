<?php

use App\Models\Reference;
use App\Models\Report;
use App\Support\CitationFormatter;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public Report $report;

    public string $format = 'london_met';

    public bool $open = false;

    public string $editingId = '';

    public string $type = 'journal';

    /** @var array<string, string> */
    public array $form = [
        'authors' => '',
        'year' => '',
        'title' => '',
        'journal' => '',
        'volume' => '',
        'issue' => '',
        'pages' => '',
        'publisher' => '',
        'place' => '',
        'edition' => '',
        'site_name' => '',
        'publication' => '',
        'url' => '',
        'accessed' => '',
    ];

    public function mount(Report $report): void
    {
        $this->report = $report;
        $this->format = $report->citationFormat();
    }

    #[Computed]
    public function references()
    {
        return $this->report->references()->get();
    }

    /**
     * The reference list serialised for the editor — each entry carries the
     * inline citation pre-formatted in every supported style so the editor can
     * switch formats without a server round-trip.
     *
     * @return list<array{id: int, type: string, label: string, inline: array<string, string>}>
     */
    #[Computed]
    public function referencesPayload(): array
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

    public function openModal(): void
    {
        $this->resetForm();
        $this->open = true;
    }

    public function closeModal(): void
    {
        $this->open = false;
        $this->resetForm();
    }

    public function changeFormat(string $format): void
    {
        if (! \in_array($format, CitationFormatter::FORMATS, true)) {
            return;
        }

        $this->format = $format;
        $this->report->update(['reference_format' => $format]);

        $this->emitChange();
    }

    public function selectType(string $type): void
    {
        if (! \in_array($type, Reference::TYPES, true)) {
            return;
        }

        $this->type = $type;
    }

    public function startEdit(int $id): void
    {
        $reference = $this->report->references()->whereKey($id)->first();

        if (! $reference) {
            return;
        }

        $this->editingId = (string) $reference->id;
        $this->type = $reference->type;
        $this->form = array_merge($this->emptyForm(), array_map(
            fn ($value) => (string) (\is_array($value) ? implode(', ', $value) : $value),
            $reference->data ?? [],
        ));
    }

    public function cancelEdit(): void
    {
        $this->resetForm();
    }

    public function save(): void
    {
        $title = trim($this->form['title'] ?? '');
        $authors = trim($this->form['authors'] ?? '');
        $siteName = trim($this->form['site_name'] ?? '');

        if ($title === '' && $authors === '' && $siteName === '') {
            return;
        }

        $data = array_filter(
            $this->form,
            fn ($value) => trim((string) $value) !== '',
        );

        if ($this->editingId !== '') {
            $reference = $this->report->references()->whereKey((int) $this->editingId)->first();

            if ($reference) {
                $reference->update([
                    'type' => $this->type,
                    'data' => $data,
                ]);
            }
        } else {
            $this->report->references()->create([
                'type' => $this->type,
                'data' => $data,
            ]);
        }

        $this->resetForm();
        unset($this->references, $this->referencesPayload);
        $this->emitChange();
    }

    public function delete(int $id): void
    {
        $reference = $this->report->references()->whereKey($id)->first();

        if (! $reference) {
            return;
        }

        $reference->delete();

        if ($this->editingId === (string) $id) {
            $this->resetForm();
        }

        unset($this->references, $this->referencesPayload);
        $this->emitChange();
    }

    /**
     * Notify the editor that the references list (or the active format) has
     * changed so it can refresh the citation picker and rewrite inline spans.
     */
    protected function emitChange(): void
    {
        $this->dispatch(
            'references-updated',
            references: $this->referencesPayload,
            format: $this->format,
        );
    }

    protected function resetForm(): void
    {
        $this->editingId = '';
        $this->type = 'journal';
        $this->form = $this->emptyForm();
    }

    /**
     * @return array<string, string>
     */
    protected function emptyForm(): array
    {
        return [
            'authors' => '',
            'year' => '',
            'title' => '',
            'journal' => '',
            'volume' => '',
            'issue' => '',
            'pages' => '',
            'publisher' => '',
            'place' => '',
            'edition' => '',
            'site_name' => '',
            'publication' => '',
            'url' => '',
            'accessed' => '',
        ];
    }

    protected function referenceLabel(Reference $reference): string
    {
        $authors = trim((string) $reference->field('authors', ''));
        $year = trim((string) $reference->field('year', ''));
        $title = trim((string) $reference->field('title', $reference->field('site_name', '')));

        $head = trim(($authors !== '' ? $authors : '').($year !== '' ? " ({$year})" : ''));
        $head = trim($head);

        return $head === '' ? ($title ?: 'Untitled reference') : "{$head} — {$title}";
    }
}; ?>

<div>
    <button
        type="button"
        wire:click="openModal"
        class="rounded-md bg-white px-3 py-1.5 text-xs font-semibold text-gray-900 ring-1 ring-gray-300 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-100 dark:ring-gray-600 dark:hover:bg-gray-700"
    >
        Manage References
    </button>

    @if ($open)
        <div class="fixed inset-0 z-40 flex items-center justify-center bg-black/40 px-4 py-6" wire:key="ref-modal">
            <div class="flex max-h-[90vh] w-full max-w-3xl flex-col overflow-hidden rounded-lg bg-white shadow-xl dark:bg-gray-800">
                <div class="flex items-center justify-between border-b border-gray-200 px-5 py-3 dark:border-gray-700">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">References for this report</h3>
                    <button type="button" wire:click="closeModal" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200" aria-label="Close">&times;</button>
                </div>

                <div class="border-b border-gray-200 px-5 py-3 dark:border-gray-700">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Citation format</p>
                    <div class="mt-2 flex flex-wrap gap-2">
                        @foreach ([
                            'london_met' => 'London Met',
                            'apa' => 'APA',
                            'ieee' => 'IEEE',
                        ] as $value => $label)
                            <button
                                type="button"
                                wire:click="changeFormat('{{ $value }}')"
                                class="rounded-md px-3 py-1.5 text-sm font-medium ring-1 {{ $format === $value ? 'bg-indigo-600 text-white ring-indigo-600' : 'bg-white text-gray-700 ring-gray-300 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-200 dark:ring-gray-600 dark:hover:bg-gray-700' }}"
                            >
                                {{ $label }}
                            </button>
                        @endforeach
                    </div>
                    <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">You can change the format any time — inline citations and the bibliography are re-rendered automatically.</p>
                </div>

                <div class="grid flex-1 grid-cols-1 gap-0 overflow-y-auto md:grid-cols-2">
                    {{-- Add / edit form --}}
                    <div class="border-b border-gray-200 px-5 py-4 md:border-b-0 md:border-r dark:border-gray-700">
                        <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            {{ $editingId !== '' ? 'Edit reference' : 'Add reference' }}
                        </p>

                        <div class="mt-2 flex flex-wrap gap-1">
                            @foreach ([
                                'url' => 'Webpage / URL',
                                'journal' => 'Journal',
                                'book' => 'Book',
                                'article' => 'Article',
                            ] as $value => $label)
                                <button
                                    type="button"
                                    wire:click="selectType('{{ $value }}')"
                                    class="rounded-md px-2.5 py-1 text-xs font-medium ring-1 {{ $type === $value ? 'bg-indigo-600 text-white ring-indigo-600' : 'bg-white text-gray-700 ring-gray-300 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-200 dark:ring-gray-600 dark:hover:bg-gray-700' }}"
                                >
                                    {{ $label }}
                                </button>
                            @endforeach
                        </div>

                        <form wire:submit.prevent="save" class="mt-3 space-y-2">
                            <div>
                                <label class="block text-[11px] font-medium text-gray-600 dark:text-gray-300">Authors <span class="text-gray-400 dark:text-gray-500">(comma or "and" separated)</span></label>
                                <input type="text" wire:model.live.debounce.400ms="form.authors" class="mt-0.5 block w-full rounded-md px-2 py-1.5 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-gray-900 dark:text-gray-100 dark:ring-gray-600 dark:placeholder-gray-500" placeholder="e.g. Smith, J.; Doe, A.">
                            </div>

                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label class="block text-[11px] font-medium text-gray-600 dark:text-gray-300">Year</label>
                                    <input type="text" wire:model.live.debounce.400ms="form.year" class="mt-0.5 block w-full rounded-md px-2 py-1.5 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-gray-900 dark:text-gray-100 dark:ring-gray-600 dark:placeholder-gray-500" placeholder="e.g. 2023">
                                </div>
                                <div>
                                    <label class="block text-[11px] font-medium text-gray-600 dark:text-gray-300">{{ $type === 'url' ? 'Page title' : 'Title' }}</label>
                                    <input type="text" wire:model.live.debounce.400ms="form.title" class="mt-0.5 block w-full rounded-md px-2 py-1.5 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-gray-900 dark:text-gray-100 dark:ring-gray-600 dark:placeholder-gray-500">
                                </div>
                            </div>

                            @if ($type === 'journal')
                                <div>
                                    <label class="block text-[11px] font-medium text-gray-600 dark:text-gray-300">Journal name</label>
                                    <input type="text" wire:model.live.debounce.400ms="form.journal" class="mt-0.5 block w-full rounded-md px-2 py-1.5 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-gray-900 dark:text-gray-100 dark:ring-gray-600 dark:placeholder-gray-500">
                                </div>
                                <div class="grid grid-cols-3 gap-2">
                                    <div>
                                        <label class="block text-[11px] font-medium text-gray-600 dark:text-gray-300">Volume</label>
                                        <input type="text" wire:model.live.debounce.400ms="form.volume" class="mt-0.5 block w-full rounded-md px-2 py-1.5 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-gray-900 dark:text-gray-100 dark:ring-gray-600 dark:placeholder-gray-500">
                                    </div>
                                    <div>
                                        <label class="block text-[11px] font-medium text-gray-600 dark:text-gray-300">Issue</label>
                                        <input type="text" wire:model.live.debounce.400ms="form.issue" class="mt-0.5 block w-full rounded-md px-2 py-1.5 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-gray-900 dark:text-gray-100 dark:ring-gray-600 dark:placeholder-gray-500">
                                    </div>
                                    <div>
                                        <label class="block text-[11px] font-medium text-gray-600 dark:text-gray-300">Pages</label>
                                        <input type="text" wire:model.live.debounce.400ms="form.pages" class="mt-0.5 block w-full rounded-md px-2 py-1.5 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-gray-900 dark:text-gray-100 dark:ring-gray-600 dark:placeholder-gray-500" placeholder="e.g. 12-24">
                                    </div>
                                </div>
                            @endif

                            @if ($type === 'book')
                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        <label class="block text-[11px] font-medium text-gray-600 dark:text-gray-300">Publisher</label>
                                        <input type="text" wire:model.live.debounce.400ms="form.publisher" class="mt-0.5 block w-full rounded-md px-2 py-1.5 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-gray-900 dark:text-gray-100 dark:ring-gray-600 dark:placeholder-gray-500">
                                    </div>
                                    <div>
                                        <label class="block text-[11px] font-medium text-gray-600 dark:text-gray-300">Place</label>
                                        <input type="text" wire:model.live.debounce.400ms="form.place" class="mt-0.5 block w-full rounded-md px-2 py-1.5 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-gray-900 dark:text-gray-100 dark:ring-gray-600 dark:placeholder-gray-500">
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-[11px] font-medium text-gray-600 dark:text-gray-300">Edition <span class="text-gray-400 dark:text-gray-500">(optional)</span></label>
                                    <input type="text" wire:model.live.debounce.400ms="form.edition" class="mt-0.5 block w-full rounded-md px-2 py-1.5 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-gray-900 dark:text-gray-100 dark:ring-gray-600 dark:placeholder-gray-500" placeholder="e.g. 2nd">
                                </div>
                            @endif

                            @if ($type === 'url' || $type === 'article')
                                <div>
                                    <label class="block text-[11px] font-medium text-gray-600 dark:text-gray-300">{{ $type === 'url' ? 'Site name' : 'Publication' }}</label>
                                    <input type="text" wire:model.live.debounce.400ms="form.{{ $type === 'url' ? 'site_name' : 'publication' }}" class="mt-0.5 block w-full rounded-md px-2 py-1.5 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-gray-900 dark:text-gray-100 dark:ring-gray-600 dark:placeholder-gray-500">
                                </div>
                                <div>
                                    <label class="block text-[11px] font-medium text-gray-600 dark:text-gray-300">URL</label>
                                    <input type="url" wire:model.live.debounce.400ms="form.url" class="mt-0.5 block w-full rounded-md px-2 py-1.5 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-gray-900 dark:text-gray-100 dark:ring-gray-600 dark:placeholder-gray-500" placeholder="https://...">
                                </div>
                                <div>
                                    <label class="block text-[11px] font-medium text-gray-600 dark:text-gray-300">Accessed <span class="text-gray-400 dark:text-gray-500">(date)</span></label>
                                    <input type="text" wire:model.live.debounce.400ms="form.accessed" class="mt-0.5 block w-full rounded-md px-2 py-1.5 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-gray-900 dark:text-gray-100 dark:ring-gray-600 dark:placeholder-gray-500" placeholder="e.g. 1 May 2026">
                                </div>
                            @endif

                            <div class="flex items-center gap-2 pt-1">
                                <button type="submit" class="rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-indigo-500">
                                    {{ $editingId !== '' ? 'Update reference' : 'Add reference' }}
                                </button>
                                @if ($editingId !== '')
                                    <button type="button" wire:click="cancelEdit" class="rounded-md px-3 py-1.5 text-sm font-medium text-gray-700 ring-1 ring-gray-300 hover:bg-gray-50 dark:text-gray-200 dark:ring-gray-600 dark:hover:bg-gray-700">Cancel</button>
                                @endif
                            </div>
                        </form>
                    </div>

                    {{-- Existing references --}}
                    <div class="px-5 py-4">
                        <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Saved references ({{ count($this->references) }})</p>

                        <ul class="mt-2 space-y-2">
                            @forelse ($this->references as $reference)
                                <li wire:key="ref-{{ $reference->id }}" class="rounded-md p-2 text-sm ring-1 ring-gray-200 dark:ring-gray-700">
                                    <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                        {{ ucfirst($reference->type) }}
                                    </p>
                                    <p class="mt-0.5 text-sm text-gray-800 dark:text-gray-200">
                                        {!! (new CitationFormatter($format, $this->references))->bibliographyMarker($reference) !!}{!! (new CitationFormatter($format, $this->references))->bibliography($reference) !!}
                                    </p>
                                    <div class="mt-1 flex gap-2 text-xs">
                                        <button type="button" wire:click="startEdit({{ $reference->id }})" class="font-medium text-indigo-600 hover:text-indigo-500">Edit</button>
                                        <button type="button" wire:click="delete({{ $reference->id }})" wire:confirm="Delete this reference?" class="font-medium text-red-600 hover:text-red-500">Delete</button>
                                    </div>
                                </li>
                            @empty
                                <li class="rounded-md p-3 text-xs text-gray-500 ring-1 ring-dashed ring-gray-200 dark:text-gray-400 dark:ring-gray-700">No references yet — add your first on the left.</li>
                            @endforelse
                        </ul>
                    </div>
                </div>

                <div class="flex justify-end border-t border-gray-200 px-5 py-3 dark:border-gray-700">
                    <button type="button" wire:click="closeModal" class="rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-indigo-500">Done</button>
                </div>
            </div>
        </div>
    @endif
</div>
