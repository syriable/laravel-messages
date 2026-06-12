# Laravel Messages — backend-only one-to-one messaging

[![Latest Version on Packagist](https://img.shields.io/packagist/v/syriable/laravel-messages.svg?style=flat-square)](https://packagist.org/packages/syriable/laravel-messages)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/syriable/laravel-messages/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/syriable/laravel-messages/actions?query=workflow%3Arun-tests+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/syriable/laravel-messages.svg?style=flat-square)](https://packagist.org/packages/syriable/laravel-messages)

A production-grade, **backend-only** one-to-one messaging system for Laravel 12+ / PHP 8.4+. It ships models, migrations, services, actions, events, policies, notifications, API resources and cleanup jobs — and deliberately **no frontend**, so you can plug any UI (Inertia, Livewire, mobile API, …) on top of clean JSON resources.

## Feature highlights

- **Private conversations** between exactly two users, created automatically on first message. Text-only, attachment-only, or both.
- **Per-participant state** — read/unread, star, archive, pin, mute, labels: every flag belongs to one participant and is invisible to the other.
- **"Delete for me"** — deleting a conversation hides it (and its history) for the deleter only; a new incoming message resurfaces the conversation containing only the new messages, exactly like major messaging platforms.
- **Blocking** — blocked senders get a *generic* delivery failure and are never told they were blocked.
- **Spam protection** — mark a sender as spam and their messages are silently rejected with the *same* generic error, so block and spam are indistinguishable to the sender. Report messages/conversations as spam into a per-user spam folder.
- **Attachments** — MIME sniffing (never trusts the client), extension/size/count limits, randomized filenames in per-conversation directories, optional temporary URLs, virus-scanner and image-optimizer hooks, expiry + cleanup.
- **Reporting** — report messages, conversations or users; moderation lifecycle (pending → reviewed/dismissed) with events and pruning.
- **Search** — database driver for message bodies, attachment filenames, subjects and participant names (LIKE-wildcard safe); swap in Scout by re-binding one contract.
- **Extras** — reactions, message editing with edit history and edit window, forwarding (with attachment copies), drafts, rate limiting, GDPR purge, configurable UUID/ULID keys, caching layer, queued jobs and notifications.

## Installation

```bash
composer require syriable/laravel-messages

php artisan vendor:publish --tag="laravel-messages-migrations"
php artisan migrate

php artisan vendor:publish --tag="laravel-messages-config"

# optional
php artisan vendor:publish --tag="laravel-messages-translations"
```

Point the package at your user model in `config/laravel-messages.php` (nothing is hard-coded to `App\Models\User`):

```php
'user_model' => App\Models\User::class,
```

Optionally add the convenience trait to that model:

```php
use Syriable\LaravelMessages\Concerns\HasMessaging;

class User extends Authenticatable
{
    use HasMessaging;
}
```

## Quick start

```php
use Syriable\LaravelMessages\Facades\Messages;
use Syriable\LaravelMessages\Data\PendingMessage;

// Via the trait
$message = $alice->sendMessageTo($bob, 'Hello!', [$uploadedPdf]);

// Or via the facade + DTO
$message = Messages::send(
    PendingMessage::make($alice, $bob)
        ->withBody('Hello!')
        ->withAttachments([$uploadedPdf])
        ->withMetadata(['client' => 'ios']),
);

// Conversation listings (Eloquent builders — paginate as you like)
$alice->conversationInbox()->with(['participants', 'lastMessage'])->paginate();
$alice->archivedConversations()->get();
$alice->spamConversations()->get();
$alice->unreadMessagesCount();

// Per-user message state
Messages::markRead($message, $bob);
Messages::markUnread($message, $bob);
Messages::star($message, $bob);
Messages::archive($conversation, $bob);
Messages::pin($conversation, $bob);
Messages::mute($conversation, $bob, now()->addDay());
Messages::label($conversation, $bob, ['work', 'urgent']);

// Delete for me (the other participant keeps everything, silently)
Messages::deleteConversationFor($conversation, $alice);
Messages::deleteMessageFor($message, $alice);

// Blocking & spam (sender always receives the same generic failure)
$alice->blockUser($bob);
$alice->markUserAsSpam($bob);

// Reporting
app(\Syriable\LaravelMessages\Contracts\ManagesReports::class)
    ->report($alice, $message, 'harassment', 'Optional description');

// Search
app(\Syriable\LaravelMessages\Contracts\SearchesMessages::class)
    ->messages($alice, 'quarterly report')->paginate();

// GDPR
Messages::purgeUserData($alice);
```

Full documentation lives in [`docs/`](docs):

- [Installation & configuration](docs/installation.md)
- [Usage](docs/usage.md)
- [Events & notifications](docs/events.md)
- [Extending the package](docs/extending.md)
- [Security considerations](docs/security.md)

## API resources

`ConversationResource`, `MessageResource`, `AttachmentResource` and `ParticipantResource` produce clean JSON with per-user state (`read`, `starred`, `unread_count`, `pinned`, …) resolved from the authenticated request user — private state of *other* participants is never exposed.

## Testing

```bash
composer test       # Pest test suite
composer analyse    # PHPStan, max level
composer format     # Laravel Pint
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
