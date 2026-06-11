<?php

declare(strict_types=1);

namespace Syriable\LaravelMessages\Exceptions;

class FeatureDisabledException extends MessagingException
{
    public static function make(string $feature): self
    {
        return new self(self::translate('laravel-messages::messages.feature_disabled', ['feature' => $feature]));
    }
}
