<?php

use App\Models\Otp;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

new #[Title('Create account')] class extends Component
{
    #[Validate('required|string|max:255')]
    public string $name = '';

    #[Validate('required|email|max:255|unique:users,email')]
    public string $email = '';

    #[Validate('required|string|min:8|confirmed')]
    public string $password = '';

    public string $password_confirmation = '';

    public function mount(): void
    {
        if (Auth::check()) {
            $this->redirectRoute('reports.index', navigate: true);
        }
    }

    public function register()
    {
        $this->validate();

        // Created unverified: the user cannot sign in until the OTP is confirmed.
        User::create([
            'name' => $this->name,
            'email' => $this->email,
            'password' => $this->password,
        ]);

        Otp::send($this->email, Otp::PURPOSE_REGISTRATION);
        session(['otp_email' => $this->email]);

        return $this->redirectRoute('verify-otp', navigate: true);
    }
}; ?>

<div class="min-h-screen bg-gray-50 dark:bg-gray-900">
    <x-app-header />
    <x-validation-popup />

    <div class="flex items-center justify-center px-4 py-12">
        <div class="w-full max-w-md rounded-xl bg-white p-8 shadow-sm ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-gray-700">
            <div class="text-center">
                <h1 class="text-2xl font-semibold font-display text-gray-900 dark:text-gray-100">{{ __('Create your account') }}</h1>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">{{ __("We'll email you a code to verify your address.") }}</p>
            </div>

            <form wire:submit="register" class="mt-8 space-y-4">
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Name') }}</label>
                    <input type="text" id="name" wire:model="name" autocomplete="name" class="mt-1 block w-full rounded-md px-3 py-2 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-gray-900 dark:text-gray-100 dark:ring-gray-600">
                    @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Email') }}</label>
                    <input type="email" id="email" wire:model="email" autocomplete="username" class="mt-1 block w-full rounded-md px-3 py-2 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-gray-900 dark:text-gray-100 dark:ring-gray-600">
                    @error('email') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Password') }}</label>
                    <input type="password" id="password" wire:model="password" autocomplete="new-password" class="mt-1 block w-full rounded-md px-3 py-2 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-gray-900 dark:text-gray-100 dark:ring-gray-600">
                    @error('password') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="password_confirmation" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Confirm password') }}</label>
                    <input type="password" id="password_confirmation" wire:model="password_confirmation" autocomplete="new-password" class="mt-1 block w-full rounded-md px-3 py-2 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-gray-900 dark:text-gray-100 dark:ring-gray-600">
                </div>

                <button type="submit" class="w-full rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                    <span wire:loading.remove wire:target="register">{{ __('Create account') }}</span>
                    <span wire:loading wire:target="register">{{ __('Sending code…') }}</span>
                </button>
            </form>

            <div class="my-6 flex items-center gap-3 text-xs text-gray-400 dark:text-gray-400">
                <span class="h-px flex-1 bg-gray-200 dark:bg-gray-700"></span>{{ __('OR') }}<span class="h-px flex-1 bg-gray-200 dark:bg-gray-700"></span>
            </div>

            <a href="{{ route('auth.google.redirect') }}"
               class="inline-flex w-full items-center justify-center gap-3 rounded-md bg-white px-4 py-2.5 text-sm font-semibold text-gray-800 ring-1 ring-gray-300 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-100 dark:ring-gray-600 dark:hover:bg-gray-700">
                <svg class="h-5 w-5" viewBox="0 0 48 48" aria-hidden="true">
                    <path fill="#FFC107" d="M43.611 20.083H42V20H24v8h11.303C33.972 32.91 29.418 36 24 36c-6.627 0-12-5.373-12-12s5.373-12 12-12c3.059 0 5.842 1.154 7.961 3.039l5.657-5.657C34.046 6.053 29.268 4 24 4 12.955 4 4 12.955 4 24s8.955 20 20 20 20-8.955 20-20c0-1.341-.138-2.65-.389-3.917z"/>
                    <path fill="#FF3D00" d="M6.306 14.691l6.571 4.819C14.655 15.108 18.961 12 24 12c3.059 0 5.842 1.154 7.961 3.039l5.657-5.657C34.046 6.053 29.268 4 24 4 16.318 4 9.656 8.337 6.306 14.691z"/>
                    <path fill="#4CAF50" d="M24 44c5.166 0 9.86-1.977 13.409-5.192l-6.19-5.238C29.211 35.091 26.715 36 24 36c-5.397 0-9.939-3.073-11.278-7.946l-6.522 5.025C9.5 39.556 16.227 44 24 44z"/>
                    <path fill="#1976D2" d="M43.611 20.083H42V20H24v8h11.303a12.04 12.04 0 0 1-4.087 5.571l.003-.002 6.19 5.238C36.971 39.205 44 34 44 24c0-1.341-.138-2.65-.389-3.917z"/>
                </svg>
                {{ __('Continue with Google') }}
            </a>

            <p class="mt-6 text-center text-sm text-gray-600 dark:text-gray-300">
                {{ __('Already have an account?') }} <a href="{{ route('login') }}" wire:navigate class="font-medium text-indigo-600 hover:text-indigo-500">{{ __('Sign in') }}</a>
            </p>
        </div>
    </div>
</div>
