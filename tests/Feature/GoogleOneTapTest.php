<?php

use App\Models\Setting;

beforeEach(function () {
    config()->set('services.google.client_id', 'test-client-id.apps.googleusercontent.com');
});

it('returns 404 when one tap is disabled', function () {
    Setting::set('google_one_tap_enabled', '0');

    $this->post(route('auth.google.one-tap'), ['credential' => 'x.y.z'])
        ->assertNotFound();
});

it('returns 404 when no google client id is configured even if enabled', function () {
    config()->set('services.google.client_id', null);
    Setting::set('google_one_tap_enabled', '1');

    $this->post(route('auth.google.one-tap'), ['credential' => 'x.y.z'])
        ->assertNotFound();
});

it('requires a credential when enabled', function () {
    Setting::set('google_one_tap_enabled', '1');

    $this->post(route('auth.google.one-tap'), [])
        ->assertSessionHasErrors('credential');
});

it('redirects back to login when the credential is not a valid google token', function () {
    Setting::set('google_one_tap_enabled', '1');

    $this->post(route('auth.google.one-tap'), ['credential' => 'not-a-real-jwt'])
        ->assertRedirect(route('login'))
        ->assertSessionHas('error');

    expect(auth()->check())->toBeFalse();
});
