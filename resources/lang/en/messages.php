<?php

declare(strict_types=1);

return [
    // Deliberately generic: shown to blocked/spam-marked senders too, so it
    // must never reveal why delivery failed.
    'delivery_failed' => 'Your message could not be delivered.',

    'cannot_message_self' => 'You cannot start a conversation with yourself.',
    'empty_message' => 'A message needs a body or at least one attachment.',
    'not_a_participant' => 'You are not a participant of this conversation.',
    'too_many_messages' => 'You are sending messages too quickly. Please try again shortly.',

    'too_many_attachments' => 'A message may have at most :max attachments.',
    'disallowed_extension' => 'Files of type ":extension" are not allowed.',
    'disallowed_mime_type' => 'Files of type ":mime" are not allowed.',
    'attachment_too_large' => 'Attachments may be at most :max KB.',
    'attachment_rejected' => 'The attachment was rejected.',
    'attachments_disabled' => 'Attachments are disabled.',

    'edit_not_sender' => 'Only the sender may edit a message.',
    'delete_not_sender' => 'Only the sender may permanently delete a message.',
    'edit_window_expired' => 'This message can no longer be edited.',
    'feature_disabled' => 'The ":feature" feature is disabled.',
];
