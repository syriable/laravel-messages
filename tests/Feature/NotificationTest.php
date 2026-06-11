<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Notification;
use Syriable\LaravelMessages\Facades\Messages;
use Syriable\LaravelMessages\Notifications\NewMessageNotification;

beforeEach(function () {
    Notification::fake();
});

it('notifies the recipient of a new message when enabled', function () {
    config()->set('laravel-messages.notifications.new_message.enabled', true);

    $alice = user();
    $bob = user();

    $alice->sendMessageTo($bob, 'ping');

    Notification::assertSentTo($bob, NewMessageNotification::class);
    Notification::assertNothingSentTo($alice);
});

it('sends no notification when disabled (the default)', function () {
    $alice = user();
    $bob = user();

    $alice->sendMessageTo($bob, 'ping');

    Notification::assertNothingSent();
});

it('suppresses notifications for muted conversations', function () {
    config()->set('laravel-messages.notifications.new_message.enabled', true);

    $alice = user();
    $bob = user();

    Messages::mute($alice->conversationWith($bob), $bob);

    $alice->sendMessageTo($bob, 'ping');

    Notification::assertNothingSent();
});

it('includes a useful database payload', function () {
    config()->set('laravel-messages.notifications.new_message.enabled', true);

    $alice = user();
    $bob = user();

    $message = $alice->sendMessageTo($bob, 'a fairly long message body for preview purposes');

    Notification::assertSentTo($bob, NewMessageNotification::class, function (NewMessageNotification $notification) use ($message, $bob): bool {
        $payload = $notification->toArray($bob);

        return (string) $payload['message_id'] === (string) $message->getKey()
            && $payload['preview'] !== ''
            && $payload['has_attachments'] === false;
    });
});
