<?php

namespace Aicl\Http\Resources;

use Aicl\Models\AiAgent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AiAgent
 */
class AiAgentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'provider' => $this->provider->value,
            'model' => $this->model,
            'system_prompt' => $this->system_prompt,
            'max_tokens' => $this->max_tokens,
            'temperature' => $this->temperature,
            'context_window' => $this->context_window,
            'context_messages' => $this->context_messages,
            'is_active' => $this->is_active,
            'icon' => $this->icon,
            'color' => $this->color,
            'sort_order' => $this->sort_order,
            'suggested_prompts' => $this->suggested_prompts,
            'capabilities' => $this->capabilities,
            'visible_to_roles' => $this->visible_to_roles,
            'max_requests_per_minute' => $this->max_requests_per_minute,
            'state' => $this->state->getValue(),
            'is_configured' => $this->is_configured,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
