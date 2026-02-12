<?php

// PATTERN: Eloquent API Resource transforms model data for JSON responses.
// PATTERN: Use @mixin for IDE autocomplete on $this properties.
// PATTERN: State and enum fields return structured objects (value, label, color).
// PATTERN: Use whenLoaded() and whenCounted() for conditional includes.

namespace Aicl\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Project
 */
class ProjectResource extends JsonResource
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
            // PATTERN: State fields return value + label + color for frontend flexibility.
            'status' => [
                'value' => (string) $this->status,
                'label' => $this->status->label(),
                'color' => $this->status->color(),
            ],
            // PATTERN: Enum fields also return structured data.
            'priority' => [
                'value' => $this->priority->value,
                'label' => $this->priority->label(),
                'color' => $this->priority->color(),
            ],
            // PATTERN: Dates use toDateString() for date-only fields.
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'budget' => $this->budget,
            'is_active' => $this->is_active,
            // PATTERN: Nested owner data (always loaded).
            'owner' => [
                'id' => $this->owner->id,
                'name' => $this->owner->name,
            ],
            // PATTERN: whenCounted() only includes if withCount() was called.
            'members_count' => $this->whenCounted('members'),
            // PATTERN: whenLoaded() only includes if with() was called.
            'members' => $this->whenLoaded('members', fn () => $this->members->map(fn ($m) => [
                'id' => $m->id,
                'name' => $m->name,
                'role' => $m->pivot->role, // @phpstan-ignore property.notFound
            ])),
            // PATTERN: Timestamps use toIso8601String() for API consistency.
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
