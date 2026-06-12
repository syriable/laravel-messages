<?php

declare(strict_types=1);

namespace Syriable\LaravelMessages\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Syriable\LaravelMessages\Contracts\ManagesBlocks;
use Syriable\LaravelMessages\Contracts\ManagesSpam;
use Syriable\LaravelMessages\Contracts\Messenger;
use Syriable\LaravelMessages\Data\PendingMessage;
use Syriable\LaravelMessages\Models\Conversation;
use Syriable\LaravelMessages\Models\Message;

/**
 * Drop-in messaging API for any authenticatable model.
 *
 * @mixin Model
 */
trait HasMessaging
{
    /**
     * @param  array<int, UploadedFile>  $attachments
     * @param  array<string, mixed>  $metadata
     */
    public function sendMessageTo(
        Model|int|string $recipient,
        ?string $body = null,
        array $attachments = [],
        array $metadata = [],
    ): Message {
        return app(Messenger::class)->send(
            new PendingMessage($this, $recipient, $body, $attachments, $metadata),
        );
    }

    public function conversationWith(Model|int|string $other): Conversation
    {
        return app(Messenger::class)->conversationBetween($this, $other);
    }

    /** @return Builder<Conversation> */
    public function conversationInbox(): Builder
    {
        return app(Messenger::class)->inbox($this);
    }

    /** @return Builder<Conversation> */
    public function archivedConversations(): Builder
    {
        return app(Messenger::class)->archived($this);
    }

    /** @return Builder<Conversation> */
    public function spamConversations(): Builder
    {
        return app(Messenger::class)->spam($this);
    }

    /** @return Builder<Message> */
    public function starredMessages(): Builder
    {
        return app(Messenger::class)->starredMessages($this);
    }

    public function unreadMessagesCount(): int
    {
        return app(Messenger::class)->unreadCount($this);
    }

    public function blockUser(Model|int|string $user): void
    {
        app(ManagesBlocks::class)->block($this, $user);
    }

    public function unblockUser(Model|int|string $user): void
    {
        app(ManagesBlocks::class)->unblock($this, $user);
    }

    public function hasBlockedUser(Model|int|string $user): bool
    {
        return app(ManagesBlocks::class)->hasBlocked($this, $user);
    }

    public function markUserAsSpam(Model|int|string $user, ?string $reason = null): void
    {
        app(ManagesSpam::class)->markUserAsSpam($this, $user, $reason);
    }

    public function unmarkUserAsSpam(Model|int|string $user): void
    {
        app(ManagesSpam::class)->unmarkUserAsSpam($this, $user);
    }
}
