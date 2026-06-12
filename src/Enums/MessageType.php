<?php

declare(strict_types=1);

namespace Syriable\LaravelMessages\Enums;

enum MessageType: string
{
    case Text = 'text';
    case Attachment = 'attachment';
    case Mixed = 'mixed';
}
