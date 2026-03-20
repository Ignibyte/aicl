<?php

declare(strict_types=1);

namespace Aicl\Search;

use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\Exception\ClientResponseException;
use Elastic\Elasticsearch\Response\Elasticsearch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/** Manages Elasticsearch index lifecycle including document indexing, removal, and bulk reindexing. */
class SearchIndexingService
{
    protected SearchDocumentBuilder $documentBuilder;

    public function __construct(
        protected Client $client,
    ) {
        $this->documentBuilder = new SearchDocumentBuilder;
    }

    /**
     * Index a single model into the unified search index.
     *
     * @param  array<string, mixed>  $entityConfig
     */
    public function index(Model $model, array $entityConfig): void
    {
        $indexName = $this->getIndexAlias();
        $document = $this->documentBuilder->build($model, $entityConfig);
        $documentId = $this->documentBuilder->documentId($model);

        try {
            $this->client->index([
                'index' => $indexName,
                'id' => $documentId,
                'body' => $document,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Search indexing failed', [
                'model' => get_class($model),
                'id' => $model->getKey(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Delete a model from the unified search index.
     */
    public function delete(Model $model): void
    {
        $indexName = $this->getIndexAlias();
        $documentId = $this->documentBuilder->documentId($model);

        try {
            $this->client->delete([
                'index' => $indexName,
                'id' => $documentId,
            ]);
        } catch (ClientResponseException $e) {
            // 404 is expected if the document doesn't exist
            if ($e->getCode() !== 404) {
                Log::warning('Search delete failed', [
                    'model' => get_class($model),
                    'id' => $model->getKey(),
                    'error' => $e->getMessage(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Search delete failed', [
                'model' => get_class($model),
                'id' => $model->getKey(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Create the search index with the proper mapping.
     */
    public function createIndex(string $indexName): void
    {
        $this->client->indices()->create([
            'index' => $indexName,
            'body' => [
                'settings' => [
                    'number_of_shards' => 1,
                    'number_of_replicas' => 0,
                ],
                'mappings' => [
                    'properties' => [
                        'entity_type' => ['type' => 'keyword'],
                        'entity_id' => ['type' => 'keyword'],
                        'title' => [
                            'type' => 'text',
                            'analyzer' => 'standard',
                            'fields' => [
                                'keyword' => ['type' => 'keyword'],
                            ],
                        ],
                        'body' => ['type' => 'text', 'analyzer' => 'standard'],
                        'url' => ['type' => 'keyword', 'index' => false],
                        'icon' => ['type' => 'keyword', 'index' => false],
                        'meta' => ['type' => 'object', 'enabled' => false],
                        'owner_id' => ['type' => 'keyword'],
                        'required_permission' => ['type' => 'keyword'],
                        'team_ids' => ['type' => 'keyword'],
                        'boost' => ['type' => 'float'],
                        'indexed_at' => ['type' => 'date'],
                    ],
                ],
            ],
        ]);
    }

    /**
     * Delete an index.
     */
    public function deleteIndex(string $indexName): void
    {
        try {
            $this->client->indices()->delete(['index' => $indexName]);
        } catch (ClientResponseException $e) {
            if ($e->getCode() !== 404) {
                throw $e;
            }
        }
    }

    /**
     * Check if an index exists.
     */
    public function indexExists(string $indexName): bool
    {
        try {
            /** @var Elasticsearch $response */
            $response = $this->client->indices()->exists(['index' => $indexName]);

            return $response->asBool();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Swap the alias from one index to another (zero-downtime reindex).
     */
    public function swapAlias(string $alias, string $oldIndex, string $newIndex): void
    {
        $actions = [
            ['add' => ['index' => $newIndex, 'alias' => $alias]],
        ];

        if ($this->indexExists($oldIndex)) {
            $actions[] = ['remove' => ['index' => $oldIndex, 'alias' => $alias]];
        }

        $this->client->indices()->updateAliases([
            'body' => ['actions' => $actions],
        ]);
    }

    /**
     * Bulk index multiple documents.
     *
     * @param  array<int, array{id: string, body: array<string, mixed>}>  $documents
     */
    public function bulkIndex(array $documents, ?string $indexName = null): int
    {
        $index = $indexName ?? $this->getIndexAlias();
        $params = ['body' => []];
        $indexed = 0;

        foreach ($documents as $doc) {
            $params['body'][] = [
                'index' => [
                    '_index' => $index,
                    '_id' => $doc['id'],
                ],
            ];
            $params['body'][] = $doc['body'];

            // Flush every 500 documents
            if (count($params['body']) >= 1000) {
                $this->client->bulk($params);
                $indexed += 500;
                $params['body'] = [];
            }
        }

        // Flush remaining
        if (! empty($params['body'])) {
            $this->client->bulk($params);
            $indexed += count($params['body']) / 2;
        }

        return (int) $indexed;
    }

    /**
     * Get the configured index alias name.
     */
    public function getIndexAlias(): string
    {
        return config('aicl.search.index', 'aicl_global_search');
    }

    /**
     * Ensure the index and alias exist, creating them if needed.
     */
    public function ensureIndex(): void
    {
        $alias = $this->getIndexAlias();
        $concreteIndex = $alias.'_v1';

        if (! $this->indexExists($concreteIndex)) {
            $this->createIndex($concreteIndex);

            // Create alias pointing to v1
            $this->client->indices()->updateAliases([
                'body' => [
                    'actions' => [
                        ['add' => ['index' => $concreteIndex, 'alias' => $alias]],
                    ],
                ],
            ]);
        }
    }
}
