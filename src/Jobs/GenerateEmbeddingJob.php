<?php

namespace Aicl\Jobs;

use Aicl\Rlm\EmbeddingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateEmbeddingJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 10;

    public function __construct(
        public Model $model,
    ) {}

    public function handle(EmbeddingService $service): void
    {
        if (! $service->isAvailable()) {
            Log::debug('GenerateEmbeddingJob: Embedding service unavailable, skipping.', [
                'model' => get_class($this->model),
                'id' => $this->model->getKey(),
            ]);

            return;
        }

        if (! method_exists($this->model, 'embeddingText') || ! method_exists($this->model, 'cacheEmbedding')) {
            return;
        }

        $text = $this->model->embeddingText();

        if (empty(trim($text))) {
            Log::debug('GenerateEmbeddingJob: Empty embedding text, skipping.', [
                'model' => get_class($this->model),
                'id' => $this->model->getKey(),
            ]);

            return;
        }

        $embedding = $service->generate($text);

        if ($embedding === null) {
            return;
        }

        // Cache the embedding so toSearchableArray() can include it
        $this->model->cacheEmbedding($embedding);

        // Re-index the model in ES with the embedding vector
        if (method_exists($this->model, 'searchable')) {
            $this->model->searchable();
        }
    }
}
