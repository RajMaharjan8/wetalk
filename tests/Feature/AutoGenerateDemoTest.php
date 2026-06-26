<?php

use App\Models\CoverTemplate;

it('does not create a numbered References body chapter in the demo', function () {
    $user = loginAsTestUser();

    Livewire::test('pages::report-form')
        ->set('cover_format', 'london_met')
        ->call('autofill');

    $report = $user->reports()->first();

    // The demo adds body sections (Introduction, Discussion) …
    $bodyTitles = $report->sections()->where('placement', 'body')->pluck('title')->all();
    expect($bodyTitles)->not->toContain('References');

    // … and exactly one References page, as unnumbered back matter.
    $references = $report->sections()->where('title', 'References')->get();
    expect($references)->toHaveCount(1)
        ->and($references->first()->placement)->toBe('back')
        ->and($references->first()->content)->toContain('data-references-list');
});

it('creates a custom-cover report with demo sections from scratch via auto-generate', function () {
    $user = loginAsTestUser();
    $cover = CoverTemplate::create(['user_id' => $user->id, 'name' => 'Mine', 'html' => '<div>cover</div>']);

    Livewire::test('pages::report-form')
        ->set('cover_format', 'custom')
        ->set('custom_cover_id', $cover->id)
        ->call('autofill')
        ->assertHasNoErrors();

    $report = $user->reports()->first();

    expect($report)->not->toBeNull()
        ->and($report->cover_format)->toBe('custom')
        ->and($report->sections()->where('placement', 'body')->count())->toBe(2)
        ->and($report->sections()->where('placement', 'front')->where('title', 'Acknowledgement')->exists())->toBeTrue()
        ->and($report->references()->count())->toBe(1)
        ->and($report->sections()->where('placement', 'back')->where('title', 'References')->count())->toBe(1);
});

it('seeds demo sections for an existing custom-cover report', function () {
    $user = loginAsTestUser();

    $report = $user->reports()->create([
        'cover_format' => 'custom',
        'title' => 'My Custom Report',
        'reference_format' => 'london_met',
        'front_overrides' => ['cover' => '<div>custom cover</div>'],
    ]);

    Livewire::test('pages::report-form', ['report' => $report])
        ->call('autofill')
        ->assertHasNoErrors();

    $report->refresh();

    // Same content as London Met: 2 body sections, an Acknowledgement, a cited
    // reference, and exactly one back-matter References page.
    expect($report->sections()->where('placement', 'body')->count())->toBe(2)
        ->and($report->sections()->where('placement', 'front')->where('title', 'Acknowledgement')->exists())->toBeTrue()
        ->and($report->references()->count())->toBe(1)
        ->and($report->sections()->where('placement', 'back')->where('title', 'References')->count())->toBe(1);
});
