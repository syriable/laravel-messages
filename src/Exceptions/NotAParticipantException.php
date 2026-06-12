<?php

declare(strict_types=1);

namespace Syriable\LaravelMessages\Exceptions;

class NotAParticipantException extends MessagingException
{
    public static function make(): self
    {
        return new self(self::translate('laravel-messages::messages.not_a_participant'));
    }
}
