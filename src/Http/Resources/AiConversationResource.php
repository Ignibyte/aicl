<?php

namespace Aicl\Http\Resources;

use Aicl\Models\AiConversation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AiConversation
 */
class AiConversationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'display_title' => $this->display_title,
            'user_id' => $this->user_id,
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ]),
            'ai_agent_id' => $this->ai_agent_id,
            'agent' => $this->whenLoaded('agent', fn () => [
                'id' => $this->agent->id,
                'name' => $this->agent->name,
                'icon' => $this->agent->icon,
                'color' => $this->agent->color,
            ]),
            'message_count' => $this->message_count,
            'token_count' => $this->token_count,
            'summary' => $this->summary,
            'is_pinned' => $this->is_pinned,
            'is_compactable' => $this->is_compactable,
            'context_page' => $this->context_page,
            'last_message_at' => $this->last_message_at?->toIso8601String(),
            'state' => $this->state->getValue(),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
