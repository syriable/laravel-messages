<?php

declare(strict_types=1);

namespace Syriable\LaravelMessages\Actions;

use Illuminate\Database\Eloquent\Model;
use Syriable\LaravelMessages\Events\MessageDeleted;
use Syriable\LaravelMessages\Exceptions\NotAParticipantException;
use Syriable\LaravelMessages\Models\Message;
use Syriable\LaravelMessages\Support\MessagingCache;
use Syriable\LaravelMessages\Support\UserKey;

class DeleteMessageForUser
{
    public function __construct(protected MessagingCache $cache) {}

    public function handle(Message $message, Model|int|string $user): void
    {
        $conversation = $message->conversation;

        if ($conversation === null || ! $conversation->hasParticipant($user)) {
            throw NotAParticipantException::make();
        }

        $message->statusFor($user)->forceFill(['deleted_at' => now()])->save();

        $this->cache->forget('unread:'.UserKey::of($user));

        MessageDeleted::dispatch($message, UserKey::of($user), false);
    }
}
