<?php

declare(strict_types=1);

namespace Syriable\LaravelMessages\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserUnmarkedAsSpam
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public int|string $userKey,
        public int|string $spammerKey,
    ) {}
}
