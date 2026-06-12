<?php

declare(strict_types=1);

namespace Syriable\LaravelMessages\Data;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;

/**
 * Everything needed to deliver a one-to-one message.
 */
final readonly class PendingMessage
{
    /**
     * @param  array<int, UploadedFile>  $attachments
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public Model|int|string $sender,
        public Model|int|string $recipient,
        public ?string $body = null,
        public array $attachments = [],
        public array $metadata = [],
    ) {}

    public static function make(Model|int|string $sender, Model|int|string $recipient): self
    {
        return new self($sender, $recipient);
    }

    public function withBody(?string $body): self
    {
        return new self($this->sender, $this->recipient, $body, $this->attachments, $this->metadata);
    }

    /**
     * @param  array<int, UploadedFile>  $attachments
     */
    public function withAttachments(array $attachments): self
    {
        return new self($this->sender, $this->recipient, $this->body, $attachments, $this->metadata);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function withMetadata(array $metadata): self
    {
        return new self($this->sender, $this->recipient, $this->body, $this->attachments, $metadata);
    }

    public function isEmpty(): bool
    {
        return ($this->body === null || trim($this->body) === '')
            && $this->attachments === [];
    }
}
