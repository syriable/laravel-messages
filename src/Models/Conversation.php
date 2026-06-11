<?php

declare(strict_types=1);

namespace Syriable\LaravelMessages\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;
use Syriable\LaravelMessages\Models\Concerns\HasConfigurableKey;
use Syriable\LaravelMessages\Models\Concerns\InteractsWithPackageTables;
use Syriable\LaravelMessages\Support\UserKey;

/**
 * @property int|string $id
 * @property string $type
 * @property string|null $subject
 * @property string|null $private_key
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $last_message_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Conversation extends Model
{
    use HasConfigurableKey;
    use InteractsWithPackageTables;
    use SoftDeletes;

    public const TYPE_PRIVATE = 'private';

    protected $guarded = [];

    protected static function tableKey(): string
    {
        return 'conversations';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    /** @return HasMany<ConversationParticipant, $this> */
    public function participants(): HasMany
    {
        return $this->hasMany(ConversationParticipant::class, 'conversation_id');
    }

    /** @return HasMany<Message, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'conversation_id');
    }

    /** @return HasOne<Message, $this> */
    public function lastMessage(): HasOne
    {
        return $this->hasOne(Message::class, 'conversation_id')->latestOfMany();
    }

    public function participantFor(Model|int|string $user): ?ConversationParticipant
    {
        $key = UserKey::of($user);

        if ($this->relationLoaded('participants')) {
            return $this->participants->firstWhere('user_id', $key);
        }

        return $this->participants()->where('user_id', $key)->first();
    }

    public function hasParticipant(Model|int|string $user): bool
    {
        return $this->participantFor($user) !== null;
    }

    public function otherParticipant(Model|int|string $user): ?ConversationParticipant
    {
        return $this->participants()
            ->where('user_id', '!=', UserKey::of($user))
            ->first();
    }

    /**
     * Messages of this conversation as seen by the given user: respects the
     * per-user "delete for me" watermark and per-message deletions.
     *
     * @return Builder<Message>
     */
    public function messagesFor(Model|int|string $user): Builder
    {
        return $this->messages()->getQuery()->visibleTo($user);
    }

    public function unreadCountFor(Model|int|string $user): int
    {
        $key = UserKey::of($user);
        $statuses = (new MessageStatus)->getTable();
        $messages = (new Message)->getTable();

        return $this->messagesFor($user)
            ->where("{$messages}.sender_id", '!=', $key)
            ->whereNotExists(fn (QueryBuilder $q) => $q
                ->from($statuses)
                ->whereColumn("{$statuses}.message_id", "{$messages}.id")
                ->where("{$statuses}.user_id", $key)
                ->whereNotNull("{$statuses}.read_at"))
            ->count();
    }

    /**
     * Conversations the user participates in and has not fully deleted:
     * a cleared conversation only reappears once it holds a newer message.
     *
     * @param  Builder<Conversation>  $query
     * @return Builder<Conversation>
     */
    public function scopeVisibleTo(Builder $query, Model|int|string $user): Builder
    {
        $key = UserKey::of($user);
        $conversations = $this->getTable();
        $participants = (new ConversationParticipant)->getTable();
        $messages = (new Message)->getTable();

        return $query->whereExists(fn (QueryBuilder $q) => $q
            ->from($participants)
            ->whereColumn("{$participants}.conversation_id", "{$conversations}.id")
            ->where("{$participants}.user_id", $key)
            ->where(fn (QueryBuilder $q) => $q
                ->whereNull("{$participants}.conversation_cleared_at")
                ->orWhereExists(fn (QueryBuilder $q) => $q
                    ->from($messages)
                    ->whereColumn("{$messages}.conversation_id", "{$conversations}.id")
                    ->whereColumn("{$messages}.created_at", '>', "{$participants}.conversation_cleared_at"))));
    }

    /**
     * @param  Builder<Conversation>  $query
     * @return Builder<Conversation>
     */
    public function scopeWhereParticipant(Builder $query, Model|int|string $user): Builder
    {
        return $query->whereHas(
            'participants',
            fn ($q) => $q->where('user_id', UserKey::of($user)),
        );
    }

    /**
     * @param  Builder<Conversation>  $query
     * @return Builder<Conversation>
     */
    public function scopeArchivedBy(Builder $query, Model|int|string $user): Builder
    {
        return $query->whereHas('participants', fn ($q) => $q
            ->where('user_id', UserKey::of($user))
            ->whereNotNull('archived_at'));
    }

    /**
     * @param  Builder<Conversation>  $query
     * @return Builder<Conversation>
     */
    public function scopeNotArchivedBy(Builder $query, Model|int|string $user): Builder
    {
        return $query->whereHas('participants', fn ($q) => $q
            ->where('user_id', UserKey::of($user))
            ->whereNull('archived_at'));
    }

    /**
     * @param  Builder<Conversation>  $query
     * @return Builder<Conversation>
     */
    public function scopePinnedBy(Builder $query, Model|int|string $user): Builder
    {
        return $query->whereHas('participants', fn ($q) => $q
            ->where('user_id', UserKey::of($user))
            ->whereNotNull('pinned_at'));
    }

    /**
     * Conversations the user put in their spam folder, either by reporting
     * the conversation itself or by marking the other participant as a
     * spammer.
     *
     * @param  Builder<Conversation>  $query
     * @return Builder<Conversation>
     */
    public function scopeSpamFor(Builder $query, Model|int|string $user): Builder
    {
        return $query->where(
            fn ($q) => $this->applySpamConstraint($q, $user),
        );
    }

    /**
     * @param  Builder<Conversation>  $query
     * @return Builder<Conversation>
     */
    public function scopeNotSpamFor(Builder $query, Model|int|string $user): Builder
    {
        return $query->whereNot(
            fn ($q) => $this->applySpamConstraint($q, $user),
        );
    }

    /**
     * @param  Builder<Conversation>  $query
     */
    protected function applySpamConstraint(Builder $query, Model|int|string $user): void
    {
        $key = UserKey::of($user);
        $conversations = $this->getTable();
        $participants = (new ConversationParticipant)->getTable();
        $spam = (new SpamEntry)->getTable();

        $query->whereExists(fn (QueryBuilder $q) => $q
            ->from($spam)
            ->where("{$spam}.user_id", $key)
            ->where(fn (QueryBuilder $q) => $q
                ->whereColumn("{$spam}.conversation_id", "{$conversations}.id")
                ->orWhereIn("{$spam}.spammer_id", fn (QueryBuilder $sub) => $sub
                    ->select("{$participants}.user_id")
                    ->from($participants)
                    ->whereColumn("{$participants}.conversation_id", "{$conversations}.id")
                    ->where("{$participants}.user_id", '!=', $key))));
    }
}
