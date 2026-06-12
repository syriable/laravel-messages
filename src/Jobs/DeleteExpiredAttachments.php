<?php

declare(strict_types=1);

namespace Syriable\LaravelMessages\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Syriable\LaravelMessages\Models\MessageAttachment;
use Syriable\LaravelMessages\Support\PackageConfig;

class DeleteExpiredAttachments implements ShouldQueue
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
        MessageAttachment::query()
            ->expired()
            ->each(function (MessageAttachment $attachment): void {
                $attachment->deleteFile();
                $attachment->delete();
            });
    }
}
