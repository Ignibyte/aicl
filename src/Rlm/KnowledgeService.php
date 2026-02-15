<?php

namespace Aicl\Rlm;

use Aicl\Models\GenerationTrace;
use Aicl\Models\RlmFailure;
use Aicl\Models\RlmLesson;
use Aicl\Models\RlmScore;
use Aicl\Swoole\Cache\KnowledgeStatsCacheManager;
use Aicl\Swoole\Cache\ServiceHealthCacheManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Central query interface for the RLM knowledge system.
 *
 * Thin delegator that routes calls to focused services:
 * - KnowledgeSearchEngine: ES hybrid search + deterministic fallback
 * - RecallService: Agent-facing recall orchestration + risk briefing
 * - KnowledgeWriter: CRUD for lessons, failures, scores, traces
 *
 * Owns health checks, stats, and simple direct queries.
 */
class KnowledgeService
{
    private ?bool $esAvailable = null;

    private KnowledgeSearchEngine $searchEngine;

    private RecallService $recallService;

    private KnowledgeWriter $writer;

    /**
     * Constructor supports both DI (via container) and direct instantiation (for tests).
     *
     * When resolved via the container, all 4 dependencies are injected.
     * When constructed directly with just EmbeddingService (as in existing tests),
     * the 3 new services are created internally with that EmbeddingService.
     */
    public function __construct(
        private EmbeddingService $embeddingService,
        ?KnowledgeSearchEngine $searchEngine = null,
        ?RecallService $recallService = null,
        ?KnowledgeWriter $writer = null,
    ) {
        $this->searchEngine = $searchEngine ?? new KnowledgeSearchEngine($embeddingService);
        $this->writer = $writer ?? new KnowledgeWriter;
        $this->recallService = $recallService ?? new RecallService($this->searchEngine, $embeddingService);
    }

    // ─── Search (delegates to KnowledgeSearchEngine) ────────────

    /**
     * Hybrid search across all knowledge types.
     *
     * Flow: generate embedding -> ES hybrid query (kNN + BM25 + RRF) -> PG hydration.
     * Falls back through degradation tiers when ES or embeddings are unavailable.
     *
     * @return Collection<int, Model>
     */
    public function search(string $query, ?string $type = null, int $limit = 10): Collection
    {
        return $this->searchEngine->search($query, $type, $limit, $this->isElasticsearchAvailable());
    }

    // ─── Recall (delegates to RecallService) ────────────────────

    /**
     * Agent-facing context retrieval with risk briefing.
     *
     * Combines ES hybrid search with deterministic topic lookups,
     * prevention rules, recent outcomes, and golden annotations.
     *
     * @param  array<string, mixed>  $entityContext
     * @return array{failures: Collection, lessons: Collection, scores: Collection, prevention_rules: Collection, golden_annotations: Collection, risk_briefing: array}
     */
    public function recall(string $agent, int $phase, ?array $entityContext = null, ?string $entityName = null): array
    {
        return $this->recallService->recall(
            $agent,
            $phase,
            $entityContext,
            $entityName,
            $this->isElasticsearchAvailable(),
        );
    }

    // ─── Write Methods (delegates to KnowledgeWriter) ───────────

    /**
     * Add a lesson to the knowledge base.
     */
    public function addLesson(
        string $topic,
        string $summary,
        string $detail,
        ?string $subtopic = null,
        ?string $tags = null,
        ?string $source = null,
        float $confidence = 1.0,
    ): RlmLesson {
        return $this->writer->addLesson($topic, $summary, $detail, $subtopic, $tags, $source, $confidence);
    }

    /**
     * Record a failure. Upserts by failure_code.
     *
     * @param  array<string, mixed>  $data
     */
    public function recordFailure(array $data): RlmFailure
    {
        return $this->writer->recordFailure($data);
    }

    /**
     * Record a validation score.
     *
     * @param  array<string, mixed>|null  $details
     */
    public function recordScore(
        string $entityName,
        string $type,
        int $passed,
        int $total,
        float $percentage,
        int $errors = 0,
        int $warnings = 0,
        ?array $details = null,
    ): RlmScore {
        return $this->writer->recordScore($entityName, $type, $passed, $total, $percentage, $errors, $warnings, $details);
    }

    /**
     * Record a generation trace.
     *
     * @param  array<string, mixed>  $data
     */
    public function recordTrace(string $entityName, array $data): GenerationTrace
    {
        return $this->writer->recordTrace($entityName, $data);
    }

    // ─── Query Methods (kept — too small to extract) ────────────

    /**
     * Get failures matching an entity context via JSONB queries.
     *
     * @param  array<string, mixed>  $context
     * @param  array<int, string>|null  $severities
     * @return Collection<int, RlmFailure>
     */
    public function getFailuresByContext(array $context = [], ?array $severities = null): Collection
    {
        $query = RlmFailure::query()->where('is_active', true);

        if ($context !== []) {
            $query->where(function ($q) use ($context) {
                // Include failures with null entity_context (universal)
                $q->whereNull('entity_context');

                foreach ($context as $key => $value) {
                    $q->orWhereJsonContains('entity_context->'.$key, $value);
                }
            });
        }

        if ($severities !== null && $severities !== []) {
            $query->whereIn('severity', $severities);
        }

        return $query->orderByRaw("CASE severity
            WHEN 'critical' THEN 1
            WHEN 'high' THEN 2
            WHEN 'medium' THEN 3
            WHEN 'low' THEN 4
            ELSE 5
        END")->get();
    }

    /**
     * Get a single failure by its failure_code.
     */
    public function getFailure(string $failureCode): ?RlmFailure
    {
        return RlmFailure::query()
            ->where('failure_code', $failureCode)
            ->first();
    }

    // ─── Stats & Health (kept — tightly coupled to delegator) ───

    /**
     * Aggregate statistics across all knowledge tables.
     *
     * @return array<string, mixed>
     */
    public function stats(): array
    {
        $cached = KnowledgeStatsCacheManager::getCachedStats();

        if ($cached !== null) {
            return array_merge([
                'storage' => 'postgresql',
                'search_engine' => $this->isElasticsearchAvailable() ? 'elasticsearch' : 'database',
                'embeddings' => $this->embeddingService->isAvailable() ? 'active' : 'unavailable',
            ], $cached);
        }

        $stats = KnowledgeStatsCacheManager::computeStats();
        KnowledgeStatsCacheManager::storeStats($stats);

        return array_merge([
            'storage' => 'postgresql',
            'search_engine' => $this->isElasticsearchAvailable() ? 'elasticsearch' : 'database',
            'embeddings' => $this->embeddingService->isAvailable() ? 'active' : 'unavailable',
        ], $stats);
    }

    /**
     * Check if Elasticsearch is reachable.
     *
     * Checks: (1) per-instance cache, (2) SwooleCache cross-worker cache,
     * (3) HTTP health check. Result is cached at all available layers.
     */
    public function isElasticsearchAvailable(): bool
    {
        // L0: per-instance cache (survives within a single request)
        if ($this->esAvailable !== null) {
            return $this->esAvailable;
        }

        // L1: SwooleCache cross-worker cache (30s TTL)
        $cached = ServiceHealthCacheManager::getCachedAvailability('elasticsearch');

        if ($cached !== null) {
            $this->esAvailable = $cached;

            return $this->esAvailable;
        }

        // L2: HTTP health check
        try {
            $response = Http::timeout(2)->get($this->searchEngine->getEsBaseUrl());
            $this->esAvailable = $response->successful();
        } catch (\Throwable $e) {
            Log::debug('KnowledgeService: ES health check failed', [
                'message' => $e->getMessage(),
            ]);
            $this->esAvailable = false;
        }

        ServiceHealthCacheManager::storeAvailability('elasticsearch', $this->esAvailable);

        return $this->esAvailable;
    }

    /**
     * Check if the full search stack is available (ES + embeddings).
     */
    public function isSearchAvailable(): bool
    {
        return $this->isElasticsearchAvailable() && $this->embeddingService->isAvailable();
    }

    /**
     * Reset cached ES availability (for testing or reconnection).
     */
    public function resetAvailabilityCache(): void
    {
        $this->esAvailable = null;
    }
}
