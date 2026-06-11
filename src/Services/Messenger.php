<?php

declare(strict_types=1);

namespace Syriable\LaravelMessages\Services;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Syriable\LaravelMessages\Actions\DeleteConversationForUser;
use Syriable\LaravelMessages\Actions\DeleteMessageForUser;
use Syriable\LaravelMessages\Actions\DeleteMessagePermanently;
use Syriable\LaravelMessages\Actions\EditMessage;
use Syriable\LaravelMessages\Actions\ForwardMessage;
use Syriable\LaravelMessages\Actions\SendMessage;
use Syriable\LaravelMessages\Contracts\Messenger as MessengerContract;
use Syriable\LaravelMessages\Data\PendingMessage;
use Syriable\LaravelMessages\Events\ConversationArchived;
use Syriable\LaravelMessages\Events\ConversationUnarchived;
use Syriable\LaravelMessages\Events\MessageReactionAdded;
use Syriable\LaravelMessages\Events\MessageReactionRemoved;
use Syriable\LaravelMessages\Events\MessageRead;
use Syriable\LaravelMessages\Events\MessageStarred;
use Syriable\LaravelMessages\Events\MessageUnread;
use Syriable\LaravelMessages\Events\MessageUnstarred;
use Syriable\LaravelMessages\Exceptions\FeatureDisabledException;
use Syriable\LaravelMessages\Exceptions\MessageDeliveryException;
use Syriable\LaravelMessages\Exceptions\NotAParticipantException;
use Syriable\LaravelMessages\Models\Conversation;
use Syriable\LaravelMessages\Models\ConversationParticipant;
use Syriable\LaravelMessages\Models\Message;
use Syriable\LaravelMessages\Models\MessageAttachment;
use Syriable\LaravelMessages\Models\MessageDraft;
use Syriable\LaravelMessages\Models\MessageReaction;
use Syriable\LaravelMessages\Models\MessageReport;
use Syriable\LaravelMessages\Models\MessageStatus;
use Syriable\LaravelMessages\Models\SpamEntry;
use Syriable\LaravelMessages\Models\UserBlock;
use Syriable\LaravelMessages\Support\MessagingCache;
use Syriable\LaravelMessages\Support\PackageConfig;
use Syriable\LaravelMessages\Support\UserKey;

class Messenger implements MessengerContract
{
    public function __construct(
        protected SendMessage $sendMessage,
        protected EditMessage $editMessage,
        protected ForwardMessage $forwardMessage,
        protected DeleteConversationForUser $deleteConversation,
        protected DeleteMessageForUser $deleteMessage,
        protected DeleteMessagePermanently $deletePermanently,
        protected MessagingCache $cache,
    ) {}

    public function conversationBetween(Model|int|string $userA, Model|int|string $userB): Conversation
    {
        $keyA = UserKey::of($userA);
        $keyB = UserKey::of($userB);

        if ((string) $keyA === (string) $keyB) {
            throw MessageDeliveryException::cannotMessageSelf();
        }

        /** @var Conversation $conversation */
        $conversation = Conversation::query()->firstOrCreate(
            ['private_key' => UserKey::pairHash($keyA, $keyB)],
            ['type' => Conversation::TYPE_PRIVATE],
        );

        foreach ([$keyA, $keyB] as $key) {
            $conversation->participants()->firstOrCreate(['user_id' => $key]);
        }

        return $conversation;
    }

    public function send(PendingMessage $message): Message
    {
        return $this->sendMessage->handle($message);
    }

    public function inbox(Model|int|string $user): Builder
    {
        return Conversation::query()
            ->visibleTo($user)
            ->notArchivedBy($user)
            ->notSpamFor($user)
            ->latest('last_message_at');
    }

    public function archived(Model|int|string $user): Builder
    {
        return Conversation::query()
            ->visibleTo($user)
            ->archivedBy($user)
            ->latest('last_message_at');
    }

    public function spam(Model|int|string $user): Builder
    {
        return Conversation::query()
            ->visibleTo($user)
            ->spamFor($user)
            ->latest('last_message_at');
    }

    public function starredMessages(Model|int|string $user): Builder
    {
        return Message::query()
            ->visibleTo($user)
            ->starredBy($user)
            ->latest();
    }

    public function unreadCount(Model|int|string $user): int
    {
        $key = UserKey::of($user);

        return $this->cache->rememberInt(
            "unread:{$key}",
            fn (): int => Message::query()
                ->visibleTo($key)
                ->unreadBy($key)
                ->count(),
        );
    }

    public function markRead(Message $message, Model|int|string $user): void
    {
        $this->assertParticipant($message, $user);

        $message->statusFor($user)->forceFill(['read_at' => now()])->save();

        $this->cache->forget('unread:'.UserKey::of($user));

        MessageRead::dispatch($message, UserKey::of($user));
    }

    public function markUnread(Message $message, Model|int|string $user): void
    {
        $this->assertParticipant($message, $user);

        $message->statusFor($user)->forceFill(['read_at' => null])->save();

        $this->cache->forget('unread:'.UserKey::of($user));

        MessageUnread::dispatch($message, UserKey::of($user));
    }

    public function markConversationRead(Conversation $conversation, Model|int|string $user): void
    {
        $key = UserKey::of($user);

        $conversation->messagesFor($key)
            ->unreadBy($key)
            ->each(fn (Message $message) => $this->markRead($message, $key));

        $conversation->participantFor($key)?->forceFill(['last_read_at' => now()])->save();
    }

    public function star(Message $message, Model|int|string $user): void
    {
        $this->assertParticipant($message, $user);

        $message->statusFor($user)->forceFill(['starred_at' => now()])->save();

        MessageStarred::dispatch($message, UserKey::of($user));
    }

    public function unstar(Message $message, Model|int|string $user): void
    {
        $this->assertParticipant($message, $user);

        $message->statusFor($user)->forceFill(['starred_at' => null])->save();

        MessageUnstarred::dispatch($message, UserKey::of($user));
    }

    public function archive(Conversation $conversation, Model|int|string $user): void
    {
        $this->participantOrFail($conversation, $user)
            ->forceFill(['archived_at' => now()])
            ->save();

        ConversationArchived::dispatch($conversation, UserKey::of($user));
    }

    public function unarchive(Conversation $conversation, Model|int|string $user): void
    {
        $this->participantOrFail($conversation, $user)
            ->forceFill(['archived_at' => null])
            ->save();

        ConversationUnarchived::dispatch($conversation, UserKey::of($user));
    }

    public function pin(Conversation $conversation, Model|int|string $user): void
    {
        $this->participantOrFail($conversation, $user)
            ->forceFill(['pinned_at' => now()])
            ->save();
    }

    public function unpin(Conversation $conversation, Model|int|string $user): void
    {
        $this->participantOrFail($conversation, $user)
            ->forceFill(['pinned_at' => null])
            ->save();
    }

    public function mute(Conversation $conversation, Model|int|string $user, ?DateTimeInterface $until = null): void
    {
        $this->participantOrFail($conversation, $user)
            ->forceFill(['muted_until' => $until ?? now()->addYears(100)])
            ->save();
    }

    public function unmute(Conversation $conversation, Model|int|string $user): void
    {
        $this->participantOrFail($conversation, $user)
            ->forceFill(['muted_until' => null])
            ->save();
    }

    /**
     * @param  array<int, string>  $labels
     */
    public function label(Conversation $conversation, Model|int|string $user, array $labels): void
    {
        $participant = $this->participantOrFail($conversation, $user);

        $participant->forceFill([
            'labels' => array_values(array_unique([...$participant->labels(), ...$labels])),
        ])->save();
    }

    /**
     * @param  array<int, string>|null  $labels
     */
    public function unlabel(Conversation $conversation, Model|int|string $user, ?array $labels = null): void
    {
        $participant = $this->participantOrFail($conversation, $user);

        $remaining = $labels === null
            ? []
            : array_values(array_diff($participant->labels(), $labels));

        $participant->forceFill(['labels' => $remaining === [] ? null : $remaining])->save();
    }

    public function deleteConversationFor(Conversation $conversation, Model|int|string $user): void
    {
        $this->deleteConversation->handle($conversation, $user);
    }

    public function deleteMessageFor(Message $message, Model|int|string $user): void
    {
        $this->deleteMessage->handle($message, $user);
    }

    public function deleteMessagePermanently(Message $message, Model|int|string $user): void
    {
        $this->deletePermanently->handle($message, $user);
    }

    public function editMessage(Message $message, Model|int|string $user, string $body): Message
    {
        return $this->editMessage->handle($message, $user, $body);
    }

    public function forward(Message $message, Model|int|string $sender, Model|int|string $recipient): Message
    {
        return $this->forwardMessage->handle($message, $sender, $recipient);
    }

    public function react(Message $message, Model|int|string $user, string $reaction): void
    {
        if (! PackageConfig::bool('reactions.enabled', true)) {
            throw FeatureDisabledException::make('reactions');
        }

        /** @var array<int, string>|null $allowed */
        $allowed = config('laravel-messages.reactions.allowed');
        $allowed = is_array($allowed) ? $allowed : null;

        if ($allowed !== null && ! in_array($reaction, $allowed, true)) {
            throw FeatureDisabledException::make("reaction \"{$reaction}\"");
        }

        $this->assertParticipant($message, $user);

        /** @var MessageReaction $model */
        $model = $message->reactions()->firstOrCreate([
            'user_id' => UserKey::of($user),
            'reaction' => $reaction,
        ]);

        if ($model->wasRecentlyCreated) {
            MessageReactionAdded::dispatch($model);
        }
    }

    public function unreact(Message $message, Model|int|string $user, string $reaction): void
    {
        $deleted = $message->reactions()
            ->where('user_id', UserKey::of($user))
            ->where('reaction', $reaction)
            ->delete();

        if ($deleted > 0) {
            MessageReactionRemoved::dispatch($message, UserKey::of($user), $reaction);
        }
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function saveDraft(Conversation $conversation, Model|int|string $user, ?string $body, array $metadata = []): MessageDraft
    {
        if (! PackageConfig::bool('drafts.enabled', true)) {
            throw FeatureDisabledException::make('drafts');
        }

        $this->participantOrFail($conversation, $user);

        /** @var MessageDraft */
        return MessageDraft::query()->updateOrCreate(
            [
                'conversation_id' => $conversation->getKey(),
                'user_id' => UserKey::of($user),
            ],
            [
                'body' => $body,
                'metadata' => $metadata !== [] ? $metadata : null,
            ],
        );
    }

    public function discardDraft(Conversation $conversation, Model|int|string $user): void
    {
        MessageDraft::query()
            ->where('conversation_id', $conversation->getKey())
            ->where('user_id', UserKey::of($user))
            ->delete();
    }

    public function purgeUserData(Model|int|string $user): void
    {
        $key = UserKey::of($user);

        DB::transaction(function () use ($key): void {
            Message::withTrashed()
                ->where('sender_id', $key)
                ->with('attachments')
                ->each(function (Message $message): void {
                    $message->attachments->each(function (MessageAttachment $attachment): void {
                        $attachment->deleteFile();
                        $attachment->delete();
                    });

                    $message->forceDelete();
                });

            MessageStatus::query()->where('user_id', $key)->delete();
            MessageReaction::query()->where('user_id', $key)->delete();
            MessageDraft::query()->where('user_id', $key)->delete();
            ConversationParticipant::query()->where('user_id', $key)->delete();

            UserBlock::query()
                ->where('blocker_id', $key)
                ->orWhere('blocked_id', $key)
                ->delete();

            SpamEntry::query()
                ->where('user_id', $key)
                ->orWhere('spammer_id', $key)
                ->delete();

            MessageReport::query()->where('reporter_id', $key)->delete();
        });

        $this->cache->forget("unread:{$key}");
    }

    protected function assertParticipant(Message $message, Model|int|string $user): void
    {
        $conversation = $message->conversation;

        if ($conversation === null || ! $conversation->hasParticipant($user)) {
            throw NotAParticipantException::make();
        }
    }

    protected function participantOrFail(Conversation $conversation, Model|int|string $user): ConversationParticipant
    {
        $participant = $conversation->participantFor($user);

        if ($participant === null) {
            throw NotAParticipantException::make();
        }

        return $participant;
    }
}
