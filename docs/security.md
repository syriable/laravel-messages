# Security considerations

## No information leaks to rejected senders

Blocked and spam-marked senders receive the **same** generic `MessageDeliveryException` ("Your message could not be delivered."). The exception type, message and behaviour are identical for both causes, so a sender can never confirm they were blocked or marked as spam. Surface this to your API as a generic `422`/`400` — never map the cause into the response.

## Attachment safety

- **Server-side MIME sniffing**: the real file content is inspected (`finfo`); the client-provided content type is never trusted. Extension *and* detected MIME must both be allow-listed.
- **Randomized storage names**: files are stored as 40-char random names under a per-conversation directory — original filenames never touch the filesystem and paths cannot be guessed.
- **Size & count limits** enforced before storage.
- **Virus scanning hook** runs before the file is stored; a failing scan rejects the whole message.
- **Private disks + temporary URLs**: keep the disk private and enable `attachments.temporary_urls` so links expire; or serve downloads through a signed route that authorizes `view` on the message first.

## Authorization

Use the registered policies in every endpoint (`can:view,conversation`, `can:update,message`, …). The query scopes (`visibleTo`) additionally guarantee that listings never include content hidden from the requesting user, even if a developer forgets a policy check.

## Abuse prevention

- Rate limiting per sender (`rate_limiting.max_messages_per_minute`).
- Participants-only: every status mutation verifies conversation membership and throws `NotAParticipantException` otherwise.
- Reports and spam marks are append-only records with events for moderation pipelines.

## Privacy

- Per-user state (stars, archives, labels, mutes) is stored per participant and `ParticipantResource` only serializes it for its owner.
- "Delete for me" is irreversible for the deleter and invisible to the other party.
- `Messages::purgeUserData($user)` provides GDPR-grade erasure including attachment files.

## Mass assignment & SQL

All write paths go through explicit `forceFill`/`create` with controlled keys; search input is LIKE-escaped and bound, never interpolated.
