<?php

use App\Models\User;

it('shows the landing page to guests at /', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('Document Generator')
        ->assertSee('Open the generator')
        ->assertSee('Sample Report for e-commerce website for bca/csit');
});

it('sends signed-in users from / to their dashboard', function () {
    $this->actingAs(User::factory()->create())
        ->get('/')
        ->assertRedirect(route('reports.index'));
});

it('serves the dashboard at /dashboard', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('reports.index'))
        ->assertOk()
        ->assertSee('Your Reports');
});
