<?php

namespace Aicl\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \Aicl\Models\FailureReport
 */
class FailureReportResource extends JsonResource
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
            'project_hash' => $this->project_hash,
            'entity_name' => $this->entity_name,
            'scaffolder_args' => $this->scaffolder_args,
            'phase' => $this->phase,
            'agent' => $this->agent,
            'resolved' => $this->resolved,
            'resolution_notes' => $this->resolution_notes,
            'resolution_method' => $this->resolution_method?->value,
            'time_to_resolve' => $this->time_to_resolve,
            'reported_at' => $this->reported_at->toIso8601String(),
            'resolved_at' => $this->resolved_at?->toIso8601String(),
            'is_active' => $this->is_active,
            'owner' => $this->whenLoaded('owner', fn () => [
                'id' => $this->owner->id,
                'name' => $this->owner->name,
            ]),
            'created_at' => $this->resource->created_at?->toIso8601String(),
            'updated_at' => $this->resource->updated_at?->toIso8601String(),
        ];
    }
}
