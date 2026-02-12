<?php

namespace Aicl\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \Aicl\Models\RlmScore
 */
class RlmScoreResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'entity_name' => $this->entity_name,
            'score_type' => $this->score_type,
            'passed' => $this->passed,
            'total' => $this->total,
            'percentage' => $this->percentage,
            'errors' => $this->errors,
            'warnings' => $this->warnings,
            'details' => $this->details,
            'owner' => $this->whenLoaded('owner', fn () => [
                'id' => $this->owner->id,
                'name' => $this->owner->name,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
