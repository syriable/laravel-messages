<?php

declare(strict_types=1);

namespace Syriable\LaravelMessages\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Syriable\LaravelMessages\Events\MessageEdited;
use Syriable\LaravelMessages\Exceptions\EditNotAllowedException;
use Syriable\LaravelMessages\Exceptions\FeatureDisabledException;
use Syriable\LaravelMessages\Models\Message;
use Syriable\LaravelMessages\Support\PackageConfig;

class EditMessage
{
    public function handle(Message $message, Model|int|string $user, string $body): Message
    {
        if (! PackageConfig::bool('editing.enabled', true)) {
            throw FeatureDisabledException::make('editing');
        }

        if (! $message->isSentBy($user)) {
            throw EditNotAllowedException::notTheSender();
        }

        $window = PackageConfig::intOrNull('editing.edit_window_minutes');

        if ($window !== null
            && $message->created_at !== null
            && $message->created_at->addMinutes($window)->isPast()) {
            throw EditNotAllowedException::windowExpired();
        }

        $previousBody = $message->body;

        DB::transaction(function () use ($message, $body, $previousBody): void {
            if (PackageConfig::bool('editing.keep_history', true)) {
                $message->edits()->create(['body' => $previousBody]);
            }

            $message->forceFill(['body' => $body, 'edited_at' => now()])->save();
        });

        MessageEdited::dispatch($message, $previousBody);

        return $message;
    }
}
