<?php

declare(strict_types=1);

namespace Syriable\LaravelMessages\Actions;

use Illuminate\Database\Eloquent\Model;
use Syriable\LaravelMessages\Events\ConversationDeleted;
use Syriable\LaravelMessages\Exceptions\NotAParticipantException;
use Syriable\LaravelMessages\Models\Conversation;
use Syriable\LaravelMessages\Support\MessagingCache;
use Syriable\LaravelMessages\Support\UserKey;

/**
 * "Delete for me": the conversation vanishes from this user's inbox while
 * the other participant keeps full history and is never informed. If a new
 * message arrives later the conversation reappears containing only
 * messages sent after this point.
 */
class DeleteConversationForUser
{
    public function __construct(protected MessagingCache $cache) {}

    public function handle(Conversation $conversation, Model|int|string $user): void
    {
        $participant = $conversation->participantFor($user);

        if ($participant === null) {
            throw NotAParticipantException::make();
        }

        $participant->forceFill([
            'conversation_cleared_at' => now(),
            'archived_at' => null,
            'pinned_at' => null,
        ])->save();

        $this->cache->forget('unread:'.UserKey::of($user));

        ConversationDeleted::dispatch($conversation, UserKey::of($user));
    }
}
