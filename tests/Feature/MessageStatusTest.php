<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Syriable\LaravelMessages\Events\ConversationArchived;
use Syriable\LaravelMessages\Events\MessageRead;
use Syriable\LaravelMessages\Events\MessageStarred;
use Syriable\LaravelMessages\Exceptions\NotAParticipantException;
use Syriable\LaravelMessages\Facades\Messages;

it('tracks read state per user', function () {
    $alice = user();
    $bob = user();

    $message = $alice->sendMessageTo($bob, 'hello');

    expect($message->isReadBy($alice))->toBeTrue() // senders implicitly read their own messages
        ->and($message->isReadBy($bob))->toBeFalse()
        ->and($bob->unreadMessagesCount())->toBe(1)
        ->and($alice->unreadMessagesCount())->toBe(0);

    Messages::markRead($message, $bob);

    expect($message->isReadBy($bob))->toBeTrue()
        ->and($bob->unreadMessagesCount())->toBe(0);
});

it('can mark a message unread again', function () {
    $alice = user();
    $bob = user();

    $message = $alice->sendMessageTo($bob, 'hello');

    Messages::markRead($message, $bob);
    Messages::markUnread($message, $bob);

    expect($message->isReadBy($bob))->toBeFalse()
        ->and($bob->unreadMessagesCount())->toBe(1);
});

it('marks a whole conversation read', function () {
    $alice = user();
    $bob = user();

    $alice->sendMessageTo($bob, 'one');
    $alice->sendMessageTo($bob, 'two');
    $conversation = $alice->conversationWith($bob);

    expect($conversation->unreadCountFor($bob))->toBe(2);

    Messages::markConversationRead($conversation, $bob);

    expect($conversation->unreadCountFor($bob))->toBe(0)
        ->and($conversation->participantFor($bob)->last_read_at)->not->toBeNull();
});

it('stars messages per user without affecting the other participant', function () {
    $alice = user();
    $bob = user();

    $message = $alice->sendMessageTo($bob, 'important');

    Messages::star($message, $alice);

    expect($message->isStarredBy($alice))->toBeTrue()
        ->and($message->isStarredBy($bob))->toBeFalse()
        ->and($alice->starredMessages()->count())->toBe(1)
        ->and($bob->starredMessages()->count())->toBe(0);

    Messages::unstar($message, $alice);

    expect($message->isStarredBy($alice))->toBeFalse();
});

it('archives conversations per user without affecting the other participant', function () {
    $alice = user();
    $bob = user();

    $alice->sendMessageTo($bob, 'hello');
    $conversation = $alice->conversationWith($bob);

    Messages::archive($conversation, $alice);

    expect($alice->conversationInbox()->count())->toBe(0)
        ->and($alice->archivedConversations()->count())->toBe(1)
        ->and($bob->conversationInbox()->count())->toBe(1)
        ->and($bob->archivedConversations()->count())->toBe(0);

    Messages::unarchive($conversation, $alice);

    expect($alice->conversationInbox()->count())->toBe(1);
});

it('moves an archived conversation back to the inbox when a new message arrives', function () {
    $alice = user();
    $bob = user();

    $alice->sendMessageTo($bob, 'hello');
    $conversation = $alice->conversationWith($bob);

    Messages::archive($conversation, $alice);
    $bob->sendMessageTo($alice, 'are you there?');

    expect($alice->conversationInbox()->count())->toBe(1);
});

it('keeps archived conversations archived when configured to', function () {
    config()->set('laravel-messages.conversations.unarchive_on_new_message', false);

    $alice = user();
    $bob = user();

    $alice->sendMessageTo($bob, 'hello');
    Messages::archive($alice->conversationWith($bob), $alice);
    $bob->sendMessageTo($alice, 'ping');

    expect($alice->conversationInbox()->count())->toBe(0)
        ->and($alice->archivedConversations()->count())->toBe(1);
});

it('dispatches status events', function () {
    $alice = user();
    $bob = user();

    $message = $alice->sendMessageTo($bob, 'hello');
    $conversation = $alice->conversationWith($bob);

    Event::fake([MessageRead::class, MessageStarred::class, ConversationArchived::class]);

    Messages::markRead($message, $bob);
    Messages::star($message, $bob);
    Messages::archive($conversation, $bob);

    Event::assertDispatched(MessageRead::class, fn ($e) => (string) $e->userKey === (string) $bob->getKey());
    Event::assertDispatched(MessageStarred::class);
    Event::assertDispatched(ConversationArchived::class);
});

it('refuses status changes from non-participants', function () {
    $alice = user();
    $bob = user();
    $mallory = user();

    $message = $alice->sendMessageTo($bob, 'private');

    Messages::markRead($message, $mallory);
})->throws(NotAParticipantException::class);
