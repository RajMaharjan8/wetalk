<?php

use App\Models\Setting;
use App\Support\FeatureSettings;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::admin')] class extends Component
{
    public bool $feedbackEnabled = true;

    public bool $checkReportEnabled = true;

    public function mount(): void
    {
        $this->feedbackEnabled = FeatureSettings::feedbackEnabled();
        $this->checkReportEnabled = FeatureSettings::checkReportEnabled();
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
</div>
