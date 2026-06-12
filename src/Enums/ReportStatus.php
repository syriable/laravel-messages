<?php

declare(strict_types=1);

namespace Syriable\LaravelMessages\Enums;

enum ReportStatus: string
{
    case Pending = 'pending';
    case Reviewed = 'reviewed';
    case Dismissed = 'dismissed';
}
