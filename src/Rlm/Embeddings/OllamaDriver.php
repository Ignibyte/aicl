<?php

namespace Aicl\Rlm\Embeddings;

use Aicl\Contracts\EmbeddingDriver;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OllamaDriver implements EmbeddingDriver
{
    private const TARGET_DIMENSION = 1536;

    public function __construct(
        private readonly string $host = 'http://localhost:11434',
        private readonly string $model = 'nomic-embed-text',
    ) {}

    /**
     * @return array<int, float>
     */
    public function embed(string $text): array
    {
        try {
            $response = Http::timeout(30)
                ->post(rtrim($this->host, '/').'/api/embed', [
                    'model' => $this->model,
                    'input' => $text,
                ])
                ->throw();

            $embeddings = $response->json('embeddings');

            if (! is_array($embeddings) || empty($embeddings)) {
                throw new RuntimeException('Invalid response from Ollama embeddings API');
            }

            return $this->zeroPad($embeddings[0]);
        } catch (RequestException $e) {
            throw new RuntimeException(
                'Ollama embeddings API request failed: '.$e->getMessage(),
                $e->getCode(),
                $e,
            );
        }
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

        // Ollama supports batch via the 'input' field as array
        try {
            $response = Http::timeout(60)
                ->post(rtrim($this->host, '/').'/api/embed', [
                    'model' => $this->model,
                    'input' => $texts,
                ])
                ->throw();

            $embeddings = $response->json('embeddings');

            if (! is_array($embeddings)) {
                throw new RuntimeException('Invalid response from Ollama embeddings API');
            }

            return array_map(fn (array $embedding): array => $this->zeroPad($embedding), $embeddings);
        } catch (RequestException $e) {
            throw new RuntimeException(
                'Ollama embeddings API request failed: '.$e->getMessage(),
                $e->getCode(),
                $e,
            );
        }
    }

    public function dimension(): int
    {
        return self::TARGET_DIMENSION;
    }

    /**
     * Zero-pad a vector to the target dimension (1536).
     *
     * @param  array<int, float>  $vector
     * @return array<int, float>
     */
    private function zeroPad(array $vector): array
    {
        $currentDim = count($vector);

        if ($currentDim >= self::TARGET_DIMENSION) {
            return array_slice($vector, 0, self::TARGET_DIMENSION);
        }

        return array_merge($vector, array_fill(0, self::TARGET_DIMENSION - $currentDim, 0.0));
    }
}
