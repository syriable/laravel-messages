<?php

declare(strict_types=1);

namespace Syriable\LaravelMessages\Resources;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Syriable\LaravelMessages\Models\ConversationParticipant;
use Syriable\LaravelMessages\Support\UserKey;

/**
 * @mixin ConversationParticipant
 */
class ParticipantResource extends JsonResource
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
            'user_id' => $this->user_id,
            'last_read_at' => $this->last_read_at,
        ];

        // Per-user state is private: only expose it to its owner.
        if ($user !== null && (string) $this->user_id === (string) UserKey::of($user)) {
            $data['archived'] = $this->hasArchived();
            $data['pinned'] = $this->hasPinned();
            $data['muted'] = $this->isMuted();
            $data['labels'] = $this->labels();
        }

        return $data;
    }
}
