<?php

namespace Aicl\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \Aicl\Models\RlmLesson
 */
class RlmLessonResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'topic' => $this->topic,
            'subtopic' => $this->subtopic,
            'summary' => $this->summary,
            'detail' => $this->detail,
            'tags' => $this->tags,
            'context_tags' => $this->context_tags,
            'source' => $this->source,
            'confidence' => $this->confidence,
            'is_verified' => $this->is_verified,
            'view_count' => $this->view_count,
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
