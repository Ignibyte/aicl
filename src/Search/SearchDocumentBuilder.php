<?php

declare(strict_types=1);

namespace Aicl\Search;

use BackedEnum;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * SearchDocumentBuilder.
 */
class SearchDocumentBuilder
{
    /**
     * Build an ES document from a model and its search config.
     *
     * @param array<string, mixed> $entityConfig
     *
     * @return array<string, mixed>
     */
    public function build(Model $model, array $entityConfig): array
    {
        $fields = $entityConfig['fields'] ?? [];
        $labelField = $entityConfig['label'] ?? $fields[0] ?? 'id';
        $title = $this->resolveFieldValue($model, $labelField);

        $bodyParts = [];
        foreach ($fields as $field) {
            $value = $this->resolveFieldValue($model, $field);
            if ($value !== null && $value !== '') {
                $bodyParts[] = $value;
            }
        }

        $resourceSlug = str(class_basename($model))->plural()->kebab()->toString();
        $url = "/admin/{$resourceSlug}/{$model->getKey()}";

        return [
            'entity_type' => get_class($model),
            'entity_id' => (string) $model->getKey(),
            'title' => (string) $title,
            'body' => implode(' ', $bodyParts),
            'url' => $url,
            'icon' => $entityConfig['icon'] ?? 'heroicon-o-document',
            'meta' => $entityConfig['meta_fields'] ?? [],
            'owner_id' => $this->resolveOwnerId($model),
            'required_permission' => $entityConfig['visibility'] ?? 'authenticated',
            'team_ids' => $this->resolveTeamIds($model),
            'boost' => (float) ($entityConfig['boost'] ?? 1.0),
            'indexed_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Get the ES document ID for a model.
     */
    public function documentId(Model $model): string
    {
        return str(get_class($model))->replace('\\', '_')->toString().'_'.$model->getKey();
    }

    protected function resolveFieldValue(Model $model, string $field): ?string
    {
        $value = $model->getAttribute($field);

        if ($value === null) {
            return null;
        }

        if ($value instanceof BackedEnum) {
            // @codeCoverageIgnoreStart — Elasticsearch dependency
            return (string) $value->value;
            // @codeCoverageIgnoreEnd
        }

        if (is_object($value) && method_exists($value, '__toString')) {
            return (string) $value;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_array($value)) {
            return json_encode($value, JSON_THROW_ON_ERROR);
        }

        return (string) $value;
    }

    protected function resolveOwnerId(Model $model): ?string
    {
        if (method_exists($model, 'getOwnerIdForSearch')) {
            return (string) $model->getOwnerIdForSearch();
        }

        if ($model->getAttribute('owner_id') !== null) {
            // @codeCoverageIgnoreStart — Elasticsearch dependency
            return (string) $model->getAttribute('owner_id');
            // @codeCoverageIgnoreEnd
        }

        if ($model->getAttribute('user_id') !== null) {
            return (string) $model->getAttribute('user_id');
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    protected function resolveTeamIds(Model $model): array
    {
        if (method_exists($model, 'getTeamIdsForSearch')) {
            return array_map('strval', $model->getTeamIdsForSearch());
        }

        if ($model->getAttribute('team_id') !== null) {
            return [(string) $model->getAttribute('team_id')];
        }

        return [];
    }
}
