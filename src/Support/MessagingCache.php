<?php

declare(strict_types=1);

namespace Syriable\LaravelMessages\Support;

use Closure;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;

/**
 * Thin caching layer for hot lookups (block checks, unread counters).
 * Disabled entirely via "laravel-messages.cache.enabled".
 */
class MessagingCache
{
    public function remember(string $key, Closure $callback): mixed
    {
        if (! $this->enabled()) {
            return $callback();
        }

        $store = $this->store();
        $qualified = $this->qualify($key);

        $value = $store->get($qualified);

        if ($value === null) {
            $value = $callback();

            $store->put($qualified, $value, PackageConfig::int('cache.ttl', 300));
        }

        return $value;
    }

    /**
     * @param  Closure(): int  $callback
     */
    public function rememberInt(string $key, Closure $callback): int
    {
        $value = $this->remember($key, $callback);

        return is_numeric($value) ? (int) $value : $callback();
    }

    /**
     * @param  Closure(): bool  $callback
     */
    public function rememberBool(string $key, Closure $callback): bool
    {
        return (bool) $this->remember($key, $callback);
    }

    public function forget(string ...$keys): void
    {
        if (! $this->enabled()) {
            return;
        }

        foreach ($keys as $key) {
            $this->store()->forget($this->qualify($key));
        }
    }

    protected function enabled(): bool
    {
        return PackageConfig::bool('cache.enabled', true);
    }

    protected function qualify(string $key): string
    {
        $prefix = PackageConfig::string('cache.prefix', 'laravel-messages');

        return "{$prefix}:{$key}";
    }

    protected function store(): Repository
    {
        return Cache::store(PackageConfig::stringOrNull('cache.store'));
    }
}
