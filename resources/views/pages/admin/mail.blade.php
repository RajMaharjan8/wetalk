<?php

use App\Models\Setting;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

new #[Layout('layouts::admin')] class extends Component
{
    #[Validate('nullable|string|max:255')]
    public string $mail_host = '';

    #[Validate('nullable|integer|min:1|max:65535')]
    public ?int $mail_port = 587;

    #[Validate('nullable|string|max:255')]
    public string $mail_username = '';

    #[Validate('nullable|string|max:255')]
    public string $mail_password = '';

    #[Validate('required|in:tls,ssl,none')]
    public string $mail_encryption = 'tls';

    #[Validate('nullable|email|max:255')]
    public string $mail_from_address = '';

    #[Validate('nullable|string|max:255')]
    public string $mail_from_name = '';

    #[Validate('nullable|email|max:255')]
    public string $admin_notification_email = '';

    /** @return array<string, string> */
    protected function validationAttributes(): array
    {
        return [
            'mail_host' => 'SMTP host',
            'mail_port' => 'port',
            'mail_username' => 'username',
            'mail_password' => 'password',
            'mail_encryption' => 'encryption',
            'mail_from_address' => 'from address',
            'mail_from_name' => 'from name',
            'admin_notification_email' => 'feedback notification email',
        ];
    }

    public function mount(): void
    {
        $this->mail_host = (string) Setting::get('mail_host', '');
        $this->mail_port = (int) Setting::get('mail_port', '587');
        $this->mail_username = (string) Setting::get('mail_username', '');
        $this->mail_password = (string) Setting::get('mail_password', '');
        $this->mail_encryption = (string) Setting::get('mail_encryption', 'tls');
        $this->mail_from_address = (string) Setting::get('mail_from_address', '');
        $this->mail_from_name = (string) Setting::get('mail_from_name', '');
        $this->admin_notification_email = (string) Setting::get('admin_notification_email', '');
    }

    public function save(): void
    {
        $this->validate();

        foreach ([
            'mail_host', 'mail_port', 'mail_username', 'mail_password',
            'mail_encryption', 'mail_from_address', 'mail_from_name',
            'admin_notification_email',
        ] as $key) {
            Setting::set($key, (string) $this->{$key});
        }

        session()->flash('mail-saved', 'Mail settings saved.');
    }

    public function sendTest(): void
    {
        $this->save();

        $recipient = $this->admin_notification_email ?: Auth::user()->email;

        try {
            Mail::raw('This is a test email from your Report Generator admin panel. SMTP is configured correctly.', function ($message) use ($recipient) {
                $message->to($recipient)->subject('Report Generator — SMTP test');
            });
            session()->flash('mail-saved', "Test email sent to {$recipient}.");
        } catch (\Throwable $e) {
            session()->flash('mail-error', 'Could not send: '.$e->getMessage());
        }
    }
}; ?>

@php($title = 'Mail settings')

<div class="max-w-2xl space-y-6">
    <x-validation-popup />

    @if (session('mail-saved'))
        <div class="rounded-md bg-green-50 px-4 py-3 text-sm font-medium text-green-800 ring-1 ring-green-200">{{ session('mail-saved') }}</div>
    @endif
    @if (session('mail-error'))
        <div class="rounded-md bg-red-50 px-4 py-3 text-sm font-medium text-red-800 ring-1 ring-red-200">{{ session('mail-error') }}</div>
    @endif

    <form wire:submit="save" class="space-y-5 rounded-lg bg-white p-6 shadow-sm ring-1 ring-gray-200">
        <p class="text-sm text-gray-500">Outgoing email (feedback notifications, test messages) is sent through this SMTP server.</p>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-gray-700">SMTP host</label>
                <input type="text" wire:model="mail_host" placeholder="e.g. smtp.gmail.com" class="mt-1 block w-full rounded-md px-3 py-2 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                @error('mail_host') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Port</label>
                <input type="number" wire:model="mail_port" placeholder="587" class="mt-1 block w-full rounded-md px-3 py-2 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                @error('mail_port') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Encryption</label>
                <select wire:model="mail_encryption" class="mt-1 block w-full rounded-md px-3 py-2 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="tls">TLS / STARTTLS</option>
                    <option value="ssl">SSL</option>
                    <option value="none">None</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Username</label>
                <input type="text" wire:model="mail_username" autocomplete="off" class="mt-1 block w-full rounded-md px-3 py-2 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                @error('mail_username') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Password</label>
                <input type="password" wire:model="mail_password" autocomplete="new-password" class="mt-1 block w-full rounded-md px-3 py-2 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                @error('mail_password') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">From address</label>
                <input type="email" wire:model="mail_from_address" placeholder="noreply@yourdomain.com" class="mt-1 block w-full rounded-md px-3 py-2 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                @error('mail_from_address') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">From name</label>
                <input type="text" wire:model="mail_from_name" placeholder="Report Generator" class="mt-1 block w-full rounded-md px-3 py-2 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                @error('mail_from_name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-gray-700">Feedback notification email</label>
                <input type="email" wire:model="admin_notification_email" placeholder="admin@admin.com" class="mt-1 block w-full rounded-md px-3 py-2 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <p class="mt-1 text-xs text-gray-500">Where user feedback is delivered.</p>
                @error('admin_notification_email') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="flex items-center justify-end gap-3 border-t border-gray-200 pt-5">
            <button type="button" wire:click="sendTest" class="rounded-md bg-white px-4 py-2 text-sm font-semibold text-gray-900 ring-1 ring-gray-300 hover:bg-gray-50">Save &amp; send test</button>
            <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">Save settings</button>
        </div>
    </form>
</div>
