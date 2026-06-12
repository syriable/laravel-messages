<?php

declare(strict_types=1);

namespace Syriable\LaravelMessages\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Syriable\LaravelMessages\Enums\ReportStatus;
use Syriable\LaravelMessages\Models\Concerns\HasConfigurableKey;
use Syriable\LaravelMessages\Models\Concerns\InteractsWithPackageTables;

/**
 * A report filed by a user against a message, a conversation or another
 * user, feeding moderation workflows.
 *
 * @property int|string $id
 * @property int|string $reporter_id
 * @property string $reportable_type
 * @property int|string $reportable_id
 * @property string $reason
 * @property string|null $description
 * @property ReportStatus $status
 * @property Carbon|null $resolved_at
 */
class MessageReport extends Model
{
    use HasConfigurableKey;
    use InteractsWithPackageTables;

    protected $guarded = [];

    /**
     * @var array<string, string>
     */
    protected $attributes = [
        'status' => 'pending',
    ];

    protected static function tableKey(): string
    {
        return 'message_reports';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ReportStatus::class,
            'resolved_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Model, $this> */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(static::userModel(), 'reporter_id');
    }

    /** @return MorphTo<Model, $this> */
    public function reportable(): MorphTo
    {
        return $this->morphTo();
    }

    public function markReviewed(): void
    {
        $this->update(['status' => ReportStatus::Reviewed, 'resolved_at' => now()]);
    }

    public function dismiss(): void
    {
        $this->update(['status' => ReportStatus::Dismissed, 'resolved_at' => now()]);
    }

    /**
     * @param  Builder<MessageReport>  $query
     * @return Builder<MessageReport>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', ReportStatus::Pending);
    }

    /**
     * Resolved reports old enough to be pruned.
     *
     * @param  Builder<MessageReport>  $query
     * @return Builder<MessageReport>
     */
    public function scopePrunable(Builder $query, int $days): Builder
    {
        return $query
            ->where('status', '!=', ReportStatus::Pending)
            ->where('resolved_at', '<=', now()->subDays($days));
    }
}
