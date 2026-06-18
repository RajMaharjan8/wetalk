<?php

use App\Models\Otp;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

new #[Title('Verify email')] class extends Component
{
    public string $email = '';

    #[Validate('required|digits:6')]
    public string $code = '';

    public function mount(): void
    {
        $this->email = (string) session('otp_email', '');

        if ($this->email === '') {
            $this->redirectRoute('login', navigate: true);
        }
    }

    public function verify()
    {
        $this->validate();

        if (! Otp::consume($this->email, Otp::PURPOSE_REGISTRATION, $this->code)) {
            $this->addError('code', 'That code is invalid or has expired.');

            return null;
        }

        $user = User::where('email', $this->email)->firstOrFail();
        $user->forceFill(['email_verified_at' => now()])->save();

        session()->forget('otp_email');
        Auth::login($user, remember: true);
        session()->regenerate();

        return $this->redirectRoute('reports.index', navigate: true);
    }

    public function resend(): void
    {
        if (! Otp::canSend($this->email, Otp::PURPOSE_REGISTRATION)) {
            $this->addError('code', 'Please wait a moment before requesting another code.');

            return;
        }

        Otp::send($this->email, Otp::PURPOSE_REGISTRATION);
        session()->flash('status', 'A new code has been sent to your email.');
    }
}; ?>

<div class="min-h-screen bg-gray-50 dark:bg-gray-900">
    <x-app-header />
    <x-validation-popup />

    <div class="flex items-center justify-center px-4 py-12">
        <div class="w-full max-w-md rounded-xl bg-white p-8 shadow-sm ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-gray-700">
            <div class="text-center">
                <h1 class="text-2xl font-semibold font-display text-gray-900 dark:text-gray-100">{{ __('Verify your email') }}</h1>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">{!! __('Enter the 6-digit code we sent to :email.', ['email' => '<span class="font-medium text-gray-900 dark:text-gray-100">'.e($email).'</span>']) !!}</p>
            </div>

            @if (session('status'))
                <div class="mt-6 rounded-md bg-green-50 px-4 py-3 text-sm font-medium text-green-800 ring-1 ring-green-200 dark:bg-green-500/10 dark:text-green-300 dark:ring-green-500/20">{{ session('status') }}</div>
            @endif

            <form wire:submit="verify" class="mt-8 space-y-4">
                <div>
                    <label for="code" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Verification code') }}</label>
                    <input type="text" id="code" inputmode="numeric" maxlength="6" wire:model="code" autocomplete="one-time-code" class="mt-1 block w-full rounded-md px-3 py-2 text-center text-lg tracking-[0.5em] ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-gray-900 dark:text-gray-100 dark:ring-gray-600">
                    @error('code') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <button type="submit" class="w-full rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                    <span wire:loading.remove wire:target="verify">{{ __('Verify & continue') }}</span>
                    <span wire:loading wire:target="verify">{{ __('Verifying…') }}</span>
                </button>
            </form>

            <p class="mt-6 text-center text-sm text-gray-600 dark:text-gray-300">
                {{ __("Didn't get it?") }} <button type="button" wire:click="resend" class="font-medium text-indigo-600 hover:text-indigo-500">{{ __('Resend code') }}</button>
            </p>
        </div>
    </div>
</div>
