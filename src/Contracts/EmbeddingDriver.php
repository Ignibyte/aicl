<?php

namespace Aicl\Contracts;

interface EmbeddingDriver
{
    /**
     * Generate an embedding vector for a single text.
     *
     * @return array<int, float>
     */
    public function embed(string $text): array;

    /**
     * Generate embedding vectors for multiple texts.
     *
     * @param  array<int, string>  $texts
     * @return array<int, array<int, float>>
     */
    public function embedBatch(array $texts): array;

    /**
     * Get the vector dimension for this driver.
     */
    public function dimension(): int;
}
