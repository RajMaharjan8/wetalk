<?php

use App\Models\User;

it('switches the locale via a cookie and redirects back', function () {
    $this->get('/locale/ne', ['referer' => route('login')])
        ->assertRedirect()
        ->assertCookie('locale', 'ne');
});

it('rejects an unsupported locale', function () {
    $this->get('/locale/fr')->assertNotFound();
});

it('renders the login page in Nepali when the locale cookie is set', function () {
    $this->withCookie('locale', 'ne')
        ->get(route('login'))
        ->assertOk()
        ->assertSee('साइन इन', false)        // "Sign in" translated
        ->assertDontSee('Remember me');       // English source string gone
});

it('renders English by default', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('Remember me');
});

it('renders the dashboard in Nepali for a signed-in user', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withCookie('locale', 'ne')
        ->get(route('reports.index'))
        ->assertOk()
        ->assertSee('तपाईंका रिपोर्टहरू', false); // "Your Reports"
});
