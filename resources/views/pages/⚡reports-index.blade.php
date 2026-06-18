<?php

use App\Models\Report;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

new class extends Component
{
    /**
     * The signed-in user's reports, newest activity first.
     *
     * @return Collection<int, Report>
     */
    public function getReportsProperty(): Collection
    {
        return Auth::user()
            ->reports()
            ->withCount('sections')
            ->latest('updated_at')
            ->get();
    }

    public function getReportLimitProperty(): int
    {
        return User::MAX_REPORTS;
    }

    public function getCanCreateMoreProperty(): bool
    {
        return $this->reports->count() < $this->reportLimit;
    }

    public function deleteReport(int $reportId): void
    {
        $report = Report::whereKey($reportId)->firstOrFail();
        $this->authorize('delete', $report);
        $report->delete();
    }
}; ?>

<div class="min-h-screen bg-gray-50 dark:bg-gray-900">
    <x-app-header />
    <x-validation-popup />

    <div class="mx-auto max-w-4xl px-4 py-10 sm:px-6 lg:px-8">
        @if (session('feedback-sent'))
            <div class="mb-6 rounded-md bg-green-50 px-4 py-3 text-sm font-medium text-green-800 ring-1 ring-green-200 dark:bg-green-500/10 dark:text-green-300 dark:ring-green-500/20">{{ __('Thanks for your feedback!') }}</div>
        @endif

        @if (session('report-limit'))
            <div class="mb-6 rounded-md bg-amber-50 p-4 text-sm text-amber-800 ring-1 ring-amber-200 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-500/20">
                {{ session('report-limit') }}
            </div>
        @endif

        <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold font-display text-gray-900 dark:text-gray-100 sm:text-3xl">{{ __('Your Reports') }}</h1>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">Islington College &middot; London Metropolitan University</p>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-500">
                    {{ __(':count of :limit reports used — delete one to start another.', ['count' => $this->reports->count(), 'limit' => $this->reportLimit]) }}
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('cover.templates') }}" wire:navigate class="inline-flex shrink-0 items-center rounded-md bg-white px-4 py-2 text-sm font-semibold text-gray-900 ring-1 ring-gray-300 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-100 dark:ring-gray-600 dark:hover:bg-gray-700">
                    {{ __('Cover Designer') }}
                </a>
                <a href="{{ route('reports.check') }}" wire:navigate class="inline-flex shrink-0 items-center rounded-md bg-white px-4 py-2 text-sm font-semibold text-gray-900 ring-1 ring-gray-300 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-100 dark:ring-gray-600 dark:hover:bg-gray-700">
                    {{ __('Check My Report') }}
                </a>
                @if ($this->canCreateMore)
                    <a href="{{ route('reports.create') }}" wire:navigate class="inline-flex shrink-0 items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                        {{ __('+ New report') }}
                    </a>
                @else
                    <span class="inline-flex shrink-0 items-center rounded-md bg-gray-200 px-4 py-2 text-sm font-semibold text-gray-500 cursor-not-allowed dark:bg-gray-700 dark:text-gray-400" title="{{ __('Delete an existing report to create another.') }}">
                        {{ __('+ New report') }}
                    </span>
                @endif
            </div>
        </div>

        @if ($this->reports->isEmpty())
            <div class="rounded-xl bg-white p-12 text-center shadow-sm ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-gray-700">
                <p class="text-sm text-gray-600 dark:text-gray-400">{{ __('No reports yet. Start one and your progress is saved automatically.') }}</p>
                <a href="{{ route('reports.create') }}" wire:navigate class="mt-4 inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                    {{ __('Create your first report') }}
                </a>
            </div>
        @else
            <ul class="space-y-3">
                @foreach ($this->reports as $report)
                    <li wire:key="report-{{ $report->id }}" class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-gray-700 sm:p-5">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div class="min-w-0">
                                <h2 class="truncate text-base font-semibold text-gray-900 dark:text-gray-100">
                                    {{ $report->student_name ?: __('Untitled draft') }}
                                </h2>
                                <p class="mt-0.5 truncate text-sm text-gray-600 dark:text-gray-400">
                                    @php($subtitle = $report->cover_format === 'tu' ? trim((string) $report->title) : trim($report->module_code.' '.$report->module_title))
                                    @if (filled($subtitle))
                                        {{ $subtitle }}
                                    @else
                                        <span class="text-gray-400 dark:text-gray-500">{{ $report->cover_format === 'tu' ? __('No assignment title yet') : __('No module set yet') }}</span>
                                    @endif
                                </p>
                                <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">
                                    {{ trans_choice(':count section|:count sections', $report->sections_count, ['count' => $report->sections_count]) }}
                                    &middot; {{ __('updated :time', ['time' => $report->updated_at->diffForHumans()]) }}
                                </p>
                            </div>

                            <div class="flex flex-wrap items-center gap-2">
                                <a href="{{ route('reports.edit', $report) }}" wire:navigate class="rounded-md bg-white px-3 py-1.5 text-xs font-semibold text-gray-900 ring-1 ring-gray-300 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-100 dark:ring-gray-600 dark:hover:bg-gray-700">
                                    {{ __('Edit cover') }}
                                </a>
                                <a href="{{ route('reports.sections', $report) }}" wire:navigate class="rounded-md bg-white px-3 py-1.5 text-xs font-semibold text-gray-900 ring-1 ring-gray-300 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-100 dark:ring-gray-600 dark:hover:bg-gray-700">
                                    {{ __('Write content') }}
                                </a>
                                <a href="{{ route('reports.live-check', $report) }}" wire:navigate class="rounded-md bg-white px-3 py-1.5 text-xs font-semibold text-gray-900 ring-1 ring-gray-300 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-100 dark:ring-gray-600 dark:hover:bg-gray-700">
                                    {{ __('Format check') }}
                                </a>
                                <a href="{{ route('reports.output', $report) }}" class="rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-500">
                                    {{ __('View report') }}
                                </a>
                                <button type="button" wire:click="deleteReport({{ $report->id }})" wire:confirm="{{ __('Delete this report and all its sections? This cannot be undone.') }}" class="rounded-md px-2 py-1.5 text-xs font-semibold text-red-600 hover:bg-red-50 dark:hover:bg-red-500/10">
                                    {{ __('Delete') }}
                                </button>
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
