<?php

declare(strict_types=1);

namespace Syriable\LaravelMessages\Exceptions;

class TooManyMessagesException extends MessagingException
{
    public static function make(): self
    {
        return new self(self::translate('laravel-messages::messages.too_many_messages'));
    }
}
