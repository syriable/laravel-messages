<?php

declare(strict_types=1);

namespace Syriable\LaravelMessages\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Syriable\LaravelMessages\Models\Concerns\HasConfigurableKey;
use Syriable\LaravelMessages\Models\Concerns\InteractsWithPackageTables;

/**
 * A spam mark created by "user_id". It may target a sender (spammer_id),
 * a whole conversation and/or a single message.
 *
 * @property int|string $id
 * @property int|string $user_id
 * @property int|string|null $spammer_id
 * @property int|string|null $conversation_id
 * @property int|string|null $message_id
 * @property string|null $reason
 */
class SpamEntry extends Model
{
    use HasConfigurableKey;
    use InteractsWithPackageTables;

    protected $guarded = [];

    protected static function tableKey(): string
    {
        return 'spam_entries';
    }

    /** @return BelongsTo<Model, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(static::userModel(), 'user_id');
    }

    /** @return BelongsTo<Model, $this> */
    public function spammer(): BelongsTo
    {
        return $this->belongsTo(static::userModel(), 'spammer_id');
    }

    /** @return BelongsTo<Conversation, $this> */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class, 'conversation_id');
    }

    /** @return BelongsTo<Message, $this> */
    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'message_id');
    }
}
