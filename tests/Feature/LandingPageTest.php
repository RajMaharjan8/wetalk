<?php

use App\Models\User;

it('shows the landing page to guests at /', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('Document Generator')
        ->assertSee('Open the generator')
        ->assertSee('Sample Report for e-commerce website for bca/csit');
});

it('shows the landing page at / to signed-in users too (single home)', function () {
    $this->actingAs(User::factory()->create())
        ->get('/')
        ->assertOk();
});

it('serves the dashboard at /dashboard', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('reports.index'))
        ->assertOk()
        ->assertSee('Your Reports');
});
