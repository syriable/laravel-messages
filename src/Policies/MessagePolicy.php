<?php

declare(strict_types=1);

namespace Syriable\LaravelMessages\Policies;

use Illuminate\Database\Eloquent\Model;
use Syriable\LaravelMessages\Models\Message;
use Syriable\LaravelMessages\Support\PackageConfig;

class MessagePolicy
{
    public function view(Model $user, Message $message): bool
    {
        return $message->newQuery()
            ->whereKey($message->getKey())
            ->visibleTo($user)
            ->exists();
    }

    public function update(Model $user, Message $message): bool
    {
        if (! PackageConfig::bool('editing.enabled', true) || ! $message->isSentBy($user)) {
            return false;
        }

        $window = PackageConfig::intOrNull('editing.edit_window_minutes');

        return $window === null
            || $message->created_at === null
            || $message->created_at->addMinutes($window)->isFuture();
    }

    public function delete(Model $user, Message $message): bool
    {
        return $message->conversation?->hasParticipant($user) ?? false;
    }

    public function forceDelete(Model $user, Message $message): bool
    {
        return PackageConfig::bool('deletes.allow_permanent')
            && $message->isSentBy($user);
    }

    public function report(Model $user, Message $message): bool
    {
        return $this->view($user, $message);
    }
}
