<?php

use App\Models\Reference;
use App\Models\Report;
use App\Models\Section;

it('lists only sample reports via the API', function () {
    $sample = Report::factory()->create([
        'is_sample' => true,
        'slug' => 'api-sample',
        'title' => 'API Sample Report',
    ]);

    Report::factory()->create([
        'is_sample' => false,
        'slug' => 'private-report',
        'title' => 'Private Report',
    ]);

    $this->getJson('/api/v1/samples')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.slug', 'api-sample')
        ->assertJsonPath('data.0.title', 'API Sample Report')
        ->assertJsonStructure(['data' => [['slug', 'title', 'url', 'api_url']]]);
});

it('returns a single sample report with sections and references', function () {
    $report = Report::factory()->create([
        'is_sample' => true,
        'slug' => 'detailed-sample',
        'title' => 'Detailed Sample',
        'reference_format' => 'ieee',
    ]);

    Section::factory()->for($report)->create([
        'placement' => 'body',
        'order' => 1,
        'title' => 'Introduction',
        'content' => '<p>Hello world</p>',
        'hidden' => false,
    ]);

    Section::factory()->for($report)->create([
        'placement' => 'body',
        'order' => 2,
        'title' => 'Hidden chapter',
        'content' => '<p>secret</p>',
        'hidden' => true,
    ]);

    Reference::factory()->for($report)->create();

    $this->getJson('/api/v1/samples/detailed-sample')
        ->assertOk()
        ->assertJsonPath('data.slug', 'detailed-sample')
        ->assertJsonPath('data.format', 'ieee')
        ->assertJsonCount(1, 'data.sections')
        ->assertJsonPath('data.sections.0.title', 'Introduction')
        ->assertJsonCount(1, 'data.references')
        ->assertJsonPath('data.references.0.type', 'journal')
        ->assertJsonStructure(['data' => ['references' => [['type', 'data', 'citation']]]]);
});

it('404s a non-sample report via the API', function () {
    Report::factory()->create(['is_sample' => false, 'slug' => 'not-sample']);

    $this->getJson('/api/v1/samples/not-sample')->assertNotFound();
});

it('404s an unknown sample slug via the API', function () {
    $this->getJson('/api/v1/samples/missing')->assertNotFound();
});

it('sends CORS headers for an allowed origin', function () {
    Report::factory()->create(['is_sample' => true, 'slug' => 'cors-sample']);

    $response = $this->withHeaders(['Origin' => 'https://rastriyaaawaj.com'])
        ->getJson('/api/v1/samples');

    $response->assertOk()
        ->assertHeader('Access-Control-Allow-Origin', 'https://rastriyaaawaj.com');
});

it('does not allow a disallowed origin', function () {
    Report::factory()->create(['is_sample' => true, 'slug' => 'cors-blocked']);

    $response = $this->withHeaders(['Origin' => 'https://evil.example'])
        ->getJson('/api/v1/samples');

    // Allowed-origin header must not echo the untrusted origin.
    expect($response->headers->get('Access-Control-Allow-Origin'))
        ->not->toBe('https://evil.example');
});
