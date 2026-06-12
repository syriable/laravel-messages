<?php

declare(strict_types=1);

namespace Syriable\LaravelMessages\Facades;

use Illuminate\Support\Facades\Facade;
use Syriable\LaravelMessages\Contracts\Messenger;

/**
 * @method static \Syriable\LaravelMessages\Models\Conversation conversationBetween(\Illuminate\Database\Eloquent\Model|int|string $userA, \Illuminate\Database\Eloquent\Model|int|string $userB)
 * @method static \Syriable\LaravelMessages\Models\Message send(\Syriable\LaravelMessages\Data\PendingMessage $message)
 * @method static \Illuminate\Database\Eloquent\Builder<\Syriable\LaravelMessages\Models\Conversation> inbox(\Illuminate\Database\Eloquent\Model|int|string $user)
 * @method static \Illuminate\Database\Eloquent\Builder<\Syriable\LaravelMessages\Models\Conversation> archived(\Illuminate\Database\Eloquent\Model|int|string $user)
 * @method static \Illuminate\Database\Eloquent\Builder<\Syriable\LaravelMessages\Models\Conversation> spam(\Illuminate\Database\Eloquent\Model|int|string $user)
 * @method static \Illuminate\Database\Eloquent\Builder<\Syriable\LaravelMessages\Models\Message> starredMessages(\Illuminate\Database\Eloquent\Model|int|string $user)
 * @method static int unreadCount(\Illuminate\Database\Eloquent\Model|int|string $user)
 * @method static void markRead(\Syriable\LaravelMessages\Models\Message $message, \Illuminate\Database\Eloquent\Model|int|string $user)
 * @method static void markUnread(\Syriable\LaravelMessages\Models\Message $message, \Illuminate\Database\Eloquent\Model|int|string $user)
 * @method static void markConversationRead(\Syriable\LaravelMessages\Models\Conversation $conversation, \Illuminate\Database\Eloquent\Model|int|string $user)
 * @method static void star(\Syriable\LaravelMessages\Models\Message $message, \Illuminate\Database\Eloquent\Model|int|string $user)
 * @method static void unstar(\Syriable\LaravelMessages\Models\Message $message, \Illuminate\Database\Eloquent\Model|int|string $user)
 * @method static void archive(\Syriable\LaravelMessages\Models\Conversation $conversation, \Illuminate\Database\Eloquent\Model|int|string $user)
 * @method static void unarchive(\Syriable\LaravelMessages\Models\Conversation $conversation, \Illuminate\Database\Eloquent\Model|int|string $user)
 * @method static void pin(\Syriable\LaravelMessages\Models\Conversation $conversation, \Illuminate\Database\Eloquent\Model|int|string $user)
 * @method static void unpin(\Syriable\LaravelMessages\Models\Conversation $conversation, \Illuminate\Database\Eloquent\Model|int|string $user)
 * @method static void mute(\Syriable\LaravelMessages\Models\Conversation $conversation, \Illuminate\Database\Eloquent\Model|int|string $user, ?\DateTimeInterface $until = null)
 * @method static void unmute(\Syriable\LaravelMessages\Models\Conversation $conversation, \Illuminate\Database\Eloquent\Model|int|string $user)
 * @method static void label(\Syriable\LaravelMessages\Models\Conversation $conversation, \Illuminate\Database\Eloquent\Model|int|string $user, array<int, string> $labels)
 * @method static void unlabel(\Syriable\LaravelMessages\Models\Conversation $conversation, \Illuminate\Database\Eloquent\Model|int|string $user, array<int, string>|null $labels = null)
 * @method static void deleteConversationFor(\Syriable\LaravelMessages\Models\Conversation $conversation, \Illuminate\Database\Eloquent\Model|int|string $user)
 * @method static void deleteMessageFor(\Syriable\LaravelMessages\Models\Message $message, \Illuminate\Database\Eloquent\Model|int|string $user)
 * @method static void deleteMessagePermanently(\Syriable\LaravelMessages\Models\Message $message, \Illuminate\Database\Eloquent\Model|int|string $user)
 * @method static \Syriable\LaravelMessages\Models\Message editMessage(\Syriable\LaravelMessages\Models\Message $message, \Illuminate\Database\Eloquent\Model|int|string $user, string $body)
 * @method static \Syriable\LaravelMessages\Models\Message forward(\Syriable\LaravelMessages\Models\Message $message, \Illuminate\Database\Eloquent\Model|int|string $sender, \Illuminate\Database\Eloquent\Model|int|string $recipient)
 * @method static void react(\Syriable\LaravelMessages\Models\Message $message, \Illuminate\Database\Eloquent\Model|int|string $user, string $reaction)
 * @method static void unreact(\Syriable\LaravelMessages\Models\Message $message, \Illuminate\Database\Eloquent\Model|int|string $user, string $reaction)
 * @method static \Syriable\LaravelMessages\Models\MessageDraft saveDraft(\Syriable\LaravelMessages\Models\Conversation $conversation, \Illuminate\Database\Eloquent\Model|int|string $user, ?string $body, array<string, mixed> $metadata = [])
 * @method static void discardDraft(\Syriable\LaravelMessages\Models\Conversation $conversation, \Illuminate\Database\Eloquent\Model|int|string $user)
 * @method static void purgeUserData(\Illuminate\Database\Eloquent\Model|int|string $user)
 *
 * @see \Syriable\LaravelMessages\Services\Messenger
 */
class Messages extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return Messenger::class;
    }
}
