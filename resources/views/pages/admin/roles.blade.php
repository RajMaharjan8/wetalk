<?php

use App\Support\AdminPermissions;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

new #[Layout('layouts::admin')] class extends Component
{
    /** New role name. */
    #[Validate('required|string|max:50|unique:roles,name')]
    public string $newRole = '';

    /** Role currently being edited (its permissions), or null. */
    public ?int $editingId = null;

    /**
     * Permission name => bool, bound to the checkboxes while editing a role.
     *
     * @var array<string, bool>
     */
    public array $perms = [];

    /** @return \Illuminate\Support\Collection<int, Role> */
    public function roles()
    {
        return Role::withCount('users')->orderBy('name')->get();
    }

    /** @return array<string, string> */
    public function permissionList(): array
    {
        return AdminPermissions::all();
    }

    public function addRole(): void
    {
        $this->validate();

        Role::findOrCreate(trim($this->newRole), 'web');

        $this->reset('newRole');
        session()->flash('roles-saved', 'Role created.');
    }

    public function edit(int $id): void
    {
        $role = Role::findOrFail($id);

        $this->editingId = $role->id;
        $granted = $role->permissions->pluck('name')->all();
        $this->perms = collect(AdminPermissions::names())
            ->mapWithKeys(fn ($name) => [$name => in_array($name, $granted, true)])
            ->all();
    }

    public function cancel(): void
    {
        $this->reset('editingId', 'perms');
    }

    public function savePermissions(): void
    {
        $role = Role::findOrFail($this->editingId);

        // The super-admin role always has everything (via Gate::before) — its
        // permission set isn't editable.
        abort_if($role->name === 'super-admin', 403);

        $granted = array_keys(array_filter($this->perms));
        $role->syncPermissions($granted);

        $this->cancel();
        session()->flash('roles-saved', 'Permissions updated.');
    }

    public function deleteRole(int $id): void
    {
        $role = Role::findOrFail($id);

        abort_if($role->name === 'super-admin', 403);

        $role->delete();
        session()->flash('roles-saved', 'Role deleted.');
    }
}; ?>

@php($title = 'Roles & permissions')

<div class="max-w-4xl space-y-6">
    <x-validation-popup />

    @if (session('roles-saved'))
        <div x-data="{ show: true }" x-show="show" x-transition x-init="setTimeout(() => show = false, 4000)"
             class="flex items-center gap-2 rounded-lg bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 ring-1 ring-emerald-200">
            <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
            {{ session('roles-saved') }}
        </div>
    @endif

    <p class="text-sm text-slate-500">Create staff roles and choose exactly which admin sections each one can access. The <span class="font-medium text-slate-700">super-admin</span> role always has full access and can't be edited.</p>

    {{-- Create role --}}
    <form wire:submit="addRole" class="flex flex-col gap-3 rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:flex-row sm:items-end">
        <div class="flex-1">
            <label class="block text-sm font-medium text-slate-700">New role name</label>
            <input type="text" wire:model="newRole" placeholder="e.g. Sub-admin, Content editor" class="mt-1.5 block w-full rounded-lg px-3.5 py-2.5 text-sm ring-1 ring-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
            @error('newRole') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>
        <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">Create role</button>
    </form>

    {{-- Role list --}}
    <div class="space-y-3">
        @foreach ($this->roles() as $role)
            <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center justify-between gap-3 px-5 py-4">
                    <div class="flex items-center gap-3">
                        <span class="flex h-9 w-9 items-center justify-center rounded-lg {{ $role->name === 'super-admin' ? 'bg-violet-100 text-violet-700' : 'bg-indigo-50 text-indigo-600' }} text-sm font-bold">{{ strtoupper(substr($role->name, 0, 1)) }}</span>
                        <div>
                            <p class="text-sm font-semibold capitalize text-slate-900">{{ str_replace('-', ' ', $role->name) }}</p>
                            <p class="text-xs text-slate-400">
                                {{ $role->users_count }} {{ \Illuminate\Support\Str::plural('member', $role->users_count) }}
                                @if ($role->name === 'super-admin')
                                    <span class="text-slate-300">·</span> full access
                                @else
                                    <span class="text-slate-300">·</span> {{ $role->permissions->count() }} {{ \Illuminate\Support\Str::plural('permission', $role->permissions->count()) }}
                                @endif
                            </p>
                        </div>
                    </div>

                    @if ($role->name !== 'super-admin')
                        <div class="flex items-center gap-2">
                            <button type="button" wire:click="edit({{ $role->id }})" class="rounded-md px-3 py-1.5 text-xs font-semibold text-indigo-600 hover:bg-indigo-50">Edit access</button>
                            <button type="button" wire:click="deleteRole({{ $role->id }})" wire:confirm="Delete this role? Members will lose its access." class="rounded-md px-3 py-1.5 text-xs font-semibold text-red-600 hover:bg-red-50">Delete</button>
                        </div>
                    @else
                        <span class="rounded-full bg-violet-50 px-2.5 py-1 text-xs font-medium text-violet-700">Protected</span>
                    @endif
                </div>

                {{-- Inline permission editor --}}
                @if ($editingId === $role->id)
                    <div class="border-t border-slate-100 bg-slate-50/60 px-5 py-4">
                        <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Section access</p>
                        <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                            @foreach ($this->permissionList() as $name => $label)
                                <label class="flex items-center gap-2.5 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700">
                                    <input type="checkbox" wire:model="perms.{{ $name }}" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                                    {{ $label }}
                                </label>
                            @endforeach
                        </div>
                        <div class="mt-4 flex gap-2">
                            <button type="button" wire:click="savePermissions" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">Save access</button>
                            <button type="button" wire:click="cancel" class="rounded-md px-4 py-2 text-sm font-semibold text-slate-600 ring-1 ring-slate-300 hover:bg-slate-50">Cancel</button>
                        </div>
                    </div>
                @endif
            </div>
        @endforeach
    </div>
</div>
