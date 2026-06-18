<?php

use App\Models\LandingFeature;
use App\Models\Setting;
use App\Support\LandingContent;
use Database\Seeders\SampleReportSeeder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts::admin')] class extends Component
{
    use WithFileUploads;

    /**
     * Single-value landing content (hero, headings, SEO meta, footer), keyed by
     * settings key. Bound to the form via wire:model="content.<key>".
     *
     * @var array<string, string>
     */
    public array $content = [];

    /** New site logo upload (shown next to the brand name everywhere). */
    #[Validate('nullable|image|max:3072')]
    public $logo = null;

    /** New Open Graph / social share image upload. */
    #[Validate('nullable|image|max:3072')]
    public $ogImage = null;

    /** New hero banner image (shown on the right of the hero). */
    #[Validate('nullable|image|max:3072')]
    public $heroImage = null;

    // ---- Card add form ----
    #[Validate('required|in:features,formats,samples,faqs')]
    public string $newSection = 'features';

    #[Validate('required|string|max:120')]
    public string $newTitle = '';

    #[Validate('nullable|string|max:500')]
    public string $newDescription = '';

    #[Validate('nullable|string|max:8')]
    public string $newBadge = '';

    #[Validate('nullable|image|max:3072')]
    public $newIcon = null;

    // ---- Inline card edit ----
    public ?int $editingId = null;

    public string $editTitle = '';

    public string $editDescription = '';

    public string $editBadge = '';

    public $editIcon = null;

    public function mount(): void
    {
        foreach (array_keys(LandingContent::defaults()) as $key) {
            $this->content[$key] = LandingContent::get($key);
        }
    }

    /** @return \Illuminate\Support\Collection<int, LandingFeature> */
    public function features(string $section)
    {
        return LandingFeature::section($section)->get();
    }

    public function logoUrl(): ?string
    {
        return LandingContent::imageUrl('landing_logo');
    }

    public function ogImageUrl(): ?string
    {
        return LandingContent::imageUrl('landing_og_image');
    }

    public function heroImageUrl(): ?string
    {
        return LandingContent::imageUrl('landing_hero_image');
    }

    /** Persist the hero / headings / SEO / footer text and uploaded images. */
    public function saveContent(): void
    {
        $this->validate([
            'logo' => 'nullable|image|max:3072',
            'ogImage' => 'nullable|image|max:3072',
            'heroImage' => 'nullable|image|max:3072',
        ]);

        foreach (LandingContent::defaults() as $key => $default) {
            Setting::set($key, $this->content[$key] ?? null);
        }

        $this->storeImageSetting('landing_logo', 'logo');
        $this->storeImageSetting('landing_og_image', 'ogImage');
        $this->storeImageSetting('landing_hero_image', 'heroImage');

        session()->flash('landing-saved', 'Landing content saved.');
    }

    /** Store an uploaded image under a setting key, replacing any previous file. */
    private function storeImageSetting(string $settingKey, string $property): void
    {
        if (! $this->{$property}) {
            return;
        }

        if ($old = Setting::get($settingKey)) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($old);
        }

        Setting::set($settingKey, $this->{$property}->store('landing', 'public'));
        $this->{$property} = null;
    }

    public function removeLogo(): void
    {
        if ($path = Setting::get('landing_logo')) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($path);
            Setting::set('landing_logo', null);
        }
    }

    public function removeHeroImage(): void
    {
        if ($path = Setting::get('landing_hero_image')) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($path);
            Setting::set('landing_hero_image', null);
        }
    }

    public function removeOgImage(): void
    {
        if ($path = Setting::get('landing_og_image')) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($path);
            Setting::set('landing_og_image', null);
        }
    }

    /** Run the sample-report seeder on demand (idempotent). */
    public function generateSamples(): void
    {
        (new SampleReportSeeder)->run();

        session()->flash('landing-saved', 'Sample reports generated. They now appear under the Sample reports section below.');
    }

    /** Show / hide a card on the public landing page (without deleting it). */
    public function toggleVisible(int $id): void
    {
        $card = LandingFeature::findOrFail($id);
        $card->update(['visible' => ! $card->visible]);
    }

    public function addCard(): void
    {
        $this->validate([
            'newSection' => 'required|in:features,formats,samples,faqs',
            'newTitle' => 'required|string|max:120',
            'newDescription' => 'nullable|string|max:500',
            'newBadge' => 'nullable|string|max:8',
            'newIcon' => 'nullable|image|max:3072',
        ]);

        $order = (int) LandingFeature::where('section', $this->newSection)->max('order') + 1;

        LandingFeature::create([
            'section' => $this->newSection,
            'title' => $this->newTitle,
            'description' => $this->newDescription ?: null,
            'badge' => $this->newBadge ?: null,
            'icon_path' => $this->newIcon ? $this->newIcon->store('landing', 'public') : null,
            'order' => $order,
        ]);

        $this->reset('newTitle', 'newDescription', 'newBadge', 'newIcon');
        session()->flash('landing-saved', 'Card added.');
    }

    public function edit(int $id): void
    {
        $card = LandingFeature::findOrFail($id);
        $this->editingId = $card->id;
        $this->editTitle = $card->title;
        $this->editDescription = (string) $card->description;
        $this->editBadge = (string) $card->badge;
        $this->editIcon = null;
    }

    public function cancelEdit(): void
    {
        $this->reset('editingId', 'editTitle', 'editDescription', 'editBadge', 'editIcon');
    }

    public function saveEdit(): void
    {
        $this->validate([
            'editTitle' => 'required|string|max:120',
            'editDescription' => 'nullable|string|max:500',
            'editBadge' => 'nullable|string|max:8',
            'editIcon' => 'nullable|image|max:3072',
        ]);

        $card = LandingFeature::findOrFail($this->editingId);

        $update = [
            'title' => $this->editTitle,
            'description' => $this->editDescription ?: null,
            'badge' => $this->editBadge ?: null,
        ];

        if ($this->editIcon) {
            if ($card->icon_path) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($card->icon_path);
            }
            $update['icon_path'] = $this->editIcon->store('landing', 'public');
        }

        $card->update($update);

        $this->cancelEdit();
        session()->flash('landing-saved', 'Card updated.');
    }

    public function removeIcon(int $id): void
    {
        $card = LandingFeature::findOrFail($id);

        if ($card->icon_path) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($card->icon_path);
            $card->update(['icon_path' => null]);
        }
    }

    public function delete(int $id): void
    {
        LandingFeature::find($id)?->delete();
    }

    /** Reorder within a section after drag-and-drop. */
    public function reorder(mixed $item, int $position = 0): void
    {
        $moved = LandingFeature::find((int) $item);

        if (! $moved) {
            return;
        }

        $ids = LandingFeature::section($moved->section)->pluck('id')->all();
        $ids = array_values(array_filter($ids, fn ($id) => (int) $id !== (int) $item));
        array_splice($ids, $position, 0, [(int) $item]);

        foreach ($ids as $order => $id) {
            LandingFeature::whereKey($id)->update(['order' => $order]);
        }
    }
}; ?>

@php($title = 'Landing page')

<div class="max-w-4xl space-y-8" x-data="{ tab: 'content' }">
    <x-validation-popup />

    @if (session('landing-saved'))
        <div class="rounded-md bg-green-50 px-4 py-3 text-sm font-medium text-green-800 ring-1 ring-green-200">{{ session('landing-saved') }}</div>
    @endif

    <p class="text-sm text-gray-500">Everything on the public landing page is managed here — page text, SEO/social meta, footer, and the feature / format / sample cards.</p>

    {{-- ============ SEO & social meta ============ --}}
    <form wire:submit="saveContent" class="space-y-5 rounded-lg bg-white p-5 shadow-sm ring-1 ring-gray-200">
        {{-- ============ Brand (logo + site name) ============ --}}
        <h2 class="text-base font-semibold text-gray-900">Brand</h2>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <label class="block text-sm font-medium text-gray-700">Site name <span class="font-normal text-gray-400">(shown next to the logo)</span></label>
                <input type="text" wire:model="content.landing_site_name" class="mt-1 block w-full rounded-md px-3 py-2 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Logo <span class="font-normal text-gray-400">(square PNG/SVG, ≤ 3 MB · falls back to “RG”)</span></label>
                <input type="file" wire:model="logo" accept="image/*" class="mt-1 block w-full text-sm text-gray-600 file:mr-3 file:rounded-md file:border-0 file:bg-indigo-50 file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-indigo-700">
                @error('logo') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                <div class="mt-2 flex items-center gap-3">
                    @if ($logo)
                        <img src="{{ $logo->temporaryUrl() }}" alt="" class="h-12 w-12 rounded-md object-contain ring-1 ring-gray-200">
                    @elseif ($this->logoUrl())
                        <img src="{{ $this->logoUrl() }}" alt="" class="h-12 w-12 rounded-md object-contain ring-1 ring-gray-200">
                        <button type="button" wire:click="removeLogo" class="text-xs font-medium text-red-600 hover:text-red-700">Remove logo</button>
                    @endif
                </div>
            </div>
        </div>

        <h2 class="border-t border-gray-100 pt-5 text-base font-semibold text-gray-900">SEO &amp; social meta</h2>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-gray-700">Meta title</label>
                <input type="text" wire:model="content.landing_meta_title" class="mt-1 block w-full rounded-md px-3 py-2 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-gray-700">Meta description</label>
                <textarea wire:model="content.landing_meta_description" rows="2" class="mt-1 block w-full rounded-md px-3 py-2 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500"></textarea>
            </div>
            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-gray-700">Meta keywords <span class="font-normal text-gray-400">(comma separated)</span></label>
                <input type="text" wire:model="content.landing_meta_keywords" class="mt-1 block w-full rounded-md px-3 py-2 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Twitter card type</label>
                <select wire:model="content.landing_twitter_card" class="mt-1 block w-full rounded-md px-3 py-2 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="summary_large_image">summary_large_image</option>
                    <option value="summary">summary</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">OG / share image <span class="font-normal text-gray-400">(≤ 3 MB, 1200×630)</span></label>
                <input type="file" wire:model="ogImage" accept="image/*" class="mt-1 block w-full text-sm text-gray-600 file:mr-3 file:rounded-md file:border-0 file:bg-indigo-50 file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-indigo-700">
                @error('ogImage') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                <div class="mt-2 flex items-center gap-3">
                    @if ($ogImage)
                        <img src="{{ $ogImage->temporaryUrl() }}" alt="" class="h-16 w-28 rounded-md object-cover ring-1 ring-gray-200">
                    @elseif ($this->ogImageUrl())
                        <img src="{{ $this->ogImageUrl() }}" alt="" class="h-16 w-28 rounded-md object-cover ring-1 ring-gray-200">
                        <button type="button" wire:click="removeOgImage" class="text-xs font-medium text-red-600 hover:text-red-700">Remove image</button>
                    @endif
                </div>
            </div>
        </div>

        {{-- ============ Hero ============ --}}
        <h2 class="border-t border-gray-100 pt-5 text-base font-semibold text-gray-900">Hero banner</h2>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <label class="block text-sm font-medium text-gray-700">Eyebrow</label>
                <input type="text" wire:model="content.landing_hero_eyebrow" class="mt-1 block w-full rounded-md px-3 py-2 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Title (H1)</label>
                <input type="text" wire:model="content.landing_hero_title" class="mt-1 block w-full rounded-md px-3 py-2 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-gray-700">Subtitle</label>
                <textarea wire:model="content.landing_hero_subtitle" rows="3" class="mt-1 block w-full rounded-md px-3 py-2 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500"></textarea>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Primary button label</label>
                <input type="text" wire:model="content.landing_hero_primary_label" class="mt-1 block w-full rounded-md px-3 py-2 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Secondary button label</label>
                <input type="text" wire:model="content.landing_hero_secondary_label" class="mt-1 block w-full rounded-md px-3 py-2 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-gray-700">Hero image <span class="font-normal text-gray-400">(shown on the right of the banner · ≤ 3 MB)</span></label>
                <input type="file" wire:model="heroImage" accept="image/*" class="mt-1 block w-full text-sm text-gray-600 file:mr-3 file:rounded-md file:border-0 file:bg-indigo-50 file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-indigo-700">
                @error('heroImage') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                <div class="mt-2 flex items-center gap-3">
                    @if ($heroImage)
                        <img src="{{ $heroImage->temporaryUrl() }}" alt="" class="h-20 w-32 rounded-md object-cover ring-1 ring-gray-200">
                    @elseif ($this->heroImageUrl())
                        <img src="{{ $this->heroImageUrl() }}" alt="" class="h-20 w-32 rounded-md object-cover ring-1 ring-gray-200">
                        <button type="button" wire:click="removeHeroImage" class="text-xs font-medium text-red-600 hover:text-red-700">Remove image</button>
                    @endif
                </div>
            </div>
        </div>

        {{-- ============ Section headings ============ --}}
        <h2 class="border-t border-gray-100 pt-5 text-base font-semibold text-gray-900">Section headings</h2>
        <div class="space-y-4">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div><label class="block text-sm font-medium text-gray-700">Features eyebrow</label><input type="text" wire:model="content.landing_features_eyebrow" class="mt-1 block w-full rounded-md px-3 py-2 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500"></div>
                <div class="sm:col-span-2"><label class="block text-sm font-medium text-gray-700">Features heading</label><input type="text" wire:model="content.landing_features_heading" class="mt-1 block w-full rounded-md px-3 py-2 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500"></div>
                <div class="sm:col-span-3"><label class="block text-sm font-medium text-gray-700">Features subheading</label><input type="text" wire:model="content.landing_features_subheading" class="mt-1 block w-full rounded-md px-3 py-2 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500"></div>
            </div>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div><label class="block text-sm font-medium text-gray-700">Formats eyebrow</label><input type="text" wire:model="content.landing_formats_eyebrow" class="mt-1 block w-full rounded-md px-3 py-2 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500"></div>
                <div class="sm:col-span-2"><label class="block text-sm font-medium text-gray-700">Formats heading</label><input type="text" wire:model="content.landing_formats_heading" class="mt-1 block w-full rounded-md px-3 py-2 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500"></div>
                <div class="sm:col-span-3"><label class="block text-sm font-medium text-gray-700">Formats subheading</label><input type="text" wire:model="content.landing_formats_subheading" class="mt-1 block w-full rounded-md px-3 py-2 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500"></div>
            </div>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div><label class="block text-sm font-medium text-gray-700">Samples eyebrow</label><input type="text" wire:model="content.landing_samples_eyebrow" class="mt-1 block w-full rounded-md px-3 py-2 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500"></div>
                <div class="sm:col-span-2"><label class="block text-sm font-medium text-gray-700">Samples heading</label><input type="text" wire:model="content.landing_samples_heading" class="mt-1 block w-full rounded-md px-3 py-2 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500"></div>
                <div class="sm:col-span-3"><label class="block text-sm font-medium text-gray-700">Samples subheading</label><input type="text" wire:model="content.landing_samples_subheading" class="mt-1 block w-full rounded-md px-3 py-2 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500"></div>
            </div>
        </div>

        {{-- ============ Footer ============ --}}
        <h2 class="border-t border-gray-100 pt-5 text-base font-semibold text-gray-900">Footer</h2>
        <div class="grid grid-cols-1 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700">Copyright line <span class="font-normal text-gray-400">(use <code>:year</code> for the year and <code>{company}</code> for the company link)</span></label>
                <input type="text" wire:model="content.landing_footer_copyright" placeholder="© :year {company} All rights reserved." class="mt-1 block w-full rounded-md px-3 py-2 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Footer tagline</label>
                <textarea wire:model="content.landing_footer_tagline" rows="2" class="mt-1 block w-full rounded-md px-3 py-2 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500"></textarea>
            </div>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Company name <span class="font-normal text-gray-400">(shown as the <code>{company}</code> link)</span></label>
                    <input type="text" wire:model="content.landing_footer_company_name" placeholder="e.g. Raj" class="mt-1 block w-full rounded-md px-3 py-2 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Company URL <span class="font-normal text-gray-400">(opens in a new tab)</span></label>
                    <input type="url" wire:model="content.landing_footer_company_url" placeholder="https://example.com" class="mt-1 block w-full rounded-md px-3 py-2 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
            </div>
        </div>

        <div class="flex justify-end border-t border-gray-100 pt-4">
            <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                <span wire:loading.remove wire:target="saveContent,logo,ogImage,heroImage">Save landing content</span>
                <span wire:loading wire:target="saveContent,logo,ogImage,heroImage">Saving…</span>
            </button>
        </div>
    </form>

    {{-- ============ Sample library generator ============ --}}
    <div class="flex flex-col gap-3 rounded-lg bg-white p-5 shadow-sm ring-1 ring-gray-200 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-base font-semibold text-gray-900">Sample reports library</h2>
            <p class="mt-0.5 text-sm text-gray-500">Generate the predefined set of sample reports (idempotent — re-running refreshes their content and links the matching sample cards below).</p>
        </div>
        <button type="button" wire:click="generateSamples" wire:confirm="Generate / refresh the sample report library?" class="shrink-0 rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
            <span wire:loading.remove wire:target="generateSamples">Generate sample reports</span>
            <span wire:loading wire:target="generateSamples">Generating…</span>
        </button>
    </div>

    {{-- ============ Card add form ============ --}}
    <form wire:submit="addCard" class="space-y-4 rounded-lg bg-white p-5 shadow-sm ring-1 ring-gray-200">
        <h2 class="text-base font-semibold text-gray-900">Add a card</h2>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <label class="block text-sm font-medium text-gray-700">Section</label>
                <select wire:model="newSection" class="mt-1 block w-full rounded-md px-3 py-2 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="features">Features</option>
                    <option value="formats">University formats</option>
                    <option value="samples">Sample reports</option>
                    <option value="faqs">FAQs</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Badge text <span class="font-normal text-gray-400">(used if no image)</span></label>
                <input type="text" wire:model="newBadge" maxlength="8" placeholder="e.g. TU" class="mt-1 block w-full rounded-md px-3 py-2 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-gray-700">Title</label>
                <input type="text" wire:model="newTitle" class="mt-1 block w-full rounded-md px-3 py-2 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                @error('newTitle') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-gray-700">Description</label>
                <textarea wire:model="newDescription" rows="2" class="mt-1 block w-full rounded-md px-3 py-2 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500"></textarea>
            </div>
            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-gray-700">Image <span class="font-normal text-gray-400">(PNG/SVG/JPG, ≤ 3 MB · features/formats: small square · sample reports: 400×150 banner)</span></label>
                <input type="file" wire:model="newIcon" accept="image/*" class="mt-1 block w-full text-sm text-gray-600 file:mr-3 file:rounded-md file:border-0 file:bg-indigo-50 file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-indigo-700">
                @error('newIcon') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                @if ($newIcon)
                    <img src="{{ $newIcon->temporaryUrl() }}" alt="" class="mt-2 h-12 w-12 rounded-md object-cover ring-1 ring-gray-200">
                @endif
            </div>
        </div>
        <div class="flex justify-end">
            <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                <span wire:loading.remove wire:target="addCard,newIcon">Add card</span>
                <span wire:loading wire:target="addCard,newIcon">Saving…</span>
            </button>
        </div>
    </form>

    {{-- ============ Card sections ============ --}}
    @foreach (['features' => 'Features', 'formats' => 'University formats', 'samples' => 'Sample reports', 'faqs' => 'FAQs'] as $section => $label)
        <div>
            <h2 class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $label }}</h2>
            <ul wire:sort="reorder" class="space-y-2">
                @forelse ($this->features($section) as $card)
                    <li wire:key="card-{{ $card->id }}" wire:sort:item="{{ $card->id }}" class="rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-200 {{ $card->visible ? '' : 'opacity-60' }}">
                        <div class="flex items-start gap-3">
                            <span wire:sort:handle class="mt-1 cursor-grab select-none text-gray-300 hover:text-gray-500" title="Drag to reorder">⠿</span>

                            {{-- Icon / badge preview (samples use a wide 400×150 banner) --}}
                            @if ($card->iconUrl())
                                <img src="{{ $card->iconUrl() }}" alt="" class="{{ $section === 'samples' ? 'h-12 w-32' : 'h-10 w-10' }} shrink-0 rounded-lg object-cover ring-1 ring-gray-200">
                            @else
                                <span class="flex {{ $section === 'samples' ? 'h-12 w-32' : 'h-10 w-10' }} shrink-0 items-center justify-center rounded-lg bg-indigo-600 text-xs font-bold text-white">{{ $card->badgeText() }}</span>
                            @endif

                            <div class="min-w-0 flex-1">
                                @if ($editingId === $card->id)
                                    <div class="space-y-2">
                                        <input type="text" wire:model="editTitle" class="block w-full rounded-md px-2 py-1.5 text-sm font-semibold ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                        @error('editTitle') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                                        <textarea wire:model="editDescription" rows="2" class="block w-full rounded-md px-2 py-1.5 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500"></textarea>
                                        <div class="flex flex-wrap items-center gap-2">
                                            <input type="text" wire:model="editBadge" maxlength="8" placeholder="Badge" class="w-24 rounded-md px-2 py-1.5 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                            <input type="file" wire:model="editIcon" accept="image/*" class="text-xs text-gray-600 file:mr-2 file:rounded file:border-0 file:bg-indigo-50 file:px-2 file:py-1 file:text-xs file:font-semibold file:text-indigo-700">
                                        </div>
                                        @error('editIcon') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                                        <div class="flex gap-2">
                                            <button type="button" wire:click="saveEdit" class="rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-500">Save</button>
                                            <button type="button" wire:click="cancelEdit" class="rounded-md px-3 py-1.5 text-xs font-semibold text-gray-700 ring-1 ring-gray-300 hover:bg-gray-50">Cancel</button>
                                        </div>
                                    </div>
                                @else
                                    <p class="flex items-center gap-2 text-sm font-semibold text-gray-900">
                                        {{ $card->title }}
                                        @unless ($card->visible)
                                            <span class="rounded bg-gray-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-gray-500">Hidden</span>
                                        @endunless
                                    </p>
                                    <p class="mt-0.5 text-sm text-gray-500">{{ $card->description }}</p>
                                @endif
                            </div>

                            @unless ($editingId === $card->id)
                                <div class="flex shrink-0 items-center gap-2">
                                    <button type="button" wire:click="toggleVisible({{ $card->id }})" class="text-xs font-medium {{ $card->visible ? 'text-gray-500 hover:text-gray-700' : 'text-green-600 hover:text-green-700' }}">
                                        {{ $card->visible ? 'Hide' : 'Show' }}
                                    </button>
                                    @if ($card->iconUrl())
                                        <button type="button" wire:click="removeIcon({{ $card->id }})" class="text-xs font-medium text-gray-500 hover:text-gray-700" title="Remove image (use badge)">Clear image</button>
                                    @endif
                                    <button type="button" wire:click="edit({{ $card->id }})" class="text-xs font-medium text-indigo-600 hover:text-indigo-500">Edit</button>
                                    <button type="button" wire:click="delete({{ $card->id }})" wire:confirm="Delete this card?" class="text-xs font-medium text-red-600 hover:text-red-700">Delete</button>
                                </div>
                            @endunless
                        </div>
                    </li>
                @empty
                    <li class="rounded-lg bg-white px-4 py-6 text-center text-sm text-gray-400 ring-1 ring-gray-200">No cards in this section yet.</li>
                @endforelse
            </ul>
        </div>
    @endforeach
</div>
