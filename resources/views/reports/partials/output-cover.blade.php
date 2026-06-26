@php($editing = $editing ?? false)

{{-- The report cover for the paginated output and the preview edit mode.
     Renders the user's saved override when present, otherwise the generated
     cover for the report's format. --}}
<div class="report-cover" data-block="cover" @if ($editing) id="edit-target-cover" contenteditable="true" spellcheck="false" @endif>
    @if ($report->frontOverride('cover'))
        {!! $report->frontOverride('cover') !!}
    @elseif ($report->cover_format === 'tu')
        @include('reports.partials.tu-cover-sheet')
    @else
        <div class="cover-sheet-plain">
            <div class="cover-logo-row">
                <img src="{{ asset('images/london-met-logo.png') }}" alt="London Metropolitan University">
                {{-- Machine-readable institution names: present in the PDF text
                     layer (so the format checker can verify them) but visually
                     unobtrusive — the logos carry the visual branding. --}}
                <span class="cover-institution-name">London Metropolitan University</span>
            </div>

            <div class="cover-logo-college">
                <img src="{{ asset('images/islington-logo.png') }}" alt="Islington College">
                <span class="cover-institution-name">Islington College</span>
            </div>

            <div class="cover-block">
                <p>Module Code &amp; Module Title</p>
                <p>{{ trim($report->module_code.' '.$report->module_title) }}</p>
            </div>

            <div class="cover-block">
                <p>{{ $report->assessment_type ?: 'Assessment Type' }}</p>
                <p>Semester</p>
                <p>{{ trim(($report->academic_year ?? '').' '.($report->semester ?? '')) ?: 'Semester' }}</p>
            </div>

            <div class="cover-block">
                <p>Student Name: {{ $report->student_name }}</p>
                <p>London Met ID: {{ $report->london_id }}</p>
                <p>College ID: {{ $report->college_id }}</p>
                @if ($report->assignment_due_date)
                    <p>Assignment Due Date: {{ $report->assignment_due_date->format('l, F j, Y') }}</p>
                @endif
                @if ($report->submission_date)
                    <p>Assignment Submission Date: {{ $report->submission_date->format('l, F j, Y') }}</p>
                @endif
                @if ($report->submitted_to)
                    <p>Submitted To: {{ $report->submitted_to }}</p>
                @endif
            </div>

            <div class="cover-confirm">
                <p>
                    I confirm that I understand my coursework needs to be submitted online via Google Classroom under
                    the relevant module page before the deadline in order for my assignment to be accepted and marked.
                    I am fully aware that late submissions will be treated as non-submission and a mark of zero will be
                    awarded.
                </p>
            </div>
        </div>
    @endif
</div>
