<?php

declare(strict_types=1);

namespace Syriable\LaravelMessages\Models\Concerns;

use Illuminate\Database\Eloquent\Concerns\HasUniqueStringIds;
use Illuminate\Support\Str;

/**
 * Gives every package model a primary key matching the configured
 * "laravel-messages.database.id_type": auto-increment id, UUID or ULID.
 */
trait HasConfigurableKey
{
    use HasUniqueStringIds;

    /**
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return in_array(static::keyStrategy(), ['uuid', 'ulid'], true)
            ? [$this->getKeyName()]
            : [];
    }

    public function newUniqueId(): string
    {
        return match (static::keyStrategy()) {
            'ulid' => strtolower((string) Str::ulid()),
            default => (string) Str::orderedUuid(),
        };
    }

    protected function isValidUniqueId(mixed $value): bool
    {
        return match (static::keyStrategy()) {
            'uuid' => Str::isUuid($value),
            'ulid' => Str::isUlid($value),
            default => true,
        };
    }

    protected static function keyStrategy(): string
    {
        /** @var string */
        return config('laravel-messages.database.id_type', 'id');
    }
}
