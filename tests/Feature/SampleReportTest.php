<?php

use App\Models\Report;

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
