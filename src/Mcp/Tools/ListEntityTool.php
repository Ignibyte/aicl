<?php

declare(strict_types=1);

namespace Aicl\Mcp\Tools;

use Aicl\Mcp\Concerns\ChecksTokenScope;
use Aicl\Traits\HasStandardScopes;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
/**
 * ListEntityTool.
 */
class ListEntityTool extends Tool
{
    use ChecksTokenScope;

    public function __construct(
        /** @var class-string<Model> */
        protected string $modelClass,
        protected string $entityLabel,
    ) {}

    public function name(): string
    {
        return 'list_'.$this->toSnakePlural();
    }

    public function title(): string
    {
        return "List {$this->entityLabel} Records";
    }

    public function description(): string
    {
        return "List all {$this->entityLabel} records with pagination, search, and sorting.";
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'page' => $schema->integer()->description('Page number (default: 1)'),
            'per_page' => $schema->integer()->description('Results per page, max 100 (default: 15)'),
            'search' => $schema->string()->description('Search term to filter results'),
            'sort_by' => $schema->string()->description('Column to sort by (default: created_at)'),
            'sort_dir' => $schema->string()->description('Sort direction')->enum(['asc', 'desc']),
        ];
    }

    public function handle(Request $request): Response
    {
        // @codeCoverageIgnoreStart — MCP server integration
        $scopeError = $this->checkScope($request, 'read');

        if ($scopeError) {
            return $scopeError;
        }

        $user = $request->user('api');

        if (! $user || ! $user->can('viewAny', $this->modelClass)) {
            return Response::error("Unauthorized to list {$this->entityLabel} records.");
        }

        $validated = $request->validate([
            'page' => 'integer|min:1',
            'per_page' => 'integer|min:1|max:100',
            'search' => 'nullable|string|max:255',
            'sort_by' => 'nullable|string|max:64',
            'sort_dir' => 'nullable|in:asc,desc',
        ]);

        $perPage = $validated['per_page'] ?? 15;
        $page = $validated['page'] ?? 1;
        $search = $validated['search'] ?? null;
        $sortBy = $validated['sort_by'] ?? 'created_at';
        $sortDir = $validated['sort_dir'] ?? 'desc';

        // Whitelist sort_by against model columns to prevent SQL injection
        $instance = new $this->modelClass;
        $allowedSortColumns = array_merge(
            $instance->getFillable(),
            ['id', 'created_at', 'updated_at'],
        );

        if (! in_array($sortBy, $allowedSortColumns, true)) {
            $sortBy = 'created_at';
        }

        $query = $this->modelClass::query();

        if ($search) {
            if (in_array(HasStandardScopes::class, class_uses_recursive($this->modelClass), true)) {
                $query->search($search); // @phpstan-ignore method.notFound
            } else {
                return Response::error("Search is not supported for {$this->entityLabel}. The model does not implement HasStandardScopes.");
            }
        }

        $query->orderBy($sortBy, $sortDir);

        $results = $query->paginate(perPage: $perPage, page: $page);

        return Response::json([
            'data' => $results->items(),
            'meta' => [
                'current_page' => $results->currentPage(),
                'last_page' => $results->lastPage(),
                'per_page' => $results->perPage(),
                'total' => $results->total(),
            ],
        ]);
        // @codeCoverageIgnoreEnd
    }

    protected function toSnakePlural(): string
    {
        $basename = class_basename($this->modelClass);

        return Str::snake(Str::plural($basename));
    }
}
