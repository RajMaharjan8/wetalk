<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Validate;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::admin')] class extends Component
{
    #[Validate('required|string')]
    public string $current_password = '';

    #[Validate('required|string|min:8|confirmed')]
    public string $password = '';

    public string $password_confirmation = '';

    public function updatePassword(): void
    {
        $this->validate();

        if (! Hash::check($this->current_password, (string) Auth::user()->password)) {
            $this->addError('current_password', 'Your current password is incorrect.');

            return;
        }

        Auth::user()->forceFill(['password' => $this->password])->save();

        $this->reset('current_password', 'password', 'password_confirmation');

        session()->flash('password-updated', 'Your password has been changed.');
    }
}; ?>

@php($title = 'Change password')

<div class="max-w-md space-y-6">
    <x-validation-popup />

    @if (session('password-updated'))
        <div class="rounded-md bg-green-50 px-4 py-3 text-sm font-medium text-green-800 ring-1 ring-green-200">{{ session('password-updated') }}</div>
    @endif

    <form wire:submit="updatePassword" class="space-y-4 rounded-lg bg-white p-6 shadow-sm ring-1 ring-gray-200">
        <div>
            <label class="block text-sm font-medium text-gray-700">Current password</label>
            <input type="password" wire:model="current_password" autocomplete="current-password" class="mt-1 block w-full rounded-md px-3 py-2 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
            @error('current_password') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700">New password</label>
            <input type="password" wire:model="password" autocomplete="new-password" class="mt-1 block w-full rounded-md px-3 py-2 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
            @error('password') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700">Confirm new password</label>
            <input type="password" wire:model="password_confirmation" autocomplete="new-password" class="mt-1 block w-full rounded-md px-3 py-2 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
        </div>

        <div class="flex justify-end border-t border-gray-200 pt-4">
            <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">Update password</button>
        </div>
    </form>
</div>
