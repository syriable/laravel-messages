<?php

declare(strict_types=1);

namespace Syriable\LaravelMessages\Services;

use Illuminate\Database\Eloquent\Model;
use Syriable\LaravelMessages\Contracts\ManagesBlocks;
use Syriable\LaravelMessages\Events\UserBlocked;
use Syriable\LaravelMessages\Events\UserUnblocked;
use Syriable\LaravelMessages\Models\UserBlock;
use Syriable\LaravelMessages\Support\MessagingCache;
use Syriable\LaravelMessages\Support\UserKey;

class BlockManager implements ManagesBlocks
{
    public function __construct(protected MessagingCache $cache) {}

    public function block(Model|int|string $blocker, Model|int|string $blocked): UserBlock
    {
        $blockerKey = UserKey::of($blocker);
        $blockedKey = UserKey::of($blocked);

        $block = UserBlock::query()->firstOrCreate([
            'blocker_id' => $blockerKey,
            'blocked_id' => $blockedKey,
        ]);

        $this->cache->forget($this->cacheKey($blockerKey, $blockedKey));

        if ($block->wasRecentlyCreated) {
            UserBlocked::dispatch($block);
        }

        return $block;
    }

    public function unblock(Model|int|string $blocker, Model|int|string $blocked): void
    {
        $blockerKey = UserKey::of($blocker);
        $blockedKey = UserKey::of($blocked);

        $deleted = UserBlock::query()
            ->between($blockerKey, $blockedKey)
            ->delete();

        $this->cache->forget($this->cacheKey($blockerKey, $blockedKey));

        if ($deleted > 0) {
            UserUnblocked::dispatch($blockerKey, $blockedKey);
        }
    }

    public function hasBlocked(Model|int|string $blocker, Model|int|string $blocked): bool
    {
        $blockerKey = UserKey::of($blocker);
        $blockedKey = UserKey::of($blocked);

        return $this->cache->rememberBool(
            $this->cacheKey($blockerKey, $blockedKey),
            fn (): bool => UserBlock::query()->between($blockerKey, $blockedKey)->exists(),
        );
    }

    protected function cacheKey(int|string $blocker, int|string $blocked): string
    {
        return "blocked:{$blocker}:{$blocked}";
    }
}
