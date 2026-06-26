<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Validate;
use Livewire\Component;

new class extends Component
{
    #[Validate('required|email')]
    public string $email = '';

    #[Validate('required|string')]
    public string $password = '';

    public function mount(): void
    {
        // An already-signed-in admin can skip the form.
        if (Auth::user()?->isAdmin()) {
            $this->redirectRoute('admin.dashboard', navigate: true);
        }
    }

    public function authenticate()
    {
        $this->validate();

        $user = \App\Models\User::where('email', $this->email)->first();

        if (! $user || ! $user->isAdmin() || ! Auth::attempt(['email' => $this->email, 'password' => $this->password])) {
            $this->addError('email', 'These credentials do not match an admin account.');

            return null;
        }

        session()->regenerate();

        return $this->redirectRoute('admin.dashboard', navigate: true);
    }
}; ?>

<div class="flex min-h-screen bg-slate-50">
    <x-validation-popup />

    {{-- Top loading bar while authenticating --}}
    <div wire:loading wire:target="authenticate" class="fixed inset-x-0 top-0 z-50 h-1 overflow-hidden bg-indigo-100">
        <div class="h-full w-1/3 animate-[loading_1s_ease-in-out_infinite] rounded-full bg-indigo-600"></div>
    </div>
    <style>@keyframes loading { 0% { transform: translateX(-100%); } 100% { transform: translateX(400%); } }</style>

    {{-- Brand panel (desktop) --}}
    <div class="relative hidden w-1/2 overflow-hidden bg-linear-to-br from-indigo-600 via-indigo-700 to-violet-800 lg:flex lg:flex-col lg:justify-between lg:p-12">
        <div class="absolute -right-16 -top-16 h-64 w-64 rounded-full bg-white/10 blur-3xl"></div>
        <div class="absolute -bottom-20 -left-10 h-72 w-72 rounded-full bg-white/5 blur-3xl"></div>

        <div class="relative flex items-center gap-3 text-white">
            <x-app-logo badge="bg-white/15 text-white ring-1 ring-white/25 backdrop-blur" />
            <span class="text-lg font-semibold">{{ \App\Support\LandingContent::siteName() }}</span>
        </div>

        <div class="relative text-white">
            <h2 class="text-3xl font-bold leading-tight">Admin control center</h2>
            <p class="mt-3 max-w-sm text-indigo-100">Manage users, content, payments and reports — all from one secure dashboard.</p>
        </div>

        <p class="relative text-xs text-indigo-200">&copy; {{ now()->year }} {{ \App\Support\LandingContent::siteName() }}. Staff access only.</p>
    </div>

    {{-- Form panel --}}
    <div class="flex w-full items-center justify-center px-4 py-12 lg:w-1/2">
        <div class="w-full max-w-sm">
            <div class="mb-8 flex items-center gap-2 lg:hidden">
                <x-app-logo />
                <span class="text-base font-semibold text-slate-900">{{ \App\Support\LandingContent::siteName() }}</span>
            </div>

            <div class="mb-8">
                <div class="mb-4 inline-flex h-11 w-11 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" /></svg>
                </div>
                <h1 class="text-2xl font-bold tracking-tight text-slate-900">Admin sign in</h1>
                <p class="mt-1 text-sm text-slate-500">Enter your staff credentials to continue.</p>
            </div>

            @error('email')
                <div class="mb-5 flex items-start gap-2.5 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700 ring-1 ring-red-200">
                    <svg class="mt-0.5 h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" /></svg>
                    <span>{{ $message }}</span>
                </div>
            @enderror

            <form wire:submit="authenticate" class="space-y-5">
                <div>
                    <label for="email" class="block text-sm font-medium text-slate-700">Email</label>
                    <input type="email" id="email" wire:model="email" autocomplete="username" placeholder="you@example.com" @class([
                        'mt-1.5 block w-full rounded-lg border-0 px-3.5 py-2.5 text-sm text-slate-900 ring-1 transition focus:outline-none focus:ring-2 focus:ring-indigo-500',
                        'ring-red-400' => $errors->has('email'),
                        'ring-slate-300' => ! $errors->has('email'),
                    ])>
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-slate-700">Password</label>
                    <input type="password" id="password" wire:model="password" autocomplete="current-password" placeholder="••••••••" class="mt-1.5 block w-full rounded-lg border-0 px-3.5 py-2.5 text-sm text-slate-900 ring-1 ring-slate-300 transition focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    @error('password') <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <button type="submit" class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                    <span wire:loading.remove wire:target="authenticate">Sign in</span>
                    <span wire:loading wire:target="authenticate" class="inline-flex items-center gap-2">
                        <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                        Signing in…
                    </span>
                </button>
            </form>
        </div>
    </div>
</div>
