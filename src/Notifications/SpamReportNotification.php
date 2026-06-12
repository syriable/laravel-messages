<?php

declare(strict_types=1);

namespace Syriable\LaravelMessages\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Syriable\LaravelMessages\Models\SpamEntry;
use Syriable\LaravelMessages\Support\PackageConfig;

/**
 * Intended for moderators/reviewers — wire it up in a listener for the
 * UserMarkedAsSpam event.
 */
class SpamReportNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public SpamEntry $entry)
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
        return config('laravel-messages.notifications.spam_report.channels', ['database']);
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject((string) __('laravel-messages::notifications.spam_report_subject'))
            ->line((string) __('laravel-messages::notifications.spam_report_line'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'spam_entry_id' => $this->entry->getKey(),
            'reporter_id' => $this->entry->user_id,
            'spammer_id' => $this->entry->spammer_id,
            'conversation_id' => $this->entry->conversation_id,
            'message_id' => $this->entry->message_id,
            'reason' => $this->entry->reason,
        ];
    }
}
