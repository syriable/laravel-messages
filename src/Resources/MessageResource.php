<?php

declare(strict_types=1);

namespace Syriable\LaravelMessages\Resources;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Syriable\LaravelMessages\Models\Message;

/**
 * @mixin Message
 */
class MessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $user = $user instanceof Model ? $user : null;

        $data = [
            'id' => $this->getKey(),
            'conversation_id' => $this->conversation_id,
            'sender_id' => $this->sender_id,
            'body' => $this->body,
            'type' => $this->type->value,
            'edited' => $this->edited_at !== null,
            'edited_at' => $this->edited_at,
            'forwarded' => $this->forwarded_from_id !== null,
            'metadata' => $this->metadata,
            'attachments' => AttachmentResource::collection($this->whenLoaded('attachments')),
            'reactions' => $this->whenLoaded(
                'reactions',
                fn () => $this->reactions->groupBy('reaction')->map->count(),
            ),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];

        // Per-user state, only resolvable for an authenticated viewer.
        if ($user !== null) {
            $data['read'] = $this->isReadBy($user);
            $data['starred'] = $this->isStarredBy($user);
            $data['sent_by_me'] = $this->isSentBy($user);
        }

        return $data;
    }
}
