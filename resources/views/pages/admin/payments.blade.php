<?php

use App\Models\Setting;
use App\Support\Payments\PaymentSettings;
use Illuminate\Support\Facades\Crypt;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::admin')] class extends Component
{
    // eSewa
    public bool $esewa_enabled = false;

    public string $esewa_mode = 'test';

    public string $esewa_merchant_id = '';

    public string $esewa_product_code = '';

    public string $esewa_secret_key = '';

    public string $esewa_merchant_secret = '';

    public bool $esewa_has_secret_key = false;

    public bool $esewa_has_merchant_secret = false;

    // Khalti
    public bool $khalti_enabled = false;

    public string $khalti_mode = 'test';

    public string $khalti_public_key = '';

    public string $khalti_secret_key = '';

    public bool $khalti_has_secret_key = false;

    // Pricing
    public string $download_price = '0';

    public function mount(): void
    {
        $this->esewa_enabled = Setting::get('esewa_enabled') === '1';
        $this->esewa_mode = (string) Setting::get('esewa_mode', 'test');
        $this->esewa_merchant_id = (string) Setting::get('esewa_merchant_id', '');
        $this->esewa_product_code = (string) Setting::get('esewa_product_code', '');
        $this->esewa_has_secret_key = filled(Setting::get('esewa_secret_key'));
        $this->esewa_has_merchant_secret = filled(Setting::get('esewa_merchant_secret'));

        $this->khalti_enabled = Setting::get('khalti_enabled') === '1';
        $this->khalti_mode = (string) Setting::get('khalti_mode', 'test');
        $this->khalti_public_key = (string) Setting::get('khalti_public_key', '');
        $this->khalti_has_secret_key = filled(Setting::get('khalti_secret_key'));

        $this->download_price = (string) PaymentSettings::price();
    }

    public function saveEsewa(): void
    {
        $this->validate([
            'esewa_mode' => 'required|in:test,live',
            'esewa_merchant_id' => 'nullable|string|max:255',
            'esewa_product_code' => [$this->esewa_enabled ? 'required' : 'nullable', 'string', 'max:255'],
            'esewa_secret_key' => 'nullable|string|max:255',
            'esewa_merchant_secret' => 'nullable|string|max:255',
        ]);

        Setting::set('esewa_enabled', $this->esewa_enabled ? '1' : '0');
        Setting::set('esewa_mode', $this->esewa_mode);
        Setting::set('esewa_merchant_id', $this->esewa_merchant_id);
        Setting::set('esewa_product_code', $this->esewa_product_code);
        $this->saveSecret('esewa_secret_key');
        $this->saveSecret('esewa_merchant_secret');

        $this->esewa_secret_key = '';
        $this->esewa_merchant_secret = '';
        $this->esewa_has_secret_key = filled(Setting::get('esewa_secret_key'));
        $this->esewa_has_merchant_secret = filled(Setting::get('esewa_merchant_secret'));

        session()->flash('payments-saved', 'eSewa settings saved.');
    }

    public function saveKhalti(): void
    {
        $this->validate([
            'khalti_mode' => 'required|in:test,live',
            'khalti_public_key' => [$this->khalti_enabled ? 'required' : 'nullable', 'string', 'max:255'],
            'khalti_secret_key' => 'nullable|string|max:255',
        ]);

        Setting::set('khalti_enabled', $this->khalti_enabled ? '1' : '0');
        Setting::set('khalti_mode', $this->khalti_mode);
        Setting::set('khalti_public_key', $this->khalti_public_key);
        $this->saveSecret('khalti_secret_key');

        $this->khalti_secret_key = '';
        $this->khalti_has_secret_key = filled(Setting::get('khalti_secret_key'));

        session()->flash('payments-saved', 'Khalti settings saved.');
    }

    public function savePricing(): void
    {
        $this->validate([
            'download_price' => 'required|numeric|min:0|max:1000000',
        ]);

        Setting::set('download_price', $this->download_price);

        session()->flash('payments-saved', 'Download price saved.');
    }

    /**
     * Persist a secret only when a new value was entered; encrypt it at rest.
     * A blank field keeps the existing stored value.
     */
    private function saveSecret(string $key): void
    {
        $value = trim((string) $this->{$key});

        if ($value !== '') {
            Setting::set($key, Crypt::encryptString($value));
        }
    }
}; ?>

@php($title = 'Payments')

<div class="max-w-3xl space-y-6">
    <x-validation-popup />

    @if (session('payments-saved'))
        <div class="rounded-md bg-green-50 px-4 py-3 text-sm font-medium text-green-800 ring-1 ring-green-200">{{ session('payments-saved') }}</div>
    @endif

    <p class="text-sm text-gray-500">Enable a gateway to charge users before they download a report. With both gateways off, downloads are free. Charges use the price below.</p>

    {{-- ============ Pricing ============ --}}
    <form wire:submit="savePricing" class="space-y-4 rounded-lg bg-white p-6 shadow-sm ring-1 ring-gray-200">
        <h2 class="text-base font-semibold text-gray-900">Download price</h2>
        <div class="max-w-xs">
            <label class="block text-sm font-medium text-gray-700">Price (NPR)</label>
            <div class="mt-1 flex rounded-md ring-1 ring-gray-300 focus-within:ring-2 focus-within:ring-indigo-500">
                <span class="inline-flex items-center rounded-l-md border-r border-gray-200 bg-gray-50 px-3 text-sm text-gray-500">Rs.</span>
                <input type="number" step="0.01" min="0" wire:model="download_price" class="block w-full rounded-r-md px-3 py-2 text-sm focus:outline-none">
            </div>
            <p class="mt-1 text-xs text-gray-500">Charged per download. eSewa and Khalti settle in Nepali Rupees.</p>
            @error('download_price') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>
        <div class="flex justify-end border-t border-gray-200 pt-4">
            <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">Save price</button>
        </div>
    </form>

    {{-- ============ eSewa ============ --}}
    <form wire:submit="saveEsewa" class="space-y-5 rounded-lg bg-white p-6 shadow-sm ring-1 ring-gray-200">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <h2 class="text-base font-semibold text-gray-900">eSewa Payment</h2>
                @if ($esewa_enabled)
                    <span class="rounded-full bg-green-50 px-2.5 py-0.5 text-xs font-medium text-green-700 ring-1 ring-green-200">Active</span>
                @else
                    <span class="rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-500">Off</span>
                @endif
            </div>
            <label class="relative inline-flex cursor-pointer items-center">
                <input type="checkbox" wire:model.live="esewa_enabled" class="peer sr-only">
                <div class="h-6 w-11 rounded-full bg-gray-200 after:absolute after:left-0.5 after:top-0.5 after:h-5 after:w-5 after:rounded-full after:bg-white after:shadow after:transition-all peer-checked:bg-indigo-600 peer-checked:after:translate-x-5"></div>
            </label>
        </div>

        <div class="rounded-md bg-amber-50 px-4 py-3 text-xs text-amber-800 ring-1 ring-amber-200">
            Secret keys are stored securely and never returned in responses. Leave secret fields blank to keep the existing values.
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <label class="block text-sm font-medium text-gray-700">Merchant ID</label>
                <input type="text" wire:model="esewa_merchant_id" class="mt-1 block w-full rounded-md px-3 py-2 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                @error('esewa_merchant_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Product Code <span class="text-red-500">*</span></label>
                <input type="text" wire:model="esewa_product_code" placeholder="e.g. EPAYTEST" class="mt-1 block w-full rounded-md px-3 py-2 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                @error('esewa_product_code') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div x-data="{ show: false }">
                <label class="block text-sm font-medium text-gray-700">Secret Key <span class="text-xs font-normal text-gray-400">({{ $esewa_has_secret_key ? 'blank = keep existing' : 'not set' }})</span></label>
                <div class="relative mt-1">
                    <input :type="show ? 'text' : 'password'" wire:model="esewa_secret_key" autocomplete="off" placeholder="Enter new secret key to replace" class="block w-full rounded-md px-3 py-2 pr-10 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <button type="button" @click="show = !show" class="absolute inset-y-0 right-0 flex items-center px-3 text-gray-400 hover:text-gray-600" x-text="show ? 'Hide' : 'Show'"></button>
                </div>
                @error('esewa_secret_key') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div x-data="{ show: false }">
                <label class="block text-sm font-medium text-gray-700">Merchant Secret <span class="text-xs font-normal text-gray-400">({{ $esewa_has_merchant_secret ? 'blank = keep existing' : 'not set' }})</span></label>
                <div class="relative mt-1">
                    <input :type="show ? 'text' : 'password'" wire:model="esewa_merchant_secret" autocomplete="off" placeholder="Enter new merchant secret to replace" class="block w-full rounded-md px-3 py-2 pr-10 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <button type="button" @click="show = !show" class="absolute inset-y-0 right-0 flex items-center px-3 text-gray-400 hover:text-gray-600" x-text="show ? 'Hide' : 'Show'"></button>
                </div>
                @error('esewa_merchant_secret') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Mode <span class="text-red-500">*</span></label>
                <select wire:model="esewa_mode" class="mt-1 block w-full rounded-md px-3 py-2 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="live">Live (Production)</option>
                    <option value="test">Test (Sandbox)</option>
                </select>
            </div>
        </div>

        <div class="flex justify-end border-t border-gray-200 pt-5">
            <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">Update eSewa Settings</button>
        </div>
    </form>

    {{-- ============ Khalti ============ --}}
    <form wire:submit="saveKhalti" class="space-y-5 rounded-lg bg-white p-6 shadow-sm ring-1 ring-gray-200">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <h2 class="text-base font-semibold text-gray-900">Khalti Payment</h2>
                @if ($khalti_enabled)
                    <span class="rounded-full bg-green-50 px-2.5 py-0.5 text-xs font-medium text-green-700 ring-1 ring-green-200">Active</span>
                @else
                    <span class="rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-500">Off</span>
                @endif
            </div>
            <label class="relative inline-flex cursor-pointer items-center">
                <input type="checkbox" wire:model.live="khalti_enabled" class="peer sr-only">
                <div class="h-6 w-11 rounded-full bg-gray-200 after:absolute after:left-0.5 after:top-0.5 after:h-5 after:w-5 after:rounded-full after:bg-white after:shadow after:transition-all peer-checked:bg-indigo-600 peer-checked:after:translate-x-5"></div>
            </label>
        </div>

        <div class="rounded-md bg-amber-50 px-4 py-3 text-xs text-amber-800 ring-1 ring-amber-200">
            Secret keys are stored securely and never returned in responses. Leave the secret key blank to keep the existing value.
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <label class="block text-sm font-medium text-gray-700">Public Key <span class="text-red-500">*</span></label>
                <input type="text" wire:model="khalti_public_key" class="mt-1 block w-full rounded-md px-3 py-2 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                @error('khalti_public_key') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div x-data="{ show: false }">
                <label class="block text-sm font-medium text-gray-700">Secret Key <span class="text-xs font-normal text-gray-400">({{ $khalti_has_secret_key ? 'blank = keep existing' : 'not set' }})</span></label>
                <div class="relative mt-1">
                    <input :type="show ? 'text' : 'password'" wire:model="khalti_secret_key" autocomplete="off" placeholder="Enter new secret key to replace" class="block w-full rounded-md px-3 py-2 pr-10 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <button type="button" @click="show = !show" class="absolute inset-y-0 right-0 flex items-center px-3 text-gray-400 hover:text-gray-600" x-text="show ? 'Hide' : 'Show'"></button>
                </div>
                @error('khalti_secret_key') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Mode <span class="text-red-500">*</span></label>
                <select wire:model="khalti_mode" class="mt-1 block w-full rounded-md px-3 py-2 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="live">Live (Production)</option>
                    <option value="test">Test (Sandbox)</option>
                </select>
            </div>
        </div>

        <div class="flex justify-end border-t border-gray-200 pt-5">
            <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">Update Khalti Settings</button>
        </div>
    </form>
</div>
