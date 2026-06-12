<?php

declare(strict_types=1);

namespace Syriable\LaravelMessages\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Syriable\LaravelMessages\Contracts\ManagesAttachments;
use Syriable\LaravelMessages\Contracts\ManagesBlocks;
use Syriable\LaravelMessages\Contracts\ManagesSpam;
use Syriable\LaravelMessages\Data\PendingMessage;
use Syriable\LaravelMessages\Enums\MessageType;
use Syriable\LaravelMessages\Events\MessageSent;
use Syriable\LaravelMessages\Exceptions\EmptyMessageException;
use Syriable\LaravelMessages\Exceptions\MessageDeliveryException;
use Syriable\LaravelMessages\Exceptions\TooManyMessagesException;
use Syriable\LaravelMessages\Models\Conversation;
use Syriable\LaravelMessages\Models\Message;
use Syriable\LaravelMessages\Models\MessageDraft;
use Syriable\LaravelMessages\Support\MessagingCache;
use Syriable\LaravelMessages\Support\PackageConfig;
use Syriable\LaravelMessages\Support\UserKey;

class SendMessage
{
    public function __construct(
        protected ManagesAttachments $attachments,
        protected ManagesBlocks $blocks,
        protected ManagesSpam $spam,
        protected MessagingCache $cache,
    ) {}

    public function handle(PendingMessage $pending): Message
    {
        $senderKey = UserKey::of($pending->sender);
        $recipientKey = UserKey::of($pending->recipient);

        if ((string) $senderKey === (string) $recipientKey) {
            throw MessageDeliveryException::cannotMessageSelf();
        }

        if ($pending->isEmpty()) {
            throw EmptyMessageException::make();
        }

        $this->guardDelivery($senderKey, $recipientKey);
        $this->guardRateLimit($senderKey);
        $this->attachments->validate($pending->attachments);

        $conversation = $this->resolveConversation($senderKey, $recipientKey);

        $message = DB::transaction(function () use ($pending, $conversation, $senderKey, $recipientKey): Message {
            /** @var Message $message */
            $message = $conversation->messages()->create([
                'sender_id' => $senderKey,
                'body' => $pending->body,
                'type' => $this->messageType($pending),
                'metadata' => $pending->metadata !== [] ? $pending->metadata : null,
            ]);

            $this->attachments->store($message, $pending->attachments);

            $conversation->forceFill(['last_message_at' => $message->created_at])->save();

            if (PackageConfig::bool('conversations.unarchive_on_new_message', true)) {
                $conversation->participants()
                    ->whereNotNull('archived_at')
                    ->update(['archived_at' => null]);
            }

            MessageDraft::query()
                ->where('conversation_id', $conversation->getKey())
                ->where('user_id', $senderKey)
                ->delete();

            $this->cache->forget("unread:{$recipientKey}");

            return $message;
        });

        MessageSent::dispatch($message);

        $this->notifyRecipient($pending, $conversation, $message, $recipientKey);

        return $message->load('attachments');
    }

    /**
     * Reject delivery when the recipient blocked the sender or marked them
     * as a spammer. Both produce the exact same generic failure so the
     * sender can never tell the difference (or that anything happened
     * at all).
     */
    protected function guardDelivery(int|string $senderKey, int|string $recipientKey): void
    {
        if (PackageConfig::bool('blocking.enabled', true)) {
            if ($this->blocks->hasBlocked($recipientKey, $senderKey)
                || $this->blocks->hasBlocked($senderKey, $recipientKey)) {
                throw MessageDeliveryException::rejected();
            }
        }

        if (PackageConfig::bool('spam.enabled', true)
            && PackageConfig::bool('spam.block_delivery_from_spammers', true)
            && $this->spam->isMarkedAsSpammerBy($recipientKey, $senderKey)) {
            throw MessageDeliveryException::rejected();
        }
    }

    protected function guardRateLimit(int|string $senderKey): void
    {
        if (! PackageConfig::bool('rate_limiting.enabled', true)) {
            return;
        }

        $key = "laravel-messages:send:{$senderKey}";
        $max = PackageConfig::int('rate_limiting.max_messages_per_minute', 30);

        if (RateLimiter::tooManyAttempts($key, $max)) {
            throw TooManyMessagesException::make();
        }

        RateLimiter::hit($key, 60);
    }

    protected function resolveConversation(int|string $senderKey, int|string $recipientKey): Conversation
    {
        /** @var Conversation $conversation */
        $conversation = Conversation::query()->firstOrCreate(
            ['private_key' => UserKey::pairHash($senderKey, $recipientKey)],
            ['type' => Conversation::TYPE_PRIVATE],
        );

        foreach ([$senderKey, $recipientKey] as $key) {
            $conversation->participants()->firstOrCreate(['user_id' => $key]);
        }

        return $conversation;
    }

    protected function messageType(PendingMessage $pending): MessageType
    {
        $hasBody = $pending->body !== null && trim($pending->body) !== '';
        $hasAttachments = $pending->attachments !== [];

        return match (true) {
            $hasBody && $hasAttachments => MessageType::Mixed,
            $hasAttachments => MessageType::Attachment,
            default => MessageType::Text,
        };
    }

    protected function notifyRecipient(
        PendingMessage $pending,
        Conversation $conversation,
        Message $message,
        int|string $recipientKey,
    ): void {
        if (! PackageConfig::bool('notifications.new_message.enabled')) {
            return;
        }

        $participant = $conversation->participantFor($recipientKey);

        if ($participant !== null && $participant->isMuted()) {
            return;
        }

        $recipient = $pending->recipient instanceof Model
            ? $pending->recipient
            : $this->resolveUser($recipientKey);

        if ($recipient === null) {
            return;
        }

        $notificationClass = PackageConfig::stringOrNull('notifications.new_message.notification');

        if ($notificationClass === null) {
            return;
        }

        $notification = app($notificationClass, ['message' => $message]);

        if (! $notification instanceof \Illuminate\Notifications\Notification) {
            return;
        }

        Notification::send($recipient, $notification);
    }

    protected function resolveUser(int|string $key): ?Model
    {
        $model = PackageConfig::string('user_model');

        if (! is_a($model, Model::class, true)) {
            return null;
        }

        return $model::query()->find($key);
    }
}
