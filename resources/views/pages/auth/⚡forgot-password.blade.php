<?php

use App\Models\Otp;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Forgot password')] class extends Component
{
    public string $email = '';

    public string $code = '';

    public string $password = '';

    public string $password_confirmation = '';

    /** 1 = request a code, 2 = enter code + new password. */
    public int $step = 1;

    public function mount(): void
    {
        if (Auth::check()) {
            $this->redirectRoute('reports.index', navigate: true);
        }
    }

    public function sendCode(): void
    {
        $this->validate(['email' => 'required|email|exists:users,email']);

        if (! Otp::canSend($this->email, Otp::PURPOSE_PASSWORD_RESET)) {
            $this->addError('email', 'Please wait a moment before requesting another code.');

            return;
        }

        Otp::send($this->email, Otp::PURPOSE_PASSWORD_RESET);
        $this->step = 2;
        session()->flash('status', 'We emailed you a reset code.');
    }

    public function resetPassword()
    {
        $this->validate([
            'code' => 'required|digits:6',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if (! Otp::consume($this->email, Otp::PURPOSE_PASSWORD_RESET, $this->code)) {
            $this->addError('code', 'That code is invalid or has expired.');

            return null;
        }

        $user = User::where('email', $this->email)->firstOrFail();

        // Proving email ownership also verifies the address (covers users who
        // signed up but never confirmed, or Google users setting a password).
        $user->forceFill([
            'password' => $this->password,
            'email_verified_at' => $user->email_verified_at ?? now(),
        ])->save();

        return $this->redirectRoute('login', navigate: true);
    }
}; ?>

<div class="min-h-screen bg-gray-50 dark:bg-gray-900">
    <x-app-header />
    <x-validation-popup />

    <div class="flex items-center justify-center px-4 py-12">
        <div class="w-full max-w-md rounded-xl bg-white p-8 shadow-sm ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-gray-700">
            <div class="text-center">
                <h1 class="text-2xl font-semibold font-display text-gray-900 dark:text-gray-100">{{ __('Reset your password') }}</h1>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">
                    {{ $step === 1 ? __("Enter your email and we'll send a reset code.") : __('Enter the code we emailed and choose a new password.') }}
                </p>
            </div>

            @if (session('status'))
                <div class="mt-6 rounded-md bg-green-50 px-4 py-3 text-sm font-medium text-green-800 ring-1 ring-green-200 dark:bg-green-500/10 dark:text-green-300 dark:ring-green-500/20">{{ session('status') }}</div>
            @endif

            @if ($step === 1)
                <form wire:submit="sendCode" class="mt-8 space-y-4">
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Email') }}</label>
                        <input type="email" id="email" wire:model="email" autocomplete="username" class="mt-1 block w-full rounded-md px-3 py-2 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-gray-900 dark:text-gray-100 dark:ring-gray-600">
                        @error('email') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <button type="submit" class="w-full rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                        <span wire:loading.remove wire:target="sendCode">{{ __('Send reset code') }}</span>
                        <span wire:loading wire:target="sendCode">{{ __('Sending…') }}</span>
                    </button>
                </form>
            @else
                <form wire:submit="resetPassword" class="mt-8 space-y-4">
                    <div>
                        <label for="code" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Reset code') }}</label>
                        <input type="text" id="code" inputmode="numeric" maxlength="6" wire:model="code" autocomplete="one-time-code" class="mt-1 block w-full rounded-md px-3 py-2 text-center text-lg tracking-[0.5em] ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-gray-900 dark:text-gray-100 dark:ring-gray-600">
                        @error('code') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('New password') }}</label>
                        <input type="password" id="password" wire:model="password" autocomplete="new-password" class="mt-1 block w-full rounded-md px-3 py-2 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-gray-900 dark:text-gray-100 dark:ring-gray-600">
                        @error('password') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="password_confirmation" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Confirm new password') }}</label>
                        <input type="password" id="password_confirmation" wire:model="password_confirmation" autocomplete="new-password" class="mt-1 block w-full rounded-md px-3 py-2 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-gray-900 dark:text-gray-100 dark:ring-gray-600">
                    </div>

                    <button type="submit" class="w-full rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                        <span wire:loading.remove wire:target="resetPassword">{{ __('Reset password') }}</span>
                        <span wire:loading wire:target="resetPassword">{{ __('Resetting…') }}</span>
                    </button>

                    <p class="text-center text-sm text-gray-600 dark:text-gray-300">
                        <button type="button" wire:click="sendCode" class="font-medium text-indigo-600 hover:text-indigo-500">{{ __('Resend code') }}</button>
                    </p>
                </form>
            @endif

            <p class="mt-6 text-center text-sm text-gray-600 dark:text-gray-300">
                <a href="{{ route('login') }}" wire:navigate class="font-medium text-indigo-600 hover:text-indigo-500">{{ __('Back to sign in') }}</a>
            </p>
        </div>
    </div>
</div>
