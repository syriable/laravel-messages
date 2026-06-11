<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Syriable\LaravelMessages\Contracts\ManagesSpam;
use Syriable\LaravelMessages\Events\UserMarkedAsSpam;
use Syriable\LaravelMessages\Exceptions\MessageDeliveryException;
use Syriable\LaravelMessages\Models\SpamEntry;

it('silently rejects messages from senders marked as spam', function () {
    $alice = user();
    $bob = user();

    $alice->markUserAsSpam($bob, 'unsolicited ads');

    expect(fn () => $bob->sendMessageTo($alice, 'great offer!!!'))
        ->toThrow(MessageDeliveryException::class);
});

it('gives the spammer the exact same generic error as a blocked user', function () {
    $alice = user();
    $bob = user();
    $carol = user();

    $alice->markUserAsSpam($bob);
    $carol->blockUser($bob);

    $spamError = null;
    $blockError = null;

    try {
        $bob->sendMessageTo($alice, 'hi');
    } catch (MessageDeliveryException $e) {
        $spamError = $e->getMessage();
    }

    try {
        $bob->sendMessageTo($carol, 'hi');
    } catch (MessageDeliveryException $e) {
        $blockError = $e->getMessage();
    }

    // Indistinguishable responses: the sender can never tell why (or that)
    // they were rejected.
    expect($spamError)->not->toBeNull()
        ->and($spamError)->toBe($blockError)
        ->and(strtolower((string) $spamError))->not->toContain('spam');
});

it('restores delivery once the spam mark is removed', function () {
    $alice = user();
    $bob = user();

    $alice->markUserAsSpam($bob);
    $alice->unmarkUserAsSpam($bob);

    expect($bob->sendMessageTo($alice, 'reformed')->body)->toBe('reformed');
});

it('moves conversations reported as spam into the spam folder', function () {
    $alice = user();
    $bob = user();

    $bob->sendMessageTo($alice, 'buy now');
    $conversation = $alice->conversationWith($bob);

    app(ManagesSpam::class)->reportConversationAsSpam($alice, $conversation);

    expect($alice->conversationInbox()->count())->toBe(0)
        ->and($alice->spamConversations()->count())->toBe(1)
        // Bob's view is untouched.
        ->and($bob->conversationInbox()->count())->toBe(1)
        ->and($bob->spamConversations()->count())->toBe(0);
});

it('shows conversations with a spam-marked sender in the spam folder', function () {
    $alice = user();
    $bob = user();

    $bob->sendMessageTo($alice, 'buy now');
    $alice->markUserAsSpam($bob);

    expect($alice->conversationInbox()->count())->toBe(0)
        ->and($alice->spamConversations()->count())->toBe(1);
});

it('restores a conversation from the spam folder', function () {
    $alice = user();
    $bob = user();

    $bob->sendMessageTo($alice, 'hello');
    $conversation = $alice->conversationWith($bob);

    $alice->markUserAsSpam($bob);
    app(ManagesSpam::class)->reportConversationAsSpam($alice, $conversation);

    app(ManagesSpam::class)->removeConversationFromSpam($alice, $conversation);

    expect($alice->spamConversations()->count())->toBe(0)
        ->and($alice->conversationInbox()->count())->toBe(1)
        // Removing the conversation from spam also unmarks the sender.
        ->and($bob->sendMessageTo($alice, 'thanks')->body)->toBe('thanks');
});

it('records message-level spam reports without blocking the sender', function () {
    $alice = user();
    $bob = user();

    $message = $bob->sendMessageTo($alice, 'suspicious link');

    app(ManagesSpam::class)->reportMessageAsSpam($alice, $message, 'phishing');

    $entry = SpamEntry::sole();

    expect((string) $entry->message_id)->toBe((string) $message->getKey())
        ->and($entry->reason)->toBe('phishing')
        ->and($entry->spammer_id)->toBeNull()
        // Sender-level delivery is not affected by a message report.
        ->and($bob->sendMessageTo($alice, 'still allowed')->body)->toBe('still allowed');
});

it('dispatches an event when a user is marked as spam', function () {
    Event::fake([UserMarkedAsSpam::class]);

    $alice = user();
    $bob = user();

    $alice->markUserAsSpam($bob);

    Event::assertDispatched(
        UserMarkedAsSpam::class,
        fn (UserMarkedAsSpam $event): bool => (string) $event->entry->spammer_id === (string) $bob->getKey(),
    );
});

it('lets configuration disable spam delivery blocking', function () {
    config()->set('laravel-messages.spam.block_delivery_from_spammers', false);

    $alice = user();
    $bob = user();

    $alice->markUserAsSpam($bob);

    expect($bob->sendMessageTo($alice, 'delivered anyway')->body)->toBe('delivered anyway');
});
