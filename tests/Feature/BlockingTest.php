<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Syriable\LaravelMessages\Events\UserBlocked;
use Syriable\LaravelMessages\Events\UserUnblocked;
use Syriable\LaravelMessages\Exceptions\MessageDeliveryException;
use Syriable\LaravelMessages\Models\SpamEntry;
use Syriable\LaravelMessages\Models\UserBlock;

it('prevents a blocked user from delivering messages', function () {
    $alice = user();
    $bob = user();

    $alice->blockUser($bob);

    expect($alice->hasBlockedUser($bob))->toBeTrue()
        ->and(fn () => $bob->sendMessageTo($alice, 'let me in'))
        ->toThrow(MessageDeliveryException::class);
});

it('returns only a generic failure to the blocked sender', function () {
    $alice = user();
    $bob = user();

    $alice->blockUser($bob);

    try {
        $bob->sendMessageTo($alice, 'hello?');
        $this->fail('Delivery should have been rejected.');
    } catch (MessageDeliveryException $exception) {
        // The message must not leak the reason ("block") in any form.
        expect($exception->getMessage())->toBe(__('laravel-messages::messages.delivery_failed'))
            ->and(strtolower($exception->getMessage()))->not->toContain('block');
    }
});

it('restores delivery after unblocking', function () {
    $alice = user();
    $bob = user();

    $alice->blockUser($bob);
    $alice->unblockUser($bob);

    expect($alice->hasBlockedUser($bob))->toBeFalse()
        ->and($bob->sendMessageTo($alice, 'free again')->body)->toBe('free again');
});

it('keeps existing history visible after a block', function () {
    $alice = user();
    $bob = user();

    $bob->sendMessageTo($alice, 'before the block');
    $alice->blockUser($bob);

    $conversation = $alice->conversationWith($bob);

    expect($conversation->messagesFor($alice)->count())->toBe(1)
        ->and($conversation->messagesFor($bob)->count())->toBe(1);
});

it('dispatches block lifecycle events', function () {
    Event::fake([UserBlocked::class, UserUnblocked::class]);

    $alice = user();
    $bob = user();

    $alice->blockUser($bob);
    $alice->unblockUser($bob);

    Event::assertDispatched(UserBlocked::class);
    Event::assertDispatched(UserUnblocked::class);
});

it('is idempotent and only fires the event on a new block', function () {
    $alice = user();
    $bob = user();

    $alice->blockUser($bob);

    Event::fake([UserBlocked::class]);

    $alice->blockUser($bob);

    Event::assertNotDispatched(UserBlocked::class);
    expect(UserBlock::count())->toBe(1);
});

it('works independently from the spam system', function () {
    $alice = user();
    $bob = user();

    $alice->blockUser($bob);

    expect(SpamEntry::count())->toBe(0);

    $alice->unblockUser($bob);

    // Spam marks survive block/unblock cycles untouched.
    $alice->markUserAsSpam($bob);
    $alice->blockUser($bob);
    $alice->unblockUser($bob);

    expect(SpamEntry::count())->toBe(1);
});

it('can be disabled via configuration', function () {
    config()->set('laravel-messages.blocking.enabled', false);

    $alice = user();
    $bob = user();

    $alice->blockUser($bob);

    expect($bob->sendMessageTo($alice, 'config wins')->body)->toBe('config wins');
});
