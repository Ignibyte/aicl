<?php

namespace Aicl\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \Aicl\Models\RlmPattern
 */
class RlmPatternResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'target' => $this->target,
            'check_regex' => $this->check_regex,
            'severity' => $this->severity,
            'weight' => $this->weight,
            'category' => $this->category,
            'applies_when' => $this->applies_when,
            'source' => $this->source,
            'is_active' => $this->is_active,
            'pass_count' => $this->pass_count,
            'fail_count' => $this->fail_count,
            'pass_rate' => $this->pass_rate,
            'last_evaluated_at' => $this->last_evaluated_at?->toIso8601String(),
            'owner' => $this->whenLoaded('owner', fn () => [
                'id' => $this->owner->id,
                'name' => $this->owner->name,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
