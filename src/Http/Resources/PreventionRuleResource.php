<?php

namespace Aicl\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \Aicl\Models\PreventionRule
 */
class PreventionRuleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'failure' => $this->whenLoaded('failure', fn () => [
                'id' => $this->failure->id,
                'failure_code' => $this->failure->failure_code,
                'title' => $this->failure->title,
            ]),
            'trigger_context' => $this->trigger_context,
            'rule_text' => $this->rule_text,
            'confidence' => $this->confidence,
            'priority' => $this->priority,
            'is_active' => $this->is_active,
            'applied_count' => $this->applied_count,
            'last_applied_at' => $this->last_applied_at?->toIso8601String(),
            'owner' => $this->whenLoaded('owner', fn () => [
                'id' => $this->owner->id,
                'name' => $this->owner->name,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
