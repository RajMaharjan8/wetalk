<?php

use App\Models\LandingFeature;

it('serves a sample detail page with custom slug, meta, description and related samples', function () {
    $main = LandingFeature::create([
        'section' => 'samples',
        'slug' => 'my-custom-slug',
        'title' => 'Main Sample',
        'description' => '<p>Detailed content for SEO.</p>',
        'meta_title' => 'Custom Sample Meta',
        'meta_description' => 'Sample meta description.',
        'meta_keywords' => 'sample, report',
        'visible' => true,
    ]);
    LandingFeature::create(['section' => 'samples', 'title' => 'Other A', 'visible' => true]);
    LandingFeature::create(['section' => 'samples', 'title' => 'Other B', 'visible' => true]);

    $this->get(route('sample-pages.show', 'my-custom-slug'))
        ->assertOk()
        ->assertSee('Custom Sample Meta', false)
        ->assertSee('Sample meta description.', false)
        ->assertSee('Detailed content for SEO.', false)
        ->assertSee('More sample reports', false)
        ->assertSee('Other A');
});

it('404s for a hidden sample page', function () {
    $s = LandingFeature::create(['section' => 'samples', 'title' => 'Hidden', 'visible' => false]);
    $this->get(route('sample-pages.show', $s->slug))->assertNotFound();
});
