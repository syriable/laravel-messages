<?php

declare(strict_types=1);

namespace Syriable\LaravelMessages\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;
use Syriable\LaravelMessages\Enums\MessageType;
use Syriable\LaravelMessages\Models\Concerns\HasConfigurableKey;
use Syriable\LaravelMessages\Models\Concerns\InteractsWithPackageTables;
use Syriable\LaravelMessages\Support\UserKey;

/**
 * @property int|string $id
 * @property int|string $conversation_id
 * @property int|string $sender_id
 * @property string|null $body
 * @property MessageType $type
 * @property int|string|null $forwarded_from_id
 * @property Carbon|null $edited_at
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Message extends Model
{
    use HasConfigurableKey;
    use InteractsWithPackageTables;
    use SoftDeletes;

    protected $guarded = [];

    protected static function tableKey(): string
    {
        return 'messages';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => MessageType::class,
            'edited_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    /** @return BelongsTo<Conversation, $this> */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class, 'conversation_id');
    }

    /** @return BelongsTo<Model, $this> */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(static::userModel(), 'sender_id');
    }

    /** @return HasMany<MessageAttachment, $this> */
    public function attachments(): HasMany
    {
        return $this->hasMany(MessageAttachment::class, 'message_id');
    }

    /** @return HasMany<MessageStatus, $this> */
    public function statuses(): HasMany
    {
        return $this->hasMany(MessageStatus::class, 'message_id');
    }

    /** @return HasMany<MessageReaction, $this> */
    public function reactions(): HasMany
    {
        return $this->hasMany(MessageReaction::class, 'message_id');
    }

    /** @return HasMany<MessageEdit, $this> */
    public function edits(): HasMany
    {
        return $this->hasMany(MessageEdit::class, 'message_id');
    }

    /** @return BelongsTo<Message, $this> */
    public function forwardedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'forwarded_from_id');
    }

    public function isSentBy(Model|int|string $user): bool
    {
        return (string) $this->sender_id === (string) UserKey::of($user);
    }

    public function hasAttachments(): bool
    {
        return $this->type !== MessageType::Text;
    }

    /**
     * The per-user status row for this message (created on demand).
     */
    public function statusFor(Model|int|string $user): MessageStatus
    {
        return $this->statuses()->firstOrCreate([
            'user_id' => UserKey::of($user),
        ]);
    }

    public function isReadBy(Model|int|string $user): bool
    {
        if ($this->isSentBy($user)) {
            return true;
        }

        return $this->statuses()
            ->where('user_id', UserKey::of($user))
            ->whereNotNull('read_at')
            ->exists();
    }

    public function isStarredBy(Model|int|string $user): bool
    {
        return $this->statuses()
            ->where('user_id', UserKey::of($user))
            ->whereNotNull('starred_at')
            ->exists();
    }

    public function isDeletedFor(Model|int|string $user): bool
    {
        return ! $this->newQuery()
            ->whereKey($this->getKey())
            ->visibleTo($user)
            ->exists();
    }

    /**
     * Messages the given user can see: they participate in the conversation,
     * the message is newer than their "delete for me" watermark, and they
     * have not deleted the individual message.
     *
     * @param  Builder<Message>  $query
     * @return Builder<Message>
     */
    public function scopeVisibleTo(Builder $query, Model|int|string $user): Builder
    {
        $key = UserKey::of($user);
        $messages = $this->getTable();
        $participants = (new ConversationParticipant)->getTable();
        $statuses = (new MessageStatus)->getTable();

        return $query
            ->whereExists(fn (QueryBuilder $q) => $q
                ->from($participants)
                ->whereColumn("{$participants}.conversation_id", "{$messages}.conversation_id")
                ->where("{$participants}.user_id", $key)
                ->where(fn (QueryBuilder $q) => $q
                    ->whereNull("{$participants}.conversation_cleared_at")
                    ->orWhereColumn("{$messages}.created_at", '>', "{$participants}.conversation_cleared_at")))
            ->whereNotExists(fn (QueryBuilder $q) => $q
                ->from($statuses)
                ->whereColumn("{$statuses}.message_id", "{$messages}.id")
                ->where("{$statuses}.user_id", $key)
                ->whereNotNull("{$statuses}.deleted_at"));
    }

    /**
     * @param  Builder<Message>  $query
     * @return Builder<Message>
     */
    public function scopeStarredBy(Builder $query, Model|int|string $user): Builder
    {
        return $query->whereHas('statuses', fn ($q) => $q
            ->where('user_id', UserKey::of($user))
            ->whereNotNull('starred_at'));
    }

    /**
     * @param  Builder<Message>  $query
     * @return Builder<Message>
     */
    public function scopeUnreadBy(Builder $query, Model|int|string $user): Builder
    {
        $key = UserKey::of($user);

        return $query
            ->where('sender_id', '!=', $key)
            ->whereDoesntHave('statuses', fn ($q) => $q
                ->where('user_id', $key)
                ->whereNotNull('read_at'));
    }
}
