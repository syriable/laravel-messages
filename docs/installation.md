# Installation & configuration

## Requirements

- PHP 8.4+
- Laravel 12 or 13

## Install

```bash
composer require syriable/laravel-messages

php artisan vendor:publish --tag="laravel-messages-migrations"
php artisan migrate

php artisan vendor:publish --tag="laravel-messages-config"
php artisan vendor:publish --tag="laravel-messages-translations" # optional
```

## Database schema

The migrations create eleven tables:

| Table | Purpose |
| --- | --- |
| `conversations` | One row per private conversation; `private_key` (hash of the participant pair) guarantees a single conversation per pair and `last_message_at` keeps inbox sorting index-friendly. |
| `conversation_participants` | One row per user per conversation. **All per-user conversation state lives here**: `last_read_at`, `archived_at`, `pinned_at`, `muted_until`, `conversation_cleared_at` (the "delete for me" watermark) and `labels`. |
| `messages` | Message body, type (`text` / `attachment` / `mixed`), edit timestamp, forward origin; soft-deletable for global removal. Indexed by `(conversation_id, created_at)`. |
| `message_attachments` | Stored file metadata: disk, randomized path, original filename, MIME, size, optional `expires_at`. |
| `message_statuses` | Per-user *message* state: `read_at`, `starred_at`, `deleted_at` (delete-for-me). Unique per `(message_id, user_id)`. |
| `message_reports` | Reports against messages, conversations or users (polymorphic), with moderation status. |
| `user_blocks` | Blocker/blocked pairs. |
| `spam_entries` | Spam marks: sender-level (`spammer_id`), conversation-level and/or message-level. |
| `message_reactions` | Emoji/string reactions, unique per user+message+reaction. |
| `message_edits` | Body snapshots forming the edit history. |
| `message_drafts` | One draft per user per conversation. |

## Key configuration options (`config/laravel-messages.php`)

### User model

```php
'user_model' => App\Models\User::class,
```

No foreign keys are created against your users table, and user key columns are configurable (`database.user_id_type`: `id`, `uuid`, `ulid` or `string`), which keeps the package compatible with **Stancl Tenancy**, **Spatie Multitenancy** and external identity providers.

### UUID / ULID primary keys

```php
'database' => [
    'id_type' => 'ulid', // id | uuid | ulid
],
```

Every package table and model switches key strategy together. Configure **before** running the migrations.

### Table names

All table names can be remapped under `database.tables` (useful for prefixing or tenant schemas).

### Attachments

```php
'attachments' => [
    'disk' => 's3',
    'directory' => 'message-attachments',
    'allowed_mime_types' => [...],
    'allowed_extensions' => [...],
    'max_file_size' => 10240,           // KB
    'max_attachments_per_message' => 5,
    'expire_after_days' => null,
    'temporary_urls' => ['enabled' => true, 'expire_after_minutes' => 30],
    'virus_scanner' => null,            // class-string<ScansAttachments>
    'image_optimizer' => null,          // class-string<OptimizesImages>
],
```

### Scheduled cleanup

```php
'cleanup' => [
    'schedule' => ['enabled' => true, 'cron' => '0 3 * * *'],
    'archive_inactive_after_days' => 180,
    'prune_reports_after_days' => 90,
],
```

When `schedule.enabled` is true the package registers four jobs on the scheduler: `DeleteExpiredAttachments`, `CleanTemporaryFiles`, `ArchiveInactiveConversations` and `PruneReports`. You can also schedule them yourself and leave this off.

### Queues

`queue.connection` / `queue.queue` apply to every package job and notification. `queue.process_attachments` controls whether post-upload processing (image optimization) runs on the queue or inline.
