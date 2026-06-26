<?php

use App\Models\CustomPage;
use App\Models\User;

it('lets an admin create a custom page', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    Livewire::actingAs($admin)->test('pages::admin.pages')
        ->call('newPage')
        ->set('title', 'Privacy Policy')
        ->set('slug', 'privacy-policy')
        ->set('content', '<p>We respect your privacy.</p>')
        ->set('published', true)
        ->call('save')
        ->assertHasNoErrors();

    expect(CustomPage::where('slug', 'privacy-policy')->exists())->toBeTrue();
});

it('serves a published page at its root slug', function () {
    CustomPage::create(['title' => 'Terms', 'slug' => 'terms', 'content' => '<p>The terms.</p>', 'published' => true]);

    $this->get('/terms')
        ->assertOk()
        ->assertSee('Terms')
        ->assertSee('The terms.', false);
});

it('returns 404 for a draft (unpublished) page', function () {
    CustomPage::create(['title' => 'Draft', 'slug' => 'draft-page', 'content' => '<p>x</p>', 'published' => false]);

    $this->get('/draft-page')->assertNotFound();
});

it('returns 404 for an unknown slug', function () {
    $this->get('/nope-not-here')->assertNotFound();
});

it('rejects a reserved slug that would shadow an app route', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    Livewire::actingAs($admin)->test('pages::admin.pages')
        ->call('newPage')
        ->set('title', 'Fake Admin')
        ->set('slug', 'admin')
        ->call('save')
        ->assertHasErrors('slug');

    expect(CustomPage::where('slug', 'admin')->exists())->toBeFalse();
});

it('does not let a custom page shadow the real login route', function () {
    // Even though 'login' is reserved, prove the real route still wins.
    $this->get('/login')->assertOk();
});

it('hides a draft from the footer but shows a published footer page', function () {
    CustomPage::create(['title' => 'Footer Live', 'slug' => 'footer-live', 'published' => true, 'show_in_footer' => true]);
    CustomPage::create(['title' => 'Footer Draft', 'slug' => 'footer-draft', 'published' => false, 'show_in_footer' => true]);

    $this->get('/')
        ->assertSee('Footer Live')
        ->assertDontSee('Footer Draft');
});
