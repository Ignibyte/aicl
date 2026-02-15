<?php

namespace Aicl\Rlm\Embeddings;

use Aicl\Contracts\EmbeddingDriver;
use NeuronAI\RAG\Embeddings\EmbeddingsProviderInterface;

/**
 * Adapter bridging NeuronAI's EmbeddingsProviderInterface to AICL's EmbeddingDriver contract.
 *
 * Handles dimension normalization (zero-padding for Ollama) and batch operations.
 */
class NeuronAiEmbeddingAdapter implements EmbeddingDriver
{
    public function __construct(
        private readonly EmbeddingsProviderInterface $provider,
        private readonly int $targetDimension = 1536,
        private readonly bool $padToTarget = false,
    ) {}

    /**
     * @return array<int, float>
     */
    public function embed(string $text): array
    {
        $embedding = $this->provider->embedText($text);

        return $this->padToTarget ? $this->zeroPad($embedding) : $embedding;
    }

    /**
     * @param  array<int, string>  $texts
     * @return array<int, array<int, float>>
     */
    public function embedBatch(array $texts): array
    {
        if (empty($texts)) {
            return [];
        }

        return array_map(fn (string $text): array => $this->embed($text), $texts);
    }

    public function dimension(): int
    {
        return $this->targetDimension;
    }

    /**
     * Zero-pad a vector to the target dimension.
     *
     * @param  array<int, float>  $vector
     * @return array<int, float>
     */
    private function zeroPad(array $vector): array
    {
        $currentDim = count($vector);

        if ($currentDim >= $this->targetDimension) {
            return array_slice($vector, 0, $this->targetDimension);
        }

        return array_merge($vector, array_fill(0, $this->targetDimension - $currentDim, 0.0));
    }
}
