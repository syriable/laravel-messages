# Extending the package

## Container bindings

Every service is bound to a contract in the service provider and can be replaced by re-binding in your `AppServiceProvider`:

| Contract | Default implementation |
| --- | --- |
| `Contracts\Messenger` | `Services\Messenger` |
| `Contracts\ManagesAttachments` | `Services\AttachmentManager` |
| `Contracts\ManagesBlocks` | `Services\BlockManager` |
| `Contracts\ManagesSpam` | `Services\SpamManager` |
| `Contracts\ManagesReports` | `Services\ReportManager` |
| `Contracts\SearchesMessages` | `Services\DatabaseMessageSearch` |

```php
$this->app->singleton(SearchesMessages::class, ScoutMessageSearch::class);
```

## Scout-backed search

```php
class ScoutMessageSearch implements SearchesMessages
{
    public function messages(Model|int|string $user, string $term): Builder
    {
        $ids = Message::search($term)->keys();

        return Message::query()->visibleTo($user)->whereKey($ids);
    }

    // conversations(): analogous
}
```

Make `Message` searchable per the Scout docs, then re-bind as above. Always re-apply `visibleTo()` so index results never leak hidden messages.

## Attachment hooks

```php
// config/laravel-messages.php
'attachments' => [
    'virus_scanner' => App\Messaging\ClamAvScanner::class,     // implements ScansAttachments
    'image_optimizer' => App\Messaging\SpatieOptimizer::class, // implements OptimizesImages
],
```

`ScansAttachments::scan(UploadedFile $file): bool` runs synchronously during validation (return `false` to reject). `OptimizesImages::optimize(MessageAttachment $attachment): void` runs in the queued `ProcessAttachment` job for image uploads.

## Actions

The behavioural building blocks are standalone, container-resolved action classes you can decorate or replace: `SendMessage`, `EditMessage`, `ForwardMessage`, `DeleteConversationForUser`, `DeleteMessageForUser`, `DeleteMessagePermanently`.

```php
$this->app->bind(SendMessage::class, AuditedSendMessage::class);
```

## Models, observers and policies

Models are standard Eloquent — register observers as usual:

```php
Message::observe(MessageObserver::class);
```

Policies (`ConversationPolicy`, `MessagePolicy`) are auto-registered with `Gate::policy()`; override them by registering your own policy for the model after the package boots.

## Translations

Publish and edit the language files (incl. the generic delivery-failure message):

```bash
php artisan vendor:publish --tag="laravel-messages-translations"
```

## Roadmap-friendly schema

The schema is group-chat ready: `conversations.type` defaults to `private` and participants live in their own table, so multi-participant conversations, webhooks and audit-log sinks can be added without breaking changes (subscribe to the events for the latter today).
