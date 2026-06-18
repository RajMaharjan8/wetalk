<?php

use App\Models\Download;
use App\Models\Payment;
use App\Models\Report;
use App\Models\User;
use Livewire\Livewire;

it('lists completed transactions with their user on the admin page', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $payer = User::factory()->create(['name' => 'Sita Sharma']);
    $report = Report::factory()->create(['user_id' => $payer->id]);

    Payment::factory()->completed()->create([
        'user_id' => $payer->id,
        'report_id' => $report->id,
        'amount' => 50,
        'gateway' => 'esewa',
    ]);

    $this->actingAs($admin);

    Livewire::test('pages::admin.transactions')
        ->assertSee('Sita Sharma')
        ->assertSee('eSewa')
        ->assertSee('Rs. 50.00');
});

it('filters transactions by status', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $report = Report::factory()->create();

    Payment::factory()->completed()->create(['report_id' => $report->id, 'ref_id' => 'REF-DONE']);
    Payment::factory()->create(['report_id' => $report->id, 'ref_id' => 'REF-PENDING']); // pending

    $this->actingAs($admin);

    Livewire::test('pages::admin.transactions')
        ->assertSet('filter', 'completed')
        ->assertSee('REF-DONE')
        ->assertDontSee('REF-PENDING')
        ->set('filter', 'pending')
        ->assertSee('REF-PENDING')
        ->assertDontSee('REF-DONE');
});

it('shows the download breakdown and revenue on the dashboard', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $report = Report::factory()->create();

    Download::create(['report_id' => $report->id, 'user_id' => $admin->id, 'cover_type' => 'tu', 'paid' => true]);
    Download::create(['report_id' => $report->id, 'user_id' => $admin->id, 'cover_type' => 'london_met', 'paid' => false]);
    Payment::factory()->completed()->create(['report_id' => $report->id, 'amount' => 99.5]);

    $this->actingAs($admin);

    Livewire::test('pages::admin.dashboard')
        ->assertSee('Downloads by cover')
        ->assertSee('TU')
        ->assertSee('London Met')
        ->assertSee('Custom')
        ->assertSee('Rs. 99.50');
});
