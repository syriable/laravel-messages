<?php

declare(strict_types=1);

namespace Syriable\LaravelMessages\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Syriable\LaravelMessages\Events\MessageDeleted;
use Syriable\LaravelMessages\Exceptions\DeleteNotAllowedException;
use Syriable\LaravelMessages\Exceptions\FeatureDisabledException;
use Syriable\LaravelMessages\Models\Message;
use Syriable\LaravelMessages\Models\MessageAttachment;
use Syriable\LaravelMessages\Support\PackageConfig;
use Syriable\LaravelMessages\Support\UserKey;

/**
 * Removes a message for everyone, including its stored attachment files.
 * Opt-in via "laravel-messages.deletes.allow_permanent" and restricted to
 * the original sender.
 */
class DeleteMessagePermanently
{
    public function handle(Message $message, Model|int|string $user): void
    {
        if (! PackageConfig::bool('deletes.allow_permanent')) {
            throw FeatureDisabledException::make('permanent delete');
        }

        if (! $message->isSentBy($user)) {
            throw DeleteNotAllowedException::notTheSender();
        }

        DB::transaction(function () use ($message): void {
            $message->attachments->each(function (MessageAttachment $attachment): void {
                $attachment->deleteFile();
                $attachment->delete();
            });

            $message->forceDelete();
        });

        MessageDeleted::dispatch($message, UserKey::of($user), true);
    }
}
