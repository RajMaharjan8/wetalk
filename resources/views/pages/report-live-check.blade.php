<?php

use App\Models\Report;
use App\Support\Checks\CheckResult;
use App\Support\Checks\LiveReportChecker;
use Livewire\Component;

new class extends Component
{
    public Report $report;

    /** @var list<array{label: string, status: string, detail: string}> */
    public array $results = [];

    public string $formatName = '';

    public function mount(Report $report): void
    {
        $this->authorize('view', $report);

        $this->report = $report;
        $this->run();
    }

    public function run(): void
    {
        $outcome = (new LiveReportChecker)->check($this->report);
        $this->formatName = $outcome['format'];
        $this->results = array_map(
            fn (CheckResult $r) => ['label' => $r->label, 'status' => $r->status, 'detail' => $r->detail],
            $outcome['results'],
        );
    }
}; ?>

<div class="min-h-screen bg-gray-50 dark:bg-gray-900">
    <x-app-header />
    <div class="mx-auto max-w-3xl py-12 px-4 sm:px-6 lg:px-8">
        <div class="mb-6">
            <a href="{{ route('reports.index') }}" wire:navigate class="text-sm font-medium text-indigo-600 hover:text-indigo-500">&larr; {{ __('All reports') }}</a>
        </div>

        <div class="mb-8">
            <h1 class="text-3xl font-semibold text-gray-900 dark:text-gray-100">{{ __('Format check') }}</h1>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">
                {!! __('Live check of :title against :format.', ['title' => '<span class="font-medium">'.e($report->title ?: __('this report')).'</span>', 'format' => '<span class="font-medium">'.e($formatName).'</span>']) !!}
            </p>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('This runs against the data you\'ve entered on the site — your cover fields, sections and references — not a PDF.') }}</p>
        </div>

        @php
            $grouped = [
                \App\Support\Checks\CheckResult::FAIL => [],
                \App\Support\Checks\CheckResult::WARN => [],
                \App\Support\Checks\CheckResult::PASS => [],
            ];
            foreach ($results as $r) {
                $grouped[$r['status']][] = $r;
            }
            $failCount = count($grouped[\App\Support\Checks\CheckResult::FAIL]);
            $warnCount = count($grouped[\App\Support\Checks\CheckResult::WARN]);
            $passCount = count($grouped[\App\Support\Checks\CheckResult::PASS]);
        @endphp

        <div class="space-y-6">
            <div class="rounded-lg bg-white p-6 shadow-sm ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-gray-700">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ __('Your report check') }}</h2>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ trans_choice(':count section|:count sections', $report->sections->count(), ['count' => $report->sections->count()]) }} &middot; {{ trans_choice(':count reference|:count references', $report->references->count(), ['count' => $report->references->count()]) }}</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2 text-sm font-semibold">
                        <span class="inline-flex items-center gap-1 rounded-full bg-red-100 px-3 py-1 text-red-800 dark:bg-red-500/10 dark:text-red-300">
                            <span class="text-base leading-none">✗</span> {{ __(':count to fix', ['count' => $failCount]) }}
                        </span>
                        <span class="inline-flex items-center gap-1 rounded-full bg-yellow-100 px-3 py-1 text-yellow-900 dark:bg-yellow-500/10 dark:text-yellow-300">
                            <span class="text-base leading-none">!</span> {{ __(':count to review', ['count' => $warnCount]) }}
                        </span>
                        <span class="inline-flex items-center gap-1 rounded-full bg-green-100 px-3 py-1 text-green-800 dark:bg-green-500/10 dark:text-green-300">
                            <span class="text-base leading-none">✓</span> {{ __(':count OK', ['count' => $passCount]) }}
                        </span>
                    </div>
                </div>
                <div class="mt-4 flex flex-wrap gap-2 text-xs">
                    <a href="{{ route('reports.edit', $report) }}" wire:navigate class="rounded-md bg-white px-3 py-1.5 font-semibold text-gray-900 ring-1 ring-gray-300 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-100 dark:ring-gray-600 dark:hover:bg-gray-700">{{ __('Edit cover') }}</a>
                    <a href="{{ route('reports.sections', $report) }}" wire:navigate class="rounded-md bg-white px-3 py-1.5 font-semibold text-gray-900 ring-1 ring-gray-300 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-100 dark:ring-gray-600 dark:hover:bg-gray-700">{{ __('Write sections') }}</a>
                    <button wire:click="run" class="rounded-md bg-indigo-600 px-3 py-1.5 font-semibold text-white hover:bg-indigo-500">{{ __('Re-check') }}</button>
                </div>
            </div>

            @if ($failCount > 0)
                <section>
                    <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-red-700 dark:text-red-300">{{ __('Fix these') }}</h3>
                    <ul class="space-y-2">
                        @foreach ($grouped[\App\Support\Checks\CheckResult::FAIL] as $result)
                            <li class="flex items-start gap-3 rounded-md border border-red-200 bg-red-50 px-4 py-3 dark:border-red-500/20 dark:bg-red-500/10">
                                <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-red-600 text-xs font-bold text-white">✗</span>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-semibold text-red-900 dark:text-red-300">{{ $result['label'] }}</p>
                                    @if ($result['detail'])
                                        <p class="mt-0.5 text-sm text-red-800 dark:text-red-300">{{ $result['detail'] }}</p>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            @if ($warnCount > 0)
                <section>
                    <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-yellow-800 dark:text-yellow-300">{{ __('Worth reviewing') }}</h3>
                    <ul class="space-y-2">
                        @foreach ($grouped[\App\Support\Checks\CheckResult::WARN] as $result)
                            <li class="flex items-start gap-3 rounded-md border border-yellow-200 bg-yellow-50 px-4 py-3 dark:border-yellow-500/20 dark:bg-yellow-500/10">
                                <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-yellow-500 text-xs font-bold text-white">!</span>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-semibold text-yellow-900 dark:text-yellow-300">{{ $result['label'] }}</p>
                                    @if ($result['detail'])
                                        <p class="mt-0.5 text-sm text-yellow-800 dark:text-yellow-300">{{ $result['detail'] }}</p>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            @if ($passCount > 0)
                <details class="rounded-md border border-green-200 bg-green-50 px-4 py-3 dark:border-green-500/20 dark:bg-green-500/10">
                    <summary class="cursor-pointer text-sm font-semibold text-green-800 dark:text-green-300">
                        ✓ {{ trans_choice(':count check passed (click to expand)|:count checks passed (click to expand)', $passCount, ['count' => $passCount]) }}
                    </summary>
                    <ul class="mt-3 space-y-1 text-sm text-green-900 dark:text-green-300">
                        @foreach ($grouped[\App\Support\Checks\CheckResult::PASS] as $result)
                            <li class="flex items-start gap-2">
                                <span class="mt-0.5 text-green-600">✓</span>
                                <span><strong>{{ $result['label'] }}</strong>@if ($result['detail']) — {{ $result['detail'] }}@endif</span>
                            </li>
                        @endforeach
                    </ul>
                </details>
            @endif
        </div>
    </div>
</div>
