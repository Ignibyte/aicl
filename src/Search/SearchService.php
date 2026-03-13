<?php

namespace Aicl\Search;

use Elastic\Elasticsearch\Client;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

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
     * @param  Collection<int, SearchResult>  $results
     * @return Collection<int, SearchResult>
     */
    public function applyPolicyFilter(Collection $results, Authenticatable $user): Collection
    {
        return $results->filter(function (SearchResult $result) use ($user): bool {
            $entityConfig = $this->getEntityConfigs()[$result->entityType] ?? null;

            if ($entityConfig === null) {
                return false;
            }

            $visibility = $entityConfig['visibility'] ?? 'authenticated';

            // Only run policy check for 'policy' visibility or as safety net
            if ($visibility === 'policy') {
                try {
                    $model = $result->entityType::find($result->entityId);

                    if ($model === null) {
                        return false;
                    }

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
