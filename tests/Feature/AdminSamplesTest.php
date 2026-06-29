<?php

use App\Models\LandingFeature;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

it('lets an admin add a sample report', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    Livewire::actingAs($admin)->test('pages::admin.samples')
        ->set('newTitle', 'My Sample')
        ->set('newDescription', 'A description')
        ->set('newLink', '/samples/my-sample')
        ->call('add');

    expect(LandingFeature::where('section', 'samples')->where('title', 'My Sample')->exists())->toBeTrue();
});

it('lets an admin hide and delete a sample', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $card = LandingFeature::create(['section' => 'samples', 'title' => 'X', 'visible' => true]);

    Livewire::actingAs($admin)->test('pages::admin.samples')
        ->call('toggleVisible', $card->id);
    expect($card->fresh()->visible)->toBeFalse();

    Livewire::actingAs($admin)->test('pages::admin.samples')
        ->call('delete', $card->id);
    expect(LandingFeature::find($card->id))->toBeNull();
});

it('only manages samples, never other sections', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $feature = LandingFeature::create(['section' => 'features', 'title' => 'Not a sample', 'visible' => true]);

    expect(fn () => Livewire::actingAs($admin)->test('pages::admin.samples')->call('delete', $feature->id))
        ->toThrow(ModelNotFoundException::class);

    expect(LandingFeature::find($feature->id))->not->toBeNull();
});

it('lets an admin attach and remove a sample PDF', function () {
    Storage::fake('public');
    $admin = User::factory()->create(['is_admin' => true]);

    Livewire::actingAs($admin)->test('pages::admin.samples')
        ->set('newTitle', 'PDF Sample')
        ->set('newPdf', UploadedFile::fake()->create('report.pdf', 100, 'application/pdf'))
        ->call('add');

    $card = LandingFeature::where('title', 'PDF Sample')->first();
    expect($card->pdf_path)->not->toBeNull();
    Storage::disk('public')->assertExists($card->pdf_path);

    Livewire::actingAs($admin)->test('pages::admin.samples')->call('removePdf', $card->id);
    expect($card->fresh()->pdf_path)->toBeNull();
});
