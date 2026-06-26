<?php

use App\Models\Setting;
use App\Models\User;
use App\Support\FeatureSettings;

it('enables both features by default', function () {
    expect(FeatureSettings::feedbackEnabled())->toBeTrue()
        ->and(FeatureSettings::checkReportEnabled())->toBeTrue();
});

it('returns 404 for the check-report page when disabled', function () {
    Setting::set('feature_check_report_enabled', '0');
    loginAsTestUser();

    $this->get(route('reports.check'))->assertNotFound();
});

it('serves the check-report page when enabled', function () {
    loginAsTestUser();

    $this->get(route('reports.check'))->assertOk();
});

it('blocks the sendFeedback action when feedback is disabled', function () {
    Setting::set('feature_feedback_enabled', '0');
    loginAsTestUser();

    Livewire::test('feedback')
        ->set('fb_working', 'Great app')
        ->call('sendFeedback')
        ->assertStatus(403);
});

it('lets an admin toggle features from the settings page', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    Livewire::actingAs($admin)->test('pages::admin.settings')
        ->set('checkReportEnabled', false)
        ->set('feedbackEnabled', false);

    expect(Setting::get('feature_check_report_enabled'))->toBe('0')
        ->and(Setting::get('feature_feedback_enabled'))->toBe('0');
});
