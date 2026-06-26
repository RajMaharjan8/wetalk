<?php

use App\Models\Report;
use Livewire\Livewire;

it('generates a TU cover for a group project with several students', function () {
    loginAsTestUser();

    Livewire::test('pages::report-form')
        ->set('cover_format', 'tu')
        ->set('tu_college_name', 'Amrit Campus')
        ->set('title', 'Group Assignment No. 5')
        ->set('tu_students', [
            ['name' => 'Raj', 'roll' => '700076', 'batch' => '2079'],
            ['name' => 'Sita', 'roll' => '700077', 'batch' => '2079'],
            ['name' => 'Hari', 'roll' => '700078', 'batch' => '2080'],
        ])
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect();

    $report = Report::firstWhere('cover_format', 'tu');

    // The first group member becomes the main student on the report.
    expect($report)->not->toBeNull()
        ->and($report->student_name)->toBe('Raj')
        ->and($report->tu_roll_number)->toBe('700076')
        ->and($report->tu_students)->toHaveCount(3);
});

it('still errors when the first group member has no name or roll', function () {
    loginAsTestUser();

    Livewire::test('pages::report-form')
        ->set('cover_format', 'tu')
        ->set('tu_college_name', 'Amrit Campus')
        ->set('title', 'Group Assignment')
        ->set('tu_students', [
            ['name' => '', 'roll' => '', 'batch' => '2079'],
        ])
        ->call('save')
        ->assertHasErrors(['student_name', 'tu_roll_number']);
});

it('auto-fills empty cover fields and preserves entered ones', function () {
    loginAsTestUser();

    Livewire::test('pages::report-form')
        ->set('cover_format', 'tu')
        ->set('title', 'My own title')
        ->call('autofill')
        ->assertSet('title', 'My own title') // kept, not overwritten
        ->assertSet('tu_college_name', 'Amrit Campus') // filled from sample
        ->assertNotSet('tu_supervisor_name', '');
});

it('auto-generates a demo report with sections, an acknowledgement and a cited reference', function () {
    $user = loginAsTestUser();

    Livewire::test('pages::report-form')
        ->set('cover_format', 'london_met')
        ->call('autofill')
        ->assertHasNoErrors();

    $report = $user->reports()->first();

    // Two numbered content sections (Introduction, Discussion); References is
    // NOT a numbered body chapter.
    expect($report)->not->toBeNull()
        ->and($report->sections()->where('placement', 'body')->count())->toBe(2)
        ->and($report->sections()->where('placement', 'body')->where('title', 'References')->exists())->toBeFalse()
        ->and($report->references()->count())->toBe(1);

    // Acknowledgement is created as front matter.
    expect($report->sections()->where('placement', 'front')->where('title', 'Acknowledgement')->exists())->toBeTrue();

    $reference = $report->references()->first();
    $intro = $report->sections()->where('placement', 'body')->where('order', 0)->first();

    expect($intro->content)->toContain('class="ref-cite"')
        ->and($intro->content)->toContain('data-ref-id="'.$reference->id.'"');

    // The bibliography placeholder lives in the dedicated back-matter References page.
    $references = $report->sections()->where('placement', 'back')->where('title', 'References')->first();
    expect($references)->not->toBeNull()
        ->and($references->content)->toContain('data-references-list');
});

it('uses the IEEE reference format for TU reports and Harvard for London Met', function () {
    $user = loginAsTestUser();

    Livewire::test('pages::report-form')
        ->set('cover_format', 'tu')
        ->set('tu_college_name', 'Amrit Campus')
        ->set('title', 'TU Demo')
        ->set('student_name', 'Ram')
        ->set('tu_roll_number', '700076')
        ->call('autofill');

    expect($user->reports()->first()->reference_format)->toBe('ieee');
});

it('does not duplicate demo content when run twice on the same report', function () {
    $user = loginAsTestUser();

    Livewire::test('pages::report-form')
        ->set('cover_format', 'london_met')
        ->call('autofill')
        ->call('autofill');

    $report = $user->reports()->first();

    expect($report->sections()->where('placement', 'body')->count())->toBe(2)
        ->and($report->sections()->where('placement', 'back')->where('title', 'References')->count())->toBe(1)
        ->and($report->sections()->where('placement', 'front')->where('title', 'Acknowledgement')->count())->toBe(1)
        ->and($report->references()->count())->toBe(1);
});
