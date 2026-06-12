<?php

declare(strict_types=1);

namespace Syriable\LaravelMessages\Exceptions;

class InvalidAttachmentException extends MessagingException
{
    public static function tooMany(int $max): self
    {
        return new self(self::translate('laravel-messages::messages.too_many_attachments', ['max' => $max]));
    }

    public static function disallowedExtension(string $extension): self
    {
        return new self(self::translate('laravel-messages::messages.disallowed_extension', ['extension' => $extension]));
    }

    public static function disallowedMimeType(string $mimeType): self
    {
        return new self(self::translate('laravel-messages::messages.disallowed_mime_type', ['mime' => $mimeType]));
    }

    public static function tooLarge(int $maxKilobytes): self
    {
        return new self(self::translate('laravel-messages::messages.attachment_too_large', ['max' => $maxKilobytes]));
    }

    public static function failedVirusScan(): self
    {
        return new self(self::translate('laravel-messages::messages.attachment_rejected'));
    }

    public static function disabled(): self
    {
        return new self(self::translate('laravel-messages::messages.attachments_disabled'));
    }
}
