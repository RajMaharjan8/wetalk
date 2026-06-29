<?php

use App\Models\Setting;

beforeEach(function () {
    config()->set('services.google.client_id', 'test-client-id.apps.googleusercontent.com');
    Setting::set('google_one_tap_enabled', '1');
});

it('shows the Google One Tap prompt on the home page', function () {
    $this->get('/')->assertSee('g_id_onload', false);
});

it('does not show the Google One Tap prompt on the /landing page', function () {
    $this->get('/landing')->assertDontSee('g_id_onload', false);
});
