<?php

declare(strict_types=1);

namespace Syriable\LaravelMessages\Support;

/**
 * Typed access to the package configuration: values always come back in
 * the shape the caller expects, even when the host app misconfigures them.
 */
final class PackageConfig
{
    public static function get(string $key): mixed
    {
        return config("laravel-messages.{$key}");
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $value = self::get($key);

        return is_bool($value) ? $value : $default;
    }

    public static function int(string $key, int $default = 0): int
    {
        $value = self::get($key);

        return is_numeric($value) ? (int) $value : $default;
    }

    public static function intOrNull(string $key): ?int
    {
        $value = self::get($key);

        return is_numeric($value) ? (int) $value : null;
    }

    public static function string(string $key, string $default = ''): string
    {
        $value = self::get($key);

        return is_string($value) ? $value : $default;
    }

    public static function stringOrNull(string $key): ?string
    {
        $value = self::get($key);

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @param  array<mixed>  $default
     * @return array<mixed>
     */
    public static function array(string $key, array $default = []): array
    {
        $value = self::get($key);

        return is_array($value) ? $value : $default;
    }
}
