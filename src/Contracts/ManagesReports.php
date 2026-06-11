<?php

declare(strict_types=1);

namespace Syriable\LaravelMessages\Contracts;

use Illuminate\Database\Eloquent\Model;
use Syriable\LaravelMessages\Models\MessageReport;

interface ManagesReports
{
    /**
     * Report a message, a conversation or a user.
     */
    public function report(
        Model|int|string $reporter,
        Model $target,
        string $reason,
        ?string $description = null,
    ): MessageReport;
}
