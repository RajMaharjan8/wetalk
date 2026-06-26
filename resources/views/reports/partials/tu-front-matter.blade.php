@php
    /**
     * The three standard Tribhuvan University front pages — Student
     * Declaration, Supervisor's Recommendation Letter and Certificate of
     * Approval. Rendered automatically for every TU report, filled from the
     * cover data with bracketed placeholders where a field is still blank.
     */
    $students = is_array($report->tu_students)
        ? array_values(array_filter($report->tu_students, fn ($s) => is_array($s) && (filled($s['name'] ?? null) || filled($s['roll'] ?? null))))
        : [];

    if ($students === [] && filled($report->student_name)) {
        $students[] = [
            'title' => $report->student_title ?: 'Mr.',
            'name' => $report->student_name,
            'roll' => $report->tu_roll_number,
            'batch' => null,
        ];
    }

    $title = filled($report->title) ? $report->title : 'Project Work Title';
    $supervisor = filled($report->tu_supervisor_name) ? $report->tu_supervisor_name : 'Name of Supervisor';
    $institute = filled($report->tu_institute) ? $report->tu_institute : 'Institute of Science and Technology';
    $campus = filled($report->tu_college_name) ? trim(preg_replace('/\s+/', ' ', $report->tu_college_name)) : 'Amrit Campus';
    $department = filled($report->tu_department) ? $report->tu_department : 'Department of Computer Science & Information Technology';
    $degree = filled($report->tu_degree) ? $report->tu_degree : 'Bachelor of Science in Computer Science and Information Technology (B.Sc. CSIT)';
    $semester = trim((string) $report->semester);

    $studentNames = collect($students)->pluck('name')->filter()->implode(', ') ?: '[Student Name]';
    $studentRolls = collect($students)
        ->map(fn ($s) => trim(($s['roll'] ?? '').(filled($s['batch'] ?? null) ? ' / '.$s['batch'] : '')))
        ->filter()
        ->implode('; ') ?: '[TU Roll No./Batch]';

    $recommendationStudents = collect($students)->map(function ($s) {
        $title = filled($s['title'] ?? null) ? trim($s['title']) : 'Mr.';
        $name = filled($s['name'] ?? null) ? $s['name'] : 'Your Name';
        $roll = filled($s['roll'] ?? null) ? ' / Roll No.'.$s['roll'] : '';
        $batch = filled($s['batch'] ?? null) ? ' / Batch '.$s['batch'] : '';

        return '['.$title.' '.$name.$roll.$batch.']';
    })->implode(', ');

    if ($recommendationStudents === '') {
        $recommendationStudents = '[Mr. Your Name / Roll No.70....]';
    }

    // Build a student's "Roll No.: … / Batch … / … Semester" line, dropping
    // any segment that has no value so blank placeholders never show.
    $rollLine = function (array $student) use ($semester) {
        $line = 'Roll No.: '.(filled($student['roll'] ?? null) ? $student['roll'] : 'Type Your Roll No');

        if (filled($student['batch'] ?? null)) {
            $line .= ' / Batch '.$student['batch'];
        }

        if (filled($semester)) {
            $line .= ' / '.$semester;
        }

        return $line;
    };

    // When true (preview "Edit pages" mode) each page is made contenteditable.
    $editable = $editable ?? false;
@endphp

{{-- Page 1 — Student Declaration --}}
<section class="report-frontmatter page-break tu-frontpage" data-block="declaration" @if ($editable) id="edit-target-block-declaration" contenteditable="true" spellcheck="false" @endif>
    @if ($report->frontOverride('declaration'))
        {!! $report->frontOverride('declaration') !!}
    @else
        @include('reports.partials.tu-fm-header')

        <h2 class="tu-fm-heading">Student Declaration</h2>

        <p class="tu-fm-body">
            I hereby declare that this project work is my original work and no part of it has been copied
            from any source except those listed in the references.
        </p>

        <div class="tu-fm-sign">
            @forelse ($students as $student)
                <div class="tu-fm-sign-block">
                    <p class="tu-fm-dots">&hellip;&hellip;&hellip;&hellip;&hellip;&hellip;&hellip;&hellip;&hellip;&hellip;&hellip;&hellip;&hellip;&hellip;&hellip;&hellip;&hellip;&hellip;&hellip;&hellip;&hellip;</p>
                    <p>{{ filled($student['name'] ?? null) ? $student['name'] : 'Type Your Name Here' }}</p>
                    <p>{{ $rollLine($student) }}</p>
                    <p>Date: &hellip;&hellip;&hellip;&hellip;</p>
                </div>
            @empty
                <div class="tu-fm-sign-block">
                    <p class="tu-fm-dots">&hellip;&hellip;&hellip;&hellip;&hellip;&hellip;&hellip;&hellip;&hellip;&hellip;&hellip;&hellip;&hellip;&hellip;&hellip;&hellip;&hellip;&hellip;&hellip;&hellip;&hellip;</p>
                    <p>Type Your Name Here</p>
                    <p>{{ $rollLine([]) }}</p>
                    <p>Date: &hellip;&hellip;&hellip;&hellip;</p>
                </div>
            @endforelse
        </div>
    @endif
</section>

{{-- Page 2 — Supervisor's Recommendation Letter --}}
<section class="report-frontmatter page-break tu-frontpage" data-block="recommendation" @if ($editable) id="edit-target-block-recommendation" contenteditable="true" spellcheck="false" @endif>
    @if ($report->frontOverride('recommendation'))
        {!! $report->frontOverride('recommendation') !!}
    @else
        @include('reports.partials.tu-fm-header')

        <h2 class="tu-fm-heading">Supervisor's Recommendation Letter</h2>

        <p class="tu-fm-body">
            This is to recommend that the project work report entitled &ldquo;{{ $title }}&rdquo;, prepared by
            {{ $recommendationStudents }} under my supervision, has been completed satisfactorily and is hereby
            submitted for evaluation. &hellip;&hellip;&hellip;&hellip;&hellip;&hellip;&hellip;&hellip;&hellip;&hellip;&hellip;&hellip;&hellip;&hellip;&hellip;&hellip;&hellip;&hellip;
        </p>

        <div class="tu-fm-sign">
            <div class="tu-fm-sign-block">
                <p>Dr./Asst.Prof./Mr. &ldquo;{{ $supervisor }}&rdquo;</p>
                <p>Supervisor</p>
                <p>{{ $department }}</p>
                <p>{{ $campus }}, TU</p>
                <p>Date: &hellip;&hellip;&hellip;&hellip;</p>
            </div>
        </div>
    @endif
</section>

{{-- Page 3 — Certificate of Approval --}}
<section class="report-frontmatter page-break tu-frontpage" data-block="certificate" @if ($editable) id="edit-target-block-certificate" contenteditable="true" spellcheck="false" @endif>
    @if ($report->frontOverride('certificate'))
        {!! $report->frontOverride('certificate') !!}
    @else
        @include('reports.partials.tu-fm-header')

        <h2 class="tu-fm-heading">Certificate of Approval</h2>

        <p class="tu-fm-body">
            The undersigned certify that they have read and recommended to the {{ $institute }},
            Tribhuvan University, the Project Work Report entitled <strong>&ldquo;{{ $title }}&rdquo;</strong>, prepared by
            <strong>{{ $studentNames }}</strong> (TU Roll No./Batch: <strong>{{ $studentRolls }}</strong>), in partial
            fulfillment of the requirements for the degree of <strong>{{ $degree }}</strong>. This report represents the
            candidate&rsquo;s original work carried out under the guidance and supervision of
            <strong>{{ filled($report->tu_supervisor_name) ? $report->tu_supervisor_name : '[Supervisor Name]' }}</strong>.
        </p>

        <div class="tu-fm-grid">
            <div class="tu-fm-sign-block">
                <p class="tu-fm-dots">&hellip;&hellip;&hellip;&hellip;&hellip;&hellip;&hellip;&hellip;&hellip;&hellip;&hellip;&hellip;&hellip;&hellip;&hellip;</p>
                <p>{{ $supervisor }}</p>
                <p>Supervisor</p>
                <p>{{ $department }}</p>
                <p>{{ $campus }}</p>
            </div>
            <div class="tu-fm-sign-block">
                <p class="tu-fm-dots">&hellip;&hellip;&hellip;&hellip;&hellip;&hellip;&hellip;&hellip;&hellip;&hellip;&hellip;&hellip;&hellip;&hellip;&hellip;</p>
                <p>Committee Chairperson</p>
                <p>{{ $department }}</p>
                <p>{{ $campus }}</p>
            </div>
            <div class="tu-fm-sign-block">
                <p class="tu-fm-dots">&hellip;&hellip;&hellip;&hellip;&hellip;&hellip;&hellip;&hellip;&hellip;&hellip;&hellip;&hellip;&hellip;&hellip;&hellip;</p>
                <p>Internal Examiner</p>
                <p>{{ $institute }}</p>
                <p>Tribhuvan University</p>
            </div>
            <div class="tu-fm-sign-block">
                <p class="tu-fm-dots">&hellip;&hellip;&hellip;&hellip;&hellip;&hellip;&hellip;&hellip;&hellip;&hellip;&hellip;&hellip;&hellip;&hellip;&hellip;</p>
                <p>External Examiner</p>
                <p>{{ $institute }}</p>
                <p>Tribhuvan University</p>
            </div>
        </div>
    @endif
</section>

{{-- Page 4 — Copyright --}}
<section class="report-frontmatter page-break tu-frontpage" data-block="copyright" @if ($editable) id="edit-target-block-copyright" contenteditable="true" spellcheck="false" @endif>
    @if ($report->frontOverride('copyright'))
        {!! $report->frontOverride('copyright') !!}
    @else
        @include('reports.partials.tu-fm-header')

        <h2 class="tu-fm-heading">Copyright</h2>

        <p class="tu-fm-body">
            The author has agreed that the library, {{ $department }}, {{ $campus }}, Tribhuvan University,
            may make this report freely available for inspection. Moreover, the author has agreed that permission
            for extensive copying of this project work report for scholarly purposes may be granted by the
            supervisor who supervised the work recorded herein or, in their absence, by the Head of the
            {{ $department }}. It is understood that due recognition will be given to the author of this report
            and to the {{ $department }}, {{ $campus }}, Tribhuvan University, in any use of the material of
            this report. Copying, publication or other use of this report for financial gain without the approval
            of the {{ $department }} and the author&rsquo;s written permission is prohibited.
        </p>

        <div class="tu-fm-sign">
            <div class="tu-fm-sign-block">
                <p class="tu-fm-dots">&hellip;&hellip;&hellip;&hellip;&hellip;&hellip;&hellip;&hellip;&hellip;&hellip;&hellip;&hellip;&hellip;&hellip;&hellip;</p>
                <p>{{ $studentNames }}</p>
                <p>{{ $department }}</p>
                <p>{{ $campus }}, Tribhuvan University</p>
            </div>
        </div>
    @endif
</section>

{{-- Page 5 — Abstract --}}
<section class="report-frontmatter page-break tu-frontpage" data-block="abstract" @if ($editable) id="edit-target-block-abstract" contenteditable="true" spellcheck="false" @endif>
    @if ($report->frontOverride('abstract'))
        {!! $report->frontOverride('abstract') !!}
    @else
        @include('reports.partials.tu-fm-header')

        <h2 class="tu-fm-heading">Abstract</h2>

        <p class="tu-fm-body">
            This project work report presents the design and implementation of &ldquo;{{ $title }}&rdquo;. It describes the
            problem addressed, the methodology followed, and the key results obtained. The system was developed using
            established software-engineering practices and tested against its requirements. The report summarises the
            objectives, scope, tools used, and the outcomes achieved, and concludes with recommendations for future
            enhancement. <em>(Replace this with your own abstract of no more than 150 words.)</em>
        </p>
    @endif
</section>
