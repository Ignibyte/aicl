<?php

namespace Aicl\Rlm\Embeddings;

use Aicl\Contracts\EmbeddingDriver;

/**
 * Null driver for when embeddings are disabled.
 *
 * Returns empty arrays — kNN search is unavailable but
 * BM25 full-text search in Elasticsearch still works.
 */
class NullDriver implements EmbeddingDriver
{
    /**
     * @return array<int, float>
     */
    public function embed(string $text): array
    {
        return [];
    }

    /**
     * @param  array<int, string>  $texts
     * @return array<int, array<int, float>>
     */
    public function embedBatch(array $texts): array
    {
        return array_map(fn (): array => [], $texts);
    }

    public function dimension(): int
    {
        return 1536;
    }
}
