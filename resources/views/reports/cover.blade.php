<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cover Page &mdash; {{ $report->student_name }}</title>
    @include('partials.pwa-head')

    @include('partials.theme-head')

    @vite(['resources/css/app.css'])
    {{-- Shared cover styles (tu-sheet etc.) so the TU cover partial renders the same here and in the report output. --}}
    <link rel="stylesheet" href="{{ asset('css/report.css') }}">
    <style>
        @page { size: A4; margin: 0; }
        @media print {
            body { background: #fff; }
            .no-print { display: none !important; }
            .cover-sheet { box-shadow: none !important; margin: 0 !important; }
        }
        summary { list-style: none; }
        summary::-webkit-details-marker { display: none; }
    </style>
</head>
<body class="bg-gray-100 dark:bg-gray-900">
    @php
        $btnSecondary = 'inline-flex items-center justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-gray-300 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-100 dark:ring-gray-600 dark:hover:bg-gray-700';
        $btnPrimary = 'inline-flex items-center justify-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500';
        $fieldClass = 'mt-1 block w-full rounded-md px-2 py-1.5 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-gray-900 dark:text-gray-100 dark:ring-gray-600';
        $panelClass = 'absolute right-0 z-30 mt-2 max-h-[75vh] overflow-y-auto rounded-lg bg-white p-4 text-left shadow-xl ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-gray-700';
        $legendClass = 'text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400';
        $labelClass = 'block text-xs font-medium text-gray-700 dark:text-gray-300';
        $hintClass = 'text-[11px] text-gray-500 dark:text-gray-400';
    @endphp

    <div class="no-print sticky top-0 z-30 border-b border-gray-200 bg-white/95 backdrop-blur dark:border-gray-800 dark:bg-gray-900/95">
        <div class="mx-auto max-w-[210mm] px-4 py-3">
            @if (session('cover-saved'))
                <div class="mb-3 rounded-md bg-green-50 px-4 py-2 text-sm font-medium text-green-800 ring-1 ring-green-200 dark:bg-green-500/10 dark:text-green-300 dark:ring-green-500/20">
                    {{ session('cover-saved') }}
                </div>
            @endif

            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <a href="{{ route('reports.index') }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-500">&larr; All reports</a>

                <div class="flex flex-wrap items-center gap-2">
                    <a href="{{ route('reports.edit', ['report' => $report]) }}" class="{{ $btnSecondary }}">Edit cover</a>

                    {{-- All document settings in one panel: one form, one save --}}
                    <details class="relative">
                        <summary class="{{ $btnSecondary }} cursor-pointer">
                            Customize
                            <svg class="ml-1 h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.17l3.71-3.94a.75.75 0 1 1 1.08 1.04l-4.25 4.5a.75.75 0 0 1-1.08 0l-4.25-4.5a.75.75 0 0 1 .02-1.06Z" clip-rule="evenodd" /></svg>
                        </summary>

                        <div class="{{ $panelClass }} w-[calc(100vw-2rem)] max-w-96">
                            <form method="POST" action="{{ route('reports.cover.settings', ['report' => $report]) }}" class="space-y-5">
                                @csrf

                                {{-- Heading 1 --}}
                                <fieldset class="space-y-2">
                                    <legend class="{{ $legendClass }}">Heading 1</legend>

                                    <label for="section_label" class="{{ $labelClass }}">Word before the number</label>
                                    <input type="text" id="section_label" name="section_label" value="{{ $report->section_label }}" placeholder="e.g. Chapter" class="{{ $fieldClass }}">
                                    <p class="{{ $hintClass }}">Blank gives <strong>1.</strong>, <strong>2.</strong>&hellip; A word like <strong>Chapter</strong> gives <strong>Chapter 1</strong>. Sub-headings (1.1) stay numeric.</p>

                                    <label for="heading_align" class="{{ $labelClass }}">Alignment</label>
                                    <select id="heading_align" name="heading_align" class="{{ $fieldClass }}">
                                        <option value="left" @selected($report->headingAlign() === 'left')>Left</option>
                                        <option value="center" @selected($report->headingAlign() === 'center')>Center</option>
                                        <option value="right" @selected($report->headingAlign() === 'right')>Right</option>
                                    </select>

                                    <label class="mt-1 flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                        <input type="checkbox" name="heading_uppercase" value="1" @checked($report->heading_uppercase) class="h-4 w-4 rounded ring-1 ring-gray-300 text-indigo-600 focus:ring-indigo-500 dark:ring-gray-600">
                                        Capitalize (UPPERCASE)
                                    </label>
                                </fieldset>

                                {{-- Abstract --}}
                                <fieldset class="space-y-1 border-t border-gray-100 pt-4 dark:border-gray-700">
                                    <legend class="{{ $legendClass }}">Abstract</legend>
                                    <p class="{{ $hintClass }}">Optional. Appears on its own page before the contents.</p>
                                    <textarea id="abstract" name="abstract" rows="4" placeholder="A short summary of the report…" class="{{ $fieldClass }}">{{ $report->abstract }}</textarea>
                                </fieldset>

                                {{-- Page layout --}}
                                <fieldset class="space-y-2 border-t border-gray-100 pt-4 dark:border-gray-700">
                                    <legend class="{{ $legendClass }}">Page margins (inches)</legend>
                                    <p class="{{ $hintClass }}">Standard rule: Top/Right/Bottom 1.0, Left 1.5 (for binding).</p>
                                    <div class="grid grid-cols-2 gap-2">
                                        @foreach (['top' => 'Top', 'right' => 'Right', 'bottom' => 'Bottom', 'left' => 'Left'] as $side => $label)
                                            <label class="block">
                                                <span class="block text-[11px] font-medium text-gray-600 dark:text-gray-400">{{ $label }}</span>
                                                <input type="number" step="any" min="0.25" max="3" name="margin_{{ $side }}" value="{{ number_format((float) $report->{'margin_'.$side}, 2) }}" class="{{ $fieldClass }}">
                                            </label>
                                        @endforeach
                                    </div>
                                </fieldset>

                                {{-- Page numbers --}}
                                <fieldset class="space-y-1 border-t border-gray-100 pt-4 dark:border-gray-700">
                                    <legend class="{{ $legendClass }}">Page number position</legend>
                                    <select id="page_number_align" name="page_number_align" class="{{ $fieldClass }}">
                                        <option value="left" @selected($report->page_number_align === 'left')>Left</option>
                                        <option value="center" @selected($report->page_number_align === 'center')>Center</option>
                                        <option value="right" @selected($report->page_number_align !== 'left' && $report->page_number_align !== 'center')>Right</option>
                                    </select>
                                </fieldset>

                                <button type="submit" class="w-full rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-500">Save changes</button>
                            </form>
                        </div>
                    </details>

                    {{-- Custom cover designs --}}
                    <details class="relative">
                        <summary class="{{ $btnSecondary }} cursor-pointer">
                            Cover design
                            <svg class="ml-1 h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.17l3.71-3.94a.75.75 0 1 1 1.08 1.04l-4.25 4.5a.75.75 0 0 1-1.08 0l-4.25-4.5a.75.75 0 0 1 .02-1.06Z" clip-rule="evenodd" /></svg>
                        </summary>

                        <div class="{{ $panelClass }} w-[calc(100vw-2rem)] max-w-80">
                            <p class="{{ $legendClass }}">Use a saved cover</p>

                            @if (filled($report->frontOverride('cover')))
                                <div class="mt-2 flex items-center justify-between rounded-md bg-indigo-50 px-2 py-1.5 text-xs text-indigo-700 dark:bg-indigo-500/15 dark:text-indigo-300">
                                    <span>A custom cover is in use.</span>
                                    <form method="POST" action="{{ route('reports.front-overrides.reset', ['report' => $report]) }}" onsubmit="return confirm('Remove the custom cover and any page edits?');">
                                        @csrf
                                        <button type="submit" class="font-semibold underline">Remove</button>
                                    </form>
                                </div>
                            @endif

                            <ul class="mt-2 space-y-1">
                                @forelse ($coverTemplates as $template)
                                    <li>
                                        <form method="POST" action="{{ route('reports.cover.use-template', ['report' => $report]) }}">
                                            @csrf
                                            <input type="hidden" name="template_id" value="{{ $template->id }}">
                                            <button type="submit" class="flex w-full items-center justify-between rounded-md px-2 py-1.5 text-left text-sm text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-gray-700">
                                                <span class="truncate">{{ $template->name }}</span>
                                                <span class="text-xs font-semibold text-indigo-600">Use</span>
                                            </button>
                                        </form>
                                    </li>
                                @empty
                                    <li class="px-2 py-1.5 text-xs text-gray-500 dark:text-gray-400">No saved covers yet.</li>
                                @endforelse
                            </ul>

                            <a href="{{ route('cover.templates') }}" class="mt-3 block rounded-md bg-indigo-600 px-3 py-1.5 text-center text-sm font-semibold text-white hover:bg-indigo-500">
                                Open Cover Designer
                            </a>
                        </div>
                    </details>

                    <a href="{{ route('reports.sections', ['report' => $report]) }}" class="{{ $btnSecondary }}">Write content</a>
                    <a href="{{ route('reports.output', ['report' => $report]) }}" class="{{ $btnPrimary }}">View report</a>
                </div>
            </div>
        </div>
    </div>

    @if (filled($report->frontOverride('cover')))
        <div class="cover-sheet mx-auto my-6 w-fit shadow-md ring-1 ring-gray-200">{!! $report->frontOverride('cover') !!}</div>
    @else
        @include($report->cover_format === 'tu' ? 'reports.partials.tu-cover-sheet' : 'reports.partials.cover-sheet')
    @endif
</body>
</html>
