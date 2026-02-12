<?php

namespace Aicl\Http\Controllers\Api;

use Aicl\Http\Resources\RlmFailureResource;
use Aicl\Models\RlmFailure;
use Aicl\Rlm\EmbeddingService;
use Aicl\Rlm\KnowledgeService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class RlmKnowledgeController extends Controller
{
    public function __construct(
        private KnowledgeService $knowledgeService,
        private EmbeddingService $embeddingService,
    ) {}

    /**
     * Search across all knowledge types via ES hybrid (kNN + BM25) or deterministic fallback.
     *
     * GET /api/v1/rlm/search?q=...&type=failure&limit=10
     */
    public function search(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', RlmFailure::class);

        $validated = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:500'],
            'type' => ['nullable', 'string', 'in:failure,lesson,pattern,prevention_rule,golden_annotation'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $results = $this->knowledgeService->search(
            query: $validated['q'],
            type: $validated['type'] ?? null,
            limit: $validated['limit'] ?? 10,
        );

        return response()->json([
            'data' => $results->map(fn ($model) => [
                'id' => $model->id,
                'type' => class_basename($model),
                'attributes' => $model->toArray(),
            ]),
            'meta' => [
                'total' => $results->count(),
                'search_available' => $this->knowledgeService->isSearchAvailable(),
            ],
        ]);
    }

    /**
     * Agent-facing context retrieval with risk briefing.
     *
     * GET /api/v1/rlm/recall?agent=architect&phase=3&entity_context={json}
     */
    public function recall(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', RlmFailure::class);

        $validated = $request->validate([
            'agent' => ['required', 'string', 'max:50'],
            'phase' => ['required', 'integer', 'min:1', 'max:10'],
            'entity_context' => ['nullable', 'string'],
            'entity_name' => ['nullable', 'string', 'max:100'],
        ]);

        $entityContext = null;
        if (! empty($validated['entity_context'])) {
            $entityContext = json_decode($validated['entity_context'], true);
        }

        $result = $this->knowledgeService->recall(
            agent: $validated['agent'],
            phase: (int) $validated['phase'],
            entityContext: $entityContext,
            entityName: $validated['entity_name'] ?? null,
        );

        return response()->json([
            'data' => [
                'failures' => $result['failures']->values(),
                'lessons' => $result['lessons']->values(),
                'scores' => $result['scores']->values(),
                'prevention_rules' => $result['prevention_rules']->values(),
                'golden_annotations' => $result['golden_annotations']->values(),
                'risk_briefing' => $result['risk_briefing'],
            ],
        ]);
    }

    /**
     * Get a full failure record by failure_code.
     *
     * GET /api/v1/rlm/failure/{failureCode}
     */
    public function getFailure(string $failureCode): JsonResponse
    {
        $failure = $this->knowledgeService->getFailure($failureCode);

        if ($failure === null) {
            return response()->json([
                'error' => "Failure '{$failureCode}' not found.",
            ], 404);
        }

        Gate::authorize('view', $failure);

        return response()->json([
            'data' => new RlmFailureResource($failure->load(['owner', 'reports', 'preventionRules'])),
        ]);
    }

    /**
     * Trigger embedding generation for specific records.
     *
     * POST /api/v1/rlm/embed  { model: 'RlmFailure', ids: [...] }
     */
    public function embed(Request $request): JsonResponse
    {
        Gate::authorize('create', RlmFailure::class);

        $validated = $request->validate([
            'model' => ['required', 'string', 'in:RlmFailure,RlmLesson,RlmPattern,PreventionRule,GoldenAnnotation'],
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['required', 'string'],
        ]);

        if (! $this->embeddingService->isAvailable()) {
            return response()->json([
                'error' => 'Embedding service is not available. Configure OPENAI_API_KEY or OLLAMA_HOST.',
            ], 503);
        }

        $modelClass = 'Aicl\\Models\\'.$validated['model'];

        if (! class_exists($modelClass)) {
            return response()->json(['error' => 'Invalid model.'], 422);
        }

        $records = $modelClass::whereIn('id', $validated['ids'])->get();

        $generated = 0;
        foreach ($records as $record) {
            if (method_exists($record, 'embeddingText')) {
                $text = $record->embeddingText();

                if (! empty($text)) {
                    $embedding = $this->embeddingService->generate($text);

                    if ($embedding !== null) {
                        $record->cacheEmbedding($embedding);
                        $record->searchable();
                        $generated++;
                    }
                }
            }
        }

        return response()->json([
            'data' => [
                'requested' => count($validated['ids']),
                'found' => $records->count(),
                'generated' => $generated,
            ],
        ]);
    }
}
