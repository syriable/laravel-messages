<?php

declare(strict_types=1);

namespace Syriable\LaravelMessages\Exceptions;

use Exception;

abstract class MessagingException extends Exception
{
    /**
     * @param  array<string, int|string>  $replace
     */
    protected static function translate(string $key, array $replace = []): string
    {
        $value = __($key, $replace);

        return is_string($value) ? $value : $key;
    }
}
