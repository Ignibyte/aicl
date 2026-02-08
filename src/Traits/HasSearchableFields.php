<?php

namespace Aicl\Traits;

use Laravel\Scout\Searchable;

/**
 * Makes a model full-text searchable via Laravel Scout.
 *
 * Wraps laravel/scout with a standard toSearchableArray() that indexes
 * common entity fields. Override toSearchableArray() in your model
 * to customize which fields are indexed.
 *
 * @mixin \Illuminate\Database\Eloquent\Model
 */
trait HasSearchableFields
{
    use Searchable;

    /**
     * Get the indexable data array for the model.
     *
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        $array = ['id' => $this->getKey()];

        $fields = $this->searchableFields();

        foreach ($fields as $field) {
            $value = $this->getAttribute($field);

            // Handle enum/state values
            if (is_object($value) && method_exists($value, '__toString')) {
                $value = (string) $value;
            } elseif ($value instanceof \BackedEnum) {
                $value = $value->value;
            } elseif ($value instanceof \DateTimeInterface) {
                $value = $value->format('Y-m-d H:i:s');
            }

            $array[$field] = $value;
        }

        return $array;
    }

    /**
     * Fields indexed for full-text search.
     * Override in your model to customize.
     *
     * @return array<int, string>
     */
    protected function searchableFields(): array
    {
        return ['name'];
    }

    /**
     * Get the name of the index for the model.
     * Uses class basename by default (e.g., 'projects' for Project model).
     */
    public function searchableAs(): string
    {
        $prefix = config('scout.prefix', '');
        $index = str(class_basename($this))->plural()->snake()->toString();

        return $prefix.$index;
    }

    /**
     * Determine if the model should be searchable.
     * Excludes soft-deleted records by default.
     */
    public function shouldBeSearchable(): bool
    {
        // Don't index soft-deleted records
        if (method_exists($this, 'trashed') && $this->trashed()) {
            return false;
        }

        return true;
    }
}
