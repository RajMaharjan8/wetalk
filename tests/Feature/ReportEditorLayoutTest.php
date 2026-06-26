<?php

use App\Models\Report;
use App\Support\ReportCompiler;
use Livewire\Livewire;

function reportWithSections(): Report
{
    $report = Report::create([
        'user_id' => loginAsTestUser()->id,
        'cover_format' => 'london_met',
        'module_code' => 'MN7001NI',
        'module_title' => 'Operations Management',
        'title' => 'Sample Report',
        'student_name' => 'Raj',
        'london_id' => '25030253',
        'college_id' => 'np01',
    ]);

    $report->sections()->create(['placement' => 'body', 'order' => 0, 'title' => 'Introduction', 'content' => '<p>Intro body unique.</p>']);
    $report->sections()->create(['placement' => 'body', 'order' => 1, 'title' => 'Literature Review', 'content' => '<p>Litreview body unique.</p>']);

    return $report;
}

it('renders every section as a card, not just the active one', function () {
    $report = reportWithSections();

    Livewire::test('pages::report-sections', ['report' => $report])
        ->assertSee('Content')
        ->assertSee('Introduction')
        ->assertSee('Literature Review')
        ->assertSee('Edit chapters'); // mobile off-canvas trigger
});

it('server-renders the live preview pane from the compiled report', function () {
    $report = reportWithSections();

    // The first section is active (mirrored live via Alpine), but a non-active
    // section's compiled body must be present in the preview HTML.
    Livewire::test('pages::report-sections', ['report' => $report])
        ->assertSee('Litreview body unique.')
        ->assertSeeHtml('x-html="$store.preview.html"'); // active section bound live
});

it('hides a section from the compiled report while keeping its content', function () {
    $report = reportWithSections();
    $intro = $report->sections()->where('title', 'Introduction')->first();

    Livewire::test('pages::report-sections', ['report' => $report])
        ->call('toggleVisibility', $intro->id);

    expect($intro->fresh()->hidden)->toBeTrue()
        ->and($intro->fresh()->content)->toBe('<p>Intro body unique.</p>'); // content kept

    $compiler = ReportCompiler::for($report->load('sections'));
    $titles = collect($compiler->sections())->pluck('title');

    expect($titles)->not->toContain('Introduction')
        ->and($titles)->toContain('Literature Review');
});

it('renumbers visible sections when one is hidden', function () {
    $report = reportWithSections();
    $report->sections()->create(['placement' => 'body', 'order' => 2, 'title' => 'Conclusion', 'content' => '<p>End.</p>']);

    // Hide the first section; the remaining two should number 1 and 2.
    $report->sections()->where('title', 'Introduction')->first()->update(['hidden' => true]);

    $sections = ReportCompiler::for($report->load('sections'))->sections();

    expect($sections)->toHaveCount(2)
        ->and($sections[0]['title'])->toBe('Literature Review')
        ->and($sections[0]['number'])->toBe('1')
        ->and($sections[1]['number'])->toBe('2');
});

it('still lets the user select, add and delete sections', function () {
    $report = reportWithSections();
    $second = $report->sections()->where('title', 'Literature Review')->first();

    $component = Livewire::test('pages::report-sections', ['report' => $report])
        ->call('selectSection', $second->id);

    expect($component->get('activeSection')->id)->toBe($second->id);

    $component->set('newSectionTitle', 'Methodology')->call('addSection');
    expect($report->sections()->where('title', 'Methodology')->exists())->toBeTrue();

    $component->call('deleteSection', $second->id);
    expect($report->sections()->whereKey($second->id)->exists())->toBeFalse();
});

it('lets a user add a custom end page (e.g. Appendix 1)', function () {
    $report = reportWithSections();

    Livewire::test('pages::report-sections', ['report' => $report])
        ->set('newBackPageTitle', 'Appendix 1')
        ->call('addBackPage')
        ->assertHasNoErrors();

    $back = $report->sections()->where('placement', 'back')->pluck('title')->all();
    expect($back)->toContain('Appendix 1');
});
