<?php

declare(strict_types=1);

namespace Syriable\LaravelMessages\Exceptions;

/**
 * Thrown whenever delivery is refused — because the sender is blocked,
 * marked as spam, or any other delivery rule. The message is deliberately
 * generic so a rejected sender can never learn why delivery failed.
 */
class MessageDeliveryException extends MessagingException
{
    public static function rejected(): self
    {
        return new self(self::translate('laravel-messages::messages.delivery_failed'));
    }

    public static function cannotMessageSelf(): self
    {
        return new self(self::translate('laravel-messages::messages.cannot_message_self'));
    }
}
