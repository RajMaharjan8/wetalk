<?php

use App\Mail\FeedbackSubmitted;
use App\Models\Feedback;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

it('stores feedback and emails the admin notification address', function () {
    Mail::fake();
    Setting::set('admin_notification_email', 'inbox@example.com');

    $user = loginAsTestUser();

    Livewire::test('feedback')
        ->set('fb_working', 'The cover generator is great.')
        ->set('fb_not_working', 'Image upload is slow.')
        ->call('sendFeedback')
        ->assertHasNoErrors();

    $feedback = Feedback::first();

    expect($feedback)->not->toBeNull()
        ->and($feedback->user_id)->toBe($user->id)
        ->and($feedback->working)->toBe('The cover generator is great.');

    Mail::assertSent(FeedbackSubmitted::class, function ($mail) {
        return $mail->hasTo('inbox@example.com');
    });
});

it('requires at least one feedback field', function () {
    loginAsTestUser();

    Livewire::test('feedback')
        ->call('sendFeedback')
        ->assertHasErrors('fb_working');

    expect(Feedback::count())->toBe(0);
});

it('attaches uploaded images and stores them', function () {
    Mail::fake();
    Storage::fake('public');
    loginAsTestUser();

    Livewire::test('feedback')
        ->set('fb_working', 'Looks great.')
        ->set('fb_images', [
            UploadedFile::fake()->image('shot1.png'),
            UploadedFile::fake()->image('shot2.png'),
        ])
        ->call('sendFeedback')
        ->assertHasNoErrors();

    $feedback = Feedback::firstOrFail();
    expect($feedback->images)->toHaveCount(2);

    foreach ($feedback->images as $path) {
        Storage::disk('public')->assertExists($path);
    }
});

it('rejects more images than the configured limit', function () {
    Storage::fake('public');
    Setting::set('feedback_image_limit', '3');
    loginAsTestUser();

    Livewire::test('feedback')
        ->set('fb_working', 'Too many shots.')
        ->set('fb_images', [
            UploadedFile::fake()->image('1.png'),
            UploadedFile::fake()->image('2.png'),
            UploadedFile::fake()->image('3.png'),
            UploadedFile::fake()->image('4.png'),
        ])
        ->call('sendFeedback')
        ->assertHasErrors('fb_images');

    expect(Feedback::count())->toBe(0);
});

it('blocks more than the configured number of feedback per day', function () {
    Mail::fake();
    Setting::set('feedback_daily_limit', '2');
    $user = loginAsTestUser();

    Feedback::create(['user_id' => $user->id, 'working' => 'first']);
    Feedback::create(['user_id' => $user->id, 'working' => 'second']);

    Livewire::test('feedback')
        ->set('fb_working', 'One more please.')
        ->call('sendFeedback')
        ->assertHasErrors('fb_working');

    expect(Feedback::where('user_id', $user->id)->count())->toBe(2);
});

it('lets an admin change the feedback limits', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($admin);

    Livewire::test('pages::admin.feedback')
        ->set('feedback_image_limit', 5)
        ->set('feedback_daily_limit', 4)
        ->call('saveLimits')
        ->assertHasNoErrors();

    expect(Feedback::imageLimit())->toBe(5)
        ->and(Feedback::dailyLimit())->toBe(4);
});
