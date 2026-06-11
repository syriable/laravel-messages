<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Syriable\LaravelMessages\Facades\Messages;
use Syriable\LaravelMessages\Resources\ConversationResource;
use Syriable\LaravelMessages\Resources\MessageResource;

it('authorizes conversation access via the registered policy', function () {
    $alice = user();
    $bob = user();
    $mallory = user();

    $alice->sendMessageTo($bob, 'hello');
    $conversation = $alice->conversationWith($bob);

    expect(Gate::forUser($alice)->allows('view', $conversation))->toBeTrue()
        ->and(Gate::forUser($bob)->allows('view', $conversation))->toBeTrue()
        ->and(Gate::forUser($mallory)->allows('view', $conversation))->toBeFalse();
});

it('authorizes message actions via the registered policy', function () {
    $alice = user();
    $bob = user();
    $mallory = user();

    $message = $alice->sendMessageTo($bob, 'hello');

    expect(Gate::forUser($bob)->allows('view', $message))->toBeTrue()
        ->and(Gate::forUser($mallory)->allows('view', $message))->toBeFalse()
        ->and(Gate::forUser($alice)->allows('update', $message))->toBeTrue()
        ->and(Gate::forUser($bob)->allows('update', $message))->toBeFalse()
        ->and(Gate::forUser($alice)->allows('forceDelete', $message))->toBeFalse();

    config()->set('laravel-messages.deletes.allow_permanent', true);

    expect(Gate::forUser($alice)->allows('forceDelete', $message))->toBeTrue();
});

it('denies viewing a message deleted for that user', function () {
    $alice = user();
    $bob = user();

    $message = $alice->sendMessageTo($bob, 'hello');

    Messages::deleteMessageFor($message, $bob);

    expect(Gate::forUser($bob)->allows('view', $message))->toBeFalse()
        ->and(Gate::forUser($alice)->allows('view', $message))->toBeTrue();
});

it('serializes messages with per-user state for the API', function () {
    Storage::fake('local');

    $alice = user();
    $bob = user();

    $message = $alice->sendMessageTo($bob, 'payload', [fakePng()]);
    Messages::star($message, $bob);

    $request = Request::create('/api/messages');
    $request->setUserResolver(fn () => $bob);

    $payload = (new MessageResource($message->load(['attachments', 'reactions'])))->resolve($request);

    expect($payload['body'])->toBe('payload')
        ->and($payload['type'])->toBe('mixed')
        ->and($payload['sent_by_me'])->toBeFalse()
        ->and($payload['starred'])->toBeTrue()
        ->and($payload['read'])->toBeFalse()
        ->and($payload['attachments'])->toHaveCount(1);
});

it('serializes conversations with unread counts and private participant state', function () {
    $alice = user();
    $bob = user();

    $alice->sendMessageTo($bob, 'one');
    $alice->sendMessageTo($bob, 'two');

    $conversation = $alice->conversationWith($bob)->load(['participants', 'lastMessage']);

    Messages::pin($conversation, $bob);

    $request = Request::create('/api/conversations');
    $request->setUserResolver(fn () => $bob);

    $payload = (new ConversationResource($conversation->fresh(['participants', 'lastMessage'])))->resolve($request);

    expect($payload['unread_count'])->toBe(2)
        ->and($payload['participants'])->toHaveCount(2);

    $participants = collect($payload['participants'])
        ->map(fn ($participant) => $participant->resolve($request));

    $own = $participants->firstWhere('user_id', $bob->getKey());
    $other = $participants->firstWhere('user_id', $alice->getKey());

    // Bob sees his own private state but never Alice's.
    expect($own)->toHaveKey('pinned')
        ->and($own['pinned'])->toBeTrue()
        ->and($other)->not->toHaveKey('pinned');
});
