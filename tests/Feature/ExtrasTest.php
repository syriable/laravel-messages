<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use Syriable\LaravelMessages\Exceptions\EditNotAllowedException;
use Syriable\LaravelMessages\Exceptions\FeatureDisabledException;
use Syriable\LaravelMessages\Exceptions\NotAParticipantException;
use Syriable\LaravelMessages\Facades\Messages;
use Syriable\LaravelMessages\Models\Conversation;
use Syriable\LaravelMessages\Models\ConversationParticipant;
use Syriable\LaravelMessages\Models\Message;
use Syriable\LaravelMessages\Models\MessageDraft;
use Syriable\LaravelMessages\Models\SpamEntry;
use Syriable\LaravelMessages\Models\UserBlock;

it('adds and removes reactions', function () {
    $alice = user();
    $bob = user();

    $message = $alice->sendMessageTo($bob, 'good news!');

    Messages::react($message, $bob, '👍');
    Messages::react($message, $bob, '👍'); // idempotent
    Messages::react($message, $alice, '🎉');

    expect($message->reactions()->count())->toBe(2);

    Messages::unreact($message, $bob, '👍');

    expect($message->reactions()->count())->toBe(1);
});

it('can restrict reactions to an allowed list', function () {
    config()->set('laravel-messages.reactions.allowed', ['👍', '❤️']);

    $alice = user();
    $bob = user();

    $message = $alice->sendMessageTo($bob, 'hello');

    Messages::react($message, $bob, '👍');

    expect(fn () => Messages::react($message, $bob, '💀'))
        ->toThrow(FeatureDisabledException::class);
});

it('edits a message and keeps the edit history', function () {
    $alice = user();
    $bob = user();

    $message = $alice->sendMessageTo($bob, 'helo');

    Messages::editMessage($message, $alice, 'hello');

    $message->refresh();

    expect($message->body)->toBe('hello')
        ->and($message->edited_at)->not->toBeNull()
        ->and($message->edits)->toHaveCount(1)
        ->and($message->edits->first()->body)->toBe('helo');
});

it('only lets the sender edit', function () {
    $alice = user();
    $bob = user();

    $message = $alice->sendMessageTo($bob, 'original');

    Messages::editMessage($message, $bob, 'hijacked');
})->throws(EditNotAllowedException::class);

it('enforces the edit window', function () {
    config()->set('laravel-messages.editing.edit_window_minutes', 5);

    $alice = user();
    $bob = user();

    $message = $alice->sendMessageTo($bob, 'original');

    $this->travel(10)->minutes();

    Messages::editMessage($message, $alice, 'too late');
})->throws(EditNotAllowedException::class);

it('forwards a message with its attachments to another user', function () {
    Storage::fake('local');

    $alice = user();
    $bob = user();
    $carol = user();

    $original = $alice->sendMessageTo($bob, 'forward me', [fakePdf()]);

    $forwarded = Messages::forward($original, $bob, $carol);

    expect($forwarded->body)->toBe('forward me')
        ->and((string) $forwarded->forwarded_from_id)->toBe((string) $original->getKey())
        ->and($forwarded->attachments)->toHaveCount(1)
        ->and($forwarded->attachments->first()->path)
        ->not->toBe($original->attachments()->first()->path)
        ->and($carol->conversationInbox()->count())->toBe(1);

    Storage::disk('local')->assertExists($forwarded->attachments->first()->path);
});

it('refuses to forward a message the sender cannot see', function () {
    $alice = user();
    $bob = user();
    $mallory = user();

    $original = $alice->sendMessageTo($bob, 'secret');

    Messages::forward($original, $mallory, user());
})->throws(NotAParticipantException::class);

it('saves, updates and discards drafts', function () {
    $alice = user();
    $bob = user();

    $conversation = $alice->conversationWith($bob);

    Messages::saveDraft($conversation, $alice, 'first attempt');
    $draft = Messages::saveDraft($conversation, $alice, 'second attempt');

    expect(MessageDraft::count())->toBe(1)
        ->and($draft->body)->toBe('second attempt');

    Messages::discardDraft($conversation, $alice);

    expect(MessageDraft::count())->toBe(0);
});

it('pins and unpins conversations per user', function () {
    $alice = user();
    $bob = user();

    $alice->sendMessageTo($bob, 'hello');
    $conversation = $alice->conversationWith($bob);

    Messages::pin($conversation, $alice);

    expect(Conversation::pinnedBy($alice)->count())->toBe(1)
        ->and(Conversation::pinnedBy($bob)->count())->toBe(0);

    Messages::unpin($conversation, $alice);

    expect(Conversation::pinnedBy($alice)->count())->toBe(0);
});

it('mutes and unmutes conversations', function () {
    $alice = user();
    $bob = user();

    $alice->sendMessageTo($bob, 'hello');
    $conversation = $alice->conversationWith($bob);

    Messages::mute($conversation, $bob);

    expect($conversation->participantFor($bob)->isMuted())->toBeTrue();

    Messages::unmute($conversation, $bob);

    expect($conversation->fresh()->participantFor($bob)->isMuted())->toBeFalse();
});

it('expires timed mutes automatically', function () {
    $alice = user();
    $bob = user();

    $alice->sendMessageTo($bob, 'hello');
    $conversation = $alice->conversationWith($bob);

    Messages::mute($conversation, $bob, now()->addHour());

    expect($conversation->participantFor($bob)->isMuted())->toBeTrue();

    $this->travel(2)->hours();

    expect($conversation->fresh()->participantFor($bob)->isMuted())->toBeFalse();
});

it('manages per-user conversation labels', function () {
    $alice = user();
    $bob = user();

    $alice->sendMessageTo($bob, 'hello');
    $conversation = $alice->conversationWith($bob);

    Messages::label($conversation, $alice, ['work', 'urgent']);
    Messages::label($conversation, $alice, ['work']); // no duplicates

    expect($conversation->fresh()->participantFor($alice)->labels())->toBe(['work', 'urgent'])
        ->and($conversation->fresh()->participantFor($bob)->labels())->toBe([]);

    Messages::unlabel($conversation, $alice, ['urgent']);

    expect($conversation->fresh()->participantFor($alice)->labels())->toBe(['work']);

    Messages::unlabel($conversation, $alice);

    expect($conversation->fresh()->participantFor($alice)->labels())->toBe([]);
});

it('purges all user data for GDPR requests', function () {
    Storage::fake('local');

    $alice = user();
    $bob = user();

    $message = $alice->sendMessageTo($bob, 'personal data', [fakePdf()]);
    $path = $message->attachments->first()->path;

    Messages::react($message, $bob, '👍');
    $alice->blockUser($bob);
    $alice->markUserAsSpam($bob);

    Messages::purgeUserData($alice);

    expect(Message::withTrashed()->where('sender_id', $alice->getKey())->count())->toBe(0)
        ->and(ConversationParticipant::where('user_id', $alice->getKey())->count())->toBe(0)
        ->and(UserBlock::count())->toBe(0)
        ->and(SpamEntry::count())->toBe(0);

    Storage::disk('local')->assertMissing($path);
});
