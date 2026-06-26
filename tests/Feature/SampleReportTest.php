<?php

use App\Models\Report;
use Database\Seeders\SampleReportSeeder;

it('shows a public sample report to guests', function () {
    $report = Report::factory()->create([
        'is_sample' => true,
        'slug' => 'public-sample',
        'title' => 'Public Sample Report',
    ]);

    $this->get('/samples/'.$report->slug)
        ->assertOk()
        ->assertSee('Public Sample Report')
        ->assertSee('Make your own', escape: false);
});

it('does not expose non-sample reports via the samples route', function () {
    $report = Report::factory()->create([
        'is_sample' => false,
        'slug' => 'not-a-sample',
    ]);

    $this->get('/samples/'.$report->slug)->assertNotFound();
});

it('404s for an unknown sample slug', function () {
    $this->get('/samples/does-not-exist')->assertNotFound();
});

it('seeds References as back matter using the auto references-list placeholder', function () {
    (new SampleReportSeeder)->run();

    $report = Report::where('is_sample', true)->where('cover_format', 'tu')->firstOrFail();
    $references = $report->sections()->where('title', 'References')->firstOrFail();

    expect($references->placement)->toBe('back')
        ->and($references->content)->toContain('data-references-list');

    // It is NOT a numbered body chapter.
    expect($report->sections()->where('placement', 'body')->where('title', 'References')->exists())->toBeFalse();
});
