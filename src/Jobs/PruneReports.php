<?php

declare(strict_types=1);

namespace Syriable\LaravelMessages\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Syriable\LaravelMessages\Models\MessageReport;
use Syriable\LaravelMessages\Support\PackageConfig;

class PruneReports implements ShouldQueue
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
        $days = PackageConfig::intOrNull('cleanup.prune_reports_after_days');

        if ($days === null) {
            return;
        }

        MessageReport::query()->prunable($days)->delete();
    }
}
