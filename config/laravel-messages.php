<?php

declare(strict_types=1);
use Syriable\LaravelMessages\Notifications\NewMessageNotification;
use Syriable\LaravelMessages\Notifications\SpamReportNotification;
use Syriable\LaravelMessages\Notifications\UserBlockedNotification;

return [

    /*
    |--------------------------------------------------------------------------
    | User Model
    |--------------------------------------------------------------------------
    |
    | The authenticatable model that participates in conversations. The
    | package never assumes App\Models\User — point this at any model
    | (multi-tenant user models included).
    |
    */
    'user_model' => env('MESSAGING_USER_MODEL', 'App\\Models\\User'),

    /*
    |--------------------------------------------------------------------------
    | Database
    |--------------------------------------------------------------------------
    |
    | "id_type" controls the primary key type of every package table:
    | "id" (auto-increment bigint), "uuid" or "ulid".
    |
    | "user_id_type" controls the column type used to reference users.
    | No foreign key constraint is created against your users table, so
    | the package stays compatible with Stancl Tenancy / Spatie
    | Multitenancy and custom user storage.
    |
    */
    'database' => [
        'id_type' => 'id', // id | uuid | ulid
        'user_id_type' => 'id', // id | uuid | ulid | string

        'tables' => [
            'conversations' => 'conversations',
            'conversation_participants' => 'conversation_participants',
            'messages' => 'messages',
            'message_attachments' => 'message_attachments',
            'message_statuses' => 'message_statuses',
            'message_reports' => 'message_reports',
            'user_blocks' => 'user_blocks',
            'spam_entries' => 'spam_entries',
            'message_reactions' => 'message_reactions',
            'message_edits' => 'message_edits',
            'message_drafts' => 'message_drafts',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Attachments
    |--------------------------------------------------------------------------
    */
    'attachments' => [
        'enabled' => true,

        'disk' => env('MESSAGING_ATTACHMENT_DISK', 'local'),

        // Root directory on the disk. Files are stored beneath it using a
        // per-conversation sub-directory and a randomized filename.
        'directory' => 'message-attachments',

        'allowed_mime_types' => [
            'image/jpeg',
            'image/png',
            'image/webp',
            'image/gif',
            'application/pdf',
        ],

        'allowed_extensions' => ['jpg', 'jpeg', 'png', 'webp', 'gif', 'pdf'],

        'max_file_size' => 1024 * 10, // kilobytes

        'max_attachments_per_message' => 5,

        // Days before an attachment expires and becomes eligible for
        // cleanup. null disables expiry.
        'expire_after_days' => null,

        // Generate temporary (signed) URLs when the disk supports them.
        'temporary_urls' => [
            'enabled' => false,
            'expire_after_minutes' => 30,
        ],

        // class-string<Syriable\LaravelMessages\Contracts\ScansAttachments>|null
        'virus_scanner' => null,

        // class-string<Syriable\LaravelMessages\Contracts\OptimizesImages>|null
        'image_optimizer' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Blocking
    |--------------------------------------------------------------------------
    |
    | When a user blocks another, the blocked user can no longer deliver
    | messages and receives only a generic failure — never the reason.
    |
    */
    'blocking' => [
        'enabled' => true,

        // Keep existing conversation history visible to both parties.
        'keep_history' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Spam
    |--------------------------------------------------------------------------
    */
    'spam' => [
        'enabled' => true,

        // Reject message delivery from senders the recipient marked as spam.
        'block_delivery_from_spammers' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Deletes
    |--------------------------------------------------------------------------
    |
    | Deletes are always "delete for me": the other participant keeps full
    | history and is never notified. Permanent (global) deletion can be
    | enabled below and is restricted to the message sender.
    |
    */
    'deletes' => [
        'allow_permanent' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Message editing
    |--------------------------------------------------------------------------
    */
    'editing' => [
        'enabled' => true,

        // Minutes after sending during which a message may be edited.
        // null means no time limit.
        'edit_window_minutes' => 15,

        'keep_history' => true,
    ],

    'reactions' => [
        'enabled' => true,

        // Restrict to a list of allowed reactions, or null to allow any.
        'allowed' => null,
    ],

    'forwarding' => [
        'enabled' => true,
    ],

    'drafts' => [
        'enabled' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Conversations
    |--------------------------------------------------------------------------
    */
    'conversations' => [
        // Pull a conversation out of the archive when a new message arrives.
        'unarchive_on_new_message' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Notifications
    |--------------------------------------------------------------------------
    */
    'notifications' => [
        'new_message' => [
            'enabled' => false,
            'notification' => NewMessageNotification::class,
            'channels' => ['database'],
        ],
        'spam_report' => [
            'enabled' => false,
            'notification' => SpamReportNotification::class,
            'channels' => ['database'],
        ],
        'user_blocked' => [
            // Notify moderators/admins, never the blocked user.
            'enabled' => false,
            'notification' => UserBlockedNotification::class,
            'channels' => ['database'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate limiting & abuse prevention
    |--------------------------------------------------------------------------
    */
    'rate_limiting' => [
        'enabled' => true,
        'max_messages_per_minute' => 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | Queues
    |--------------------------------------------------------------------------
    */
    'queue' => [
        // Queue connection / name used by package jobs and notifications.
        'connection' => null,
        'queue' => null,

        // Process attachments (virus scan / image optimization) on a queue.
        'process_attachments' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Cleanup
    |--------------------------------------------------------------------------
    */
    'cleanup' => [
        // Register the scheduled cleanup jobs automatically.
        'schedule' => [
            'enabled' => false,
            'cron' => '0 3 * * *',
        ],

        // Archive conversations without activity for N days. null disables.
        'archive_inactive_after_days' => null,

        // Delete resolved/dismissed reports older than N days. null disables.
        'prune_reports_after_days' => 90,
    ],

    /*
    |--------------------------------------------------------------------------
    | Search
    |--------------------------------------------------------------------------
    |
    | The default driver searches the database. Bind your own implementation
    | of Syriable\LaravelMessages\Contracts\SearchesMessages (e.g. backed by
    | Laravel Scout) to replace it.
    |
    */
    'search' => [
        'driver' => 'database',

        // Columns on your users table that participant search matches.
        'participant_columns' => ['name'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Caching
    |--------------------------------------------------------------------------
    */
    'cache' => [
        'enabled' => true,
        'store' => null, // null = default cache store
        'ttl' => 300, // seconds
        'prefix' => 'laravel-messages',
    ],
];
