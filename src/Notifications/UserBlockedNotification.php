<?php

declare(strict_types=1);

namespace Syriable\LaravelMessages\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Syriable\LaravelMessages\Models\UserBlock;
use Syriable\LaravelMessages\Support\PackageConfig;

/**
 * Intended for moderators/audit trails — never sent to the blocked user,
 * who must not learn they were blocked.
 */
class UserBlockedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public UserBlock $block)
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
        return config('laravel-messages.notifications.user_blocked.channels', ['database']);
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject((string) __('laravel-messages::notifications.user_blocked_subject'))
            ->line((string) __('laravel-messages::notifications.user_blocked_line'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'block_id' => $this->block->getKey(),
            'blocker_id' => $this->block->blocker_id,
            'blocked_id' => $this->block->blocked_id,
        ];
    }
}
