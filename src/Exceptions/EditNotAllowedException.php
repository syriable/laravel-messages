<?php

declare(strict_types=1);

namespace Syriable\LaravelMessages\Exceptions;

class EditNotAllowedException extends MessagingException
{
    public static function notTheSender(): self
    {
        return new self(self::translate('laravel-messages::messages.edit_not_sender'));
    }

    public static function windowExpired(): self
    {
        return new self(self::translate('laravel-messages::messages.edit_window_expired'));
    }
}
