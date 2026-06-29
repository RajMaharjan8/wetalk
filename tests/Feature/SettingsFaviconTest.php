<?php

use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

it('lets an admin upload and remove a favicon', function () {
    Storage::fake('public');
    $admin = User::factory()->create(['is_admin' => true]);

    Livewire::actingAs($admin)->test('pages::admin.settings')
        ->set('favicon', UploadedFile::fake()->image('favicon.png', 32, 32));

    $path = Setting::get('site_favicon');
    expect($path)->not->toBeNull();
    Storage::disk('public')->assertExists($path);

    Livewire::actingAs($admin)->test('pages::admin.settings')->call('removeFavicon');
    expect(Setting::get('site_favicon'))->toBeNull();
    Storage::disk('public')->assertMissing($path);
});

it('renders the uploaded favicon in the page head', function () {
    Storage::fake('public');
    Setting::set('site_favicon', 'branding/test.png');

    $this->get('/')->assertSee('branding/test.png', false);
});
