<?php

declare(strict_types=1);

namespace Aicl\Search;

use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\Response\Elasticsearch;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/** Executes permission-filtered search queries against the unified Elasticsearch index. */
/**
 * @codeCoverageIgnore Elasticsearch dependency
 */
class SearchService
{
    protected PermissionFilterBuilder $permissionBuilder;

    public function __construct(
        protected Client $client,
    ) {
        $this->permissionBuilder = new PermissionFilterBuilder;
    }

    /**
     * Execute a search query with permission filtering.
     */
    public function search(
        string $query,
        Authenticatable $user,
        ?string $entityTypeFilter = null,
        int $page = 1,
        int $perPage = 20,
    ): SearchResultCollection {
        $minLength = (int) config('aicl.search.min_query_length', 2);

        if (strlen(trim($query)) < $minLength) {
            return SearchResultCollection::empty();
        }

        $entityConfigs = $this->getEntityConfigs();

        if (empty($entityConfigs)) {
            return SearchResultCollection::empty();
        }

        // Filter to specific entity type if requested
        if ($entityTypeFilter !== null) {
            $entityConfigs = array_filter(
                $entityConfigs,
                fn (mixed $config, string $class): bool => class_basename($class) === $entityTypeFilter || $class === $entityTypeFilter,
                ARRAY_FILTER_USE_BOTH,
            );

            if (empty($entityConfigs)) {
                return SearchResultCollection::empty();
            }
        }

        $body = $this->buildSearchBody($query, $user, $entityConfigs, $page, $perPage);

        try {
            /** @var Elasticsearch $response */
            $response = $this->client->search([
                'index' => config('aicl.search.index', 'aicl_global_search'),
                'body' => $body,
            ]);

            return $this->parseResponse($response->asArray(), $page, $perPage);
        } catch (\Throwable $e) {
            Log::warning('Search query failed', [
                'query' => $query,
                'error' => $e->getMessage(),
            ]);

            return SearchResultCollection::empty();
        }
    }

    /**
     * Build the ES search request body.
     *
     * @param  array<string, array<string, mixed>>  $entityConfigs
     * @return array<string, mixed>
     */
    protected function buildSearchBody(
        string $query,
        Authenticatable $user,
        array $entityConfigs,
        int $page,
        int $perPage,
    ): array {
        $from = ($page - 1) * $perPage;

        $must = [
            [
                'multi_match' => [
                    'query' => $query,
                    'fields' => ['title^3', 'body'],
                    'type' => 'best_fields',
                    'fuzziness' => 'AUTO',
                ],
            ],
        ];

        $filter = $this->permissionBuilder->buildFilters($user, $entityConfigs);

        $body = [
            'query' => [
                'bool' => [
                    'must' => $must,
                ],
            ],
            'from' => $from,
            'size' => $perPage,
            'aggs' => [
                'entity_types' => [
                    'terms' => [
                        'field' => 'entity_type',
                        'size' => 50,
                    ],
                ],
            ],
        ];

        if (! empty($filter)) {
            $body['query']['bool']['filter'] = $filter;
        }

        // Apply per-entity boost via function_score
        $boostFunctions = [];
        foreach ($entityConfigs as $entityClass => $config) {
            $boost = (float) ($config['boost'] ?? 1.0);
            if ($boost !== 1.0) {
                $boostFunctions[] = [
                    'filter' => ['term' => ['entity_type' => $entityClass]],
                    'weight' => $boost,
                ];
            }
        }

        if (! empty($boostFunctions)) {
            $body['query'] = [
                'function_score' => [
                    'query' => $body['query'],
                    'functions' => $boostFunctions,
                    'score_mode' => 'multiply',
                    'boost_mode' => 'multiply',
                ],
            ];
        }

        return $body;
    }

    /**
     * Parse the ES response into a SearchResultCollection.
     *
     * @param  array<string, mixed>  $response
     */
    protected function parseResponse(array $response, int $page, int $perPage): SearchResultCollection
    {
        $hits = $response['hits']['hits'] ?? [];
        $total = $response['hits']['total']['value'] ?? 0;

        /** @var array<int, array<string, mixed>> $hits */
        $results = collect($hits)->map(fn (array $hit): SearchResult => SearchResult::fromEsHit($hit));

        $facets = [];
        $buckets = $response['aggregations']['entity_types']['buckets'] ?? [];
        foreach ($buckets as $bucket) {
            $facets[$bucket['key']] = $bucket['doc_count'];
        }

        return new SearchResultCollection(
            results: $results,
            facets: $facets,
            total: $total,
            page: $page,
            perPage: $perPage,
        );
    }

    /**
     * Apply policy safety-net filtering on loaded models.
     *
     * Batch-loads models per entity type to avoid N+1 queries (one query per
     * entity type instead of one per search result).
     *
     * @param  Collection<int, SearchResult>  $results
     * @return Collection<int, SearchResult>
     */
    public function applyPolicyFilter(Collection $results, Authenticatable $user): Collection
    {
        $entityConfigs = $this->getEntityConfigs();

        // Group results that need policy checks by entity type for batch loading
        $policyResults = $results->filter(function (SearchResult $result) use ($entityConfigs): bool {
            $config = $entityConfigs[$result->entityType] ?? null;

            return $config !== null && ($config['visibility'] ?? 'authenticated') === 'policy';
        });

        // Batch-load all models per entity type (1 query per type instead of N)
        $loadedModels = [];
        foreach ($policyResults->groupBy('entityType') as $entityType => $group) {
            try {
                /** @var class-string<Model> $entityType */
                $ids = $group->pluck('entityId')->all();
                $models = $entityType::whereIn('id', $ids)->get()->keyBy('id');
                $loadedModels[$entityType] = $models;
            } catch (\Throwable) {
                $loadedModels[$entityType] = collect();
            }
        }

        return $results->filter(function (SearchResult $result) use ($entityConfigs, $user, $loadedModels): bool {
            $config = $entityConfigs[$result->entityType] ?? null;

            if ($config === null) {
                return false;
            }

            $visibility = $config['visibility'] ?? 'authenticated';

            if ($visibility === 'policy') {
                $model = ($loadedModels[$result->entityType] ?? collect())->get($result->entityId);

                if ($model === null) {
                    return false;
                }

                try {
                    return $user->can('view', $model);
                } catch (\Throwable) {
                    return false;
                }
            }

            // For other visibility types, ES filter already handled it
            return true;
        })->values();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getEntityConfigs(): array
    {
        return config('aicl.search.entities', []);
    }

    /**
     * Get available entity types for the facet filter UI.
     *
     * @return array<string, string>
     */
    public function getEntityTypes(): array
    {
        $types = ['' => 'All Types'];

        foreach ($this->getEntityConfigs() as $entityClass => $config) {
            $types[class_basename($entityClass)] = str(class_basename($entityClass))
                ->headline()
                ->plural()
                ->toString();
        }

        return $types;
    }
}
