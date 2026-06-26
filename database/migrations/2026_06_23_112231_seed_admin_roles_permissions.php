<?php

use App\Models\User;
use App\Support\AdminPermissions;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Create every admin-section permission (idempotent).
        foreach (AdminPermissions::names() as $name) {
            Permission::findOrCreate($name, 'web');
        }

        // Super-admin role: granted all access via Gate::before, so it needs no
        // explicit permissions, but we create it so it can be assigned in the UI.
        $superAdmin = Role::findOrCreate('super-admin', 'web');

        // Promote existing admins to super-admin so nobody is locked out.
        User::where('is_admin', true)->each(fn (User $user) => $user->assignRole($superAdmin));
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Role::where('name', 'super-admin')->delete();

        foreach (AdminPermissions::names() as $name) {
            Permission::where('name', $name)->delete();
        }
    }
};
