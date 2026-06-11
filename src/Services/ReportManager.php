<?php

declare(strict_types=1);

namespace Syriable\LaravelMessages\Services;

use Illuminate\Database\Eloquent\Model;
use Syriable\LaravelMessages\Contracts\ManagesReports;
use Syriable\LaravelMessages\Events\MessageReported;
use Syriable\LaravelMessages\Models\MessageReport;
use Syriable\LaravelMessages\Support\UserKey;

class ReportManager implements ManagesReports
{
    public function report(
        Model|int|string $reporter,
        Model $target,
        string $reason,
        ?string $description = null,
    ): MessageReport {
        $report = MessageReport::query()->create([
            'reporter_id' => UserKey::of($reporter),
            'reportable_type' => $target->getMorphClass(),
            'reportable_id' => $target->getKey(),
            'reason' => $reason,
            'description' => $description,
        ]);

        MessageReported::dispatch($report);

        return $report;
    }
}
