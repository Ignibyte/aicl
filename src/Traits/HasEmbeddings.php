<?php

namespace Aicl\Traits;

use Aicl\Jobs\GenerateEmbeddingJob;
use Illuminate\Support\Facades\Cache;

/**
 * Adds embedding support to a model for vector search in Elasticsearch.
 *
 * Models using this trait must implement embeddingText() to define
 * which fields are concatenated for the embedding vector.
 *
 * Embeddings are generated asynchronously via GenerateEmbeddingJob
 * and stored only in Elasticsearch (not in PostgreSQL).
 *
 * @mixin \Illuminate\Database\Eloquent\Model
 */
trait HasEmbeddings
{
    /**
     * Get the text that should be embedded for vector search.
     * Override in each model to combine relevant text fields.
     */
    abstract public function embeddingText(): string;

    /**
     * Dispatch a job to generate the embedding for this model.
     */
    public function dispatchEmbeddingJob(): void
    {
        if (! config('aicl.features.rlm_search', true)) {
            return;
        }

        GenerateEmbeddingJob::dispatch($this);
    }

    /**
     * Get the cached embedding vector, if available.
     * Embeddings are cached after generation to avoid re-fetching from ES.
     *
     * @return array<int, float>|null
     */
    public function getCachedEmbedding(): ?array
    {
        $cacheKey = 'embedding:'.static::class.':'.$this->getKey();

        return Cache::get($cacheKey);
    }

    /**
     * Store the embedding in cache after generation.
     *
     * @param  array<int, float>  $embedding
     */
    public function cacheEmbedding(array $embedding): void
    {
        $cacheKey = 'embedding:'.static::class.':'.$this->getKey();

        // Cache for 24 hours — regenerated on next save via observer
        Cache::put($cacheKey, $embedding, now()->addDay());
    }

    /**
     * Clear the cached embedding.
     */
    public function clearCachedEmbedding(): void
    {
        $cacheKey = 'embedding:'.static::class.':'.$this->getKey();

        Cache::forget($cacheKey);
    }
}
