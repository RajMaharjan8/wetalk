<?php

use App\Models\Report;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Validate;
use Livewire\Component;

new class extends Component
{
    public ?Report $report = null;

    public string $cover_format = 'london_met';

    /** The saved cover the user picked when cover_format is "custom". */
    public ?int $custom_cover_id = null;

    public string $tu_college_name = '';

    public string $tu_institute = '';

    public string $tu_department = '';

    public string $tu_campus_address = '';

    public string $tu_report_type = 'Project Work Report';

    public string $tu_supervisor_name = '';

    public string $tu_degree = '';

    /** @var list<array{name: string, roll: string, batch: string}> */
    public array $tu_students = [];

    public string $tu_roll_number = '';

    public string $tu_submitted_to_position = '';

    #[Validate('nullable|string|max:50')]
    public string $module_code = '';

    #[Validate('nullable|string|max:255')]
    public string $module_title = '';

    #[Validate('nullable|string|max:255')]
    public string $title = '';

    #[Validate('nullable|string|max:5000')]
    public string $abstract = '';

    #[Validate('nullable|string|max:30')]
    public string $section_label = '';

    #[Validate('nullable|string|max:100')]
    public string $assessment_type = '';

    #[Validate('nullable|string|max:50')]
    public string $semester = '';

    #[Validate('nullable|string|max:20')]
    public string $academic_year = '';

    #[Validate('nullable|string|max:255')]
    public string $student_name = '';

    /** Honorific shown before the student's name on the recommendation page. */
    #[Validate('nullable|string|max:10')]
    public string $student_title = 'Mr.';

    /** @var list<string> */
    public const TITLES = ['Mr.', 'Miss', 'Mrs.'];

    #[Validate('nullable|string|max:50')]
    public string $london_id = '';

    #[Validate('nullable|string|max:255')]
    public string $college_id = '';

    #[Validate('nullable|date')]
    public string $assignment_due_date = '';

    #[Validate('nullable|date')]
    public string $submission_date = '';

    #[Validate('nullable|string|max:255')]
    public string $submitted_to = '';

    /** @return \Illuminate\Support\Collection<int, \App\Models\CoverTemplate> */
    public function getCoverTemplatesProperty()
    {
        return \App\Models\CoverTemplate::where('user_id', Auth::id())->latest()->get();
    }

    public function mount(?Report $report = null)
    {
        if ($report && $report->exists) {
            $this->authorize('update', $report);

            $this->report = $report;
            $this->cover_format = $report->cover_format ?: 'london_met';
            $this->tu_college_name = (string) $report->tu_college_name;
            $this->tu_institute = (string) $report->tu_institute;
            $this->tu_department = (string) $report->tu_department;
            $this->tu_campus_address = (string) $report->tu_campus_address;
            $this->tu_report_type = (string) ($report->tu_report_type ?: 'Project Work Report');
            $this->tu_supervisor_name = (string) $report->tu_supervisor_name;
            $this->tu_degree = (string) $report->tu_degree;
            $this->tu_students = is_array($report->tu_students) ? $report->tu_students : [];
            $this->tu_roll_number = (string) $report->tu_roll_number;
            $this->tu_submitted_to_position = (string) $report->tu_submitted_to_position;
            $this->module_code = (string) $report->module_code;
            $this->module_title = (string) $report->module_title;
            $this->title = (string) $report->title;
            $this->abstract = (string) $report->abstract;
            $this->section_label = (string) $report->section_label;
            $this->assessment_type = (string) $report->assessment_type;
            $this->semester = (string) $report->semester;
            $this->academic_year = (string) $report->academic_year;
            $this->student_name = (string) $report->student_name;
            $this->student_title = (string) ($report->student_title ?: 'Mr.');
            $this->london_id = (string) $report->london_id;
            $this->college_id = (string) $report->college_id;
            $this->assignment_due_date = $report->assignment_due_date?->format('Y-m-d') ?? '';
            $this->submission_date = $report->submission_date?->format('Y-m-d') ?? '';
            $this->submitted_to = (string) $report->submitted_to;
        } elseif (Auth::user()->hasReachedReportLimit()) {
            return $this->redirectToReportLimitNotice();
        }
    }

    /**
     * Send the student back to the dashboard with a friendly explanation
     * instead of a raw 403 when they have no free report slots left.
     */
    protected function redirectToReportLimitNotice()
    {
        session()->flash('report-limit', __('You already have :count of :max reports. Adding more students to a group project is fine and never counts against this — but to start a brand-new report, delete one of your existing reports first.', ['count' => Auth::user()->reports()->count(), 'max' => User::reportLimit()]));

        return $this->redirectRoute('reports.index', navigate: true);
    }

    /**
     * Human-friendly field names so validation messages read naturally
     * (e.g. "The campus name field is required." instead of "tu college name").
     *
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'tu_college_name' => __('campus name'),
            'tu_institute' => __('institute'),
            'tu_department' => __('department'),
            'tu_campus_address' => __('campus address'),
            'tu_report_type' => __('report type'),
            'tu_supervisor_name' => __('supervisor name'),
            'tu_degree' => __('degree'),
            'tu_roll_number' => __('roll number'),
            'student_name' => __('student name'),
            'title' => __('title'),
            'module_code' => __('module code'),
            'module_title' => __('module title'),
            'london_id' => __('London Met ID'),
            'college_id' => __('College ID'),
            'assessment_type' => __('assessment type'),
            'academic_year' => __('academic year'),
            'submission_date' => __('submission date'),
            'assignment_due_date' => __('assignment due date'),
            'custom_cover_id' => __('saved cover'),
        ];
    }

    /**
     * Every cover field, all optional — the baseline for drafts.
     *
     * @return array<string, string>
     */
    protected function draftRules(): array
    {
        return [
            'cover_format' => 'required|in:london_met,tu,custom',
            'custom_cover_id' => 'nullable|integer',
            'tu_college_name' => 'nullable|string|max:255',
            'tu_institute' => 'nullable|string|max:255',
            'tu_department' => 'nullable|string|max:255',
            'tu_campus_address' => 'nullable|string|max:255',
            'tu_report_type' => 'nullable|string|max:80',
            'tu_supervisor_name' => 'nullable|string|max:255',
            'tu_degree' => 'nullable|string|max:255',
            'tu_students' => 'nullable|array',
            'tu_students.*.title' => 'nullable|string|max:10',
            'tu_students.*.name' => 'nullable|string|max:255',
            'tu_students.*.roll' => 'nullable|string|max:50',
            'tu_students.*.batch' => 'nullable|string|max:50',
            'student_title' => 'nullable|string|max:10',
            'tu_roll_number' => 'nullable|string|max:50',
            'tu_submitted_to_position' => 'nullable|string|max:255',
            'module_code' => 'nullable|string|max:50',
            'module_title' => 'nullable|string|max:255',
            'title' => 'nullable|string|max:255',
            'abstract' => 'nullable|string|max:5000',
            'section_label' => 'nullable|string|max:30',
            'assessment_type' => 'nullable|string|max:100',
            'semester' => 'nullable|string|max:50',
            'academic_year' => 'nullable|string|max:20',
            'student_name' => 'nullable|string|max:255',
            'london_id' => 'nullable|string|max:50',
            'college_id' => 'nullable|string|max:255',
            'assignment_due_date' => 'nullable|date',
            'submission_date' => 'nullable|date',
            'submitted_to' => 'nullable|string|max:255',
        ];
    }

    /**
     * Validation rules for a finished cover, scoped to the chosen format.
     *
     * @return array<string, string>
     */
    protected function coverRules(): array
    {
        if ($this->cover_format === 'custom') {
            $required = ['title' => 'required|string|max:255'];

            // Must pick a saved cover when creating, or if none is applied yet.
            if (! $this->report || blank($this->report->frontOverride('cover'))) {
                $required['custom_cover_id'] = 'required|integer';
            }

            return array_merge($this->draftRules(), $required);
        }

        $required = $this->cover_format === 'tu'
            ? [
                'tu_college_name' => 'required|string|max:255',
                'title' => 'required|string|max:255',
                'student_name' => 'required|string|max:255',
                'tu_roll_number' => 'required|string|max:50',
            ]
            : [
                'module_code' => 'required|string|max:50',
                'module_title' => 'required|string|max:255',
                'title' => 'required|string|max:255',
                'student_name' => 'required|string|max:255',
                'london_id' => 'required|string|max:50',
                'college_id' => 'required|string|max:255',
            ];

        return array_merge($this->draftRules(), $required);
    }

    /**
     * Turn empty date strings into null so the date columns accept them.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function normalizeDates(array $data): array
    {
        foreach (['assignment_due_date', 'submission_date'] as $field) {
            if (($data[$field] ?? null) === '') {
                $data[$field] = null;
            }
        }

        return $data;
    }

    /**
     * The "Submitted by" group list, when used, hides the single student
     * fields. Mirror the first group member into the scalar properties the
     * TU cover rules require so validation passes for group projects.
     */
    protected function syncPrimaryStudentFromGroup(): void
    {
        if ($this->tu_students === []) {
            return;
        }

        $first = $this->tu_students[0];

        $this->student_name = trim((string) ($first['name'] ?? ''));
        $this->tu_roll_number = trim((string) ($first['roll'] ?? ''));
        $this->student_title = trim((string) ($first['title'] ?? 'Mr.')) ?: 'Mr.';
    }

    public function addTuStudent(): void
    {
        // Moving from the single-student fields into the group list: carry the
        // already-entered student in as the first row so they aren't lost.
        if ($this->tu_students === [] && (trim($this->student_name) !== '' || trim($this->tu_roll_number) !== '')) {
            $this->tu_students[] = [
                'title' => $this->student_title ?: 'Mr.',
                'name' => $this->student_name,
                'roll' => $this->tu_roll_number,
                'batch' => '',
            ];
        }

        $this->tu_students[] = ['title' => 'Mr.', 'name' => '', 'roll' => '', 'batch' => ''];
    }

    public function removeTuStudent(int $index): void
    {
        unset($this->tu_students[$index]);
        $this->tu_students = array_values($this->tu_students);
    }

    /**
     * Fill the cover fields for the chosen format with realistic example
     * values so a student can preview a finished cover quickly. Only blank
     * fields are touched, so anything already typed is preserved.
     */
    public function autofill()
    {
        $this->fillSampleFields();

        if ($this->report === null) {
            if (Auth::user()->hasReachedReportLimit()) {
                return $this->redirectToReportLimitNotice();
            }

            // Validate + create the report for ANY format (TU, London Met, or
            // custom). For custom this requires a cover to be selected — the
            // coverRules() handle that and surface a validation error if not.
            $this->syncPrimaryStudentFromGroup();
            $this->report = $this->persist($this->normalizeDates($this->validate($this->coverRules())));
        }

        // Once the report exists (any format — TU, London Met or custom), add
        // the demo sections + a cited reference.
        $this->seedDemoContent();

        session()->flash('demo-added', __('Demo report ready — example cover, an acknowledgement, and two sample sections with a cited reference were added. The cited source appears in your References page automatically.'));

        return null;
    }

    /**
     * Fill empty cover fields with realistic sample values for the chosen
     * format, leaving anything the user already typed untouched.
     */
    protected function fillSampleFields(): void
    {
        $samples = $this->cover_format === 'tu'
            ? [
                'tu_college_name' => 'Amrit Campus',
                'tu_institute' => 'Institute of Science and Technology',
                'tu_department' => 'Department of Computer Science & Information Technology',
                'tu_campus_address' => 'Thamel, Kathmandu',
                'tu_report_type' => 'Project Work Report',
                'tu_supervisor_name' => 'Mr. Akkal Bahadur Bist',
                'tu_degree' => 'Bachelor of Science in Computer Science and Information Technology (B.Sc. CSIT)',
                'title' => 'A Study of Renewable Energy Adoption in Urban Nepal',
                'student_name' => 'Sita Sharma',
                'tu_roll_number' => '700076',
                'semester' => 'VII Semester',
                'submission_date' => now()->format('Y-m-d'),
            ]
            : [
                'module_code' => 'MN7983NI',
                'module_title' => 'Management Learning and Research',
                'title' => "Amazon's Fulfilment Network",
                'assessment_type' => 'Individual Report',
                'semester' => 'Spring',
                'academic_year' => '2024/25',
                'student_name' => 'Sita Sharma',
                'london_id' => '25030253',
                'college_id' => 'np01mb7a250180@islingtoncollege.edu.np',
                'submitted_to' => 'Ichchhuk Poudel',
                'assignment_due_date' => now()->addWeeks(2)->format('Y-m-d'),
                'submission_date' => now()->format('Y-m-d'),
            ];

        foreach ($samples as $field => $value) {
            if (trim((string) $this->{$field}) === '') {
                $this->{$field} = $value;
            }
        }

        // For TU group projects, seed a first student row only when none exist.
        if ($this->cover_format === 'tu' && $this->tu_students === []) {
            $this->tu_students = [[
                'title' => $this->student_title ?: 'Mr.',
                'name' => $this->student_name,
                'roll' => $this->tu_roll_number,
                'batch' => '2079',
            ]];
        }
    }

    /**
     * Add a demo acknowledgement front page, two body sections that cite a
     * reference, and a dedicated References section (always last) holding the
     * auto-generated bibliography. Skips quietly if the report already has body
     * content so it never duplicates.
     */
    protected function seedDemoContent(): void
    {
        $report = $this->report;

        if ($report === null || $report->sections()->where('placement', 'body')->exists()) {
            return;
        }

        // Acknowledgement is front matter, shown before the body sections.
        $report->sections()->create([
            'placement' => 'front',
            'title' => 'Acknowledgement',
            'order' => ($report->sections()->where('placement', 'front')->max('order') ?? -1) + 1,
            'content' => '<p>I would like to express my sincere gratitude to my supervisor and the faculty '
                .'for their continuous guidance and support throughout this work. I am also thankful to my '
                .'family and friends for their encouragement during the preparation of this report.</p>',
        ]);

        $reference = $report->references()->create([
            'type' => 'journal',
            'data' => Arr::random([
                ['authors' => 'Anderson, K. and Mehta, R.', 'year' => '2021', 'title' => 'Drivers of clean energy adoption in emerging cities', 'journal' => 'Energy Policy Review', 'volume' => '18', 'issue' => '3', 'pages' => '221-238'],
                ['authors' => 'Thompson, L.', 'year' => '2020', 'title' => 'Operations strategy in global fulfilment networks', 'journal' => 'Journal of Operations Management', 'volume' => '42', 'issue' => '1', 'pages' => '55-74'],
                ['authors' => 'Gurung, S. and Patel, N.', 'year' => '2022', 'title' => 'A review of distributed systems reliability', 'journal' => 'Computing Frontiers', 'volume' => '9', 'issue' => '2', 'pages' => '110-129'],
            ]),
        ]);

        $citation = '<span class="ref-cite" data-ref-id="'.$reference->id.'" contenteditable="false">'
            .e($this->demoInlineCitation($report, $reference->data)).'</span>';

        $intro = Arr::random([
            'This report examines the topic in depth, drawing on recent academic work to frame the discussion.',
            'The following study sets out the background, scope, and aims of the work undertaken.',
            'This section introduces the problem and explains why it is relevant to the field today.',
        ]);

        $report->sections()->create([
            'placement' => 'body',
            'title' => 'Introduction',
            'order' => 0,
            'content' => '<p>'.$intro.' Prior research supports this position '.$citation.'.</p>'
                .'<p>The remainder of the report builds on this foundation with analysis and discussion.</p>',
        ]);

        $discussion = Arr::random([
            'The findings indicate a clear trend that aligns with the wider literature on the subject.',
            'Analysis of the data reveals several patterns worth examining in more detail.',
            'The results are discussed below, with attention to their practical implications.',
        ]);

        $report->sections()->create([
            'placement' => 'body',
            'title' => 'Discussion',
            'order' => 1,
            'content' => '<p>'.$discussion.' These observations are consistent with earlier work '.$citation.'.</p>'
                .'<p>Overall, the evidence points to a coherent and well-supported conclusion.</p>',
        ]);

        // No References section is created here — every report already has the
        // dedicated back-matter "References" page (auto-generated list). If a
        // demo runs before that exists, ensure it does.
        $this->seedBackMatter($report);
    }

    /**
     * Build the in-text citation label matching the report's reference format,
     * e.g. "(Anderson, 2021)" for Harvard/APA or "[1]" for IEEE. The compiler
     * re-renders this on output, so it only needs to read sensibly in the editor.
     *
     * @param  array<string, mixed>  $data
     */
    protected function demoInlineCitation(Report $report, array $data): string
    {
        if ($report->citationFormat() === 'ieee') {
            return '[1]';
        }

        $authors = (string) ($data['authors'] ?? '');
        $surname = trim((string) Str::of($authors)->before(',')->before(' and ')->before(' & '));

        return '('.($surname !== '' ? $surname : 'Author').', '.($data['year'] ?? 'n.d.').')';
    }

    /**
     * Create or update the report, then apply the chosen saved cover when the
     * format is "custom". Strips the non-column custom_cover_id first.
     *
     * @param  array<string, mixed>  $data
     */
    protected function persist(array $data): Report
    {
        $coverId = $data['custom_cover_id'] ?? null;
        unset($data['custom_cover_id']);

        // New TU reports follow the IEEE numbered citation style (the Nepali
        // university convention); London Met reports use the Harvard variant.
        // New TU reports also reserve the binding (left) margin and use TU's
        // "CHAPTER 1: INTRODUCTION" heading style (label + uppercase).
        if ($this->report === null) {
            $data['reference_format'] = $this->cover_format === 'tu' ? 'ieee' : 'london_met';

            if ($this->cover_format === 'tu') {
                $data['margin_left'] = Report::TU_BINDING_MARGIN_LEFT;
                $data['section_label'] = 'CHAPTER';
                $data['heading_uppercase'] = true;
            }
        }

        $isNew = $this->report === null;

        $report = $this->report
            ? tap($this->report)->update($data)
            : Auth::user()->reports()->create($data);

        $saved = $this->report ?? $report;

        if ($isNew) {
            // TU reports get the full numbered chapter scaffold (Introduction …
            // Conclusion). Every report type — TU, London Met, custom — gets the
            // References + Appendix back matter so the structure is complete.
            if ($this->cover_format === 'tu') {
                $this->seedTuChapters($saved);
            }

            $this->seedBackMatter($saved);
        }

        if ($this->cover_format === 'custom' && $coverId) {
            $template = \App\Models\CoverTemplate::where('user_id', Auth::id())->find($coverId);

            if ($template) {
                $overrides = $saved->front_overrides ?? [];
                $overrides['cover'] = '<div class="cover-sheet-custom cover-custom">'.$template->html.'</div>';
                $saved->update(['front_overrides' => $overrides]);
            }
        }

        return $saved;
    }

    /**
     * Create the default numbered chapter sections (placement "body" →
     * "CHAPTER 1: …") on a freshly-created TU report. Skipped if the report
     * already has body sections.
     */
    protected function seedTuChapters(Report $report): void
    {
        if ($report->sections()->where('placement', 'body')->exists()) {
            return;
        }

        $chapters = [
            ['Introduction', '<p>Introduce the project: its background, problem statement, objectives and scope.</p>'],
            ['Literature Review', '<p>Review existing systems and related work that informed this project.</p>'],
            ['System Analysis and Design', '<p>Describe the requirements, methodology and the system design (diagrams, database, architecture).</p>'],
            ['Implementation and Testing', '<p>Explain how the system was built and how each part was tested.</p>'],
            ['Conclusion and Future Work', '<p>Summarise the outcomes against the objectives and outline future enhancements.</p>'],
        ];

        $order = ($report->sections()->max('order') ?? -1) + 1;

        foreach ($chapters as [$title, $content]) {
            $report->sections()->create([
                'placement' => 'body',
                'title' => $title,
                'content' => $content,
                'order' => $order++,
            ]);
        }
    }

    /**
     * Create the References + Appendix back-matter pages (placement "back" →
     * unnumbered, after the body) on a freshly-created report of ANY format.
     * The user can delete either if they don't need it. Skipped if back matter
     * already exists.
     */
    protected function seedBackMatter(Report $report): void
    {
        // The References page carries the auto-generated references list
        // (renders the sources actually cited via [[key]]); the user doesn't
        // type entries by hand.
        $referencesList = '<div class="references-list-placeholder" data-references-list contenteditable="false">References list (auto-generated — shows the references you actually cite)</div>';

        $backMatter = [
            ['References', $referencesList],
            ['Appendix', '<p>Add any supporting material (code listings, diagrams, survey forms) here.</p>'],
        ];

        // Don't recreate a page the report already has under any placement
        // (e.g. a pre-existing "References" body section).
        $existing = $report->sections()->pluck('title')->map(fn ($t) => strtolower(trim((string) $t)))->all();

        $order = ($report->sections()->max('order') ?? -1) + 1;

        foreach ($backMatter as [$title, $content]) {
            if (in_array(strtolower($title), $existing, true)) {
                continue;
            }

            $report->sections()->create([
                'placement' => 'back',
                'title' => $title,
                'content' => $content,
                'order' => $order++,
            ]);
        }
    }

    public function save()
    {
        if ($this->report === null && Auth::user()->hasReachedReportLimit()) {
            return $this->redirectToReportLimitNotice();
        }

        $this->syncPrimaryStudentFromGroup();

        $saved = $this->persist($this->normalizeDates($this->validate($this->coverRules())));

        return $this->redirectRoute('reports.cover', ['report' => $saved], navigate: true);
    }

    /**
     * Persist whatever the student has entered so far, even if incomplete.
     */
    public function saveDraft()
    {
        if ($this->report === null && Auth::user()->hasReachedReportLimit()) {
            return $this->redirectToReportLimitNotice();
        }

        $this->syncPrimaryStudentFromGroup();

        $saved = $this->persist($this->normalizeDates($this->validate($this->draftRules())));

        session()->flash('draft-saved', __('Draft saved — you can safely close this page and finish later.'));

        return $this->redirectRoute('reports.edit', ['report' => $saved], navigate: true);
    }

    public function isEditing(): bool
    {
        return $this->report !== null;
    }
}; ?>

<div class="min-h-screen bg-gray-50 dark:bg-gray-900">
    <x-app-header />

    <x-validation-popup />

    <div class="mx-auto max-w-3xl py-12 px-4 sm:px-6 lg:px-8">
        <div class="mb-6">
            <a href="{{ route('reports.index') }}" wire:navigate class="text-sm font-medium text-indigo-600 hover:text-indigo-500">&larr; {{ __('All reports') }}</a>
        </div>

        <div class="mb-8 text-center">
            <h1 class="text-3xl font-semibold font-display text-gray-900 dark:text-gray-100">
                {{ $this->isEditing() ? __('Edit Cover Page') : __('Assignment Cover Page Generator') }}
            </h1>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">
                @if ($cover_format === 'tu')
                    Tribhuvan University
                @elseif ($cover_format === 'custom')
                    {{ __('Your custom cover design') }}
                @else
                    Islington College &middot; London Metropolitan University
                @endif
            </p>

            <div class="mt-4">
                <button type="button" wire:click="autofill" class="inline-flex items-center gap-1.5 rounded-md bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700 ring-1 ring-indigo-200 hover:bg-indigo-100">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 00-2.456 2.456z" /></svg>
                    <span wire:loading.remove wire:target="autofill">{{ __('Auto-generate demo report') }}</span>
                    <span wire:loading wire:target="autofill">{{ __('Generating…') }}</span>
                </button>
                <p class="mt-1 text-xs text-gray-400">{{ __('Fills the cover, then adds two sample sections and a cited reference so you can preview a full report. Your entries are kept.') }}</p>
            </div>
        </div>

        @if (session('draft-saved'))
            <div class="mb-6 rounded-md bg-green-50 px-4 py-3 text-sm font-medium text-green-800 ring-1 ring-green-200 dark:bg-green-500/10 dark:text-green-300 dark:ring-green-500/20">
                {{ session('draft-saved') }}
            </div>
        @endif

        @if (session('demo-added') && $this->report)
            <div class="mb-6 rounded-md bg-green-50 px-4 py-3 text-sm text-green-800 ring-1 ring-green-200 dark:bg-green-500/10 dark:text-green-300 dark:ring-green-500/20">
                <p class="font-medium">{{ session('demo-added') }}</p>
                <p class="mt-1">
                    <a href="{{ route('reports.sections', $report) }}" wire:navigate class="font-semibold underline hover:text-green-900">{{ __('Write content') }} &rarr;</a>
                    <span class="mx-1 text-green-400">&middot;</span>
                    <a href="{{ route('reports.output', $report) }}" class="font-semibold underline hover:text-green-900">{{ __('Preview full report') }} &rarr;</a>
                </p>
            </div>
        @endif

        <form wire:submit="save" class="space-y-8 rounded-lg bg-white p-6 shadow-sm ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-gray-700 sm:p-8">
            <section>
                <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('Cover format') }}</h2>

                <div class="mt-4">
                    <label for="cover_format" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Choose a cover page style') }}</label>
                    <select id="cover_format" wire:model.live="cover_format" class="mt-1 block w-full rounded-md px-3 py-2 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-gray-900 dark:text-gray-100 dark:ring-gray-600">
                        <option value="london_met">London Metropolitan University</option>
                        <option value="tu">Tribhuvan University (TU)</option>
                        @if ($this->coverTemplates->isNotEmpty())
                            <option value="custom">{{ __('My custom cover') }}</option>
                        @endif
                    </select>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('This decides which cover layout and fields are used.') }}</p>
                    @error('cover_format') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror

                    @if ($cover_format === 'custom')
                        <div class="mt-4">
                            <label for="custom_cover_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Choose your saved cover') }} <span class="text-red-500">*</span></label>
                            <select id="custom_cover_id" wire:model="custom_cover_id" class="mt-1 block w-full rounded-md px-3 py-2 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-gray-900 dark:text-gray-100 dark:ring-gray-600">
                                <option value="">&mdash; {{ __('Select a cover') }} &mdash;</option>
                                @foreach ($this->coverTemplates as $template)
                                    <option value="{{ $template->id }}">{{ $template->name }}</option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('Your designed cover is used as the cover page.') }} <a href="{{ route('cover.templates') }}" class="font-medium text-indigo-600 hover:text-indigo-500">{{ __('Design or edit covers') }} &rarr;</a></p>
                            @error('custom_cover_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    @else
                        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">{{ __('Want your own design?') }} <a href="{{ route('cover.templates') }}" class="font-medium text-indigo-600 hover:text-indigo-500">{{ __('Open the Cover Designer') }} &rarr;</a></p>
                    @endif
                </div>
            </section>

            <section>
                <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('Report') }}</h2>

                <div class="mt-4 grid grid-cols-1 gap-4">
                    <div>
                        <label for="title" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            {{ $cover_format === 'tu' ? __('Assignment title') : __('Report title') }} <span class="text-red-500">*</span>
                        </label>
                        <input type="text" id="title" wire:model="title" placeholder="{{ $cover_format === 'tu' ? __('e.g. Energy, Finance and Economics — Assignment No. 10') : __("e.g. Amazon's Fulfilment Network") }}" class="mt-1 block w-full rounded-md px-3 py-2 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-gray-900 dark:text-gray-100 dark:ring-gray-600">
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('Shown on the cover and title page, and used as the report heading.') }}</p>
                        @error('title') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    @if ($this->isEditing())
                        <div>
                            <label for="abstract" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Abstract') }} <span class="text-xs font-normal text-gray-400">{{ __('(optional)') }}</span></label>
                            <textarea id="abstract" wire:model="abstract" rows="5" placeholder="{{ __('A short summary of the report. Appears on its own page before the contents.') }}" class="mt-1 block w-full rounded-md px-3 py-2 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-gray-900 dark:text-gray-100 dark:ring-gray-600"></textarea>
                            @error('abstract') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="section_label" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Section heading word') }}</label>
                            <input type="text" id="section_label" wire:model="section_label" placeholder="{{ __('e.g. Chapter') }}" class="mt-1 block w-full rounded-md px-3 py-2 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-gray-900 dark:text-gray-100 dark:ring-gray-600">
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('Leave blank to number sections') }} <strong>1.</strong>, <strong>2.</strong> &hellip; {{ __('Enter a word like') }} <strong>Chapter</strong> {{ __('to get') }} <strong>Chapter 1</strong>, <strong>Chapter 2</strong>.</p>
                            @error('section_label') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    @else
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('You can add an abstract and customize section numbering later from the cover page.') }}</p>
                    @endif
                </div>
            </section>

            @if ($cover_format === 'tu')
            <section>
                <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('Institute & campus') }}</h2>

                <div class="mt-4 grid grid-cols-1 gap-4">
                    <div>
                        <label for="tu_institute" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Institute') }}</label>
                        <input type="text" id="tu_institute" wire:model="tu_institute" placeholder="{{ __('e.g. Institute of Science and Technology') }}" class="mt-1 block w-full rounded-md px-3 py-2 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-gray-900 dark:text-gray-100 dark:ring-gray-600">
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('Examples: Institute of Engineering, Institute of Science and Technology, Institute of Medicine.') }}</p>
                        @error('tu_institute') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="tu_college_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Campus name') }} <span class="text-red-500">*</span></label>
                        <input type="text" id="tu_college_name" wire:model="tu_college_name" placeholder="{{ __('e.g. Amrit Campus') }}" class="mt-1 block w-full rounded-md px-3 py-2 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-gray-900 dark:text-gray-100 dark:ring-gray-600">
                        @error('tu_college_name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="tu_department" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Department') }}</label>
                        <input type="text" id="tu_department" wire:model="tu_department" placeholder="{{ __('e.g. Department of Computer Science & Information Technology') }}" class="mt-1 block w-full rounded-md px-3 py-2 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-gray-900 dark:text-gray-100 dark:ring-gray-600">
                        @error('tu_department') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="tu_campus_address" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Campus address') }}</label>
                        <input type="text" id="tu_campus_address" wire:model="tu_campus_address" placeholder="{{ __('e.g. Thamel, Kathmandu') }}" class="mt-1 block w-full rounded-md px-3 py-2 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-gray-900 dark:text-gray-100 dark:ring-gray-600">
                        @error('tu_campus_address') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
            </section>

            <section>
                <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('Report & degree') }}</h2>

                <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label for="tu_report_type" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Report type') }}</label>
                        <input type="text" id="tu_report_type" wire:model="tu_report_type" placeholder="{{ __('e.g. Project Work Report') }}" class="mt-1 block w-full rounded-md px-3 py-2 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-gray-900 dark:text-gray-100 dark:ring-gray-600">
                        @error('tu_report_type') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="tu_supervisor_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Supervisor name') }}</label>
                        <input type="text" id="tu_supervisor_name" wire:model="tu_supervisor_name" placeholder="{{ __('e.g. Mr. Akkal Bahadur Bist') }}" class="mt-1 block w-full rounded-md px-3 py-2 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-gray-900 dark:text-gray-100 dark:ring-gray-600">
                        @error('tu_supervisor_name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="sm:col-span-2">
                        <label for="tu_degree" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Degree (long form)') }}</label>
                        <input type="text" id="tu_degree" wire:model="tu_degree" placeholder="{{ __('e.g. Bachelor of Science in Computer Science and Information Technology (B.Sc. CSIT)') }}" class="mt-1 block w-full rounded-md px-3 py-2 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-gray-900 dark:text-gray-100 dark:ring-gray-600">
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('Goes into "In partial fulfillment of the requirements for the …".') }}</p>
                        @error('tu_degree') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
            </section>

            <section>
                <div class="flex items-end justify-between">
                    <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('Submitted by') }}</h2>
                    <button type="button" wire:click="addTuStudent" class="text-xs font-semibold text-indigo-600 hover:text-indigo-500">+ {{ __('Add another student') }}</button>
                </div>

                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('Use this for group projects. The first student is also used as the main student name on the report.') }}</p>

                <div class="mt-4">
                    <label for="tu_semester" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Semester') }}</label>
                    <input type="text" id="tu_semester" wire:model="semester" placeholder="{{ __('e.g. VII Semester') }}" class="mt-1 block w-full rounded-md px-3 py-2 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-gray-900 dark:text-gray-100 dark:ring-gray-600">
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('Optional. Shown after the roll number on the declaration page (e.g. “VII Semester”).') }}</p>
                    @error('semester') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                @if ($tu_students === [])
                    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <label for="tu_student_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Name') }} <span class="text-red-500">*</span></label>
                            <div class="mt-1 flex gap-2">
                                <select wire:model="student_title" class="w-24 shrink-0 rounded-md px-2 py-2 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-gray-900 dark:text-gray-100 dark:ring-gray-600">
                                    @foreach (self::TITLES as $option)
                                        <option value="{{ $option }}">{{ $option }}</option>
                                    @endforeach
                                </select>
                                <input type="text" id="tu_student_name" wire:model="student_name" class="block w-full rounded-md px-3 py-2 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-gray-900 dark:text-gray-100 dark:ring-gray-600">
                            </div>
                            @error('student_name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="tu_roll_number" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Roll number') }} <span class="text-red-500">*</span></label>
                            <input type="text" id="tu_roll_number" wire:model="tu_roll_number" placeholder="{{ __('e.g. 700076') }}" class="mt-1 block w-full rounded-md px-3 py-2 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-gray-900 dark:text-gray-100 dark:ring-gray-600">
                            @error('tu_roll_number') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="tu_submission_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Date') }}</label>
                            <input type="date" id="tu_submission_date" wire:model="submission_date" class="mt-1 block w-full rounded-md px-3 py-2 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-gray-900 dark:text-gray-100 dark:ring-gray-600">
                            @error('submission_date') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>
                @else
                    <div class="mt-4 space-y-4">
                        @foreach ($tu_students as $index => $student)
                            <div wire:key="tu-student-{{ $index }}" class="grid grid-cols-1 gap-3 rounded-md ring-1 ring-gray-200 dark:ring-gray-700 p-3 sm:grid-cols-12">
                                <div class="sm:col-span-2">
                                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('Title') }}</label>
                                    <select wire:model="tu_students.{{ $index }}.title" class="mt-1 block w-full rounded-md px-2 py-1.5 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-gray-900 dark:text-gray-100 dark:ring-gray-600">
                                        @foreach (self::TITLES as $option)
                                            <option value="{{ $option }}">{{ $option }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="sm:col-span-4">
                                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('Name') }}</label>
                                    <input type="text" wire:model="tu_students.{{ $index }}.name" class="mt-1 block w-full rounded-md px-2 py-1.5 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-gray-900 dark:text-gray-100 dark:ring-gray-600">
                                </div>
                                <div class="sm:col-span-3">
                                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('Roll No.') }}</label>
                                    <input type="text" wire:model="tu_students.{{ $index }}.roll" placeholder="700076" class="mt-1 block w-full rounded-md px-2 py-1.5 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-gray-900 dark:text-gray-100 dark:ring-gray-600">
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('Batch') }}</label>
                                    <input type="text" wire:model="tu_students.{{ $index }}.batch" placeholder="2079" class="mt-1 block w-full rounded-md px-2 py-1.5 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-gray-900 dark:text-gray-100 dark:ring-gray-600">
                                </div>
                                <div class="sm:col-span-1 flex items-end">
                                    <button type="button" wire:click="removeTuStudent({{ $index }})" class="rounded-md px-2 py-1.5 text-xs font-semibold text-red-600 hover:bg-red-50" title="{{ __('Remove') }}">&times;</button>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    @if ($errors->has('student_name') || $errors->has('tu_roll_number'))
                        <p class="mt-2 text-xs text-red-600">{{ __('The first student needs a name and roll number — they appear as the main student on the report.') }}</p>
                    @endif

                    <div class="mt-3">
                        <label for="tu_submission_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Submission date') }}</label>
                        <input type="date" id="tu_submission_date" wire:model="submission_date" class="mt-1 block w-full rounded-md px-3 py-2 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-gray-900 dark:text-gray-100 dark:ring-gray-600">
                        @error('submission_date') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                @endif
            </section>
            @endif

            @if ($cover_format === 'london_met')
            <section>
                <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('Module') }}</h2>

                <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div>
                        <label for="module_code" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Module code') }} <span class="text-red-500">*</span></label>
                        <input type="text" id="module_code" wire:model="module_code" placeholder="e.g. MN7983NI" class="mt-1 block w-full rounded-md px-3 py-2 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-gray-900 dark:text-gray-100 dark:ring-gray-600">
                        @error('module_code') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="sm:col-span-2">
                        <label for="module_title" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Module title') }} <span class="text-red-500">*</span></label>
                        <input type="text" id="module_title" wire:model="module_title" placeholder="{{ __('e.g. Management Learning and Research') }}" class="mt-1 block w-full rounded-md px-3 py-2 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-gray-900 dark:text-gray-100 dark:ring-gray-600">
                        @error('module_title') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="assessment_type" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Assessment type') }}</label>
                        <input type="text" id="assessment_type" wire:model="assessment_type" placeholder="{{ __('e.g. Individual Report') }}" class="mt-1 block w-full rounded-md px-3 py-2 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-gray-900 dark:text-gray-100 dark:ring-gray-600">
                        @error('assessment_type') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="semester" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Semester') }}</label>
                        <select id="semester" wire:model="semester" class="mt-1 block w-full rounded-md px-3 py-2 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-gray-900 dark:text-gray-100 dark:ring-gray-600">
                            <option value="">&mdash; {{ __('Select') }} &mdash;</option>
                            <option value="Spring">{{ __('Spring') }}</option>
                            <option value="Autumn">{{ __('Autumn') }}</option>
                            <option value="Spring/Autumn">{{ __('Spring/Autumn') }}</option>
                        </select>
                        @error('semester') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="academic_year" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Academic year') }}</label>
                        <input type="text" id="academic_year" wire:model="academic_year" placeholder="e.g. 2024/25" class="mt-1 block w-full rounded-md px-3 py-2 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-gray-900 dark:text-gray-100 dark:ring-gray-600">
                        @error('academic_year') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
            </section>

            <section>
                <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('Student') }}</h2>

                <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label for="student_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Student name') }} <span class="text-red-500">*</span></label>
                        <input type="text" id="student_name" wire:model="student_name" class="mt-1 block w-full rounded-md px-3 py-2 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-gray-900 dark:text-gray-100 dark:ring-gray-600">
                        @error('student_name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="london_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">London Met ID <span class="text-red-500">*</span></label>
                        <input type="text" id="london_id" wire:model="london_id" placeholder="e.g. 25030253" class="mt-1 block w-full rounded-md px-3 py-2 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-gray-900 dark:text-gray-100 dark:ring-gray-600">
                        @error('london_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="college_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">College ID <span class="text-red-500">*</span></label>
                        <input type="text" id="college_id" wire:model="college_id" placeholder="e.g. np01mb7a250180@islingtoncollege.edu.np" class="mt-1 block w-full rounded-md px-3 py-2 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-gray-900 dark:text-gray-100 dark:ring-gray-600">
                        @error('college_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
            </section>

            <section>
                <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('Submission') }}</h2>

                <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label for="assignment_due_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Assignment due date') }}</label>
                        <input type="date" id="assignment_due_date" wire:model="assignment_due_date" class="mt-1 block w-full rounded-md px-3 py-2 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-gray-900 dark:text-gray-100 dark:ring-gray-600">
                        @error('assignment_due_date') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="submission_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Submission date') }}</label>
                        <input type="date" id="submission_date" wire:model="submission_date" class="mt-1 block w-full rounded-md px-3 py-2 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-gray-900 dark:text-gray-100 dark:ring-gray-600">
                        @error('submission_date') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="sm:col-span-2">
                        <label for="submitted_to" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Submitted to') }}</label>
                        <input type="text" id="submitted_to" wire:model="submitted_to" placeholder="e.g. Ichchhuk Poudel" class="mt-1 block w-full rounded-md px-3 py-2 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-gray-900 dark:text-gray-100 dark:ring-gray-600">
                        @error('submitted_to') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
            </section>
            @endif

            <div class="flex flex-wrap items-center justify-end gap-3 border-t border-gray-200 dark:border-gray-700 pt-6">
                @if ($this->isEditing())
                    <a href="{{ route('reports.cover', ['report' => $report]) }}" wire:navigate class="mr-auto text-sm font-medium text-gray-600 hover:text-gray-900 dark:text-gray-300">{{ __('Cancel') }}</a>
                @endif
                <button type="button" wire:click="saveDraft" class="inline-flex items-center rounded-md bg-white px-4 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-gray-300 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-100 dark:ring-gray-600 dark:hover:bg-gray-700">
                    <span wire:loading.remove wire:target="saveDraft">{{ __('Save draft') }}</span>
                    <span wire:loading wire:target="saveDraft">{{ __('Saving...') }}</span>
                </button>
                <button type="submit" class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                    <span wire:loading.remove wire:target="save">{{ $this->isEditing() ? __('Save changes') : __('Generate cover page') }}</span>
                    <span wire:loading wire:target="save">{{ $this->isEditing() ? __('Saving...') : __('Generating...') }}</span>
                </button>
            </div>
        </form>
    </div>
</div>
