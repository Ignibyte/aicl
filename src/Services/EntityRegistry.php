<?php

namespace Aicl\Services;

use Aicl\Contracts\HasEntityLifecycle;
use Aicl\Mcp\AiclMcpServer;
use Aicl\Swoole\Concurrent;
use Aicl\Traits\HasStandardScopes;
use Illuminate\Cache\TaggableStore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use ReflectionClass;

/**
 * Central registry of all AICL entity types.
 *
 * Discovers entity models by scanning app/Models/ for classes implementing
 * HasEntityLifecycle, caches the results (with tag support for Redis), and
 * provides query methods for cross-entity search, location lookup, and
 * status aggregation. Uses Swoole Concurrent::map() for parallel queries.
 *
 * @see HasEntityLifecycle  Interface that marks a model as an AICL entity
 * @see AiclMcpServer  Uses this registry to auto-expose entities via MCP
 */
class EntityRegistry
{
    /** @var string Cache key for the discovered entity types collection */
    protected const CACHE_KEY = 'entity_registry_types';

    /** @var array<string> Cache tags for tagged cache stores (Redis) */
    protected const CACHE_TAGS = ['aicl', 'entity-registry'];

    /**
     * All registered entity types with metadata.
     *
     * @return Collection<int, array{
     *     class: class-string,
     *     table: string,
     *     label: string,
     *     base_class: class-string|null,
     *     columns: array{has_name: bool, has_status: bool, has_location_id: bool, has_owner_id: bool, has_is_active: bool},
     * }>
     */
    public function allTypes(): Collection
    {
        if ($this->supportsTagging()) {
            return Cache::tags(self::CACHE_TAGS)->rememberForever(self::CACHE_KEY, fn (): Collection => $this->discover());
        }

        return Cache::rememberForever(self::CACHE_KEY, fn (): Collection => $this->discover());
    }

    /**
     * Search across all entities with a name column.
     * Returns grouped results: ['Entity Label' => Collection<Model>].
     *
     * @return Collection<string, Collection<int, Model>>
     */
    public function search(string $term, int $limit = 10): Collection
    {
        $eligible = $this->allTypes()->filter(fn (array $entry): bool => $entry['columns']['has_name'])->all();

        if (empty($eligible)) {
            return collect();
        }

        $queryResults = Concurrent::map(
            $eligible,
            function (array $entry) use ($term, $limit): Collection {
                $class = $entry['class'];

                if (in_array(HasStandardScopes::class, class_uses_recursive($class), true)) {
                    return $class::query()->search($term)->limit($limit)->get();
                }

                return $class::query()
                    ->whereRaw('LOWER(name) LIKE ?', ['%'.mb_strtolower($term).'%'])
                    ->limit($limit)
                    ->get();
            },
        );

        $results = collect();

        foreach ($queryResults as $key => $matches) {
            if ($matches->isNotEmpty()) {
                $results->put($eligible[$key]['label'], $matches);
            }
        }

        return $results;
    }

    /**
     * Find all entities at a given location.
     * Only queries entities with a location_id column.
     *
     * @return Collection<string, Collection<int, Model>>
     */
    public function atLocation(int $locationId): Collection
    {
        $eligible = $this->allTypes()->filter(fn (array $entry): bool => $entry['columns']['has_location_id'])->all();

        if (empty($eligible)) {
            return collect();
        }

        $queryResults = Concurrent::map(
            $eligible,
            fn (array $entry): Collection => $entry['class']::query()->where('location_id', $locationId)->get(),
        );

        $results = collect();

        foreach ($queryResults as $key => $matches) {
            if ($matches->isNotEmpty()) {
                $results->put($eligible[$key]['label'], $matches);
            }
        }

        return $results;
    }

    /**
     * Count entities by status across all entity types that have a status column.
     *
     * @return array<string, array<string, int>>
     */
    public function countsByStatus(): array
    {
        $eligible = $this->allTypes()->filter(fn (array $entry): bool => $entry['columns']['has_status'])->all();

        if (empty($eligible)) {
            return [];
        }

        $queryResults = Concurrent::map(
            $eligible,
            fn (array $entry): array => $entry['class']::query()
                ->selectRaw('status, count(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status')
                ->toArray(),
        );

        $counts = [];

        foreach ($queryResults as $key => $statusCounts) {
            $counts[$eligible[$key]['label']] = $statusCounts;
        }

        return $counts;
    }

    /**
     * Resolve a morph class string to its AICL entity class name.
     */
    public function resolveType(string $morphClass): ?string
    {
        foreach ($this->allTypes() as $entry) {
            $class = $entry['class'];

            if ($class === $morphClass) {
                return $class;
            }

            /** @var Model $instance */
            $instance = new $class;
            if ($instance->getMorphClass() === $morphClass) {
                return $class;
            }
        }

        return null;
    }

    /**
     * Check if a class is a registered AICL entity.
     */
    public function isEntity(string $class): bool
    {
        return $this->allTypes()->contains('class', $class);
    }

    /**
     * Flush the discovery cache. Called by make-entity and remove-entity.
     */
    public static function flush(): void
    {
        if ((new self)->supportsTagging()) {
            Cache::tags(self::CACHE_TAGS)->flush();
        } else {
            Cache::forget(self::CACHE_KEY);
        }
    }

    /**
     * Discover AICL entity models by scanning app/Models/ recursively.
     *
     * @return Collection<int, array{
     *     class: class-string,
     *     table: string,
     *     label: string,
     *     base_class: class-string|null,
     *     columns: array{has_name: bool, has_status: bool, has_location_id: bool, has_owner_id: bool, has_is_active: bool},
     * }>
     */
    protected function discover(): Collection
    {
        $modelsPath = app_path('Models');

        if (! is_dir($modelsPath)) {
            return collect();
        }

        $entities = collect();

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($modelsPath, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $relativePath = str_replace($modelsPath.'/', '', $file->getPathname());
            $className = 'App\\Models\\'.str_replace(['/', '.php'], ['\\', ''], $relativePath);

            if (! class_exists($className)) {
                continue;
            }

            $reflection = new ReflectionClass($className);

            if ($reflection->isAbstract()) {
                continue;
            }

            if (! is_subclass_of($className, HasEntityLifecycle::class)) {
                continue;
            }

            /** @var Model&HasEntityLifecycle $instance */
            $instance = new $className;
            $table = $instance->getTable();

            $parentClass = $reflection->getParentClass();
            $baseClass = null;
            if ($parentClass && $parentClass->getName() !== Model::class) {
                $baseClass = $parentClass->getName();
            }

            $entities->push([
                'class' => $className,
                'table' => $table,
                'label' => $this->humanize($reflection->getShortName()),
                'base_class' => $baseClass,
                'columns' => [
                    'has_name' => Schema::hasColumn($table, 'name'),
                    'has_status' => Schema::hasColumn($table, 'status'),
                    'has_location_id' => Schema::hasColumn($table, 'location_id'),
                    'has_owner_id' => Schema::hasColumn($table, 'owner_id'),
                    'has_is_active' => Schema::hasColumn($table, 'is_active'),
                ],
            ]);
        }

        return $entities->sortBy('label')->values();
    }

    /**
     * Convert a PascalCase class name to a human-readable label.
     */
    protected function humanize(string $className): string
    {
        return Str::headline($className);
    }

    /**
     * Check if the cache store supports tagging.
     */
    protected function supportsTagging(): bool
    {
        return Cache::getStore() instanceof TaggableStore;
    }
}
