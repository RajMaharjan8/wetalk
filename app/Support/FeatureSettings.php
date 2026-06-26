<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Read-side helper for optional, admin-toggleable app features. Each flag
 * defaults to enabled so existing installs keep their current behaviour.
 */
class FeatureSettings
{
    /** Whether users can submit feedback ("Send feedback"). */
    public static function feedbackEnabled(): bool
    {
        return Setting::get('feature_feedback_enabled', '1') === '1';
    }

    /** Whether the "Check my report" PDF format-checker tool is available. */
    public static function checkReportEnabled(): bool
    {
        return Setting::get('feature_check_report_enabled', '1') === '1';
    }
}
