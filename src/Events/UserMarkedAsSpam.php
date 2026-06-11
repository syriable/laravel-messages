<?php

declare(strict_types=1);

namespace Syriable\LaravelMessages\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Syriable\LaravelMessages\Models\SpamEntry;

class UserMarkedAsSpam
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public SpamEntry $entry) {}
}
