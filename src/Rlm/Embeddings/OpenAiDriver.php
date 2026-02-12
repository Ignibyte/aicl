<?php

namespace Aicl\Rlm\Embeddings;

use Aicl\Contracts\EmbeddingDriver;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenAiDriver implements EmbeddingDriver
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $model = 'text-embedding-3-small',
    ) {}

    /**
     * @return array<int, float>
     */
    public function embed(string $text): array
    {
        $result = $this->embedBatch([$text]);

        return $result[0];
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

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$this->apiKey,
            ])
                ->timeout(30)
                ->post('https://api.openai.com/v1/embeddings', [
                    'model' => $this->model,
                    'input' => $texts,
                ])
                ->throw();

            $data = $response->json('data');

            if (! is_array($data)) {
                throw new RuntimeException('Invalid response from OpenAI embeddings API');
            }

            // Sort by index to maintain input order
            usort($data, fn (array $a, array $b): int => $a['index'] <=> $b['index']);

            return array_map(fn (array $item): array => $item['embedding'], $data);
        } catch (RequestException $e) {
            throw new RuntimeException(
                'OpenAI embeddings API request failed: '.$e->getMessage(),
                $e->getCode(),
                $e,
            );
        }
    }

    public function dimension(): int
    {
        return 1536;
    }
}
