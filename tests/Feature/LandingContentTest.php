<?php

use App\Models\LandingFeature;
use App\Models\Report;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

it('renders landing sections from the database (seeded defaults)', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('One-click PDF')                                 // a seeded feature
        ->assertSee('Tribhuvan University')                          // a seeded format
        ->assertSee('Sample Report for e-commerce website for bca/csit'); // a seeded sample
});

it('serves the landing page at /landing for signed-in users (logo target)', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/landing')
        ->assertOk()
        ->assertSee('One-click PDF'); // a seeded landing feature — the real page, no redirect
});

it('reflects an admin-added feature on the landing page', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    Livewire::actingAs($admin)->test('pages::admin.landing')
        ->set('newSection', 'features')
        ->set('newTitle', 'Brand new feature card')
        ->set('newDescription', 'Added from the admin dashboard.')
        ->call('addCard')
        ->assertHasNoErrors();

    expect(LandingFeature::where('title', 'Brand new feature card')->exists())->toBeTrue();

    auth()->logout(); // the landing is guest-only
    $this->get('/')->assertSee('Brand new feature card');
});

it('stores an uploaded icon image and shows it on the landing page', function () {
    Storage::fake('public');
    $admin = User::factory()->create(['is_admin' => true]);

    Livewire::actingAs($admin)->test('pages::admin.landing')
        ->set('newSection', 'samples')
        ->set('newTitle', 'Image sample card')
        ->set('newIcon', UploadedFile::fake()->image('banner.jpg', 400, 150))
        ->call('addCard')
        ->assertHasNoErrors();

    $card = LandingFeature::where('title', 'Image sample card')->firstOrFail();
    expect($card->icon_path)->not->toBeNull();
    Storage::disk('public')->assertExists($card->icon_path);

    auth()->logout(); // the landing is guest-only
    $this->get('/')->assertSee($card->iconUrl(), false); // <img src> rendered
});

it('hides a card from the landing page when toggled invisible', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $card = LandingFeature::create(['section' => 'features', 'title' => 'Hide me card', 'order' => 99]);

    Livewire::actingAs($admin)->test('pages::admin.landing')->call('toggleVisible', $card->id);
    expect($card->fresh()->visible)->toBeFalse();

    auth()->logout();
    $this->get('/')->assertDontSee('Hide me card');
});

it('saves dynamic landing content and renders it on the page', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    Livewire::actingAs($admin)->test('pages::admin.landing')
        ->set('content.landing_hero_title', 'My Custom Headline')
        ->set('content.landing_footer_copyright', '© :year Custom Co')
        ->call('saveContent')
        ->assertHasNoErrors();

    auth()->logout();
    $this->get('/')
        ->assertSee('My Custom Headline')
        ->assertSee('© '.now()->year.' Custom Co');
});

it('renders the How it works steps from the database', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    Livewire::actingAs($admin)->test('pages::admin.landing')
        ->set('newSection', 'steps')
        ->set('newTitle', 'My custom step')
        ->set('newDescription', 'Do this first.')
        ->call('addCard')
        ->assertHasNoErrors();

    auth()->logout();
    $this->get('/')
        ->assertSee('My custom step')
        ->assertSee('Do this first.');
});

it('saves dynamic How it works and FAQ headings', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    Livewire::actingAs($admin)->test('pages::admin.landing')
        ->set('content.landing_steps_heading', 'My steps heading')
        ->set('content.landing_faqs_heading', 'My FAQ heading')
        ->call('saveContent')
        ->assertHasNoErrors();

    auth()->logout();
    $this->get('/')
        ->assertSee('My steps heading')
        ->assertSee('My FAQ heading');
});

it('saves dynamic hero trust badges and hides blank ones', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    Livewire::actingAs($admin)->test('pages::admin.landing')
        ->set('content.landing_hero_badge_1', 'Custom badge one')
        ->set('content.landing_hero_badge_2', '') // blank — should be hidden
        ->set('content.landing_hero_badge_3', 'Custom badge three')
        ->call('saveContent')
        ->assertHasNoErrors();

    auth()->logout();
    $this->get('/')
        ->assertSee('Custom badge one')
        ->assertSee('Custom badge three')
        ->assertDontSee('Runs in your browser'); // the default for badge 2 is gone
});

it('saves a dynamic site name and renders it next to the logo', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    Livewire::actingAs($admin)->test('pages::admin.landing')
        ->set('content.landing_site_name', 'DocForge')
        ->call('saveContent')
        ->assertHasNoErrors();

    auth()->logout();
    $this->get('/')->assertSee('DocForge');
});

it('stores an uploaded logo and renders it across the brand spots', function () {
    Storage::fake('public');
    $admin = User::factory()->create(['is_admin' => true]);

    Livewire::actingAs($admin)->test('pages::admin.landing')
        ->set('logo', UploadedFile::fake()->image('logo.png', 64, 64))
        ->call('saveContent')
        ->assertHasNoErrors();

    $path = Setting::get('landing_logo');
    expect($path)->not->toBeNull();
    Storage::disk('public')->assertExists($path);

    auth()->logout();
    $this->get('/')
        ->assertSee(Storage::disk('public')->url($path), false) // <img src> on landing
        ->assertDontSee('>RG</span>', false);                   // fallback badge gone
});

it('removes the uploaded logo and falls back to the RG badge', function () {
    Storage::fake('public');
    $admin = User::factory()->create(['is_admin' => true]);

    Livewire::actingAs($admin)->test('pages::admin.landing')
        ->set('logo', UploadedFile::fake()->image('logo.png', 64, 64))
        ->call('saveContent')
        ->call('removeLogo')
        ->assertHasNoErrors();

    expect(Setting::get('landing_logo'))->toBeNull();

    auth()->logout();
    $this->get('/')->assertSee('>RG</span>', false); // fallback badge back
});

it('renders the footer company name as a new-tab link in the copyright', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    Livewire::actingAs($admin)->test('pages::admin.landing')
        ->set('content.landing_footer_copyright', '© :year {company} All rights reserved.')
        ->set('content.landing_footer_company_name', 'Raj')
        ->set('content.landing_footer_company_url', 'https://example.com')
        ->call('saveContent')
        ->assertHasNoErrors();

    auth()->logout();
    $this->get('/')
        ->assertSee('© '.now()->year, false)
        ->assertSee('All rights reserved.')
        ->assertSee('<a href="https://example.com" target="_blank"', false)
        ->assertSee('>Raj</a>', false);
});

it('renders FAQs from the database and reflects an admin-added one', function () {
    expect(LandingFeature::section('faqs')->count())->toBeGreaterThanOrEqual(1);

    $admin = User::factory()->create(['is_admin' => true]);
    Livewire::actingAs($admin)->test('pages::admin.landing')
        ->set('newSection', 'faqs')
        ->set('newTitle', 'Is there a free plan?')
        ->set('newDescription', 'Yes, you can try it for free.')
        ->call('addCard')
        ->assertHasNoErrors();

    auth()->logout();
    $this->get('/')
        ->assertSee('Is there a free plan?')
        ->assertSee('Yes, you can try it for free.');
});

it('generates the sample report library from the admin page', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    Livewire::actingAs($admin)->test('pages::admin.landing')->call('generateSamples');

    expect(Report::where('is_sample', true)->count())->toBeGreaterThanOrEqual(6);
});

it('lets an admin edit and delete a card', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $card = LandingFeature::create(['section' => 'features', 'title' => 'Temp', 'order' => 99]);

    Livewire::actingAs($admin)->test('pages::admin.landing')
        ->call('edit', $card->id)
        ->set('editTitle', 'Renamed card')
        ->call('saveEdit')
        ->assertHasNoErrors();

    expect($card->fresh()->title)->toBe('Renamed card');

    Livewire::actingAs($admin)->test('pages::admin.landing')->call('delete', $card->id);
    expect(LandingFeature::whereKey($card->id)->exists())->toBeFalse();
});
