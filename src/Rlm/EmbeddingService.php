<?php

namespace Aicl\Rlm;

use Aicl\Contracts\EmbeddingDriver;
use Aicl\Rlm\Embeddings\NeuronAiEmbeddingAdapter;
use Aicl\Rlm\Embeddings\NullDriver;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use NeuronAI\Laravel\Facades\EmbeddingProvider;

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

        try {
            $embedding = $driver->embed($text);
        } catch (\Throwable $e) {
            Log::warning('EmbeddingService: embed() call failed', [
                'message' => $e->getMessage(),
                'text_length' => mb_strlen($text),
            ]);

            return null;
        }

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

        try {
            $results = $driver->embedBatch($texts);
        } catch (\Throwable $e) {
            Log::warning('EmbeddingService: embedBatch() call failed', [
                'message' => $e->getMessage(),
                'batch_size' => count($texts),
            ]);

            return array_map(fn (): null => null, $texts);
        }

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
     * 1. NullDriver if explicitly configured
     * 2. NeuronAI OpenAI adapter if API key present
     * 3. NeuronAI Ollama adapter if Ollama reachable
     * 4. Fallback: NullDriver
     */
    private function resolveDriver(): EmbeddingDriver
    {
        $explicitDriver = config('aicl.rlm.embeddings.driver');

        if ($explicitDriver === 'null') {
            return new NullDriver;
        }

        $dimension = (int) config('aicl.rlm.embeddings.dimension', 1536);

        if ($explicitDriver === 'openai' || ($explicitDriver === null && ! empty(config('aicl.rlm.embeddings.openai.api_key')))) {
            try {
                $provider = EmbeddingProvider::driver('openai');

                return new NeuronAiEmbeddingAdapter($provider, $dimension, padToTarget: false);
            } catch (\Throwable $e) {
                Log::warning('EmbeddingService: NeuronAI OpenAI provider failed.', ['error' => $e->getMessage()]);

                return new NullDriver;
            }
        }

        if ($explicitDriver === 'ollama' || ($explicitDriver === null && $this->isOllamaReachable())) {
            try {
                $provider = EmbeddingProvider::driver('ollama');

                return new NeuronAiEmbeddingAdapter($provider, $dimension, padToTarget: true);
            } catch (\Throwable $e) {
                Log::warning('EmbeddingService: NeuronAI Ollama provider failed.', ['error' => $e->getMessage()]);

                return new NullDriver;
            }
        }

        Log::info('EmbeddingService: No embedding driver available. kNN search disabled, BM25 still active.');

        return new NullDriver;
    }

    private function isOllamaReachable(): bool
    {
        $host = (string) config('aicl.rlm.embeddings.ollama.host', 'http://localhost:11434');

        try {
            $response = Http::timeout(2)->get(rtrim($host, '/').'/api/tags');

            return $response->successful();
        } catch (\Throwable $e) {
            Log::debug('EmbeddingService: Ollama unreachable', [
                'host' => $host,
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
