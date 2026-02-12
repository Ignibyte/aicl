<?php

namespace Aicl\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \Aicl\Models\RlmFailure
 */
class RlmFailureResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'failure_code' => $this->failure_code,
            'pattern_id' => $this->pattern_id,
            'category' => $this->category,
            'subcategory' => $this->subcategory,
            'title' => $this->title,
            'description' => $this->description,
            'root_cause' => $this->root_cause,
            'fix' => $this->fix,
            'preventive_rule' => $this->preventive_rule,
            'severity' => $this->severity,
            'entity_context' => $this->entity_context,
            'scaffolding_fixed' => $this->scaffolding_fixed,
            'first_seen_at' => $this->first_seen_at?->toIso8601String(),
            'last_seen_at' => $this->last_seen_at?->toIso8601String(),
            'report_count' => $this->report_count,
            'project_count' => $this->project_count,
            'resolution_count' => $this->resolution_count,
            'resolution_rate' => $this->resolution_rate,
            'computed_resolution_rate' => $this->computed_resolution_rate,
            'promoted_to_base' => $this->promoted_to_base,
            'promoted_at' => $this->promoted_at?->toIso8601String(),
            'aicl_version' => $this->aicl_version,
            'laravel_version' => $this->laravel_version,
            'status' => $this->status ? ['value' => (string) $this->status, 'label' => $this->status->label()] : null,
            'is_active' => $this->is_active,
            'owner' => $this->whenLoaded('owner', fn () => [
                'id' => $this->owner->id,
                'name' => $this->owner->name,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
