<?php

declare(strict_types=1);

namespace Syriable\LaravelMessages\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Syriable\LaravelMessages\Models\Concerns\HasConfigurableKey;
use Syriable\LaravelMessages\Models\Concerns\InteractsWithPackageTables;
use Syriable\LaravelMessages\Support\UserKey;

/**
 * @property int|string $id
 * @property int|string $blocker_id
 * @property int|string $blocked_id
 */
class UserBlock extends Model
{
    use HasConfigurableKey;
    use InteractsWithPackageTables;

    protected $guarded = [];

    protected static function tableKey(): string
    {
        return 'user_blocks';
    }

    /** @return BelongsTo<Model, $this> */
    public function blocker(): BelongsTo
    {
        return $this->belongsTo(static::userModel(), 'blocker_id');
    }

    /** @return BelongsTo<Model, $this> */
    public function blocked(): BelongsTo
    {
        return $this->belongsTo(static::userModel(), 'blocked_id');
    }

    /**
     * @param  Builder<UserBlock>  $query
     * @return Builder<UserBlock>
     */
    public function scopeBetween(Builder $query, Model|int|string $blocker, Model|int|string $blocked): Builder
    {
        return $query
            ->where('blocker_id', UserKey::of($blocker))
            ->where('blocked_id', UserKey::of($blocked));
    }
}
