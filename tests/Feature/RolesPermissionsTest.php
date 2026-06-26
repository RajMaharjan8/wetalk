<?php

use App\Models\User;
use App\Support\AdminPermissions;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    // RefreshDatabase truncates the permission tables, so re-seed the catalog
    // and the super-admin role each test, clearing Spatie's cache first.
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    foreach (AdminPermissions::names() as $name) {
        Permission::findOrCreate($name, 'web');
    }
    Role::findOrCreate('super-admin', 'web');
});

it('grants super-admins every permission via Gate::before', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    expect($admin->can('users.manage'))->toBeTrue()
        ->and($admin->can('payments.manage'))->toBeTrue();
});

it('lets a staff member reach only their permitted section', function () {
    $role = Role::findOrCreate('feedback-staff', 'web');
    $role->givePermissionTo('feedback.manage');

    $staff = User::factory()->create(['is_admin' => false]);
    $staff->assignRole($role);

    // Permitted section: allowed.
    $this->actingAs($staff)->get(route('admin.feedback'))->assertOk();

    // Un-permitted section: forbidden.
    $this->actingAs($staff)->get(route('admin.payments'))->assertForbidden();
});

it('keeps non-staff users out of the admin area entirely', function () {
    $user = User::factory()->create(['is_admin' => false]);

    $this->actingAs($user)->get(route('admin.dashboard'))->assertForbidden();
});

it('lets an admin create a role and assign permissions', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    Livewire::actingAs($admin)->test('pages::admin.roles')
        ->set('newRole', 'Content editor')
        ->call('addRole')
        ->assertHasNoErrors();

    $role = Role::where('name', 'Content editor')->firstOrFail();

    Livewire::actingAs($admin)->test('pages::admin.roles')
        ->call('edit', $role->id)
        ->set('perms', ['landing.manage' => true])
        ->call('savePermissions');

    expect($role->fresh()->hasPermissionTo('landing.manage'))->toBeTrue();
});

it('refuses to edit the protected super-admin role', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $superAdmin = Role::findByName('super-admin');

    Livewire::actingAs($admin)->test('pages::admin.roles')
        ->call('edit', $superAdmin->id)
        ->call('savePermissions')
        ->assertStatus(403);
});

it('lets an admin assign roles to a user from the users page', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    Role::findOrCreate('sub-admin', 'web');
    $target = User::factory()->create();

    Livewire::actingAs($admin)->test('pages::admin.users')
        ->call('manageRoles', $target->id)
        ->set('selectedRoles', ['sub-admin'])
        ->call('saveRoles');

    expect($target->fresh()->hasRole('sub-admin'))->toBeTrue();
});
