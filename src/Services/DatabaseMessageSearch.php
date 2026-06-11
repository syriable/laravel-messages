<?php

declare(strict_types=1);

namespace Syriable\LaravelMessages\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Syriable\LaravelMessages\Contracts\SearchesMessages;
use Syriable\LaravelMessages\Models\Conversation;
use Syriable\LaravelMessages\Models\Message;

/**
 * Database-backed search. Swap this for a Laravel Scout implementation by
 * re-binding the SearchesMessages contract in your service provider.
 */
class DatabaseMessageSearch implements SearchesMessages
{
    public function messages(Model|int|string $user, string $term): Builder
    {
        $like = $this->likeTerm($term);

        return Message::query()
            ->visibleTo($user)
            ->where(fn (Builder $q) => $q
                ->whereRaw("body like ? escape '!'", [$like])
                ->orWhereHas('attachments', fn ($q) => $q->whereRaw("filename like ? escape '!'", [$like])))
            ->latest();
    }

    public function conversations(Model|int|string $user, string $term): Builder
    {
        $like = $this->likeTerm($term);

        /** @var array<int, string> $participantColumns */
        $participantColumns = config('laravel-messages.search.participant_columns', ['name']);

        return Conversation::query()
            ->visibleTo($user)
            ->where(fn (Builder $q) => $q
                ->whereRaw("subject like ? escape '!'", [$like])
                ->orWhereHas('messages', fn ($q) => $q->whereRaw("body like ? escape '!'", [$like]))
                ->orWhereHas('participants.user', function ($q) use ($participantColumns, $like) {
                    $q->where(function ($q) use ($participantColumns, $like) {
                        foreach ($participantColumns as $column) {
                            $q->orWhereRaw("{$column} like ? escape '!'", [$like]);
                        }
                    });
                }))
            ->latest('last_message_at');
    }

    /**
     * Escape LIKE wildcards using "!" so user input never acts as a
     * pattern ("!" itself works across SQLite, MySQL and Postgres).
     */
    protected function likeTerm(string $term): string
    {
        $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term);

        return "%{$escaped}%";
    }
}
