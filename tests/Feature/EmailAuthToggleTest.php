<?php

use App\Models\Setting;
use App\Models\User;
use App\Support\AuthSettings;

it('allows email login by default', function () {
    expect(AuthSettings::emailAuthEnabled())->toBeTrue();

    $this->get(route('login'))->assertOk()->assertSee('Email');
});

it('blocks the authenticate action when email auth is disabled', function () {
    Setting::set('email_auth_enabled', '0');
    $user = User::factory()->create(['password' => bcrypt('secret123'), 'email_verified_at' => now()]);

    Livewire::test('pages::auth.login')
        ->set('email', $user->email)
        ->set('password', 'secret123')
        ->call('authenticate')
        ->assertStatus(403);
});

it('hides the email form on the login page when disabled (Google-only)', function () {
    Setting::set('email_auth_enabled', '0');

    $this->get(route('login'))
        ->assertOk()
        ->assertSee('Continue with Google')
        ->assertDontSee('wire:submit="authenticate"', false);
});

it('redirects register / verify-otp / forgot-password to login when disabled', function () {
    Setting::set('email_auth_enabled', '0');

    $this->get(route('register'))->assertRedirect(route('login'));
    $this->get(route('verify-otp'))->assertRedirect(route('login'));
    $this->get(route('forgot-password'))->assertRedirect(route('login'));
});

it('blocks the register action when email auth is disabled', function () {
    Setting::set('email_auth_enabled', '0');

    Livewire::test('pages::auth.register')
        ->set('name', 'Test')
        ->set('email', 'new@example.com')
        ->set('password', 'secret123')
        ->set('password_confirmation', 'secret123')
        ->call('register')
        ->assertStatus(403);

    expect(User::where('email', 'new@example.com')->exists())->toBeFalse();
});

it('lets an admin toggle email auth from the dashboard', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    Livewire::actingAs($admin)->test('pages::admin.dashboard')
        ->set('emailAuthEnabled', false);

    expect(Setting::get('email_auth_enabled'))->toBe('0')
        ->and(AuthSettings::emailAuthEnabled())->toBeFalse();
});
