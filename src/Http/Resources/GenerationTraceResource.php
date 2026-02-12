<?php

namespace Aicl\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \Aicl\Models\GenerationTrace
 */
class GenerationTraceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'entity_name' => $this->entity_name,
            'project_hash' => $this->project_hash,
            'scaffolder_args' => $this->scaffolder_args,
            'file_manifest' => $this->file_manifest,
            'structural_score' => $this->structural_score,
            'semantic_score' => $this->semantic_score,
            'test_results' => $this->test_results,
            'fixes_applied' => $this->fixes_applied,
            'fix_iterations' => $this->fix_iterations,
            'pipeline_duration' => $this->pipeline_duration,
            'agent_versions' => $this->agent_versions,
            'is_processed' => $this->is_processed,
            'aicl_version' => $this->aicl_version,
            'laravel_version' => $this->laravel_version,
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
