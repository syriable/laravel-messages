# Usage

Everything is reachable two ways: the `Messages` facade (backed by the `Syriable\LaravelMessages\Contracts\Messenger` contract) or the `HasMessaging` trait on your user model.

## Sending messages

```php
// Text, attachments, or both — at least one is required.
$message = $alice->sendMessageTo($bob, 'Hi!');
$message = $alice->sendMessageTo($bob, null, [$upload]);
$message = $alice->sendMessageTo($bob, 'Contract attached', [$upload], ['source' => 'web']);
```

The conversation between a pair of users is created automatically on first contact and always reused afterwards (enforced by a unique pair hash, so concurrent first messages cannot create duplicates).

Delivery is refused with a **generic** `MessageDeliveryException` when the recipient blocked the sender or marked them as spam — the sender cannot distinguish the two, or even confirm anything happened. `TooManyMessagesException` is thrown beyond the configured rate limit, `EmptyMessageException` for empty payloads, `InvalidAttachmentException` for rejected files.

## Reading conversations

```php
Messages::inbox($user);     // visible, not archived, not spam
Messages::archived($user);
Messages::spam($user);
Messages::starredMessages($user);
Messages::unreadCount($user);   // cached (config: cache.*)

$conversation->messagesFor($user);   // respects delete-for-me + per-message deletes
$conversation->unreadCountFor($user);
$conversation->otherParticipant($user);
```

All listing methods return Eloquent builders, so you can eager-load, filter and paginate freely.

## Per-user statuses

```php
Messages::markRead($message, $user);
Messages::markUnread($message, $user);
Messages::markConversationRead($conversation, $user);
Messages::star($message, $user);
Messages::unstar($message, $user);
Messages::archive($conversation, $user);
Messages::unarchive($conversation, $user);
Messages::pin($conversation, $user);
Messages::mute($conversation, $user);                  // forever
Messages::mute($conversation, $user, now()->addDay()); // timed
Messages::label($conversation, $user, ['work']);
Messages::unlabel($conversation, $user, ['work']);     // null = clear all
```

Statuses are strictly per participant: if Alice stars or archives something, Bob never sees it.

By default a new incoming message moves an archived conversation back into the inbox (`conversations.unarchive_on_new_message`).

## Delete for me

```php
Messages::deleteConversationFor($conversation, $alice);
```

- The conversation disappears completely from Alice's inbox; Bob keeps full history and is never notified.
- When Bob sends a new message, the conversation reappears for Alice **containing only the new message** — the old ones stay hidden forever.

```php
Messages::deleteMessageFor($message, $alice);          // single message, for Alice only
Messages::deleteMessagePermanently($message, $alice);  // global; requires deletes.allow_permanent
```

Permanent deletion is opt-in via config, restricted to the sender, and removes the stored attachment files too.

## Blocking

```php
$alice->blockUser($bob);
$alice->unblockUser($bob);
$alice->hasBlockedUser($bob);
```

Blocked users receive the generic delivery failure; existing history stays visible. Blocking is fully independent from the spam system.

## Spam

```php
$alice->markUserAsSpam($bob, 'unsolicited ads'); // silently rejects future deliveries
$alice->unmarkUserAsSpam($bob);

$spam = app(\Syriable\LaravelMessages\Contracts\ManagesSpam::class);
$spam->reportMessageAsSpam($alice, $message);            // record only, no delivery impact
$spam->reportConversationAsSpam($alice, $conversation);  // moves it to Alice's spam folder
$spam->removeConversationFromSpam($alice, $conversation);
```

## Reporting

```php
$reports = app(\Syriable\LaravelMessages\Contracts\ManagesReports::class);

$report = $reports->report($alice, $message, 'harassment', 'Details…');
$reports->report($alice, $conversation, 'spam');
$reports->report($alice, $bob, 'impersonation');

$report->markReviewed();
$report->dismiss();
MessageReport::pending()->get();
```

## Search

```php
$search = app(\Syriable\LaravelMessages\Contracts\SearchesMessages::class);

$search->messages($user, 'invoice')->paginate();      // bodies + attachment filenames
$search->conversations($user, 'alice')->get();        // subjects, content, participant names
```

Results always respect visibility (delete-for-me, per-message deletes). LIKE wildcards in the term are escaped.

## Reactions, edits, forwards, drafts

```php
Messages::react($message, $user, '👍');
Messages::unreact($message, $user, '👍');

Messages::editMessage($message, $sender, 'fixed typo');  // history in $message->edits
Messages::forward($message, $bob, $carol);               // attachments are copied

Messages::saveDraft($conversation, $user, 'wip…');       // auto-discarded on send
Messages::discardDraft($conversation, $user);
```

## Authorization

Policies are registered automatically for `Conversation` (`view`, `participate`, `archive`, `delete`, `report`) and `Message` (`view`, `update`, `delete`, `forceDelete`, `report`):

```php
$request->user()->can('view', $conversation);
$request->user()->can('update', $message); // sender + edit window
```

## API resources

```php
use Syriable\LaravelMessages\Resources\{ConversationResource, MessageResource};

return ConversationResource::collection(
    Messages::inbox($request->user())->with(['participants', 'lastMessage'])->paginate()
);

return MessageResource::collection(
    $conversation->messagesFor($request->user())->with('attachments')->latest()->paginate()
);
```

## GDPR

```php
Messages::purgeUserData($user);
```

Force-deletes the user's messages (incl. attachment files on disk), statuses, reactions, drafts, participations, blocks, spam entries and reports.
