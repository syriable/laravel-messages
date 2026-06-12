<?php

declare(strict_types=1);

namespace Syriable\LaravelMessages\Policies;

use Illuminate\Database\Eloquent\Model;
use Syriable\LaravelMessages\Models\Conversation;

class ConversationPolicy
{
    public function view(Model $user, Conversation $conversation): bool
    {
        return $conversation->hasParticipant($user);
    }

    public function participate(Model $user, Conversation $conversation): bool
    {
        return $conversation->hasParticipant($user);
    }

    public function archive(Model $user, Conversation $conversation): bool
    {
        return $conversation->hasParticipant($user);
    }

    public function delete(Model $user, Conversation $conversation): bool
    {
        return $conversation->hasParticipant($user);
    }

    public function report(Model $user, Conversation $conversation): bool
    {
        return $conversation->hasParticipant($user);
    }
}
