<?php

use App\Models\LandingFeature;

it('renders the standalone samples page with visible sample cards', function () {
    LandingFeature::create([
        'section' => 'samples',
        'title' => 'Hospital Management System',
        'description' => 'Modules, use-cases and database design.',
        'link' => '/samples/hospital-management',
        'visible' => true,
    ]);

    $this->get(route('samples.index'))
        ->assertOk()
        ->assertSee('Hospital Management System')
        ->assertSee('View sample report');
});

it('does not show hidden sample cards', function () {
    LandingFeature::create([
        'section' => 'samples',
        'title' => 'Hidden Sample',
        'description' => 'Should not appear.',
        'visible' => false,
    ]);

    $this->get(route('samples.index'))
        ->assertOk()
        ->assertDontSee('Hidden Sample');
});

it('shows an empty state when there are no samples', function () {
    LandingFeature::where('section', 'samples')->delete();

    $this->get(route('samples.index'))
        ->assertOk()
        ->assertSee('No sample reports are available yet.');
});
