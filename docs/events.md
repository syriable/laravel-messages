# Events & notifications

## Events

All events are plain, serializable classes (`SerializesModels`, `InteractsWithSockets`) — attach **queued listeners** (`ShouldQueue`) to run any workflow asynchronously, and add `ShouldBroadcast` implementations of your own for realtime UIs.

| Event | Payload | Fired when |
| --- | --- | --- |
| `MessageSent` | `Message $message` | a message is delivered |
| `MessageRead` / `MessageUnread` | `Message`, `int\|string $userKey` | per-user read state changes |
| `MessageStarred` / `MessageUnstarred` | `Message`, `$userKey` | per-user star state changes |
| `MessageDeleted` | `Message`, `$userKey`, `bool $permanent` | delete-for-me or permanent delete |
| `MessageEdited` | `Message`, `?string $previousBody` | a message is edited |
| `MessageForwarded` | `Message $message`, `Message $original` | a message is forwarded |
| `MessageReported` | `MessageReport $report` | any report is filed (message/conversation/user) |
| `MessageReactionAdded` / `MessageReactionRemoved` | reaction data | reactions change |
| `ConversationArchived` / `ConversationUnarchived` | `Conversation`, `$userKey` | per-user archive state changes |
| `ConversationDeleted` | `Conversation`, `$userKey` | delete-for-me on a conversation |
| `UserBlocked` / `UserUnblocked` | `UserBlock` / keys | block lifecycle |
| `UserMarkedAsSpam` / `UserUnmarkedAsSpam` | `SpamEntry` / keys | sender-level spam marks |
| `AttachmentUploaded` | `MessageAttachment` | each stored attachment |

Example moderation listener:

```php
class EscalateReport implements ShouldQueue
{
    public function handle(MessageReported $event): void
    {
        Moderator::all()->each->notify(new ReportFiled($event->report));
    }
}
```

## Notifications

Three queue-aware notifications ship with the package (channels configurable per notification in `notifications.*.channels`):

- `NewMessageNotification` — sent automatically to the recipient when `notifications.new_message.enabled` is true. Muted conversations suppress it. Replace the class via `notifications.new_message.notification`.
- `SpamReportNotification` — for moderators; wire it in a `UserMarkedAsSpam` listener.
- `UserBlockedNotification` — for moderation/audit; **never** sent to the blocked user.

All notifications implement `ShouldQueue` and honour `queue.connection` / `queue.queue`.
