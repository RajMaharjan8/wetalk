<?php

use App\Mail\FeedbackSubmitted;
use App\Models\Feedback;
use App\Models\Setting;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    #[Validate('nullable|string|max:2000')]
    public string $fb_working = '';

    #[Validate('nullable|string|max:2000')]
    public string $fb_not_working = '';

    /** @var list<\Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
    public array $fb_images = [];

    /** @return array<string, string> */
    protected function validationAttributes(): array
    {
        return [
            'fb_working' => "what's working",
            'fb_not_working' => "what's not working",
            'fb_images.*' => 'image',
        ];
    }

    public function getFeedbackImageLimitProperty(): int
    {
        return Feedback::imageLimit();
    }

    public function getFeedbackDailyLimitProperty(): int
    {
        return Feedback::dailyLimit();
    }

    /** Feedback submissions the user has left today. */
    public function getFeedbackLeftTodayProperty(): int
    {
        $used = Feedback::where('user_id', Auth::id())
            ->whereDate('created_at', today())
            ->count();

        return max(0, $this->feedbackDailyLimit - $used);
    }

    public function removeFeedbackImage(int $index): void
    {
        unset($this->fb_images[$index]);
        $this->fb_images = array_values($this->fb_images);
    }

    /**
     * Store the user's feedback and email it to the configured admin address
     * via the admin-managed SMTP settings.
     */
    public function sendFeedback(): void
    {
        abort_unless(\App\Support\FeatureSettings::feedbackEnabled(), 403);

        if ($this->feedbackLeftToday <= 0) {
            $this->addError('fb_working', __("You've reached today's feedback limit (:limit). Please try again tomorrow.", ['limit' => $this->feedbackDailyLimit]));

            return;
        }

        $this->validate([
            'fb_images' => 'array|max:'.$this->feedbackImageLimit,
            'fb_images.*' => 'image|max:5120',
        ]);

        $this->validate();

        if (trim($this->fb_working) === '' && trim($this->fb_not_working) === '') {
            $this->addError('fb_working', __('Please tell us what is working or what is not.'));

            return;
        }

        $paths = [];

        foreach ($this->fb_images as $image) {
            $paths[] = $image->store('feedback', 'public');
        }

        $feedback = Feedback::create([
            'user_id' => Auth::id(),
            'working' => $this->fb_working ?: null,
            'not_working' => $this->fb_not_working ?: null,
            'images' => $paths ?: null,
        ]);

        $recipient = Setting::get('admin_notification_email', config('mail.from.address'));

        if ($recipient) {
            try {
                Mail::to($recipient)->send(new FeedbackSubmitted($feedback->load('user')));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        $this->reset('fb_working', 'fb_not_working', 'fb_images');

        $this->dispatch('feedback-sent');
    }
}; ?>

<div
    x-data="{ open: false }"
    x-on:open-feedback.window="open = true"
    x-on:feedback-sent.window="open = false"
>
    <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" x-on:keydown.escape.window="open = false">
        <div class="w-full max-w-lg rounded-lg bg-white p-6 shadow-xl ring-1 ring-black/5 dark:bg-gray-800 dark:ring-white/10" x-on:click.outside="open = false">
            <div class="flex items-start justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ __('Send feedback') }}</h2>
                    <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">{{ __('Tell us how the report generator is working for you.') }}</p>
                </div>
                <button type="button" x-on:click="open = false" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200" title="{{ __('Cancel') }}">&times;</button>
            </div>

            <form wire:submit="sendFeedback" class="mt-4 space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __("What's working for you?") }}</label>
                    <textarea wire:model="fb_working" rows="3" placeholder="{{ __('Things you like or that work well…') }}" class="mt-1 block w-full rounded-md bg-white px-3 py-2 text-sm text-gray-900 ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-gray-900 dark:text-gray-100 dark:ring-gray-600"></textarea>
                    @error('fb_working') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __("What's not working?") }}</label>
                    <textarea wire:model="fb_not_working" rows="3" placeholder="{{ __("Problems, bugs, or things you'd change…") }}" class="mt-1 block w-full rounded-md bg-white px-3 py-2 text-sm text-gray-900 ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-gray-900 dark:text-gray-100 dark:ring-gray-600"></textarea>
                    @error('fb_not_working') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>

                <div>
                    <div class="flex items-center justify-between">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Screenshots') }} <span class="font-normal text-gray-400">{{ __('(optional)') }}</span></label>
                        <span class="text-xs text-gray-400">{{ count($fb_images) }}/{{ $this->feedbackImageLimit }}</span>
                    </div>

                    @if (count($fb_images) < $this->feedbackImageLimit)
                        <label class="mt-1 flex cursor-pointer items-center justify-center gap-2 rounded-md border border-dashed border-gray-300 px-3 py-3 text-sm text-gray-500 hover:border-indigo-400 hover:text-indigo-600 dark:border-gray-600 dark:text-gray-400">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5z" /></svg>
                            <span wire:loading.remove wire:target="fb_images">{{ __('Add image') }}</span>
                            <span wire:loading wire:target="fb_images">{{ __('Uploading…') }}</span>
                            <input type="file" wire:model="fb_images" multiple accept="image/*" class="hidden">
                        </label>
                    @endif
                    @error('fb_images.*') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    @error('fb_images') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror

                    @if (count($fb_images))
                        <div class="mt-2 grid grid-cols-3 gap-2">
                            @foreach ($fb_images as $index => $image)
                                <div wire:key="fb-img-{{ $index }}" class="group relative">
                                    <img src="{{ $image->temporaryUrl() }}" alt="" class="h-20 w-full rounded-md object-cover ring-1 ring-gray-200 dark:ring-gray-600">
                                    <button type="button" wire:click="removeFeedbackImage({{ $index }})" class="absolute -right-1.5 -top-1.5 flex h-5 w-5 items-center justify-center rounded-full bg-gray-900 text-xs text-white shadow hover:bg-red-600" title="{{ __('Delete') }}">&times;</button>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="flex items-center justify-between gap-3">
                    <span class="text-xs {{ $this->feedbackLeftToday <= 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-400' }}">
                        @if ($this->feedbackLeftToday <= 0)
                            {{ __('Daily limit reached — try again tomorrow.') }}
                        @else
                            {{ __(':left of :limit feedbacks left today.', ['left' => $this->feedbackLeftToday, 'limit' => $this->feedbackDailyLimit]) }}
                        @endif
                    </span>
                    <div class="flex gap-3">
                        <button type="button" x-on:click="open = false" class="rounded-md bg-white px-4 py-2 text-sm font-semibold text-gray-700 ring-1 ring-gray-300 hover:bg-gray-50 dark:bg-gray-700 dark:text-gray-200 dark:ring-gray-600 dark:hover:bg-gray-600">{{ __('Cancel') }}</button>
                        <button type="submit" @disabled($this->feedbackLeftToday <= 0) class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-50">
                            <span wire:loading.remove wire:target="sendFeedback">{{ __('Send feedback') }}</span>
                            <span wire:loading wire:target="sendFeedback">{{ __('Sending…') }}</span>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
