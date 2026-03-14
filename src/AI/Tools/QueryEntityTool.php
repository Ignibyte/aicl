<?php

namespace Aicl\AI\Tools;

use Aicl\AI\Enums\ToolRenderType;
use Aicl\Services\EntityRegistry;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use NeuronAI\Tools\PropertyType;
use NeuronAI\Tools\ToolProperty;

class QueryEntityTool extends BaseTool
{
    public function __construct()
    {
        parent::__construct(
            name: 'query_entity',
            description: 'Query database entities by type with optional filters. Returns matching records. Use this to look up data when users ask about specific records.',
            properties: [
                ToolProperty::make(
                    name: 'entity_type',
                    type: PropertyType::STRING,
                    description: 'The entity type to query (e.g., "users", "rlm_patterns", "rlm_failures"). Use the table name or label.',
                    required: true,
                ),
                ToolProperty::make(
                    name: 'filters',
                    type: PropertyType::STRING,
                    description: 'Optional filter as "field:value" pairs, comma-separated (e.g., "status:active,role:admin")',
                    required: false,
                ),
                ToolProperty::make(
                    name: 'limit',
                    type: PropertyType::INTEGER,
                    description: 'Maximum number of records to return (default 10, max 50)',
                    required: false,
                ),
            ],
        );
    }

    public function category(): string
    {
        return 'queries';
    }

    public function requiresAuth(): bool
    {
        return true;
    }

    public function renderAs(): ToolRenderType
    {
        return ToolRenderType::Table;
    }

    /**
     * @return array{type: string, data: mixed}
     */
    public function formatResultForDisplay(mixed $result): array
    {
        if (is_string($result)) {
            return ['type' => ToolRenderType::Text->value, 'data' => $result];
        }

        if (! is_array($result) || empty($result)) {
            return ['type' => ToolRenderType::Text->value, 'data' => 'No results.'];
        }

        $columns = array_keys($result[0]);

        return [
            'type' => ToolRenderType::Table->value,
            'data' => [
                'columns' => $columns,
                'rows' => $result,
            ],
        ];
    }

    /**
     * @return string|array<int, array<string, mixed>>
     */
    public function __invoke(string $entity_type, ?string $filters = null, ?int $limit = null): string|array
    {
        $registry = app(EntityRegistry::class);
        $entityMeta = $this->resolveEntityType($registry, $entity_type);

        if ($entityMeta === null) {
            $available = $registry->allTypes()->pluck('table')->implode(', ');

            return "Unknown entity type: '{$entity_type}'. Available types: {$available}";
        }

        // Check policy if user context available
        if ($this->authenticatedUserId !== null) {
            $user = User::find($this->authenticatedUserId);
            if ($user && ! Gate::forUser($user)->allows('viewAny', $entityMeta['class'])) {
                return "You do not have permission to view {$entityMeta['label']} records.";
            }
        }

        $limit = min(max($limit ?? 10, 1), 50);
        $query = $entityMeta['class']::query();

        if ($filters !== null && $filters !== '') {
            $this->applyFilters($query, $filters);
        }

        $results = $query->limit($limit)->get();

        if ($results->isEmpty()) {
            return "No {$entityMeta['label']} records found matching the criteria.";
        }

        return $results->map(fn (Model $model): array => $model->toArray())->toArray();
    }

    /**
     * Resolve a user-provided entity type string to registry metadata.
     *
     * @return array{class: class-string, table: string, label: string}|null
     */
    private function resolveEntityType(EntityRegistry $registry, string $type): ?array
    {
        $normalizedType = strtolower(str_replace([' ', '-'], '_', $type));

        foreach ($registry->allTypes() as $entry) {
            if (
                strtolower($entry['table']) === $normalizedType
                || strtolower($entry['label']) === strtolower($type)
                || strtolower(class_basename($entry['class'])) === strtolower($type)
            ) {
                return $entry;
            }
        }

        return null;
    }

    /** @var array<string> Columns that must never be used in WHERE filters. */
    private const BLOCKED_COLUMNS = [
        'password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes',
        'api_token', 'secret', 'token', 'hash',
    ];

    /**
     * Apply "field:value" filter pairs to the query.
     * Only allows filtering on columns that exist in the model's fillable
     * attributes or the table schema, excluding sensitive columns.
     *
     * @param  Builder<Model>  $query
     */
    private function applyFilters(mixed $query, string $filters): void
    {
        $model = $query->getModel();
        $fillable = $model->getFillable();
        $pairs = array_map('trim', explode(',', $filters));

        foreach ($pairs as $pair) {
            $parts = explode(':', $pair, 2);

            if (count($parts) !== 2) {
                continue;
            }

            [$field, $value] = $parts;
            $field = trim($field);
            $value = trim($value);

            if ($field === '' || $value === '') {
                continue;
            }

            // Block sensitive columns
            if (in_array(strtolower($field), self::BLOCKED_COLUMNS, true)) {
                continue;
            }

            // Only allow fillable columns + common safe columns
            if (! empty($fillable) && ! in_array($field, $fillable, true)
                && ! in_array($field, ['id', 'status', 'state', 'created_at', 'updated_at', 'name', 'title', 'email', 'slug', 'type'], true)) {
                continue;
            }

            $query->where($field, $value);
        }
    }
}
