<?php

declare(strict_types=1);

namespace Syriable\LaravelMessages\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Syriable\LaravelMessages\Data\PendingMessage;
use Syriable\LaravelMessages\Events\MessageForwarded;
use Syriable\LaravelMessages\Exceptions\FeatureDisabledException;
use Syriable\LaravelMessages\Exceptions\NotAParticipantException;
use Syriable\LaravelMessages\Models\Message;
use Syriable\LaravelMessages\Models\MessageAttachment;
use Syriable\LaravelMessages\Support\PackageConfig;

class ForwardMessage
{
    public function __construct(protected SendMessage $sendMessage) {}

    public function handle(Message $original, Model|int|string $sender, Model|int|string $recipient): Message
    {
        if (! PackageConfig::bool('forwarding.enabled', true)) {
            throw FeatureDisabledException::make('forwarding');
        }

        if (! $original->newQuery()->whereKey($original->getKey())->visibleTo($sender)->exists()) {
            throw NotAParticipantException::make();
        }

        // Delivery goes through the regular pipeline so blocking, spam and
        // rate limiting all apply to forwards as well.
        $message = $this->sendMessage->handle(
            new PendingMessage(
                sender: $sender,
                recipient: $recipient,
                body: $original->body ?? '[attachment]',
            ),
        );

        $message->forceFill([
            'forwarded_from_id' => $original->getKey(),
            'body' => $original->body,
            'type' => $original->type,
        ])->save();

        $original->attachments->each(function (MessageAttachment $attachment) use ($message): void {
            $directory = trim(PackageConfig::string('attachments.directory', 'message-attachments'), '/');
            $extension = $attachment->extension;
            $newPath = "{$directory}/{$message->conversation_id}/".Str::random(40).($extension !== '' ? ".{$extension}" : '');

            Storage::disk($attachment->disk)->copy($attachment->path, $newPath);

            $message->attachments()->create([
                ...$attachment->only(['disk', 'filename', 'mime_type', 'extension', 'size', 'metadata', 'expires_at']),
                'path' => $newPath,
            ]);
        });

        MessageForwarded::dispatch($message, $original);

        return $message->load('attachments');
    }
}
