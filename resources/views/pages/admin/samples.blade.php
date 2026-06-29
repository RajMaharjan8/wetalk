<?php

use App\Models\LandingFeature;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts::admin')] class extends Component
{
    use WithFileUploads;

    /** The section these cards belong to. Samples only. */
    private const SECTION = 'samples';

    // ---- Add form ----
    #[Validate('required|string|max:120')]
    public string $newTitle = '';

    #[Validate('nullable|string|max:300')]
    public string $newExcerpt = '';

    #[Validate('nullable|string|max:2000')]
    public string $newDescription = '';

    #[Validate('nullable|string|max:8')]
    public string $newBadge = '';

    /** Optional link the card opens (defaults to a live sample report preview). */
    #[Validate('nullable|string|max:255')]
    public string $newLink = '';

    #[Validate('nullable|image|max:3072')]
    public $newIcon = null;

    #[Validate('nullable|mimes:pdf|max:20480')]
    public $newPdf = null;

    // ---- Inline edit ----
    public ?int $editingId = null;

    public string $editTitle = '';

    public string $editExcerpt = '';

    public string $editDescription = '';

    public string $editBadge = '';

    public string $editLink = '';

    public string $editSlug = '';

    public string $editMetaTitle = '';

    public string $editMetaDescription = '';

    public string $editMetaKeywords = '';

    #[Validate('nullable|mimes:pdf|max:20480')]
    public $editPdf = null;

    #[Validate('nullable|image|max:3072')]
    public $editIcon = null;

    public function mount(): void
    {
        abort_unless(auth()->user()->can('landing.manage'), 403);
    }

    /** @return Collection<int, LandingFeature> */
    public function getSamplesProperty(): Collection
    {
        return LandingFeature::section(self::SECTION)->get();
    }

    public function add(): void
    {
        $this->validate();

        $order = (int) LandingFeature::where('section', self::SECTION)->max('order') + 1;

        LandingFeature::create([
            'section' => self::SECTION,
            'title' => $this->newTitle,
            'excerpt' => $this->newExcerpt ?: null,
            'description' => $this->newDescription ?: null,
            'badge' => $this->newBadge ?: null,
            'link' => $this->newLink ?: null,
            'icon_path' => $this->newIcon ? $this->newIcon->store('landing', 'public') : null,
            'pdf_path' => $this->newPdf ? $this->newPdf->store('sample-pdfs', 'public') : null,
            'order' => $order,
        ]);

        $this->reset('newTitle', 'newExcerpt', 'newDescription', 'newBadge', 'newLink', 'newIcon', 'newPdf');
        session()->flash('saved', 'Sample report added.');
    }

    public function edit(int $id): void
    {
        $card = $this->findSample($id);
        $this->editingId = $card->id;
        $this->editTitle = $card->title;
        $this->editExcerpt = (string) $card->excerpt;
        $this->editDescription = (string) $card->description;
        $this->editBadge = (string) $card->badge;
        $this->editLink = (string) $card->link;
        $this->editSlug = (string) $card->slug;
        $this->editMetaTitle = (string) $card->meta_title;
        $this->editMetaDescription = (string) $card->meta_description;
        $this->editMetaKeywords = (string) $card->meta_keywords;
        $this->editIcon = null;
    }

    public function cancelEdit(): void
    {
        $this->reset('editingId', 'editTitle', 'editExcerpt', 'editDescription', 'editBadge', 'editLink', 'editSlug', 'editMetaTitle', 'editMetaDescription', 'editMetaKeywords', 'editIcon', 'editPdf');
    }

    public function saveEdit(): void
    {
        $this->validate([
            'editTitle' => 'required|string|max:120',
            'editExcerpt' => 'nullable|string|max:300',
            'editDescription' => 'nullable|string|max:2000',
            'editBadge' => 'nullable|string|max:8',
            'editLink' => 'nullable|string|max:255',
            'editSlug' => 'nullable|alpha_dash|max:120',
            'editMetaTitle' => 'nullable|string|max:160',
            'editMetaDescription' => 'nullable|string|max:300',
            'editMetaKeywords' => 'nullable|string|max:255',
            'editIcon' => 'nullable|image|max:3072',
            'editPdf' => 'nullable|mimes:pdf|max:20480',
        ]);

        $card = $this->findSample($this->editingId);

        $update = [
            'title' => $this->editTitle,
            'excerpt' => $this->editExcerpt ?: null,
            'description' => $this->editDescription ?: null,
            'badge' => $this->editBadge ?: null,
            'link' => $this->editLink ?: null,
            'slug' => $this->editSlug ?: null,
            'meta_title' => $this->editMetaTitle ?: null,
            'meta_description' => $this->editMetaDescription ?: null,
            'meta_keywords' => $this->editMetaKeywords ?: null,
        ];

        if ($this->editIcon) {
            if ($card->icon_path) {
                Storage::disk('public')->delete($card->icon_path);
            }
            $update['icon_path'] = $this->editIcon->store('landing', 'public');
        }

        if ($this->editPdf) {
            if ($card->pdf_path) {
                Storage::disk('public')->delete($card->pdf_path);
            }
            $update['pdf_path'] = $this->editPdf->store('sample-pdfs', 'public');
        }

        $card->update($update);

        $this->cancelEdit();
        session()->flash('saved', 'Sample report updated.');
    }

    public function removePdf(int $id): void
    {
        $card = $this->findSample($id);

        if ($card->pdf_path) {
            Storage::disk('public')->delete($card->pdf_path);
            $card->update(['pdf_path' => null]);
        }
    }

    public function toggleVisible(int $id): void
    {
        $card = $this->findSample($id);
        $card->update(['visible' => ! $card->visible]);
    }

    public function removeIcon(int $id): void
    {
        $card = $this->findSample($id);

        if ($card->icon_path) {
            Storage::disk('public')->delete($card->icon_path);
            $card->update(['icon_path' => null]);
        }
    }

    public function delete(int $id): void
    {
        $card = $this->findSample($id);

        if ($card->icon_path) {
            Storage::disk('public')->delete($card->icon_path);
        }

        if ($card->pdf_path) {
            Storage::disk('public')->delete($card->pdf_path);
        }

        $card->delete();
        session()->flash('saved', 'Sample report deleted.');
    }

    /** Reorder after drag-and-drop. */
    public function reorder(mixed $item, int $position = 0): void
    {
        $moved = $this->findSampleOrNull((int) $item);

        if (! $moved) {
            return;
        }

        $ids = LandingFeature::section(self::SECTION)->pluck('id')->all();
        $ids = array_values(array_filter($ids, fn ($id) => (int) $id !== (int) $item));
        array_splice($ids, $position, 0, [(int) $item]);

        foreach ($ids as $order => $id) {
            LandingFeature::whereKey($id)->update(['order' => $order]);
        }
    }

    /** Look up a card, ensuring it is a sample (never touch other sections). */
    private function findSample(int $id): LandingFeature
    {
        return LandingFeature::where('section', self::SECTION)->findOrFail($id);
    }

    private function findSampleOrNull(int $id): ?LandingFeature
    {
        return LandingFeature::where('section', self::SECTION)->find($id);
    }
}; ?>

@php($title = 'Sample reports')

<div class="mx-auto max-w-4xl px-4 py-8 sm:px-6">
    <div class="mb-6 flex items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-gray-900">Sample reports</h1>
            <p class="mt-1 text-sm text-gray-500">Manage the sample report cards shown on the landing page and the <a href="{{ route('samples.index') }}" target="_blank" class="font-medium text-indigo-600 hover:text-indigo-500">/samples</a> page.</p>
        </div>
    </div>

    @if (session('saved'))
        <div class="mb-5 rounded-lg bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700 ring-1 ring-emerald-200">{{ session('saved') }}</div>
    @endif

    {{-- ============ Add form ============ --}}
    <form wire:submit="add" class="mb-8 space-y-4 rounded-lg bg-white p-5 shadow-sm ring-1 ring-gray-200">
        <h2 class="text-base font-semibold text-gray-900">Add a sample report</h2>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-gray-700">Title</label>
                <input type="text" wire:model="newTitle" class="mt-1 block w-full rounded-md px-3 py-2 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                @error('newTitle') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-gray-700">Excerpt <span class="font-normal text-gray-400">(short — shown on the card)</span></label>
                <textarea wire:model="newExcerpt" rows="2" class="mt-1 block w-full rounded-md px-3 py-2 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500"></textarea>
                @error('newExcerpt') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-gray-700">Description <span class="font-normal text-gray-400">(long — shown on the detail view)</span></label>
                <div wire:ignore wire:key="add-editor">
                    <x-sample-description-editor :content="$newDescription" model="newDescription" />
                </div>
                @error('newDescription') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Badge text <span class="font-normal text-gray-400">(used if no image)</span></label>
                <input type="text" wire:model="newBadge" maxlength="8" placeholder="e.g. HM" class="mt-1 block w-full rounded-md px-3 py-2 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Preview link <span class="font-normal text-gray-400">(e.g. /samples/&lt;slug&gt;)</span></label>
                <input type="text" wire:model="newLink" placeholder="/samples/hospital-management" class="mt-1 block w-full rounded-md px-3 py-2 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-gray-700">Banner image <span class="font-normal text-gray-400">(400×150, PNG/JPG/SVG, ≤ 3 MB)</span></label>
                <input type="file" wire:model="newIcon" accept="image/*" class="mt-1 block w-full text-sm text-gray-600 file:mr-3 file:rounded-md file:border-0 file:bg-indigo-50 file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-indigo-700">
                @error('newIcon') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                @if ($newIcon)
                    <img src="{{ $newIcon->temporaryUrl() }}" alt="" class="mt-2 h-12 w-32 rounded-md object-cover ring-1 ring-gray-200">
                @endif
            </div>
            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-gray-700">Sample PDF <span class="font-normal text-gray-400">(optional, ≤ 20 MB · shown via “View PDF”)</span></label>
                <input type="file" wire:model="newPdf" accept="application/pdf" class="mt-1 block w-full text-sm text-gray-600 file:mr-3 file:rounded-md file:border-0 file:bg-indigo-50 file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-indigo-700">
                @error('newPdf') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                <p wire:loading wire:target="newPdf" class="mt-1 text-xs text-gray-400">Uploading…</p>
                @if ($newPdf)
                    <p class="mt-1 text-xs text-emerald-600">✓ {{ $newPdf->getClientOriginalName() }}</p>
                @endif
            </div>
        </div>
        <div class="flex justify-end">
            <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                <span wire:loading.remove wire:target="add,newIcon">Add sample</span>
                <span wire:loading wire:target="add,newIcon">Saving…</span>
            </button>
        </div>
    </form>

    {{-- ============ List ============ --}}
    <ul wire:sort="reorder" class="space-y-2">
        @forelse ($this->samples as $card)
            <li wire:key="sample-{{ $card->id }}" wire:sort:item="{{ $card->id }}" class="rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-200 {{ $card->visible ? '' : 'opacity-60' }}">
                <div class="flex items-start gap-3">
                    <span wire:sort:handle class="mt-1 cursor-grab select-none text-gray-300 hover:text-gray-500" title="Drag to reorder">⠿</span>

                    @if ($card->iconUrl())
                        <img src="{{ $card->iconUrl() }}" alt="" class="h-12 w-32 shrink-0 rounded-lg object-cover ring-1 ring-gray-200">
                    @else
                        <span class="flex h-12 w-32 shrink-0 items-center justify-center rounded-lg bg-indigo-600 text-xs font-bold text-white">{{ $card->badgeText() }}</span>
                    @endif

                    <div class="min-w-0 flex-1">
                        @if ($editingId === $card->id)
                            <div class="space-y-2">
                                <input type="text" wire:model="editTitle" class="block w-full rounded-md px-2 py-1.5 text-sm font-semibold ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                @error('editTitle') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                                <label class="block text-xs font-medium text-gray-500">Excerpt (shown on the card)</label>
                                <textarea wire:model="editExcerpt" rows="2" class="block w-full rounded-md px-2 py-1.5 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500"></textarea>
                                @error('editExcerpt') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                                <label class="block text-xs font-medium text-gray-500">Description (shown on the detail view)</label>
                                <div wire:ignore wire:key="edit-editor-{{ $card->id }}">
                                    <x-sample-description-editor :content="$editDescription" model="editDescription" />
                                </div>
                                @error('editDescription') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                                <div class="flex flex-wrap items-center gap-2">
                                    <input type="text" wire:model="editBadge" maxlength="8" placeholder="Badge" class="w-24 rounded-md px-2 py-1.5 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                </div>

                                <label class="block text-xs font-medium text-gray-500">“View sample” link <span class="font-normal text-gray-400">(the live report URL, opened by the button on the detail page)</span></label>
                                <input type="text" wire:model="editLink" placeholder="/samples/your-report-slug" class="block w-full rounded-md px-2 py-1.5 text-xs ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                @error('editLink') <p class="text-xs text-red-600">{{ $message }}</p> @enderror

                                {{-- Detail page slug + SEO meta --}}
                                <div class="space-y-2 rounded-md bg-indigo-50/60 p-3 ring-1 ring-indigo-100">
                                    <p class="text-xs font-semibold text-indigo-700">Detail page &amp; SEO <span class="font-normal text-indigo-400">(opens at /sample-reports/&lt;slug&gt;)</span></p>
                                    <input type="text" wire:model="editSlug" placeholder="Slug (auto from title if blank)" class="block w-full rounded-md px-2 py-1.5 text-xs ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                    @error('editSlug') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                                    <input type="text" wire:model="editMetaTitle" placeholder="Meta title" class="block w-full rounded-md px-2 py-1.5 text-xs ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                    <textarea wire:model="editMetaDescription" rows="2" placeholder="Meta description" class="block w-full rounded-md px-2 py-1.5 text-xs ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500"></textarea>
                                    <input type="text" wire:model="editMetaKeywords" placeholder="Meta keywords (comma-separated)" class="block w-full rounded-md px-2 py-1.5 text-xs ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                    @if ($card->slug)
                                        <a href="{{ route('sample-pages.show', $card->slug) }}" target="_blank" class="inline-block text-xs font-medium text-indigo-600 hover:text-indigo-500">View detail page →</a>
                                    @endif
                                </div>

                                <label class="block text-xs font-medium text-gray-500">Banner image</label>
                                <input type="file" wire:model="editIcon" accept="image/*" class="text-xs text-gray-600 file:mr-2 file:rounded file:border-0 file:bg-indigo-50 file:px-2 file:py-1 file:text-xs file:font-semibold file:text-indigo-700">
                                @error('editIcon') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                                <label class="block text-xs font-medium text-gray-500">Sample PDF @if ($card->pdf_path)<span class="text-emerald-600"> · current PDF attached</span>@endif</label>
                                <input type="file" wire:model="editPdf" accept="application/pdf" class="text-xs text-gray-600 file:mr-2 file:rounded file:border-0 file:bg-indigo-50 file:px-2 file:py-1 file:text-xs file:font-semibold file:text-indigo-700">
                                @error('editPdf') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                                <p wire:loading wire:target="editPdf" class="text-xs text-gray-400">Uploading…</p>
                                <div class="flex flex-wrap gap-2">
                                    <button type="button" wire:click="saveEdit" class="rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-500">Save</button>
                                    <button type="button" wire:click="cancelEdit" class="rounded-md px-3 py-1.5 text-xs font-semibold text-gray-700 ring-1 ring-gray-300 hover:bg-gray-50">Cancel</button>
                                    @if ($card->icon_path)
                                        <button type="button" wire:click="removeIcon({{ $card->id }})" class="rounded-md px-3 py-1.5 text-xs font-semibold text-red-600 hover:bg-red-50">Remove image</button>
                                    @endif
                                    @if ($card->pdf_path)
                                        <button type="button" wire:click="removePdf({{ $card->id }})" class="rounded-md px-3 py-1.5 text-xs font-semibold text-red-600 hover:bg-red-50">Remove PDF</button>
                                    @endif
                                </div>
                            </div>
                        @else
                            <p class="flex items-center gap-2 text-sm font-semibold text-gray-900">
                                {{ $card->title }}
                                @unless ($card->visible)
                                    <span class="rounded bg-gray-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-gray-500">Hidden</span>
                                @endunless
                            </p>
                            @if ($card->excerpt)
                                <p class="mt-0.5 text-sm text-gray-500">{{ $card->excerpt }}</p>
                            @endif
                            @if ($card->link)
                                <p class="mt-0.5 truncate text-xs text-gray-400">{{ $card->link }}</p>
                            @endif
                            @if ($card->pdfUrl())
                                <a href="{{ $card->pdfUrl() }}" target="_blank" class="mt-0.5 inline-flex items-center gap-1 text-xs font-medium text-indigo-600 hover:text-indigo-500">
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" /></svg>
                                    View PDF
                                </a>
                            @endif
                        @endif
                    </div>

                    @unless ($editingId === $card->id)
                        <div class="flex shrink-0 items-center gap-3 text-sm font-medium">
                            <button type="button" wire:click="toggleVisible({{ $card->id }})" class="text-gray-500 hover:text-gray-800">{{ $card->visible ? 'Hide' : 'Show' }}</button>
                            <button type="button" wire:click="edit({{ $card->id }})" class="text-indigo-600 hover:text-indigo-500">Edit</button>
                            <button type="button" wire:click="delete({{ $card->id }})" wire:confirm="Delete this sample report?" class="text-red-600 hover:text-red-500">Delete</button>
                        </div>
                    @endunless
                </div>
            </li>
        @empty
            <li class="rounded-lg bg-white p-8 text-center text-sm text-gray-400 shadow-sm ring-1 ring-gray-200">No sample reports yet. Add one above.</li>
        @endforelse
    </ul>
</div>

