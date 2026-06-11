<?php

declare(strict_types=1);

namespace Syriable\LaravelMessages\Resources;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Syriable\LaravelMessages\Models\Conversation;

/**
 * @mixin Conversation
 */
class ConversationResource extends JsonResource
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
            'type' => $this->type,
            'subject' => $this->subject,
            'last_message_at' => $this->last_message_at,
            'participants' => ParticipantResource::collection($this->whenLoaded('participants')),
            'last_message' => new MessageResource($this->whenLoaded('lastMessage')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];

        if ($user !== null) {
            $data['unread_count'] = $this->unreadCountFor($user);
        }

        return $data;
    }
}
