<?php

use App\Models\Feedback;
use App\Models\Setting;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::admin')] class extends Component
{
    use WithPagination;

    #[Validate('required|integer|min:0|max:10')]
    public int $feedback_image_limit = Feedback::DEFAULT_IMAGE_LIMIT;

    #[Validate('required|integer|min:1|max:50')]
    public int $feedback_daily_limit = Feedback::DEFAULT_DAILY_LIMIT;

    public function mount(): void
    {
        $this->feedback_image_limit = Feedback::imageLimit();
        $this->feedback_daily_limit = Feedback::dailyLimit();
    }

    public function saveLimits(): void
    {
        $this->validate();

        Setting::set('feedback_image_limit', (string) $this->feedback_image_limit);
        Setting::set('feedback_daily_limit', (string) $this->feedback_daily_limit);

        session()->flash('limits-saved', 'Feedback limits saved.');
    }

    public function delete(int $id): void
    {
        Feedback::whereKey($id)->delete();
    }

    public function getFeedbackProperty()
    {
        return Feedback::with('user')->latest()->paginate(20);
    }
}; ?>

@php($title = 'Feedback')

<div class="space-y-6">
    <x-validation-popup />

    {{-- Limits — controls the user-facing feedback form --}}
    <form wire:submit="saveLimits" class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-gray-200">
        <h2 class="text-base font-semibold text-gray-900">Feedback limits</h2>
        <p class="mt-1 text-xs text-gray-500">Applies to every user's feedback form.</p>

        @if (session('limits-saved'))
            <div class="mt-3 rounded-md bg-green-50 px-4 py-2 text-sm font-medium text-green-800 ring-1 ring-green-200">{{ session('limits-saved') }}</div>
        @endif

        <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <label class="block text-sm font-medium text-gray-700">Max images per feedback</label>
                <input type="number" min="0" max="10" wire:model="feedback_image_limit" class="mt-1 block w-full rounded-md px-3 py-2 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                @error('feedback_image_limit') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Max feedback per day (per user)</label>
                <input type="number" min="1" max="50" wire:model="feedback_daily_limit" class="mt-1 block w-full rounded-md px-3 py-2 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                @error('feedback_daily_limit') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="mt-4 flex justify-end">
            <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">Save limits</button>
        </div>
    </form>

    @if ($this->feedback->isEmpty())
        <div class="rounded-lg bg-white p-12 text-center text-sm text-gray-500 shadow-sm ring-1 ring-gray-200">No feedback has been submitted yet.</div>
    @else
        <div class="space-y-4">
            @foreach ($this->feedback as $item)
                <div wire:key="fb-{{ $item->id }}" class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-gray-200">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-sm font-semibold text-gray-900">{{ $item->user?->name ?? 'Anonymous' }}</p>
                            <p class="text-xs text-gray-500">{{ $item->user?->email }} &middot; {{ $item->created_at->diffForHumans() }}</p>
                        </div>
                        <button wire:click="delete({{ $item->id }})" wire:confirm="Delete this feedback?" class="text-xs font-medium text-red-600 hover:text-red-700">Delete</button>
                    </div>
                    @if ($item->working)
                        <div class="mt-3 rounded-md bg-green-50 px-3 py-2 text-sm text-green-900"><span class="font-semibold">Working well:</span> {{ $item->working }}</div>
                    @endif
                    @if ($item->not_working)
                        <div class="mt-2 rounded-md bg-red-50 px-3 py-2 text-sm text-red-900"><span class="font-semibold">Not working:</span> {{ $item->not_working }}</div>
                    @endif
                    @if (!empty($item->images))
                        <div class="mt-3 flex flex-wrap gap-2">
                            @foreach ($item->imageUrls() as $url)
                                <a href="{{ $url }}" target="_blank" rel="noopener">
                                    <img src="{{ $url }}" alt="Feedback screenshot" class="h-24 w-24 rounded-md object-cover ring-1 ring-gray-200 hover:ring-indigo-400">
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforeach
        </div>

        <div>{{ $this->feedback->links() }}</div>
    @endif
</div>
