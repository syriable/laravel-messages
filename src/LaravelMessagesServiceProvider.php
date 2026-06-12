<?php

declare(strict_types=1);

namespace Syriable\LaravelMessages;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Gate;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Syriable\LaravelMessages\Contracts\ManagesAttachments;
use Syriable\LaravelMessages\Contracts\ManagesBlocks;
use Syriable\LaravelMessages\Contracts\ManagesReports;
use Syriable\LaravelMessages\Contracts\ManagesSpam;
use Syriable\LaravelMessages\Contracts\Messenger as MessengerContract;
use Syriable\LaravelMessages\Contracts\SearchesMessages;
use Syriable\LaravelMessages\Jobs\ArchiveInactiveConversations;
use Syriable\LaravelMessages\Jobs\CleanTemporaryFiles;
use Syriable\LaravelMessages\Jobs\DeleteExpiredAttachments;
use Syriable\LaravelMessages\Jobs\PruneReports;
use Syriable\LaravelMessages\Models\Conversation;
use Syriable\LaravelMessages\Models\Message;
use Syriable\LaravelMessages\Policies\ConversationPolicy;
use Syriable\LaravelMessages\Policies\MessagePolicy;
use Syriable\LaravelMessages\Services\AttachmentManager;
use Syriable\LaravelMessages\Services\BlockManager;
use Syriable\LaravelMessages\Services\DatabaseMessageSearch;
use Syriable\LaravelMessages\Services\Messenger;
use Syriable\LaravelMessages\Services\ReportManager;
use Syriable\LaravelMessages\Services\SpamManager;
use Syriable\LaravelMessages\Support\PackageConfig;

class LaravelMessagesServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-messages')
            ->hasConfigFile('laravel-messages')
            ->hasMigrations([
                'create_conversations_table',
                'create_conversation_participants_table',
                'create_messages_table',
                'create_message_attachments_table',
                'create_message_statuses_table',
                'create_message_reports_table',
                'create_user_blocks_table',
                'create_spam_entries_table',
                'create_message_reactions_table',
                'create_message_edits_table',
                'create_message_drafts_table',
            ]);
    }

    public function packageRegistered(): void
    {
        // Every binding can be overridden by re-binding the contract in the
        // host application's service provider.
        $this->app->singleton(MessengerContract::class, Messenger::class);
        $this->app->singleton(ManagesAttachments::class, AttachmentManager::class);
        $this->app->singleton(ManagesBlocks::class, BlockManager::class);
        $this->app->singleton(ManagesSpam::class, SpamManager::class);
        $this->app->singleton(ManagesReports::class, ReportManager::class);
        $this->app->singleton(SearchesMessages::class, DatabaseMessageSearch::class);
    }

    public function packageBooted(): void
    {
        // Registered manually (instead of ->hasTranslations()) so the
        // namespace stays "laravel-messages" rather than the short name.
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'laravel-messages');

        if ($this->app->runningInConsole()) {
            $this->publishes(
                [__DIR__.'/../resources/lang' => $this->app->langPath('vendor/laravel-messages')],
                'laravel-messages-translations',
            );
        }

        Gate::policy(Conversation::class, ConversationPolicy::class);
        Gate::policy(Message::class, MessagePolicy::class);

        if (PackageConfig::bool('cleanup.schedule.enabled')) {
            $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
                $cron = PackageConfig::string('cleanup.schedule.cron', '0 3 * * *');

                $schedule->job(new DeleteExpiredAttachments)->cron($cron);
                $schedule->job(new CleanTemporaryFiles)->cron($cron);
                $schedule->job(new ArchiveInactiveConversations)->cron($cron);
                $schedule->job(new PruneReports)->cron($cron);
            });
        }
    }
}
