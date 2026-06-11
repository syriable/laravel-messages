<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use Syriable\LaravelMessages\Exceptions\DeleteNotAllowedException;
use Syriable\LaravelMessages\Exceptions\FeatureDisabledException;
use Syriable\LaravelMessages\Facades\Messages;
use Syriable\LaravelMessages\Models\Message;

it('hides a deleted conversation from the deleter only', function () {
    $alice = user();
    $bob = user();

    $alice->sendMessageTo($bob, 'one');
    $bob->sendMessageTo($alice, 'two');

    $conversation = $alice->conversationWith($bob);

    Messages::deleteConversationFor($conversation, $alice);

    // Alice: gone completely. Bob: untouched, full history.
    expect($alice->conversationInbox()->count())->toBe(0)
        ->and($conversation->messagesFor($alice)->count())->toBe(0)
        ->and($bob->conversationInbox()->count())->toBe(1)
        ->and($conversation->messagesFor($bob)->count())->toBe(2);
});

it('resurfaces the conversation on a new message while keeping old messages hidden', function () {
    $alice = user();
    $bob = user();

    $alice->sendMessageTo($bob, 'old 1');
    $bob->sendMessageTo($alice, 'old 2');

    $conversation = $alice->conversationWith($bob);

    Messages::deleteConversationFor($conversation, $alice);

    expect($alice->conversationInbox()->count())->toBe(0);

    $this->travel(2)->seconds();

    $bob->sendMessageTo($alice, 'brand new');

    // A "new" conversation appears for Alice containing only the new message.
    expect($alice->conversationInbox()->count())->toBe(1)
        ->and($conversation->messagesFor($alice)->pluck('body')->all())->toBe(['brand new'])
        ->and($conversation->messagesFor($bob)->count())->toBe(3);
});

it('deletes a single message for one user only', function () {
    $alice = user();
    $bob = user();

    $message = $alice->sendMessageTo($bob, 'oops');

    Messages::deleteMessageFor($message, $alice);

    expect($message->isDeletedFor($alice))->toBeTrue()
        ->and($message->isDeletedFor($bob))->toBeFalse()
        ->and($message->fresh())->not->toBeNull();
});

it('excludes per-user deleted messages from unread counts', function () {
    $alice = user();
    $bob = user();

    $message = $alice->sendMessageTo($bob, 'spam-ish');

    expect($bob->unreadMessagesCount())->toBe(1);

    Messages::deleteMessageFor($message, $bob);

    expect($bob->unreadMessagesCount())->toBe(0);
});

it('refuses permanent deletes unless enabled', function () {
    $alice = user();
    $bob = user();

    $message = $alice->sendMessageTo($bob, 'hello');

    Messages::deleteMessagePermanently($message, $alice);
})->throws(FeatureDisabledException::class);

it('permanently deletes a message and its attachments when enabled', function () {
    config()->set('laravel-messages.deletes.allow_permanent', true);
    Storage::fake('local');

    $alice = user();
    $bob = user();

    $message = $alice->sendMessageTo($bob, 'sensitive', [fakePdf()]);
    $path = $message->attachments->first()->path;

    Storage::disk('local')->assertExists($path);

    Messages::deleteMessagePermanently($message, $alice);

    expect(Message::withTrashed()->whereKey($message->getKey())->exists())->toBeFalse();
    Storage::disk('local')->assertMissing($path);
});

it('only lets the sender permanently delete', function () {
    config()->set('laravel-messages.deletes.allow_permanent', true);

    $alice = user();
    $bob = user();

    $message = $alice->sendMessageTo($bob, 'hello');

    Messages::deleteMessagePermanently($message, $bob);
})->throws(DeleteNotAllowedException::class);

it('does not restore a deleted conversation when the deleter sends a new message', function () {
    $alice = user();
    $bob = user();

    $alice->sendMessageTo($bob, 'history');
    $conversation = $alice->conversationWith($bob);

    Messages::deleteConversationFor($conversation, $alice);

    $this->travel(2)->seconds();

    $alice->sendMessageTo($bob, 'starting fresh');

    expect($conversation->messagesFor($alice)->pluck('body')->all())->toBe(['starting fresh'])
        ->and($conversation->messagesFor($bob)->count())->toBe(2);
});
