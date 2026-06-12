<?php

declare(strict_types=1);

namespace Syriable\LaravelMessages\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Syriable\LaravelMessages\Contracts\OptimizesImages;
use Syriable\LaravelMessages\Models\MessageAttachment;
use Syriable\LaravelMessages\Support\PackageConfig;

/**
 * Post-upload processing: runs the configured image optimizer hook.
 */
class ProcessAttachment implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public MessageAttachment $attachment)
    {
        $this->onConnection(PackageConfig::stringOrNull('queue.connection'));
        $this->onQueue(PackageConfig::stringOrNull('queue.queue'));
    }

    public function handle(): void
    {
        $optimizer = PackageConfig::stringOrNull('attachments.image_optimizer');

        if ($optimizer === null || ! $this->attachment->isImage()) {
            return;
        }

        $instance = app($optimizer);

        if ($instance instanceof OptimizesImages) {
            $instance->optimize($this->attachment);
        }
    }
}
