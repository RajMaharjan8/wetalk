<?php

use App\Http\Controllers\PaymentController;
use App\Models\Download;
use App\Models\Payment;
use App\Models\Report;
use App\Models\Setting;
use App\Models\User;

function enableEsewa(): void
{
    Setting::set('esewa_enabled', '1');
    Setting::set('esewa_product_code', 'EPAYTEST');
    Setting::set('download_price', '50');
}

it('shows the print button and no pay options when no gateway is enabled', function () {
    $user = loginAsTestUser();
    $report = Report::factory()->create(['user_id' => $user->id]);

    $this->get(route('reports.output', $report))
        ->assertOk()
        ->assertSee('Download PDF')              // free download button
        ->assertDontSee('Download your report'); // no payment popup heading when free
});

it('shows the paywall over a free preview, with no print button, when unpaid', function () {
    $user = loginAsTestUser();
    $report = Report::factory()->create(['user_id' => $user->id]);
    enableEsewa();

    $this->get(route('reports.output', $report))
        ->assertOk()
        ->assertSee('Download your report')   // payment popup heading (only when locked)
        ->assertSee('eSewa')
        ->assertSee('report-source', false)   // preview is rendered (free to view)
        ->assertSee('paywall-print', false)   // print output is blocked
        ->assertDontSee('Download PDF');      // no free download button
});

it('shows both gateways on the paywall when both are enabled', function () {
    $user = loginAsTestUser();
    $report = Report::factory()->create(['user_id' => $user->id]);
    enableEsewa();
    Setting::set('khalti_enabled', '1');

    $this->get(route('reports.output', $report))
        ->assertOk()
        ->assertSee('eSewa')
        ->assertSee('Khalti');
});

it('still locks the download when a gateway is enabled but no price is set', function () {
    $user = loginAsTestUser();
    $report = Report::factory()->create(['user_id' => $user->id]);

    Setting::set('esewa_enabled', '1'); // price left unset (free would be wrong)

    $this->get(route('reports.output', $report))
        ->assertOk()
        ->assertDontSee('Download PDF')
        ->assertSee('no price has been set');
});

it('shows the print button once a redeemable payment unlocks the session', function () {
    $user = loginAsTestUser();
    $report = Report::factory()->create(['user_id' => $user->id]);
    enableEsewa();

    $payment = Payment::factory()->completed()->create([
        'user_id' => $user->id,
        'report_id' => $report->id,
    ]);

    $this->withSession([PaymentController::unlockKey($report) => $payment->id])
        ->get(route('reports.output', $report))
        ->assertOk()
        ->assertSee('Download PDF');
});

it('consumes the unlock so the next download requires payment again', function () {
    $user = loginAsTestUser();
    $report = Report::factory()->create(['user_id' => $user->id]);
    enableEsewa();

    $payment = Payment::factory()->completed()->create([
        'user_id' => $user->id,
        'report_id' => $report->id,
    ]);

    $this->withSession([PaymentController::unlockKey($report) => $payment->id])
        ->post(route('reports.download.consume', $report))
        ->assertNoContent();

    expect($payment->fresh()->consumed_at)->not->toBeNull();

    // Without an armed unlock the gate is closed again.
    $this->get(route('reports.output', $report))->assertDontSee('Download PDF');
});

it('records a free download classified by cover type', function () {
    $user = loginAsTestUser();
    $report = Report::factory()->create(['user_id' => $user->id, 'cover_format' => 'tu']);

    $this->post(route('reports.download.consume', $report))->assertNoContent();

    $download = Download::firstOrFail();
    expect($download->cover_type)->toBe('tu')
        ->and($download->paid)->toBeFalse()
        ->and($download->user_id)->toBe($user->id);
});

it('records a paid download and flags it as paid', function () {
    $user = loginAsTestUser();
    $report = Report::factory()->create(['user_id' => $user->id]); // london_met
    enableEsewa();

    $payment = Payment::factory()->completed()->create([
        'user_id' => $user->id,
        'report_id' => $report->id,
    ]);

    $this->withSession([PaymentController::unlockKey($report) => $payment->id])
        ->post(route('reports.download.consume', $report))
        ->assertNoContent();

    $download = Download::firstOrFail();
    expect($download->cover_type)->toBe('london_met')
        ->and($download->paid)->toBeTrue();
});

it('classifies cover type (tu / london_met / custom)', function () {
    $tu = Report::factory()->create(['cover_format' => 'tu']);
    $london = Report::factory()->create(['cover_format' => 'london_met']);
    $custom = Report::factory()->create([
        'front_overrides' => ['cover' => '<div class="cover-sheet-custom cover-custom">x</div>'],
    ]);

    expect($tu->coverType())->toBe('tu')
        ->and($london->coverType())->toBe('london_met')
        ->and($custom->coverType())->toBe('custom');
});

it('forbids paying for or viewing another user’s report', function () {
    $owner = User::factory()->create();
    $report = Report::factory()->create(['user_id' => $owner->id]);
    enableEsewa();

    $this->actingAs(User::factory()->create());

    $this->get(route('reports.output', $report))->assertForbidden();
    $this->post(route('reports.pay', ['report' => $report, 'gateway' => 'esewa']))->assertForbidden();
});
