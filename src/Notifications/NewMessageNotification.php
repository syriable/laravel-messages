<?php

declare(strict_types=1);

namespace Syriable\LaravelMessages\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Syriable\LaravelMessages\Models\Message;
use Syriable\LaravelMessages\Support\PackageConfig;

class NewMessageNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Message $message)
    {
        $this->onConnection(PackageConfig::stringOrNull('queue.connection'));
        $this->onQueue(PackageConfig::stringOrNull('queue.queue'));
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        /** @var array<int, string> */
        return config('laravel-messages.notifications.new_message.channels', ['database']);
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject((string) __('laravel-messages::notifications.new_message_subject'))
            ->line((string) __('laravel-messages::notifications.new_message_line'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'message_id' => $this->message->getKey(),
            'conversation_id' => $this->message->conversation_id,
            'sender_id' => $this->message->sender_id,
            'preview' => str($this->message->body ?? '')->limit(100)->toString(),
            'has_attachments' => $this->message->hasAttachments(),
        ];
    }
}
