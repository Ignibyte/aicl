<?php

namespace Aicl\Rlm;

use Aicl\Models\PreventionRule;
use Aicl\Models\RlmFailure;
use Aicl\Models\RlmPattern;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class HubClient
{
    private const QUEUE_CACHE_KEY = 'rlm:hub:sync_queue';

    /**
     * @var array<string, mixed>
     */
    protected array $lastResponse = [];

    public function __construct(
        protected ProjectIdentity $identity,
    ) {}

    /**
     * Check if the hub is reachable.
     */
    public function isReachable(): bool
    {
        if (! $this->identity->isHubEnabled()) {
            return false;
        }

        try {
            $response = $this->http()->get('/api/v1/rlm_patterns', ['per_page' => 1]);

            return $response->successful();
        } catch (ConnectionException $e) {
            Log::warning('HubClient: hub unreachable during health check', [
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Push unsynced failures to the hub.
     *
     * @return array{pushed: int, errors: int, queued: int}
     */
    public function pushFailures(?string $since = null): array
    {
        $query = RlmFailure::query();
        if ($since !== null) {
            $query->where('updated_at', '>', $since);
        }

        $failures = $query->get();

        return $this->pushBatch('rlm_failures/upsert', $failures->toArray(), function (array $failure): array {
            return $this->identity->anonymize([
                'failure_code' => $failure['failure_code'],
                'category' => $failure['category'] ?? null,
                'severity' => $failure['severity'] ?? null,
                'title' => $failure['title'],
                'description' => $failure['description'],
                'root_cause' => $failure['root_cause'] ?? null,
                'fix' => $failure['fix'] ?? null,
                'preventive_rule' => $failure['preventive_rule'] ?? null,
                'entity_context' => $failure['entity_context'] ?? null,
                'scaffolding_fixed' => (bool) ($failure['scaffolding_fixed'] ?? false),
                'aicl_version' => $failure['aicl_version'] ?? null,
                'project_hash' => $this->identity->hash(),
            ]);
        });
    }

    /**
     * Push unsynced lessons to the hub.
     *
     * @return array{pushed: int, errors: int, queued: int}
     */
    public function pushLessons(?string $since = null): array
    {
        $query = \Aicl\Models\RlmLesson::query();
        if ($since !== null) {
            $query->where('updated_at', '>', $since);
        }

        $lessons = $query->get();

        return $this->pushBatch('rlm_lessons', $lessons->toArray(), function (array $lesson): array {
            return $this->identity->anonymize([
                'topic' => $lesson['topic'],
                'subtopic' => $lesson['subtopic'] ?? null,
                'summary' => $lesson['summary'],
                'detail' => $lesson['detail'],
                'tags' => $lesson['tags'] ?? null,
                'source' => $lesson['source'] ?? null,
                'confidence' => (float) ($lesson['confidence'] ?? 1.0),
                'project_hash' => $this->identity->hash(),
            ]);
        });
    }

    /**
     * Push unsynced traces to the hub.
     *
     * @return array{pushed: int, errors: int, queued: int}
     */
    public function pushTraces(?string $since = null): array
    {
        $query = \Aicl\Models\GenerationTrace::query();
        if ($since !== null) {
            $query->where('updated_at', '>', $since);
        }

        $traces = $query->get();

        return $this->pushBatch('generation_traces', $traces->toArray(), function (array $trace): array {
            return $this->identity->anonymize([
                'entity_name' => $trace['entity_name'],
                'scaffolder_args' => $trace['scaffolder_args'] ?? '',
                'file_manifest' => is_array($trace['file_manifest']) ? json_encode($trace['file_manifest']) : ($trace['file_manifest'] ?? '[]'),
                'structural_score' => $trace['structural_score'] ?? '0',
                'semantic_score' => $trace['semantic_score'] ?? null,
                'fixes_applied' => is_array($trace['fixes_applied']) ? json_encode($trace['fixes_applied']) : ($trace['fixes_applied'] ?? null),
                'fix_iterations' => (int) ($trace['fix_iterations'] ?? 0),
                'pipeline_duration' => $trace['pipeline_duration'] ? (int) $trace['pipeline_duration'] : null,
                'project_hash' => $this->identity->hash(),
            ]);
        });
    }

    /**
     * Push failure reports to the hub.
     *
     * @param  array<int, array<string, mixed>>  $reports
     * @return array{pushed: int, errors: int, queued: int}
     */
    public function pushReports(array $reports): array
    {
        return $this->pushBatch('failure_reports', $reports, function (array $report): array {
            return $this->identity->anonymize([
                'failure_code' => $report['failure_code'] ?? $report['failure_id'] ?? '',
                'entity_name' => $report['entity_name'] ?? null,
                'entity_context' => is_string($report['entity_context'] ?? null) ? json_decode($report['entity_context'], true) : ($report['entity_context'] ?? null),
                'resolution_method' => $report['resolution_method'] ?? null,
                'aicl_version' => $report['aicl_version'] ?? null,
                'project_hash' => $this->identity->hash(),
            ]);
        });
    }

    /**
     * Get the last response metadata.
     *
     * @return array<string, mixed>
     */
    public function getLastResponse(): array
    {
        return $this->lastResponse;
    }

    /**
     * Push a batch of records to a hub endpoint.
     *
     * @param  array<int, array<string, mixed>>  $records
     * @param  callable(array<string, mixed>): array<string, mixed>  $transform
     * @return array{pushed: int, errors: int, queued: int}
     */
    protected function pushBatch(string $endpoint, array $records, callable $transform): array
    {
        $pushed = 0;
        $errors = 0;
        $queued = 0;

        foreach ($records as $record) {
            $payload = $transform($record);

            try {
                $response = $this->http()->post("/api/v1/{$endpoint}", $payload);

                if ($response->successful()) {
                    $pushed++;
                } else {
                    $errors++;
                    $this->lastResponse = [
                        'status' => $response->status(),
                        'body' => $response->json(),
                    ];
                }
            } catch (ConnectionException $e) {
                Log::warning('HubClient: connection failed during pushBatch, enqueuing for retry', [
                    'endpoint' => $endpoint,
                    'message' => $e->getMessage(),
                ]);
                $this->enqueue($endpoint, $payload);
                $queued++;
            }
        }

        return compact('pushed', 'errors', 'queued');
    }

    /**
     * Drain the offline sync queue, retrying previously failed requests.
     *
     * @return array{pushed: int, errors: int, remaining: int}
     */
    public function drainQueue(): array
    {
        $pushed = 0;
        $errors = 0;

        $items = $this->dequeue(50);

        foreach ($items as $index => $item) {
            $payload = $item['payload'];
            $endpoint = $item['endpoint'];

            try {
                $response = $this->http()->post("/api/v1/{$endpoint}", $payload);

                if ($response->successful()) {
                    $pushed++;
                } else {
                    $errors++;
                }
            } catch (ConnectionException $e) {
                // Still offline — re-enqueue remaining items and stop
                Log::warning('HubClient: connection failed during drainQueue, re-enqueuing remaining items', [
                    'endpoint' => $endpoint,
                    'remaining_count' => count($items) - $index,
                    'message' => $e->getMessage(),
                ]);
                $remaining = array_slice($items, $index);
                $this->replaceQueue($remaining);
                $errors++;

                break;
            }
        }

        return [
            'pushed' => $pushed,
            'errors' => $errors,
            'remaining' => $this->getQueueSize(),
        ];
    }

    // ─── Pull Methods ────────────────────────────────────────

    /**
     * Pull patterns from the hub and merge into local DB.
     *
     * @return array{received: int, merged: int}
     */
    public function pullPatterns(): array
    {
        $patterns = $this->paginatedGet('rlm_patterns');
        $merged = 0;

        foreach ($patterns as $pattern) {
            RlmPattern::query()->updateOrCreate(
                ['name' => $pattern['name']],
                [
                    'description' => $pattern['description'] ?? '',
                    'target' => $pattern['target'] ?? 'model',
                    'check_regex' => $pattern['check_regex'] ?? $pattern['check'] ?? '',
                    'severity' => $pattern['severity'] ?? 'error',
                    'weight' => (float) ($pattern['weight'] ?? 1.0),
                    'category' => $pattern['category'] ?? 'structural',
                    'source' => 'hub',
                    'is_active' => true,
                    'owner_id' => 1,
                ],
            );
            $merged++;
        }

        return ['received' => count($patterns), 'merged' => $merged];
    }

    /**
     * Pull base failures from the hub and merge into local DB.
     *
     * @return array{received: int, merged: int}
     */
    public function pullFailures(): array
    {
        $failures = $this->paginatedGet('rlm_failures');
        $merged = 0;

        foreach ($failures as $failure) {
            // Only merge base failures (BF-*), not project-specific ones
            $failureCode = $failure['failure_code'] ?? '';
            if (! str_starts_with($failureCode, 'BF-')) {
                continue;
            }

            RlmFailure::query()->updateOrCreate(
                ['failure_code' => $failureCode],
                [
                    'category' => $failure['category'] ?? 'general',
                    'severity' => $failure['severity'] ?? 'medium',
                    'title' => $failure['title'] ?? '',
                    'description' => $failure['description'] ?? '',
                    'root_cause' => $failure['root_cause'] ?? null,
                    'fix' => $failure['fix'] ?? null,
                    'preventive_rule' => $failure['preventive_rule'] ?? null,
                    'promoted_to_base' => true,
                    'is_active' => true,
                    'owner_id' => 1,
                ],
            );
            $merged++;
        }

        return ['received' => count($failures), 'merged' => $merged];
    }

    /**
     * Pull prevention rules from the hub and cache locally.
     *
     * @return array{received: int, cached: int}
     */
    public function pullPreventionRules(): array
    {
        $rules = $this->paginatedGet('prevention_rules');
        $cached = 0;

        foreach ($rules as $rule) {
            PreventionRule::query()->updateOrCreate(
                ['rule_text' => $rule['rule_text']],
                [
                    'source_failure_code' => $rule['source_failure_code'] ?? null,
                    'trigger_context' => $rule['trigger_context'] ?? null,
                    'confidence' => (float) ($rule['confidence'] ?? 0.8),
                    'priority' => (int) ($rule['priority'] ?? 50),
                    'applied_count' => (int) ($rule['applied_count'] ?? 0),
                    'is_active' => true,
                    'owner_id' => 1,
                ],
            );
            $cached++;
        }

        return ['received' => count($rules), 'cached' => $cached];
    }

    // ─── Redis-Based Sync Queue ──────────────────────────────

    /**
     * Add an item to the sync queue (Redis-backed).
     *
     * @param  array<string, mixed>  $payload
     */
    public function enqueue(string $endpoint, array $payload): void
    {
        $queue = Cache::get(self::QUEUE_CACHE_KEY, []);
        $queue[] = ['endpoint' => $endpoint, 'payload' => $payload];
        Cache::forever(self::QUEUE_CACHE_KEY, $queue);
    }

    /**
     * Dequeue items from the sync queue.
     *
     * @return array<int, array{endpoint: string, payload: array<string, mixed>}>
     */
    public function dequeue(int $limit = 50): array
    {
        $queue = Cache::get(self::QUEUE_CACHE_KEY, []);
        $items = array_slice($queue, 0, $limit);
        $remaining = array_slice($queue, $limit);
        Cache::forever(self::QUEUE_CACHE_KEY, $remaining);

        return $items;
    }

    /**
     * Replace the queue contents (for re-enqueuing failed items).
     *
     * @param  array<int, array{endpoint: string, payload: array<string, mixed>}>  $items
     */
    protected function replaceQueue(array $items): void
    {
        $existing = Cache::get(self::QUEUE_CACHE_KEY, []);
        Cache::forever(self::QUEUE_CACHE_KEY, array_merge($items, $existing));
    }

    /**
     * Get the current queue size.
     */
    public function getQueueSize(): int
    {
        return count(Cache::get(self::QUEUE_CACHE_KEY, []));
    }

    // ─── HTTP Client ─────────────────────────────────────────

    /**
     * Fetch all records from a paginated hub endpoint.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function paginatedGet(string $endpoint, int $perPage = 50): array
    {
        $allRecords = [];
        $page = 1;

        try {
            do {
                $response = $this->http()->get("/api/v1/{$endpoint}", [
                    'page' => $page,
                    'per_page' => $perPage,
                ]);

                if (! $response->successful()) {
                    break;
                }

                $data = $response->json('data', []);
                $allRecords = array_merge($allRecords, $data);

                // Check if there are more pages
                $lastPage = $response->json('meta.last_page', $response->json('last_page', 1));
                $page++;
            } while ($page <= $lastPage);
        } catch (ConnectionException $e) {
            // Return whatever we collected so far
            Log::warning('HubClient: connection failed during paginatedGet', [
                'endpoint' => $endpoint,
                'page' => $page,
                'records_collected' => count($allRecords),
                'message' => $e->getMessage(),
            ]);
        }

        return $allRecords;
    }

    /**
     * Get the configured HTTP client for hub requests.
     */
    protected function http(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::withToken($this->identity->hubToken())
            ->baseUrl($this->identity->hubUrl())
            ->timeout((int) config('aicl.rlm.hub.timeout', 30))
            ->acceptJson();
    }
}
