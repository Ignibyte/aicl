<?php

declare(strict_types=1);

namespace Aicl\AI\Tools;

use Aicl\AI\Enums\ToolRenderType;
use Aicl\Services\EntityRegistry;
use Illuminate\Support\Collection;
use NeuronAI\Tools\PropertyType;
use NeuronAI\Tools\ToolProperty;

/**
 * EntityCountTool.
 */
class EntityCountTool extends BaseTool
{
    public function __construct()
    {
        parent::__construct(
            name: 'entity_count',
            description: 'Count records for registered entity types, optionally grouped by status. Use this when users ask "how many" or want statistics.',
            properties: [
                ToolProperty::make(
                    name: 'entity_type',
                    type: PropertyType::STRING,
                    description: 'Optional entity type to count (e.g., "users", "rlm_patterns"). If omitted, counts all entity types.',
                    required: false,
                ),
                ToolProperty::make(
                    name: 'group_by_status',
                    type: PropertyType::BOOLEAN,
                    description: 'Whether to group counts by status (default false)',
                    required: false,
                ),
            ],
        );
    }

    public function category(): string
    {
        // @codeCoverageIgnoreStart — AI provider dependency
        return 'queries';
        // @codeCoverageIgnoreEnd
    }

    public function renderAs(): ToolRenderType
    {
        // @codeCoverageIgnoreStart — AI provider dependency
        return ToolRenderType::KeyValue;
        // @codeCoverageIgnoreEnd
    }

    /**
     * @return array{type: string, data: mixed}
     */
    public function formatResultForDisplay(mixed $result): array
    {
        // @codeCoverageIgnoreStart — AI provider dependency
        if (is_string($result)) {
            return ['type' => ToolRenderType::Text->value, 'data' => $result];
        }

        $pairs = [];

        foreach ($result as $key => $value) {
            if (is_array($value)) {
                // Grouped by status: "Entity" => ["active" => 5, "draft" => 2]
                foreach ($value as $status => $count) {
                    $pairs[] = ['key' => "{$key} ({$status})", 'value' => $count];
                }
            } else {
                $pairs[] = ['key' => (string) $key, 'value' => $value];
            }
        }

        return [
            'type' => ToolRenderType::KeyValue->value,
            'data' => ['pairs' => $pairs],
        ];
        // @codeCoverageIgnoreEnd
    }

    /**
     * @return string|array<string, mixed>
     */
    public function __invoke(?string $entity_type = null, ?bool $group_by_status = null): string|array
    {
        // @codeCoverageIgnoreStart — AI provider dependency
        $registry = app(EntityRegistry::class);
        $allTypes = $registry->allTypes();

        if ($allTypes->isEmpty()) {
            return 'No entity types are registered in this application.';
        }

        if ($group_by_status) {
            return $this->countsByStatus($registry, $entity_type);
        }

        return $this->simpleCounts($allTypes, $entity_type);
        // @codeCoverageIgnoreEnd
    }

    /**
     * @param Collection<int, mixed> $allTypes
     *
     * @return array<string, int>|string
     */
    private function simpleCounts(Collection $allTypes, ?string $entityType): array|string
    {
        // @codeCoverageIgnoreStart — AI provider dependency
        if ($entityType !== null) {
            $normalizedType = strtolower(str_replace([' ', '-'], '_', $entityType));

            $entry = $allTypes->first(fn (array $e): bool => strtolower($e['table']) === $normalizedType
                || strtolower($e['label']) === strtolower($entityType)
                || strtolower(class_basename($e['class'])) === strtolower($entityType)
            );

            if ($entry === null) {
                return "Unknown entity type: '{$entityType}'.";
            }

            return [$entry['label'] => $entry['class']::query()->count()];
        }

        $counts = [];

        foreach ($allTypes as $entry) {
            $counts[$entry['label']] = $entry['class']::query()->count();
        }

        return $counts;
        // @codeCoverageIgnoreEnd
    }

    /**
     * @return array<string, array<string, int>>|string
     */
    private function countsByStatus(EntityRegistry $registry, ?string $entityType): array|string
    {
        // @codeCoverageIgnoreStart — AI provider dependency
        $allCounts = $registry->countsByStatus();

        if (empty($allCounts)) {
            return 'No entity types with a status column found.';
        }

        if ($entityType !== null) {
            $normalizedType = strtolower(str_replace([' ', '-'], '_', $entityType));

            foreach ($allCounts as $label => $statusCounts) {
                if (strtolower($label) === strtolower($entityType) || strtolower(str_replace(' ', '_', $label)) === $normalizedType) {
                    return [$label => $statusCounts];
                }
            }

            return "Entity type '{$entityType}' has no status column or doesn't exist.";
        }

        return $allCounts;
        // @codeCoverageIgnoreEnd
    }
}
