<?php

declare(strict_types=1);

namespace Syriable\LaravelMessages\Services;

use Illuminate\Database\Eloquent\Model;
use Syriable\LaravelMessages\Contracts\ManagesSpam;
use Syriable\LaravelMessages\Events\UserMarkedAsSpam;
use Syriable\LaravelMessages\Events\UserUnmarkedAsSpam;
use Syriable\LaravelMessages\Models\Conversation;
use Syriable\LaravelMessages\Models\Message;
use Syriable\LaravelMessages\Models\SpamEntry;
use Syriable\LaravelMessages\Support\MessagingCache;
use Syriable\LaravelMessages\Support\UserKey;

class SpamManager implements ManagesSpam
{
    public function __construct(protected MessagingCache $cache) {}

    public function markUserAsSpam(Model|int|string $user, Model|int|string $spammer, ?string $reason = null): SpamEntry
    {
        $userKey = UserKey::of($user);
        $spammerKey = UserKey::of($spammer);

        $entry = SpamEntry::query()->firstOrCreate(
            [
                'user_id' => $userKey,
                'spammer_id' => $spammerKey,
                'conversation_id' => null,
                'message_id' => null,
            ],
            ['reason' => $reason],
        );

        $this->cache->forget($this->cacheKey($userKey, $spammerKey));

        if ($entry->wasRecentlyCreated) {
            UserMarkedAsSpam::dispatch($entry);
        }

        return $entry;
    }

    public function unmarkUserAsSpam(Model|int|string $user, Model|int|string $spammer): void
    {
        $userKey = UserKey::of($user);
        $spammerKey = UserKey::of($spammer);

        $deleted = SpamEntry::query()
            ->where('user_id', $userKey)
            ->where('spammer_id', $spammerKey)
            ->delete();

        $this->cache->forget($this->cacheKey($userKey, $spammerKey));

        if ($deleted > 0) {
            UserUnmarkedAsSpam::dispatch($userKey, $spammerKey);
        }
    }

    public function isMarkedAsSpammerBy(Model|int|string $user, Model|int|string $sender): bool
    {
        $userKey = UserKey::of($user);
        $senderKey = UserKey::of($sender);

        return $this->cache->rememberBool(
            $this->cacheKey($userKey, $senderKey),
            fn (): bool => SpamEntry::query()
                ->where('user_id', $userKey)
                ->where('spammer_id', $senderKey)
                ->exists(),
        );
    }

    public function reportMessageAsSpam(Model|int|string $user, Message $message, ?string $reason = null): SpamEntry
    {
        return SpamEntry::query()->firstOrCreate(
            [
                'user_id' => UserKey::of($user),
                'spammer_id' => null,
                'conversation_id' => $message->conversation_id,
                'message_id' => $message->getKey(),
            ],
            ['reason' => $reason],
        );
    }

    public function reportConversationAsSpam(Model|int|string $user, Conversation $conversation, ?string $reason = null): SpamEntry
    {
        return SpamEntry::query()->firstOrCreate(
            [
                'user_id' => UserKey::of($user),
                'spammer_id' => null,
                'conversation_id' => $conversation->getKey(),
                'message_id' => null,
            ],
            ['reason' => $reason],
        );
    }

    public function removeConversationFromSpam(Model|int|string $user, Conversation $conversation): void
    {
        $userKey = UserKey::of($user);

        SpamEntry::query()
            ->where('user_id', $userKey)
            ->where('conversation_id', $conversation->getKey())
            ->delete();

        // A conversation may also sit in spam because the other participant
        // was marked as a spammer; lift that mark as well.
        $other = $conversation->otherParticipant($userKey);

        if ($other !== null) {
            $this->unmarkUserAsSpam($userKey, $other->user_id);
        }
    }

    protected function cacheKey(int|string $user, int|string $spammer): string
    {
        return "spam:{$user}:{$spammer}";
    }
}
