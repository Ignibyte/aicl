<?php

declare(strict_types=1);

namespace Aicl\Mcp\Prompts;

use Aicl\Services\EntityRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Prompts\Argument;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;

/**
 * InspectEntityPrompt.
 */
class InspectEntityPrompt extends Prompt
{
    protected string $name = 'inspect_entity';

    protected string $description = 'Inspect an entity\'s data, state, audit trail, and relationships.';

    /**
     * @return array<int, Argument>
     */
    public function arguments(): array
    {
        return [
            new Argument(
                name: 'entity_type',
                description: 'The entity type to inspect (snake_case, e.g. "user", "blog_post").',
                required: true,
            ),
            new Argument(
                name: 'entity_id',
                description: 'The ID of the specific entity record to inspect.',
                required: true,
            ),
        ];
    }

    public function handle(Request $request): Response
    {
        // @codeCoverageIgnoreStart — MCP server integration
        $validated = $request->validate([
            'entity_type' => 'required|string',
            'entity_id' => 'required|string',
        ]);

        $entityType = $validated['entity_type'];
        $entityId = $validated['entity_id'];

        /** @var EntityRegistry $registry */
        $registry = app(EntityRegistry::class);
        $entities = $registry->allTypes();

        $entry = $entities->first(
            fn (array $entry): bool => Str::snake(class_basename($entry['class'])) === $entityType
        );

        if (! $entry) {
            return Response::error("Entity type '{$entityType}' not found.");
        }

        /** @var Model|null $model */
        $model = $entry['class']::find($entityId);

        if (! $model) {
            return Response::error("{$entry['label']} with ID '{$entityId}' not found.");
        }

        $inspection = $this->buildInspection($model, $entry);

        return Response::text($inspection);
        // @codeCoverageIgnoreEnd
    }

    /**
     * Build the inspection report for an entity instance.
     *
     * @param array{class: class-string, table: string, label: string, base_class: class-string|null, columns: array<string, bool>} $entry
     */
    protected function buildInspection(Model $model, array $entry): string
    {
        // @codeCoverageIgnoreStart — MCP server integration
        $label = $entry['label'];
        $sections = [];

        $sections[] = "## {$label} Inspection\n";

        // Core data
        $sections[] = "### Record Data\n".json_encode($model->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        // Current state
        if ($entry['columns']['has_status']) {
            $status = $model->getAttribute('status');
            $sections[] = "\n\n### Current State\nStatus: {$status}";
        }

        // Timestamps
        if ($model->usesTimestamps()) {
            $sections[] = "\n\n### Timestamps";
            $sections[] = 'Created: '.($model->getAttribute('created_at') ?? 'N/A');
            $sections[] = 'Updated: '.($model->getAttribute('updated_at') ?? 'N/A');
        }

        // Relationship summary
        $relationships = $this->discoverLoadedRelationships($model);
        if (! empty($relationships)) {
            $sections[] = "\n\n### Loaded Relationships";
            foreach ($relationships as $name => $count) {
                $sections[] = "- {$name}: {$count} record(s)";
                // @codeCoverageIgnoreEnd
            }
        }

        // Available relationships
        // @codeCoverageIgnoreStart — MCP server integration
        $availableRelations = $this->discoverRelationshipMethods($entry['class']);
        if (! empty($availableRelations)) {
            $sections[] = "\n\n### Available Relationships";
            foreach ($availableRelations as $name => $type) {
                $sections[] = "- {$name} ({$type})";
            }
        }

        return implode("\n", $sections);
        // @codeCoverageIgnoreEnd
    }

    /**
     * Get counts of already-loaded relationships.
     *
     * @return array<string, int>
     */
    protected function discoverLoadedRelationships(Model $model): array
    {
        // @codeCoverageIgnoreStart — MCP server integration
        $loaded = [];

        foreach ($model->getRelations() as $name => $relation) {
            if ($relation === null) {
                $loaded[$name] = 0;

                continue;
            }

            if ($relation instanceof Collection) {
                $loaded[$name] = $relation->count();

                continue;
            }

            $loaded[$name] = 1;
        }

        return $loaded;
        // @codeCoverageIgnoreEnd
    }

    /**
     * Discover relationship method names from the model class.
     *
     * @param class-string $modelClass
     *
     * @return array<string, string>
     */
    protected function discoverRelationshipMethods(string $modelClass): array
    {
        // @codeCoverageIgnoreStart — MCP server integration
        $relationships = [];
        $reflection = new ReflectionClass($modelClass);
        $relationBaseClass = 'Illuminate\Database\Eloquent\Relations\Relation';

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->class !== $modelClass) {
                continue;
            }

            if ($method->getNumberOfParameters() > 0) {
                continue;
            }

            $returnType = $method->getReturnType();
            if (! $returnType instanceof ReflectionNamedType) {
                continue;
            }

            $typeName = $returnType->getName();

            if ($typeName === $relationBaseClass || is_subclass_of($typeName, $relationBaseClass)) {
                $relationships[$method->getName()] = class_basename($typeName);
            }
        }

        return $relationships;
        // @codeCoverageIgnoreEnd
    }
}
