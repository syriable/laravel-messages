<?php

declare(strict_types=1);

namespace Syriable\LaravelMessages\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Syriable\LaravelMessages\Models\Concerns\HasConfigurableKey;
use Syriable\LaravelMessages\Models\Concerns\InteractsWithPackageTables;

/**
 * @property int|string $id
 * @property int|string $conversation_id
 * @property int|string $user_id
 * @property string|null $body
 * @property array<string, mixed>|null $metadata
 */
class MessageDraft extends Model
{
    use HasConfigurableKey;
    use InteractsWithPackageTables;

    protected $guarded = [];

    protected static function tableKey(): string
    {
        return 'message_drafts';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
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
}
