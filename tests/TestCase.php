<?php

declare(strict_types=1);

namespace Syriable\LaravelMessages\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;
use Syriable\LaravelMessages\LaravelMessagesServiceProvider;
use Syriable\LaravelMessages\Tests\TestSupport\Models\User;

class TestCase extends Orchestra
{
    /**
     * @param  Application  $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            LaravelMessagesServiceProvider::class,
        ];
    }

    /**
     * @param  Application  $app
     */
    public function getEnvironmentSetUp($app): void
    {
        config()->set('database.default', 'testing');
        config()->set('queue.default', 'sync');
        // Only top-level keys may be set before the package config merges;
        // nested keys would shadow the package defaults entirely.
        config()->set('laravel-messages.user_model', User::class);
    }

    protected function defineDatabaseMigrations(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamps();
        });

        $migrations = [
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
        ];

        foreach ($migrations as $migration) {
            (include __DIR__."/../database/migrations/{$migration}.php.stub")->up();
        }
    }
}
