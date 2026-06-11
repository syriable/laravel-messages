<?php

declare(strict_types=1);

namespace Syriable\LaravelMessages\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Syriable\LaravelMessages\Models\MessageAttachment;

/**
 * @mixin MessageAttachment
 */
class AttachmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getKey(),
            'message_id' => $this->message_id,
            'filename' => $this->filename,
            'mime_type' => $this->mime_type,
            'extension' => $this->extension,
            'size' => $this->size,
            'is_image' => $this->isImage(),
            'created_at' => $this->created_at,
        ];
    }
}
