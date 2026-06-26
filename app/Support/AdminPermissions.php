<?php

namespace App\Support;

/**
 * Single source of truth for admin section permissions. Each key is a Spatie
 * permission name; the value is its human label shown in the role editor.
 *
 * Used by the seeder, route gating, the sidebar nav filter and the roles UI so
 * they never drift apart.
 */
class AdminPermissions
{
    /**
     * @return array<string, string> permission name => label
     */
    public static function all(): array
    {
        return [
            'users.manage' => 'Manage users',
            'roles.manage' => 'Manage roles & permissions',
            'landing.manage' => 'Manage landing page',
            'payments.manage' => 'Manage payments',
            'transactions.view' => 'View transactions',
            'feedback.manage' => 'Manage feedback',
            'mail.manage' => 'Manage mail settings',
            'settings.manage' => 'Manage app settings (dashboard)',
        ];
    }

    /** @return list<string> just the permission names */
    public static function names(): array
    {
        return array_keys(self::all());
    }
}
