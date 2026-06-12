<?php

declare(strict_types=1);

namespace Syriable\LaravelMessages\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Storage;
use Syriable\LaravelMessages\Models\MessageAttachment;
use Syriable\LaravelMessages\Support\PackageConfig;

/**
 * Removes orphaned files from the attachment directory: anything on disk
 * without a matching attachment record (failed uploads, leftovers from
 * permanently deleted messages).
 */
class CleanTemporaryFiles implements ShouldQueue
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
        $disk = PackageConfig::string('attachments.disk', 'local');
        $directory = trim(PackageConfig::string('attachments.directory', 'message-attachments'), '/');

        $storage = Storage::disk($disk);

        $known = MessageAttachment::query()
            ->where('disk', $disk)
            ->pluck('path')
            ->flip();

        /** @var array<int, string> $files */
        $files = $storage->allFiles($directory);

        foreach ($files as $file) {
            if (! $known->has($file)) {
                $storage->delete($file);
            }
        }
    }
}
