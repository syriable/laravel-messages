<?php

declare(strict_types=1);

namespace Syriable\LaravelMessages\Exceptions;

class DeleteNotAllowedException extends MessagingException
{
    public static function notTheSender(): self
    {
        return new self(self::translate('laravel-messages::messages.delete_not_sender'));
    }
}
