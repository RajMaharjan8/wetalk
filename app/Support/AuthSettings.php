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

    /**
     * Whether the Google One Tap prompt is shown to signed-out visitors on the
     * landing and login pages. Requires a configured Google client id; defaults
     * to off so it must be explicitly enabled.
     */
    public static function googleOneTapEnabled(): bool
    {
        return Setting::get('google_one_tap_enabled', '0') === '1' && self::googleClientId() !== null;
    }

    /**
     * The Google OAuth client id used by both Socialite and One Tap, or null
     * when Google auth is not configured.
     */
    public static function googleClientId(): ?string
    {
        return config('services.google.client_id') ?: null;
    }
}
