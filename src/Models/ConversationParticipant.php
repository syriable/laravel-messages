<?php

declare(strict_types=1);

namespace Syriable\LaravelMessages\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Syriable\LaravelMessages\Models\Concerns\HasConfigurableKey;
use Syriable\LaravelMessages\Models\Concerns\InteractsWithPackageTables;

/**
 * Per-user state of a conversation. Every flag here belongs to a single
 * participant and is invisible to the other side.
 *
 * @property int|string $id
 * @property int|string $conversation_id
 * @property int|string $user_id
 * @property Carbon|null $last_read_at
 * @property Carbon|null $archived_at
 * @property Carbon|null $pinned_at
 * @property Carbon|null $muted_until
 * @property Carbon|null $conversation_cleared_at
 * @property array<int, string>|null $labels
 */
class ConversationParticipant extends Model
{
    use HasConfigurableKey;
    use InteractsWithPackageTables;

    protected $guarded = [];

    protected static function tableKey(): string
    {
        return 'conversation_participants';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_read_at' => 'datetime',
            'archived_at' => 'datetime',
            'pinned_at' => 'datetime',
            'muted_until' => 'datetime',
            'conversation_cleared_at' => 'datetime',
            'labels' => 'array',
        ];
    }

    /** @return BelongsTo<Conversation, $this> */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class, 'conversation_id');
    }

    /** @return BelongsTo<Model, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(static::userModel(), 'user_id');
    }

    public function hasArchived(): bool
    {
        return $this->archived_at !== null;
    }

    public function hasPinned(): bool
    {
        return $this->pinned_at !== null;
    }

    public function isMuted(): bool
    {
        return $this->muted_until !== null && $this->muted_until->isFuture();
    }

    /**
     * @return array<int, string>
     */
    public function labels(): array
    {
        return $this->labels ?? [];
    }
}
