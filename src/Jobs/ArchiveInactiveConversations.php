<?php

declare(strict_types=1);

namespace Syriable\LaravelMessages\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Syriable\LaravelMessages\Models\Conversation;
use Syriable\LaravelMessages\Support\PackageConfig;

/**
 * Archives (for every participant) conversations without activity for the
 * configured number of days.
 */
class ArchiveInactiveConversations implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public function __construct()
    {
        $this->onConnection(PackageConfig::stringOrNull('queue.connection'));
        $this->onQueue(PackageConfig::stringOrNull('queue.queue'));
    }

    public function handle(): void
    {
        $days = PackageConfig::intOrNull('cleanup.archive_inactive_after_days');

        if ($days === null) {
            return;
        }

        Conversation::query()
            ->where('last_message_at', '<=', now()->subDays($days))
            ->each(function (Conversation $conversation): void {
                $conversation->participants()
                    ->whereNull('archived_at')
                    ->update(['archived_at' => now()]);
            });
    }
}
