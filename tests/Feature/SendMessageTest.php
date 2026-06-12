<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Syriable\LaravelMessages\Data\PendingMessage;
use Syriable\LaravelMessages\Enums\MessageType;
use Syriable\LaravelMessages\Events\MessageSent;
use Syriable\LaravelMessages\Exceptions\EmptyMessageException;
use Syriable\LaravelMessages\Exceptions\MessageDeliveryException;
use Syriable\LaravelMessages\Exceptions\TooManyMessagesException;
use Syriable\LaravelMessages\Facades\Messages;
use Syriable\LaravelMessages\Models\Conversation;
use Syriable\LaravelMessages\Models\MessageDraft;

it('sends a text message and creates the conversation automatically', function () {
    $alice = user();
    $bob = user();

    $message = $alice->sendMessageTo($bob, 'Hello Bob!');

    expect($message->body)->toBe('Hello Bob!')
        ->and($message->type)->toBe(MessageType::Text)
        ->and($message->isSentBy($alice))->toBeTrue();

    $conversation = $message->conversation;

    expect($conversation)->not->toBeNull()
        ->and($conversation->participants)->toHaveCount(2)
        ->and($conversation->hasParticipant($alice))->toBeTrue()
        ->and($conversation->hasParticipant($bob))->toBeTrue()
        ->and($conversation->last_message_at)->not->toBeNull();
});

it('reuses the single private conversation between the same pair', function () {
    $alice = user();
    $bob = user();

    $alice->sendMessageTo($bob, 'first');
    $bob->sendMessageTo($alice, 'second');
    $alice->sendMessageTo($bob, 'third');

    expect(Conversation::count())->toBe(1)
        ->and(Conversation::first()->messages)->toHaveCount(3);
});

it('sends an attachment-only message', function () {
    Storage::fake('local');

    $alice = user();
    $bob = user();

    $message = $alice->sendMessageTo($bob, null, [fakePdf()]);

    expect($message->type)->toBe(MessageType::Attachment)
        ->and($message->body)->toBeNull()
        ->and($message->attachments)->toHaveCount(1);
});

it('sends text and attachment together', function () {
    Storage::fake('local');

    $alice = user();
    $bob = user();

    $message = $alice->sendMessageTo($bob, 'see attached', [fakePng()]);

    expect($message->type)->toBe(MessageType::Mixed)
        ->and($message->attachments)->toHaveCount(1);
});

it('rejects empty messages', function () {
    $alice = user();
    $bob = user();

    $alice->sendMessageTo($bob, '   ');
})->throws(EmptyMessageException::class);

it('rejects messaging yourself', function () {
    $alice = user();

    $alice->sendMessageTo($alice, 'hi me');
})->throws(MessageDeliveryException::class);

it('dispatches the MessageSent event', function () {
    Event::fake([MessageSent::class]);

    $alice = user();
    $bob = user();

    $message = $alice->sendMessageTo($bob, 'hello');

    Event::assertDispatched(
        MessageSent::class,
        fn (MessageSent $event): bool => $event->message->is($message),
    );
});

it('supports the facade and pending message builder', function () {
    $alice = user();
    $bob = user();

    $message = Messages::send(
        PendingMessage::make($alice, $bob)
            ->withBody('via facade')
            ->withMetadata(['client' => 'test']),
    );

    expect($message->body)->toBe('via facade')
        ->and($message->metadata)->toBe(['client' => 'test']);
});

it('rate limits senders', function () {
    config()->set('laravel-messages.rate_limiting.max_messages_per_minute', 2);

    $alice = user();
    $bob = user();

    $alice->sendMessageTo($bob, 'one');
    $alice->sendMessageTo($bob, 'two');

    expect(fn () => $alice->sendMessageTo($bob, 'three'))
        ->toThrow(TooManyMessagesException::class);

    RateLimiter::clear('laravel-messages:send:'.$alice->getKey());

    expect($alice->sendMessageTo($bob, 'four')->body)->toBe('four');
});

it('clears the sender draft once the message is sent', function () {
    $alice = user();
    $bob = user();

    $conversation = $alice->conversationWith($bob);
    Messages::saveDraft($conversation, $alice, 'work in progress');

    expect($conversation->fresh()->getKey())->not->toBeNull();

    $alice->sendMessageTo($bob, 'final version');

    expect(
        MessageDraft::query()
            ->where('user_id', $alice->getKey())
            ->exists(),
    )->toBeFalse();
});
