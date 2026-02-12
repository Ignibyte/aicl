<?php

namespace Aicl\Rlm;

use Aicl\Contracts\EmbeddingDriver;
use Aicl\Rlm\Embeddings\NullDriver;
use Aicl\Rlm\Embeddings\OllamaDriver;
use Aicl\Rlm\Embeddings\OpenAiDriver;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EmbeddingService
{
    private ?EmbeddingDriver $driver = null;

    /**
     * Generate an embedding vector for a single text.
     *
     * @return array<int, float>|null Returns null if NullDriver is active
     */
    public function generate(string $text): ?array
    {
        $driver = $this->getDriver();

        if ($driver instanceof NullDriver) {
            return null;
        }

        $embedding = $driver->embed($text);

        return ! empty($embedding) ? $embedding : null;
    }

    /**
     * Generate embedding vectors for multiple texts.
     *
     * @param  array<int, string>  $texts
     * @return array<int, array<int, float>|null>
     */
    public function generateBatch(array $texts): array
    {
        if (empty($texts)) {
            return [];
        }

        $driver = $this->getDriver();

        if ($driver instanceof NullDriver) {
            return array_map(fn (): null => null, $texts);
        }

        $results = $driver->embedBatch($texts);

        return array_map(
            fn (array $embedding): ?array => ! empty($embedding) ? $embedding : null,
            $results,
        );
    }

    /**
     * Get the currently configured embedding driver.
     */
    public function getDriver(): EmbeddingDriver
    {
        if ($this->driver !== null) {
            return $this->driver;
        }

        $this->driver = $this->resolveDriver();

        return $this->driver;
    }

    /**
     * Check if embedding generation is available (not NullDriver).
     */
    public function isAvailable(): bool
    {
        return ! ($this->getDriver() instanceof NullDriver);
    }

    /**
     * Get the vector dimension for the current driver.
     */
    public function getDimension(): int
    {
        return $this->getDriver()->dimension();
    }

    /**
     * Resolve the driver based on configuration and environment.
     *
     * Priority:
     * 1. Explicit AICL_EMBEDDING_DRIVER config
     * 2. Auto-detect: OpenAI key present → OpenAI
     * 3. Auto-detect: Ollama reachable → Ollama
     * 4. Fallback: NullDriver
     */
    private function resolveDriver(): EmbeddingDriver
    {
        $explicitDriver = config('aicl.rlm.embeddings.driver');

        if ($explicitDriver !== null) {
            return match ($explicitDriver) {
                'openai' => $this->makeOpenAiDriver(),
                'ollama' => $this->makeOllamaDriver(),
                'null' => new NullDriver,
                default => new NullDriver,
            };
        }

        // Auto-detect: check for OpenAI API key
        $openAiKey = config('aicl.rlm.embeddings.openai.api_key');

        if (! empty($openAiKey)) {
            return $this->makeOpenAiDriver();
        }

        // Auto-detect: check if Ollama is reachable
        if ($this->isOllamaReachable()) {
            return $this->makeOllamaDriver();
        }

        Log::info('EmbeddingService: No embedding driver available. kNN search disabled, BM25 still active.');

        return new NullDriver;
    }

    private function makeOpenAiDriver(): OpenAiDriver
    {
        return new OpenAiDriver(
            apiKey: (string) config('aicl.rlm.embeddings.openai.api_key'),
            model: (string) config('aicl.rlm.embeddings.openai.model', 'text-embedding-3-small'),
        );
    }

    private function makeOllamaDriver(): OllamaDriver
    {
        return new OllamaDriver(
            host: (string) config('aicl.rlm.embeddings.ollama.host', 'http://localhost:11434'),
            model: (string) config('aicl.rlm.embeddings.ollama.model', 'nomic-embed-text'),
        );
    }

    private function isOllamaReachable(): bool
    {
        $host = (string) config('aicl.rlm.embeddings.ollama.host', 'http://localhost:11434');

        try {
            $response = Http::timeout(2)->get(rtrim($host, '/').'/api/tags');

            return $response->successful();
        } catch (\Throwable) {
            return false;
        }
    }
}
