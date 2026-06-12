<?php

declare(strict_types=1);

namespace Syriable\LaravelMessages\Contracts;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Syriable\LaravelMessages\Data\PendingMessage;
use Syriable\LaravelMessages\Models\Conversation;
use Syriable\LaravelMessages\Models\Message;
use Syriable\LaravelMessages\Models\MessageDraft;

interface Messenger
{
    /**
     * Find or create the single private conversation between two users.
     */
    public function conversationBetween(Model|int|string $userA, Model|int|string $userB): Conversation;

    public function send(PendingMessage $message): Message;

    /**
     * Inbox: conversations visible to the user, excluding archived and spam.
     *
     * @return Builder<Conversation>
     */
    public function inbox(Model|int|string $user): Builder;

    /** @return Builder<Conversation> */
    public function archived(Model|int|string $user): Builder;

    /** @return Builder<Conversation> */
    public function spam(Model|int|string $user): Builder;

    /** @return Builder<Message> */
    public function starredMessages(Model|int|string $user): Builder;

    public function unreadCount(Model|int|string $user): int;

    public function markRead(Message $message, Model|int|string $user): void;

    public function markUnread(Message $message, Model|int|string $user): void;

    public function markConversationRead(Conversation $conversation, Model|int|string $user): void;

    public function star(Message $message, Model|int|string $user): void;

    public function unstar(Message $message, Model|int|string $user): void;

    public function archive(Conversation $conversation, Model|int|string $user): void;

    public function unarchive(Conversation $conversation, Model|int|string $user): void;

    public function pin(Conversation $conversation, Model|int|string $user): void;

    public function unpin(Conversation $conversation, Model|int|string $user): void;

    public function mute(Conversation $conversation, Model|int|string $user, ?\DateTimeInterface $until = null): void;

    public function unmute(Conversation $conversation, Model|int|string $user): void;

    /**
     * @param  array<int, string>  $labels
     */
    public function label(Conversation $conversation, Model|int|string $user, array $labels): void;

    /**
     * @param  array<int, string>|null  $labels
     */
    public function unlabel(Conversation $conversation, Model|int|string $user, ?array $labels = null): void;

    /**
     * "Delete for me": hides the conversation and all its current messages
     * for this user only. The other participant keeps full history.
     */
    public function deleteConversationFor(Conversation $conversation, Model|int|string $user): void;

    /**
     * "Delete for me" on a single message.
     */
    public function deleteMessageFor(Message $message, Model|int|string $user): void;

    /**
     * Permanently delete a message for everyone. Requires
     * "laravel-messages.deletes.allow_permanent" to be enabled.
     */
    public function deleteMessagePermanently(Message $message, Model|int|string $user): void;

    public function editMessage(Message $message, Model|int|string $user, string $body): Message;

    public function forward(Message $message, Model|int|string $sender, Model|int|string $recipient): Message;

    public function react(Message $message, Model|int|string $user, string $reaction): void;

    public function unreact(Message $message, Model|int|string $user, string $reaction): void;

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function saveDraft(Conversation $conversation, Model|int|string $user, ?string $body, array $metadata = []): MessageDraft;

    public function discardDraft(Conversation $conversation, Model|int|string $user): void;

    /**
     * GDPR-friendly removal of every trace of a user from the package
     * tables, including stored attachment files of their messages.
     */
    public function purgeUserData(Model|int|string $user): void;
}
