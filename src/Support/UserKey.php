<?php

declare(strict_types=1);

namespace Syriable\LaravelMessages\Support;

use Illuminate\Database\Eloquent\Model;

/**
 * Normalizes "a user" (model instance or raw key) into the scalar key
 * stored in the package tables.
 */
final class UserKey
{
    public static function of(Model|int|string $user): int|string
    {
        if ($user instanceof Model) {
            /** @var int|string */
            return $user->getKey();
        }

        return $user;
    }

    /**
     * Deterministic identity for a pair of users, used to enforce a single
     * private conversation per pair.
     */
    public static function pairHash(Model|int|string $a, Model|int|string $b): string
    {
        $keys = [(string) self::of($a), (string) self::of($b)];

        sort($keys, SORT_STRING);

        return hash('sha256', implode('|', $keys));
    }
}
