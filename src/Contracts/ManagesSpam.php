<?php

declare(strict_types=1);

namespace Syriable\LaravelMessages\Contracts;

use Illuminate\Database\Eloquent\Model;
use Syriable\LaravelMessages\Models\Conversation;
use Syriable\LaravelMessages\Models\Message;
use Syriable\LaravelMessages\Models\SpamEntry;

interface ManagesSpam
{
    /**
     * Mark a sender as a spammer: all their future deliveries to $user are
     * silently rejected.
     */
    public function markUserAsSpam(Model|int|string $user, Model|int|string $spammer, ?string $reason = null): SpamEntry;

    public function unmarkUserAsSpam(Model|int|string $user, Model|int|string $spammer): void;

    public function isMarkedAsSpammerBy(Model|int|string $user, Model|int|string $sender): bool;

    public function reportMessageAsSpam(Model|int|string $user, Message $message, ?string $reason = null): SpamEntry;

    public function reportConversationAsSpam(Model|int|string $user, Conversation $conversation, ?string $reason = null): SpamEntry;

    public function removeConversationFromSpam(Model|int|string $user, Conversation $conversation): void;
}
