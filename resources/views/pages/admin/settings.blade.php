<?php

use App\Models\Setting;
use App\Support\FeatureSettings;
use App\Support\LandingContent;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts::admin')] class extends Component
{
    use WithFileUploads;

    public bool $feedbackEnabled = true;

    public bool $checkReportEnabled = true;

    /** New favicon upload (ICO/PNG/SVG, ≤ 1 MB). */
    #[Validate('nullable|image|max:1024')]
    public $favicon = null;

    public function mount(): void
    {
        $this->feedbackEnabled = FeatureSettings::feedbackEnabled();
        $this->checkReportEnabled = FeatureSettings::checkReportEnabled();
    }

    public function faviconUrl(): ?string
    {
        return LandingContent::imageUrl('site_favicon');
    }

    /** Store the uploaded favicon, replacing any previous one. */
    public function updatedFavicon(): void
    {
        abort_unless(auth()->user()->can('settings.manage'), 403);

        $this->validateOnly('favicon');

        if ($old = Setting::get('site_favicon')) {
            Storage::disk('public')->delete($old);
        }

        Setting::set('site_favicon', $this->favicon->store('branding', 'public'));
        $this->favicon = null;

        session()->flash('settings-saved', 'Favicon updated.');
    }

    public function removeFavicon(): void
    {
        abort_unless(auth()->user()->can('settings.manage'), 403);

        if ($path = Setting::get('site_favicon')) {
            Storage::disk('public')->delete($path);
            Setting::set('site_favicon', null);
        }

        session()->flash('settings-saved', 'Favicon removed.');
    }

    public function updatedFeedbackEnabled(bool $value): void
    {
        abort_unless(auth()->user()->can('settings.manage'), 403);

        Setting::set('feature_feedback_enabled', $value ? '1' : '0');
        session()->flash('settings-saved', $value ? 'Send feedback enabled.' : 'Send feedback disabled.');
    }

    public function updatedCheckReportEnabled(bool $value): void
    {
        abort_unless(auth()->user()->can('settings.manage'), 403);

        Setting::set('feature_check_report_enabled', $value ? '1' : '0');
        session()->flash('settings-saved', $value ? 'Check report enabled.' : 'Check report disabled.');
    }
}; ?>

@php($title = 'Settings')

<div class="max-w-3xl space-y-6">
    @if (session('settings-saved'))
        <div x-data="{ show: true }" x-show="show" x-transition x-init="setTimeout(() => show = false, 4000)"
             class="flex items-center gap-2 rounded-lg bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 ring-1 ring-emerald-200">
            <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
            {{ session('settings-saved') }}
        </div>
    @endif

    <div>
        <h1 class="text-lg font-semibold text-slate-900">App settings</h1>
        <p class="mt-1 text-sm text-slate-500">Turn user-facing features on or off. Changes take effect immediately.</p>
    </div>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <p class="border-b border-slate-100 px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Features</p>

        {{-- Send feedback --}}
        <div class="flex items-start justify-between gap-4 px-5 py-4">
            <div>
                <h2 class="flex items-center gap-2 text-sm font-semibold text-slate-900">
                    Send feedback
                    <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $feedbackEnabled ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">{{ $feedbackEnabled ? 'On' : 'Off' }}</span>
                </h2>
                <p class="mt-1 text-sm text-slate-500">Lets signed-in users send feedback from the profile menu.</p>
            </div>
            <label class="relative inline-flex shrink-0 cursor-pointer items-center">
                <input type="checkbox" wire:model.live="feedbackEnabled" class="peer sr-only">
                <div class="h-6 w-11 rounded-full bg-slate-200 after:absolute after:left-0.5 after:top-0.5 after:h-5 after:w-5 after:rounded-full after:bg-white after:shadow after:transition-all peer-checked:bg-indigo-600 peer-checked:after:translate-x-5"></div>
            </label>
        </div>

        {{-- Check report --}}
        <div class="flex items-start justify-between gap-4 border-t border-slate-100 px-5 py-4">
            <div>
                <h2 class="flex items-center gap-2 text-sm font-semibold text-slate-900">
                    Check my report
                    <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $checkReportEnabled ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">{{ $checkReportEnabled ? 'On' : 'Off' }}</span>
                </h2>
                <p class="mt-1 text-sm text-slate-500">The PDF format-checker tool that flags missing or non-conforming parts.</p>
            </div>
            <label class="relative inline-flex shrink-0 cursor-pointer items-center">
                <input type="checkbox" wire:model.live="checkReportEnabled" class="peer sr-only">
                <div class="h-6 w-11 rounded-full bg-slate-200 after:absolute after:left-0.5 after:top-0.5 after:h-5 after:w-5 after:rounded-full after:bg-white after:shadow after:transition-all peer-checked:bg-indigo-600 peer-checked:after:translate-x-5"></div>
            </label>
        </div>
    </div>

    {{-- ============ Branding ============ --}}
    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <p class="border-b border-slate-100 px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Branding</p>

        <div class="px-5 py-4">
            <h2 class="text-sm font-semibold text-slate-900">Favicon</h2>
            <p class="mt-1 text-sm text-slate-500">The small icon shown in browser tabs and bookmarks. Use a square PNG, SVG or ICO (32×32 or larger), ≤ 1 MB.</p>

            <div class="mt-3 flex items-center gap-4">
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg bg-slate-50 ring-1 ring-slate-200">
                    @if ($favicon)
                        <img src="{{ $favicon->temporaryUrl() }}" alt="" class="h-8 w-8 rounded object-contain">
                    @elseif ($this->faviconUrl())
                        <img src="{{ $this->faviconUrl() }}" alt="" class="h-8 w-8 rounded object-contain">
                    @else
                        <svg class="h-6 w-6 text-slate-300" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909M18 14.25V8.25a2.25 2.25 0 00-2.25-2.25H6A2.25 2.25 0 003.75 8.25v9.75A2.25 2.25 0 006 20.25h12a2.25 2.25 0 002.25-2.25z" /></svg>
                    @endif
                </div>

                <div class="flex-1">
                    <input type="file" wire:model="favicon" accept="image/*,.ico" class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-indigo-50 file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-indigo-700">
                    @error('favicon') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    <p wire:loading wire:target="favicon" class="mt-1 text-xs text-slate-400">Uploading…</p>
                    @if ($this->faviconUrl())
                        <button type="button" wire:click="removeFavicon" class="mt-1.5 text-xs font-medium text-red-600 hover:text-red-700">Remove favicon</button>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
