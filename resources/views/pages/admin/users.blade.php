<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Permission\Models\Role;

new #[Layout('layouts::admin')] class extends Component
{
    use WithPagination;

    public string $search = '';

    /** User whose roles are being edited in the modal, or null. */
    public ?int $rolesUserId = null;

    /** Role names currently checked in the modal. @var array<int, string> */
    public array $selectedRoles = [];

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    /** @return \Illuminate\Support\Collection<int, Role> */
    public function allRoles()
    {
        return Role::orderBy('name')->get();
    }

    public function manageRoles(int $userId): void
    {
        $user = User::findOrFail($userId);

        $this->rolesUserId = $user->id;
        $this->selectedRoles = $user->roles->pluck('name')->all();
    }

    public function closeRoles(): void
    {
        $this->reset('rolesUserId', 'selectedRoles');
    }

    public function saveRoles(): void
    {
        $user = User::findOrFail($this->rolesUserId);

        $valid = Role::pluck('name')->all();
        $user->syncRoles(array_values(array_intersect($this->selectedRoles, $valid)));

        $this->closeRoles();
        session()->flash('users-saved', 'Roles updated.');
    }

    public function suspend(int $userId): void
    {
        $user = User::findOrFail($userId);

        // Never suspend an admin or yourself.
        if ($user->isAdmin() || $user->id === Auth::id()) {
            return;
        }

        $user->forceFill(['suspended_at' => now()])->save();
    }

    public function unsuspend(int $userId): void
    {
        User::findOrFail($userId)->forceFill(['suspended_at' => null])->save();
    }

    public function getUsersProperty()
    {
        return User::query()
            ->when($this->search !== '', function ($query) {
                $query->where(function ($q) {
                    $q->where('name', 'like', "%{$this->search}%")
                        ->orWhere('email', 'like', "%{$this->search}%");
                });
            })
            ->with('roles')
            ->withCount('reports')
            ->latest()
            ->paginate(15);
    }
}; ?>

@php($title = 'Users')

<div class="space-y-4">
    @if (session('users-saved'))
        <div x-data="{ show: true }" x-show="show" x-transition x-init="setTimeout(() => show = false, 4000)"
             class="flex items-center gap-2 rounded-lg bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 ring-1 ring-emerald-200">
            <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
            {{ session('users-saved') }}
        </div>
    @endif

    <div class="flex items-center justify-between gap-4">
        <input type="search" wire:model.live.debounce.300ms="search" placeholder="Search name or email…" class="w-full max-w-xs rounded-md px-3 py-2 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
    </div>

    <div class="overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-gray-200">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                <tr>
                    <th class="px-4 py-3">User</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Roles</th>
                    <th class="px-4 py-3">Reports</th>
                    <th class="px-4 py-3">Last active</th>
                    <th class="px-4 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($this->users as $user)
                    @php($online = $user->last_active_at && $user->last_active_at->gte(now()->subMinutes(5)))
                    <tr wire:key="user-{{ $user->id }}">
                        <td class="px-4 py-3">
                            <div class="font-medium text-gray-900">{{ $user->name }} @if($user->isAdmin())<span class="ml-1 rounded bg-indigo-100 px-1.5 py-0.5 text-[10px] font-semibold text-indigo-700">ADMIN</span>@endif</div>
                            <div class="text-xs text-gray-500">{{ $user->email }}</div>
                        </td>
                        <td class="px-4 py-3">
                            @if ($user->isSuspended())
                                <span class="inline-flex items-center rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700">Suspended</span>
                            @elseif ($online)
                                <span class="inline-flex items-center gap-1 rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700"><span class="h-1.5 w-1.5 rounded-full bg-green-500"></span>Online</span>
                            @else
                                <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600">Offline</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @forelse ($user->roles as $role)
                                <span class="mr-1 inline-block rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-medium capitalize text-indigo-700">{{ str_replace('-', ' ', $role->name) }}</span>
                            @empty
                                <span class="text-xs text-gray-400">—</span>
                            @endforelse
                        </td>
                        <td class="px-4 py-3 text-gray-700">{{ $user->reports_count }}</td>
                        <td class="px-4 py-3 text-xs text-gray-500">{{ $user->last_active_at?->diffForHumans() ?? '—' }}</td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <button wire:click="manageRoles({{ $user->id }})" class="rounded-md bg-slate-50 px-3 py-1.5 text-xs font-semibold text-slate-700 ring-1 ring-slate-200 hover:bg-slate-100">Roles</button>
                                @if (! $user->isAdmin() && $user->id !== auth()->id())
                                    @if ($user->isSuspended())
                                        <button wire:click="unsuspend({{ $user->id }})" class="rounded-md bg-green-50 px-3 py-1.5 text-xs font-semibold text-green-700 ring-1 ring-green-200 hover:bg-green-100">Unsuspend</button>
                                    @else
                                        <button wire:click="suspend({{ $user->id }})" wire:confirm="Suspend {{ $user->name }}? They will be signed out and blocked." class="rounded-md bg-red-50 px-3 py-1.5 text-xs font-semibold text-red-700 ring-1 ring-red-200 hover:bg-red-100">Suspend</button>
                                    @endif
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-gray-500">No users found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $this->users->links() }}</div>

    {{-- Manage roles modal --}}
    @if ($rolesUserId)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4" wire:key="roles-modal-{{ $rolesUserId }}">
            <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl" @click.outside="$wire.closeRoles()">
                <h2 class="text-base font-semibold text-slate-900">Assign roles</h2>
                <p class="mt-1 text-sm text-slate-500">Choose which staff roles this user has. Each role grants access to specific admin sections.</p>

                <div class="mt-4 space-y-2">
                    @forelse ($this->allRoles() as $role)
                        <label class="flex items-center gap-2.5 rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700">
                            <input type="checkbox" wire:model="selectedRoles" value="{{ $role->name }}" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                            <span class="capitalize">{{ str_replace('-', ' ', $role->name) }}</span>
                            @if ($role->name === 'super-admin')
                                <span class="ml-auto rounded-full bg-violet-50 px-2 py-0.5 text-xs font-medium text-violet-700">Full access</span>
                            @endif
                        </label>
                    @empty
                        <p class="rounded-lg bg-slate-50 px-3 py-4 text-center text-sm text-slate-400">No roles yet. Create one under Roles &amp; permissions.</p>
                    @endforelse
                </div>

                <div class="mt-6 flex justify-end gap-2">
                    <button type="button" wire:click="closeRoles" class="rounded-md px-4 py-2 text-sm font-semibold text-slate-600 ring-1 ring-slate-300 hover:bg-slate-50">Cancel</button>
                    <button type="button" wire:click="saveRoles" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">Save roles</button>
                </div>
            </div>
        </div>
    @endif
</div>
