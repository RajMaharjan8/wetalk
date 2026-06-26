<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Read-side helper for authentication feature flags stored in the key-value
 * {@see Setting} store.
 */
class AuthSettings
{
    /**
     * Whether email + password login/registration is available. When false the
     * app is Google-only: the email/password forms are hidden and their actions
     * refuse to run. Defaults to enabled so existing installs are unchanged.
     */
    public static function emailAuthEnabled(): bool
    {
        return Setting::get('email_auth_enabled', '1') === '1';
    }
}
