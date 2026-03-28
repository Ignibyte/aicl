<?php

declare(strict_types=1);

namespace Aicl\Traits;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Provides structured context for LLM prompts.
 *
 * Serializes the model into a format optimized for AI consumption:
 * type metadata, key attributes, relationships, and timestamps.
 * Override aiContextFields() or toAiContext() to customize.
 *
 * @mixin Model
 */
trait HasAiContext
{
    /**
     * Build a structured context array for LLM consumption.
     *
     * @return array{type: string, id: mixed, label: string, attributes: array<string, mixed>, relationships: array<string, mixed>, meta: array<string, mixed>}
     */
    public function toAiContext(): array
    {
        return [
            'type' => $this->aiContextType(),
            'id' => $this->getKey(),
            'label' => $this->aiContextLabel(),
            'attributes' => $this->aiContextAttributes(),
            'relationships' => $this->aiContextRelationships(),
            'meta' => $this->aiContextMeta(),
        ];
    }

    /**
     * Get the human-readable type name for this entity.
     */
    protected function aiContextType(): string
    {
        return Str::headline(class_basename($this));
    }

    /**
     * Get a short label for this entity (used as summary in prompts).
     */
    protected function aiContextLabel(): string
    {
        return $this->getAttribute('name')
            ?? $this->getAttribute('title')
            ?? (string) $this->getKey();
    }

    /**
     * Get the key attributes to include in AI context.
     * Override to customize which fields the LLM sees.
     *
     * @return array<string, mixed>
     */
    protected function aiContextAttributes(): array
    {
        $fields = $this->aiContextFields();
        $attributes = [];

        foreach ($fields as $field) {
            $value = $this->getAttribute($field);

            if ($value instanceof \BackedEnum) {
                $value = $value->value;
            } elseif (is_object($value) && method_exists($value, '__toString')) {
                $value = (string) $value;
            } elseif ($value instanceof \DateTimeInterface) {
                $value = $value->format('Y-m-d H:i:s');
            }

            $attributes[$field] = $value;
        }

        return $attributes;
    }

    /**
     * Fields to include in AI context.
     * Override in your model to customize. Defaults to fillable fields.
     *
     * @return array<int, string>
     */
    protected function aiContextFields(): array
    {
        return $this->getFillable();
    }

    /**
     * Get loaded relationship data for AI context.
     * Only includes already-loaded relations to avoid N+1.
     *
     * @return array<string, mixed>
     */
    protected function aiContextRelationships(): array
    {
        $loaded = $this->getRelations();
        $context = [];

        foreach ($loaded as $name => $related) {
            if ($related === null) {
                $context[$name] = null;
            } elseif ($related instanceof Collection) {
                $context[$name] = $related->map(fn ($model): array => [
                    'id' => $model->getKey(),
                    'type' => class_basename($model),
                    'label' => $model->getAttribute('name') ?? $model->getAttribute('title') ?? (string) $model->getKey(),
                ])->toArray();
            } elseif ($related instanceof Model) {
                $context[$name] = [
                    'id' => $related->getKey(),
                    'type' => class_basename($related),
                    'label' => $related->getAttribute('name') ?? $related->getAttribute('title') ?? (string) $related->getKey(),
                ];
            }
        }

        return $context;
    }

    /**
     * Get metadata for AI context (timestamps, status, etc.).
     *
     * @return array<string, mixed>
     */
    protected function aiContextMeta(): array
    {
        $meta = [];

        if ($this->getAttribute('created_at')) {
            $meta['created_at'] = $this->getAttribute('created_at')->format('Y-m-d H:i:s');
        }

        if ($this->getAttribute('updated_at')) {
            $meta['updated_at'] = $this->getAttribute('updated_at')->format('Y-m-d H:i:s');
        }

        if ($this->getAttribute('status')) {
            $value = $this->getAttribute('status');
            $meta['status'] = $value instanceof \BackedEnum ? $value->value : (string) $value;
        }

        if ($this->getAttribute('is_active') !== null) {
            $meta['is_active'] = (bool) $this->getAttribute('is_active');
        }

        return $meta;
    }
}
