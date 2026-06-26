<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $report->title ?: $report->module_title }}</title>

    @include('partials.theme-head')
    @include('partials.pwa-head')

    @vite(['resources/js/report.js'])

    @php
        $align = in_array($report->page_number_align, ['left', 'center', 'right'], true)
            ? $report->page_number_align
            : 'right';
        $margins = $report->pageMargins();
        $lineSpacing = $report->lineSpacing();
        $headingAlign = $report->headingAlign();
        $headingTransform = $report->heading_uppercase ? 'uppercase' : 'none';
        // Public sample mode (the landing-page iframe preview): read-only, free,
        // no editor chrome and no owner-only actions.
        $sample = $sample ?? false;
        // "Edit pages" mode: only owners (update ability) and only when ?edit=1.
        $canEdit = ! $sample && (request()->user()?->can('update', $report) ?? false);
        $editing = $canEdit && request()->boolean('edit');
    @endphp

    @if ($editing)
        {{-- Edit mode renders the raw (un-paginated) sheets, so it needs the
             print stylesheet loaded directly rather than via Paged.js. --}}
        <link rel="stylesheet" href="{{ asset('css/report.css') }}?v={{ filemtime(public_path('css/report.css')) }}">
    @endif

    <script>
        /* Only the hand-written print stylesheet is handed to Paged.js — its
           CSS parser cannot digest the compiled Tailwind bundle. Page numbers
           are applied after pagination by report.js. */
        window.reportStylesheets = [
            @json(asset('css/report.css').'?v='.filemtime(public_path('css/report.css'))),
        ];
        window.reportPageAlign = @json($align);
        window.reportPageMargins = @json($margins);
        /* When the owner is viewing (not a sample), report.js adds a hover
           "Edit this page" button to each rendered page, linking into edit mode
           anchored to that page's block. */
        window.reportEditUrl = @json($canEdit ? route('reports.output', ['report' => $report, 'edit' => 1]) : null);
        /* The roman numeral printed on the first front page. TU reports number
           their declaration page "i" (the cover is unnumbered and uncounted);
           every other format keeps the cover as page i and starts front matter
           at "ii". */
        window.reportRomanStart = @json($report->cover_format === 'tu' ? 1 : 2);
        /* Per-report margin + line-spacing rules — picked up by report.js which
           turns this string into a Blob URL and appends it to the stylesheet
           list passed to Paged.js, so it layers on top of report.css. */
        window.reportInlineCss = `
            @page {
                margin:
                    {{ number_format($margins['top'], 2) }}in
                    {{ number_format($margins['right'], 2) }}in
                    {{ number_format($margins['bottom'], 2) }}in
                    {{ number_format($margins['left'], 2) }}in;
            }
            .report-doc { line-height: {{ number_format($lineSpacing, 2) }}; }
            .report-content p { line-height: {{ number_format($lineSpacing, 2) }}; }
            .section-title { text-align: {{ $headingAlign }}; text-transform: {{ $headingTransform }}; }
            .frontmatter-heading { text-align: {{ $headingAlign }}; text-transform: {{ $headingTransform }}; }
            .toc-level-1 .toc-label { text-transform: {{ $headingTransform }}; }
        `;
    </script>

    <style>
        body { margin: 0; background: #ffffff; font-family: system-ui, sans-serif; }

        /* ---- Dark chrome (toolbar, page background) — the A4 sheets stay white
             because they are the printed document. Applied via the .dark class
             that the theme-head script sets on <html>. ---- */
        .dark body { background: #0b1220; }
        .dark .report-toolbar { background: #111827; border-bottom-color: #1f2937; }
        .dark .report-toolbar a { color: #a5b4fc; }
        .dark .report-toolbar .report-download { color: #a5b4fc; border-color: #4f46e5; }
        .dark .report-toolbar .report-download:hover { background: rgba(79,70,229,0.15); }
        .dark .report-title-label { color: #f3f4f6; }
        .dark .report-loading { color: #9ca3af; }
        /* The rendered document is the printed page — it must ALWAYS be white
           with dark text, even in dark mode. Force it so the chrome's dark
           theme can't bleed into the sheet. */
        .dark #report-render .pagedjs_page,
        .dark #report-render .pagedjs_sheet,
        .dark #report-render .pagedjs_page_content,
        .dark .edit-doc .report-cover,
        .dark .edit-doc .tu-frontpage {
            background: #ffffff !important;
        }
        .dark #report-render,
        .dark #report-render .report-doc,
        .dark .edit-doc .report-doc {
            color: #111111;
        }
        /* Keep everything white when printing, regardless of theme. */
        @media print {
            .dark body { background: #fff; }
        }

        /* Outline each rendered page so it reads as a sheet on the white background */
        #report-render .pagedjs_page { box-shadow: 0 0 0 1px #e5e7eb; }

        /* Hover "Edit this page" button on each rendered page (owner, screen only). */
        .page-edit-btn {
            position: absolute; top: 10px; right: 10px; z-index: 5;
            display: inline-flex; align-items: center; gap: 6px;
            padding: 5px 10px; border-radius: 7px;
            background: #4f46e5; color: #fff; text-decoration: none;
            font-family: system-ui, -apple-system, sans-serif; font-size: 12px; font-weight: 600;
            box-shadow: 0 2px 6px rgba(0,0,0,0.18);
            opacity: 0; transform: translateY(-3px); pointer-events: none;
            transition: opacity .15s ease, transform .15s ease;
        }
        .pagedjs_pagebox:hover .page-edit-btn,
        .pagedjs_page:hover .page-edit-btn { opacity: 1; transform: translateY(0); pointer-events: auto; }
        .page-edit-btn:hover { background: #6366f1; }
        @media print { .page-edit-btn { display: none !important; } }
        /* Center the sheets in the available width instead of hugging the left. */
        #report-render .pagedjs_pages { display: flex; flex-direction: column; align-items: center; }
        #report-render .pagedjs_page { margin-left: auto; margin-right: auto; }

        .report-toolbar {
            position: sticky; top: 0; z-index: 50;
            display: flex; align-items: center; justify-content: space-between;
            flex-wrap: wrap; gap: 8px 12px; padding: 10px 20px;
            background: #fff; border-bottom: 1px solid #d1d5db;
        }
        .report-toolbar a { color: #4f46e5; text-decoration: none; font-size: 14px; font-weight: 600; }
        .report-actions { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; }
        .report-toolbar button {
            background: #4f46e5; color: #fff; border: 0; border-radius: 6px;
            padding: 8px 16px; font-size: 14px; font-weight: 600; cursor: pointer;
        }
        .report-toolbar button:hover { background: #4338ca; }
        .report-toolbar .report-download {
            display: inline-flex; align-items: center;
            border: 1px solid #4f46e5; border-radius: 6px;
            padding: 7px 16px; font-size: 14px; font-weight: 600;
        }
        .report-toolbar .report-download:hover { background: #eef2ff; }
        .report-title-label { font-weight: 600; color: #111827; font-size: 14px; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

        /* ---- Floating download button (fixed, bottom-right) ---- */
        .report-fab {
            position: fixed; right: 24px; bottom: 24px; z-index: 60;
            display: inline-flex; align-items: center; gap: 8px;
            background: #4f46e5; color: #fff; border: 0; border-radius: 9999px;
            padding: 13px 22px; font-size: 14px; font-weight: 700; cursor: pointer;
            box-shadow: 0 8px 24px rgba(79, 70, 229, 0.35); transition: background .15s, transform .05s;
        }
        .report-fab:hover { background: #4338ca; }
        .report-fab:active { transform: translateY(1px); }
        .report-fab svg { width: 18px; height: 18px; }
        @media print { .report-fab { display: none !important; } }
        @media (max-width: 640px) {
            .report-fab { right: 14px; bottom: 14px; padding: 12px 18px; font-size: 13px; }
        }

        /* ---- Payment popup (opened from the floating button when locked) ---- */
        .pay-modal {
            display: none; position: fixed; inset: 0; z-index: 100;
            align-items: center; justify-content: center; padding: 20px;
            background: rgba(17, 24, 39, 0.6);
        }
        .pay-modal.is-open { display: flex; }
        .pay-modal-card {
            position: relative; width: 100%; max-width: 420px; background: #fff;
            border-radius: 16px; box-shadow: 0 20px 50px rgba(17, 24, 39, 0.25);
            padding: 32px 30px; text-align: center;
        }
        .pay-modal-close {
            position: absolute; top: 14px; right: 14px; border: 0; background: transparent;
            color: #9ca3af; cursor: pointer; padding: 4px; line-height: 0;
        }
        .pay-modal-close:hover { color: #374151; }
        .pay-modal-close svg { width: 20px; height: 20px; }

        /* ---- Paywall (banner above the free preview when payment is required) ---- */
        .paywall { display: flex; justify-content: center; padding: 56px 20px; }
        .paywall-banner { padding: 28px 20px 4px; }
        .paywall-card {
            width: 100%; max-width: 420px; background: #fff; border-radius: 16px;
            box-shadow: 0 10px 40px rgba(17, 24, 39, 0.08), 0 0 0 1px #e5e7eb;
            padding: 32px 30px; text-align: center;
        }
        .paywall-lock {
            width: 52px; height: 52px; margin: 0 auto 18px; border-radius: 9999px;
            display: flex; align-items: center; justify-content: center;
            background: #eef2ff; color: #4f46e5;
        }
        .paywall-lock svg { width: 26px; height: 26px; }
        .paywall-card h1 { margin: 0 0 4px; font-size: 19px; font-weight: 700; color: #111827; }
        .paywall-title { margin: 0; font-size: 14px; color: #6b7280; }
        .paywall-price { margin: 18px 0 4px; font-size: 30px; font-weight: 800; color: #111827; letter-spacing: -0.5px; }
        .paywall-sub { margin: 0 0 20px; font-size: 13px; color: #6b7280; }
        .paywall-methods { display: flex; flex-direction: column; gap: 10px; }
        .paywall-methods form { margin: 0; }
        .pay-method {
            display: flex; align-items: center; justify-content: space-between; width: 100%;
            border: 0; border-radius: 10px; padding: 13px 18px; cursor: pointer;
            font-size: 15px; font-weight: 600; color: #fff; transition: background .15s, transform .05s;
        }
        .pay-method:active { transform: translateY(1px); }
        .pay-method-go { font-weight: 700; opacity: .9; }
        .pay-method-esewa { background: #60bb46; }
        .pay-method-esewa:hover { background: #4ea537; }
        .pay-method-khalti { background: #5c2d91; }
        .pay-method-khalti:hover { background: #4a2475; }
        .paywall-note { margin: 18px 0 0; font-size: 12px; color: #9ca3af; }
        .paywall-warn { margin: 18px 0 0; font-size: 13px; color: #92400e; background: #fffbeb; border: 1px solid #fde68a; border-radius: 8px; padding: 12px 14px; }

        .report-flash { max-width: 210mm; margin: 12px auto -4px; padding: 10px 16px; border-radius: 6px; font-size: 14px; font-weight: 500; }
        .report-flash-success { background: #ecfdf5; color: #065f46; }
        .report-flash-error { background: #fef2f2; color: #b91c1c; }

        .report-loading { padding: 80px 20px; text-align: center; color: #6b7280; font-size: 14px; }
        body.is-paginated .report-loading { display: none; }

        /* Scaled to fit the viewport on small screens by report.js (zoom). */
        #report-render { padding: 24px 0; }

        @media (max-width: 640px) {
            .report-toolbar { padding: 8px 12px; gap: 6px; }
            .report-toolbar a, .report-toolbar button, .report-toolbar .report-download { font-size: 13px; }
            .report-toolbar button, .report-toolbar .report-download { padding: 6px 12px; }
            .report-actions { gap: 6px; }
            #report-render { padding: 12px 0; }
        }

        @media print {
            body { background: #fff; padding-top: 0; }
            .report-toolbar, .report-loading, .cover-edit-hint { display: none !important; }
            #report-render { padding: 0; zoom: 1 !important; }
        }

        /* ---- "You can edit the cover" hint banner (owner, screen only) ---- */
        .cover-edit-hint { background: #eef2ff; border-bottom: 1px solid #e0e7ff; }
        .cover-edit-hint-inner {
            display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
            max-width: 960px; margin: 0 auto; padding: 9px 16px;
            font-size: 13px; color: #3730a3;
        }
        .cover-edit-hint-icon { width: 16px; height: 16px; flex-shrink: 0; }
        .cover-edit-hint-inner span { flex: 1; min-width: 180px; }
        .cover-edit-hint-btn {
            flex-shrink: 0; background: #4f46e5; color: #fff !important; text-decoration: none;
            font-weight: 600; font-size: 12px; padding: 5px 12px; border-radius: 6px;
        }
        .cover-edit-hint-btn:hover { background: #6366f1; }
        .cover-edit-hint-close {
            flex-shrink: 0; background: transparent; border: 0; cursor: pointer;
            color: #6366f1; padding: 4px; line-height: 0;
        }
        .cover-edit-hint-close:hover { color: #3730a3; }
        .dark .cover-edit-hint { background: #1e1b4b; border-bottom-color: #312e81; }
        .dark .cover-edit-hint-inner { color: #c7d2fe; }

        /* ---- Edit pages mode ---- */
        .edit-toolbar {
            position: fixed; top: 0; left: 0; right: 0; z-index: 50;
            display: flex; align-items: center; justify-content: space-between;
            gap: 12px; padding: 10px 16px; flex-wrap: wrap;
            background: #fff; border-bottom: 1px solid #d1d5db;
        }
        .edit-toolbar a { color: #4f46e5; text-decoration: none; font-size: 14px; font-weight: 600; }
        .dark .edit-toolbar { background: #111827; border-bottom-color: #1f2937; }
        .dark .edit-toolbar a { color: #a5b4fc; }
        .dark .edit-toolbar .btn-reset { background: #111827; color: #f87171; border-color: #7f1d1d; }
        .edit-toolbar .edit-actions { display: flex; align-items: center; gap: 10px; }
        .edit-toolbar button { border-radius: 6px; padding: 8px 16px; font-size: 14px; font-weight: 600; cursor: pointer; border: 0; }
        .edit-toolbar .btn-save { background: #4f46e5; color: #fff; }
        .edit-toolbar .btn-save:hover { background: #4338ca; }
        .edit-toolbar .btn-reset { background: #fff; color: #b91c1c; border: 1px solid #fecaca; }
        .edit-toolbar .btn-reset:hover { background: #fef2f2; }

        .edit-hint { max-width: 210mm; margin: 8px auto -8px; padding: 0 8px; color: #6b7280; font-size: 13px; }
        .edit-flash { max-width: 210mm; margin: 8px auto; padding: 8px 14px; border-radius: 6px; background: #ecfdf5; color: #065f46; font-size: 14px; font-weight: 500; }

        .edit-doc { padding: 16px 12px 64px; }
        /* Each editable block looks like an A4 sheet. */
        .edit-doc .report-cover,
        .edit-doc .tu-frontpage {
            width: 210mm; min-height: 297mm; margin: 0 auto 24px; box-sizing: border-box;
            background: #fff; box-shadow: 0 0 0 1px #e5e7eb, 0 8px 24px rgba(0,0,0,.10);
        }
        /* Front pages get the page margins as padding (cover supplies its own). */
        .edit-doc .tu-frontpage { padding: 25.4mm 25.4mm 25.4mm 38.1mm; }
        .edit-doc .report-cover { height: auto; overflow: visible; }
        .edit-doc [contenteditable]:focus { outline: 2px solid #6366f1; outline-offset: 2px; }
        .edit-doc [contenteditable] { cursor: text; }

        .edit-sections { max-width: 210mm; margin: 8px auto 64px; }
        .edit-sections-intro { padding: 4px 4px 12px; }
        .edit-sections-intro h2 { margin: 0 0 4px; font-size: 15px; font-weight: 700; color: #111827; }
        .edit-sections-intro p { margin: 0; font-size: 13px; color: #6b7280; }
        .edit-section-card { margin-bottom: 16px; background: #fff; border-radius: 10px; box-shadow: 0 0 0 1px #e5e7eb; overflow: hidden; }
        .edit-section-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 10px 14px; border-bottom: 1px solid #f3f4f6; background: #fafafa; }
        .edit-section-head .ec-label { display: flex; align-items: center; gap: 8px; min-width: 0; font-size: 14px; font-weight: 600; color: #111827; }
        .ec-tag { flex: none; display: inline-flex; align-items: center; justify-content: center; min-width: 22px; height: 22px; padding: 0 6px; border-radius: 6px; font-size: 12px; font-weight: 700; }
        .ec-body { background: #eef2ff; color: #4338ca; }
        .ec-front { background: #fef3c7; color: #92400e; }
        .ec-edit { flex: none; color: #4f46e5; text-decoration: none; font-size: 13px; font-weight: 600; white-space: nowrap; }
        .ec-edit:hover { text-decoration: underline; }
        .edit-section-body { padding: 16px 20px; min-height: 80px; font-family: "Times New Roman", Times, serif; color: #111; }
        .edit-section-body:focus { outline: 2px solid #6366f1; outline-offset: -2px; }
        /* Brief highlight when arriving from a "Edit this page" button. */
        .edit-target-flash { animation: edit-target-flash 1.6s ease-out; }
        @keyframes edit-target-flash {
            0%, 100% { box-shadow: 0 0 0 0 rgba(99,102,241,0); }
            15% { box-shadow: 0 0 0 4px rgba(99,102,241,0.45); }
        }
        .edit-sections-empty { padding: 14px; font-size: 14px; color: #6b7280; }
    </style>
</head>
<body>
@if ($editing)
    {{-- ============ EDIT PAGES MODE ============ --}}
    <div class="edit-toolbar">
        <a href="{{ route('reports.output', ['report' => $report]) }}">&larr; Done editing</a>
        <div class="edit-actions">
            <form method="POST" action="{{ route('reports.front-overrides.reset', ['report' => $report]) }}" onsubmit="return confirm('Reset all pages back to the generated template? Your edits will be lost.');">
                @csrf
                <button type="submit" class="btn-reset">Reset all</button>
            </form>
            <button type="button" class="btn-save" onclick="saveEdits()">Save changes</button>
        </div>
    </div>

    @if (session('cover-saved'))
        <div class="edit-flash">{{ session('cover-saved') }}</div>
    @endif

    <p class="edit-hint">Click any page and type to edit it &mdash; press Enter for new lines/spacing. Click <strong>Save changes</strong> when done, or <strong>Reset all</strong> to restore the generated pages.</p>

    <div class="edit-doc report-doc">
        @include('reports.partials.output-cover', ['editing' => true])

        @if ($report->cover_format === 'tu')
            @include('reports.partials.tu-front-matter', ['editable' => true])
        @endif
    </div>

    {{-- Body & custom pages: edit the text inline here, or open a section in
         the full editor for headings, citations, images and tables. --}}
    @php($bodyNo = 0)
    <div class="edit-sections">
        <div class="edit-sections-intro">
            <h2>Written content</h2>
            <p>Edit your text directly below, then <strong>Save changes</strong>. For headings, citations, images or tables, use <strong>Open in editor</strong>. Numbering and citations are applied automatically in the final report.</p>
        </div>

        @forelse ($report->sections as $section)
            <div class="edit-section-card" id="edit-target-{{ $section->placement === 'front' ? 'front' : ($section->placement === 'back' ? 'back' : 'sec') }}-{{ $section->id }}">
                <div class="edit-section-head">
                    <span class="ec-label">
                        @if ($section->placement === 'front')
                            <span class="ec-tag ec-front">Front page</span>
                        @else
                            @php($bodyNo++)
                            <span class="ec-tag ec-body">{{ $bodyNo }}</span>
                        @endif
                        {{ $section->title }}
                    </span>
                    <a href="{{ route('reports.sections', ['report' => $report, 'section' => $section->id]) }}" class="ec-edit">Open in editor&nbsp;&#9998;</a>
                </div>
                <div class="report-content edit-section-body" contenteditable="true" spellcheck="false" data-section="{{ $section->id }}">
                    {!! \App\Support\SectionContent::toHtml($section->content) ?: '<p></p>' !!}
                </div>
            </div>
        @empty
            <div class="edit-section-card">
                <p class="edit-sections-empty">No sections yet. <a href="{{ route('reports.sections', ['report' => $report]) }}" class="ec-edit">Add content &#9998;</a></p>
            </div>
        @endforelse
    </div>

    <form id="save-form" method="POST" action="{{ route('reports.front-overrides.save', ['report' => $report]) }}" style="display:none">
        @csrf
        @foreach ($report->editableFrontBlocks() as $block)
            <textarea name="blocks[{{ $block }}]" data-input="{{ $block }}"></textarea>
        @endforeach
        @foreach ($report->sections as $section)
            <textarea name="sections[{{ $section->id }}]" data-section-input="{{ $section->id }}"></textarea>
        @endforeach
    </form>

    <script>
        // Figures, captions and tables are atomic — lock them so quick text
        // edits can't break the figure markup or its auto-numbered caption.
        // Use "Open in editor" to change a figure/table or its caption.
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.edit-section-body figure, .edit-section-body figcaption, .edit-section-body table, .edit-section-body img').forEach(function (el) {
                el.setAttribute('contenteditable', 'false');
            });

            // When opened from a "Edit this page" button (URL has #edit-target-…),
            // scroll to that block and briefly highlight it so it's obvious.
            if (location.hash && location.hash.indexOf('#edit-target') === 0) {
                var target = document.getElementById(location.hash.slice(1));
                if (target) {
                    setTimeout(function () {
                        target.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        target.classList.add('edit-target-flash');
                        setTimeout(function () { target.classList.remove('edit-target-flash'); }, 1600);
                        var editable = target.matches('[contenteditable]') ? target : target.querySelector('[contenteditable]');
                        if (editable) { editable.focus(); }
                    }, 120);
                }
            }
        });

        function saveEdits() {
            document.querySelectorAll('#save-form [data-input]').forEach(function (input) {
                var block = document.querySelector('[data-block="' + input.dataset.input + '"]');
                if (block) {
                    input.value = block.innerHTML;
                }
            });
            document.querySelectorAll('#save-form [data-section-input]').forEach(function (input) {
                var body = document.querySelector('[data-section="' + input.dataset.sectionInput + '"]');
                if (body) {
                    input.value = body.innerHTML;
                }
            });
            document.getElementById('save-form').submit();
        }
    </script>
@else
    {{-- ============ VIEW MODE ============ --}}
    {{-- Uniform header: back link (or title for samples) on the left, owner-only
         "Edit pages" on the right. The download lives in the floating button. --}}
    <div class="report-toolbar">
        @if ($sample)
            <span class="report-title-label">{{ $report->title ?: $report->module_title }}</span>
        @else
            <a href="{{ route('reports.sections', ['report' => $report]) }}">&larr; Back to editor</a>
        @endif
        <div class="report-actions">
            @if ($canEdit)
                <a href="{{ route('reports.output', ['report' => $report, 'edit' => 1]) }}" class="report-download">Edit pages</a>
            @endif
            @if ($sample)
                <a href="{{ route('register') }}" class="report-download" target="_top">Make your own &rarr;</a>
            @endif
        </div>
    </div>

    @if ($canEdit)
        {{-- Owner hint: the cover and front/back pages are editable in place.
             Dismissible, screen-only (hidden on print), remembered per browser. --}}
        <div class="cover-edit-hint"
             x-data="{ show: localStorage.getItem('coverHintDismissed') !== '1' }"
             x-show="show" x-cloak x-transition>
            <div class="cover-edit-hint-inner">
                <svg class="cover-edit-hint-icon" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zM19.5 7.125L16.875 4.5" /></svg>
                <span>Want to change the cover or any page? Click <strong>Edit pages</strong> to edit the text in place.</span>
                <a href="{{ route('reports.output', ['report' => $report, 'edit' => 1]) }}" class="cover-edit-hint-btn">Edit pages</a>
                <button type="button" class="cover-edit-hint-close" x-on:click="show = false; localStorage.setItem('coverHintDismissed', '1')" aria-label="Dismiss">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                </button>
            </div>
        </div>
    @endif

    @if (session('payment-success'))
        <div class="report-flash report-flash-success">{{ session('payment-success') }}</div>
    @endif
    @if (session('payment-error'))
        <div class="report-flash report-flash-error">{{ session('payment-error') }}</div>
    @endif

    {{-- The printed output is blocked when locked (Cmd/Ctrl+P yields a payment
         notice, not the report) so no free PDF can be produced. The on-screen
         preview stays visible for everyone. --}}
    @unless ($downloadUnlocked)
        @include('reports.partials.print-block')
    @endunless

    <div class="report-loading">Preparing your report&hellip;</div>

    <div id="report-render"></div>

    {{-- ============ Floating download button (fixed, right) ============ --}}
    <button type="button" class="report-fab" onclick="startDownload()">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
        <span>{{ ($downloadUnlocked || $sample) ? 'Download PDF' : 'Download — Rs. ' . number_format($downloadPrice, 0) }}</span>
    </button>

    {{-- ============ Payment popup (shown when locked & a gateway is enabled) ============ --}}
    @unless ($downloadUnlocked || $sample)
        <div id="pay-modal" class="pay-modal" role="dialog" aria-modal="true" aria-labelledby="pay-modal-title">
            <div class="pay-modal-card">
                <button type="button" class="pay-modal-close" onclick="closePayModal()" aria-label="Close">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                </button>
                <div class="paywall-lock">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" /></svg>
                </div>
                <h1 id="pay-modal-title">Download your report</h1>
                <p class="paywall-title">{{ $report->title ?: $report->module_title ?: 'Your report' }}</p>

                @if ($downloadPrice > 0)
                    <div class="paywall-price">Rs.&nbsp;{{ number_format($downloadPrice, 2) }}</div>
                    <p class="paywall-sub">Choose a payment method to unlock the PDF download.</p>

                    <div class="paywall-methods">
                        @foreach ($enabledGateways as $gateway)
                            <form method="POST" action="{{ route('reports.pay', ['report' => $report, 'gateway' => $gateway]) }}">
                                @csrf
                                <button type="submit" class="pay-method pay-method-{{ $gateway }}">
                                    <span class="pay-method-name">{{ $gateway === 'esewa' ? 'eSewa' : 'Khalti' }}</span>
                                    <span class="pay-method-go">Pay&nbsp;&rarr;</span>
                                </button>
                            </form>
                        @endforeach
                    </div>

                    <p class="paywall-note">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" width="13" height="13" style="vertical-align:-2px"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/></svg>
                        Secure payment — you'll return here to download once it's confirmed.
                    </p>
                @else
                    <p class="paywall-warn">Paid downloads are enabled, but no price has been set yet. Please set a download price in <strong>Admin&nbsp;→&nbsp;Payments</strong>.</p>
                @endif
            </div>
        </div>
    @endunless

    <template id="report-source">
        <div class="report-doc">
            {{-- Cover page (plain CSS so Paged.js needs no Tailwind) --}}
            @include('reports.partials.output-cover')

            @if ($report->cover_format === 'tu')
                {{-- Standard TU front matter: Declaration, Recommendation, Approval --}}
                @include('reports.partials.tu-front-matter')
            @else
                {{-- Title page --}}
                <section class="report-frontmatter page-break report-title-page">
                    <h1 class="doc-title">{{ $report->title ?: $report->module_title }}</h1>
                </section>
            @endif

            {{-- Custom front pages (before the contents, unnumbered) --}}
            @foreach ($compiler->frontMatter() as $page)
                <section class="report-frontmatter page-break">
                    @if (filled($page['title']))
                        <h2 class="frontmatter-heading">{{ $page['title'] }}</h2>
                    @endif
                    <div class="report-content">
                        @if (trim($page['html']) !== '')
                            {!! $page['html'] !!}
                        @else
                            <p>No content yet.</p>
                        @endif
                    </div>
                </section>
            @endforeach

            {{-- Table of Contents --}}
            <section class="report-frontmatter page-break">
                <h2 class="frontmatter-heading">Table of Contents</h2>
                <ul class="toc">
                    @forelse ($compiler->contents() as $entry)
                        <li class="toc-entry toc-level-{{ $entry['level'] }}">
                            <a href="#{{ $entry['id'] }}"><span class="toc-label">@if ($entry['marker'] !== ''){{ $entry['marker'] }}&nbsp; @endif{{ $entry['label'] }}</span></a>
                        </li>
                    @empty
                        <li class="toc-empty">No sections yet &mdash; add sections in the editor.</li>
                    @endforelse
                </ul>
            </section>

            {{-- Table of Tables --}}
            @if ($compiler->hasTables())
                <section class="report-frontmatter page-break">
                    <h2 class="frontmatter-heading">Table of Tables</h2>
                    <ul class="toc">
                        @foreach ($compiler->tables() as $table)
                            <li class="toc-entry">
                                <a href="#{{ $table['id'] }}"><span class="toc-label">{{ $table['label'] }}</span></a>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            {{-- Table of Figures --}}
            @if ($compiler->hasFigures())
                <section class="report-frontmatter page-break">
                    <h2 class="frontmatter-heading">Table of Figures</h2>
                    <ul class="toc">
                        @foreach ($compiler->figures() as $figure)
                            <li class="toc-entry">
                                <a href="#{{ $figure['id'] }}"><span class="toc-label">{{ $figure['label'] }}</span></a>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            {{-- Abstract --}}
            @if (filled($report->abstract))
                <section class="report-frontmatter page-break">
                    <h2 class="frontmatter-heading">Abstract</h2>
                    <div class="abstract-text">{{ $report->abstract }}</div>
                </section>
            @endif

            {{-- Body --}}
            @forelse ($compiler->sections() as $section)
                <section class="report-bodymatter report-section page-break">
                    <h1 class="section-title" id="{{ $section['id'] }}">{{ $section['marker'] }}&nbsp; {{ $section['title'] }}</h1>
                    <div class="report-content">
                        @if (trim($section['html']) !== '')
                            {!! $section['html'] !!}
                        @else
                            <p>No content yet.</p>
                        @endif
                    </div>
                </section>
            @empty
                <section class="report-bodymatter report-section page-break">
                    <p>No sections yet. Add sections in the editor to build the report.</p>
                </section>
            @endforelse

            {{-- Back matter (References, Appendix …): after the body, unnumbered. --}}
            @foreach ($compiler->backMatter() as $back)
                <section class="report-bodymatter report-section report-backmatter page-break">
                    <h1 class="section-title" id="{{ $back['id'] }}">{{ $back['title'] }}</h1>
                    <div class="report-content">
                        @if (trim($back['html']) !== '')
                            {!! $back['html'] !!}
                        @else
                            <p>No content yet.</p>
                        @endif
                    </div>
                </section>
            @endforeach
        </div>
    </template>

    <script>
        var downloadUnlocked = @json($downloadUnlocked || $sample);

        // Floating button entry point: download when unlocked, otherwise open
        // the payment popup (when a gateway is enabled).
        function startDownload() {
            if (downloadUnlocked) {
                downloadReport();
            } else {
                openPayModal();
            }
        }

        // Record the download (for the admin dashboard) and spend any one-shot
        // payment unlock right before opening the print dialog. Runs for both
        // free and paid downloads.
        function downloadReport() {
            @if ($sample)
                window.print();
            @else
                fetch(@json(route('reports.download.consume', ['report' => $report])), {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': @json(csrf_token()) },
                    keepalive: true,
                }).finally(function () { window.print(); });
            @endif
        }

        function openPayModal() {
            var m = document.getElementById('pay-modal');
            if (m) { m.classList.add('is-open'); document.body.style.overflow = 'hidden'; }
        }
        function closePayModal() {
            var m = document.getElementById('pay-modal');
            if (m) { m.classList.remove('is-open'); document.body.style.overflow = ''; }
        }
        // Close on backdrop click / Escape.
        document.addEventListener('click', function (e) {
            if (e.target && e.target.id === 'pay-modal') { closePayModal(); }
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') { closePayModal(); }
        });
    </script>
@endif
</body>
</html>
