<?php

namespace Aicl\Rlm;

use Aicl\Models\GenerationTrace;
use Aicl\Models\RlmFailure;
use Aicl\Models\RlmLesson;
use Aicl\Models\RlmScore;
use Aicl\Repositories\RlmFailureRepository;
use Aicl\Rlm\Exceptions\RlmInvalidArgumentException;

/**
 * Write operations for the RLM knowledge system.
 *
 * Handles creation and upsert of lessons, failures, scores, and traces.
 * All operations write to PostgreSQL as the source of truth.
 */
class KnowledgeWriter
{
    /**
     * Add a lesson to the knowledge base.
     *
     * Scout auto-indexes → observer dispatches embedding job.
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
        return RlmLesson::query()->create([
            'topic' => $topic,
            'subtopic' => $subtopic,
            'summary' => $summary,
            'detail' => $detail,
            'tags' => $tags,
            'source' => $source,
            'confidence' => $confidence,
            'is_verified' => false,
            'is_active' => true,
            'owner_id' => $this->getDefaultOwnerId(),
        ]);
    }

    /**
     * Record a failure. Upserts by failure_code.
     *
     * @param  array<string, mixed>  $data
     */
    public function recordFailure(array $data): RlmFailure
    {
        $failureCode = $data['failure_code'] ?? $data['failure_id'] ?? null;

        if ($failureCode === null) {
            throw RlmInvalidArgumentException::missingRequiredField('failure_code');
        }

        $result = app(RlmFailureRepository::class)->upsertByCode(
            array_merge($data, ['failure_code' => $failureCode]),
            $data['owner_id'] ?? $this->getDefaultOwnerId(),
            incrementReportCount: false,
        );

        return $result['record'];
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
        return RlmScore::query()->create([
            'entity_name' => $entityName,
            'score_type' => $type,
            'passed' => $passed,
            'total' => $total,
            'percentage' => $percentage,
            'errors' => $errors,
            'warnings' => $warnings,
            'details' => $details,
            'owner_id' => $this->getDefaultOwnerId(),
        ]);
    }

    /**
     * Record a generation trace.
     *
     * @param  array<string, mixed>  $data
     */
    public function recordTrace(string $entityName, array $data): GenerationTrace
    {
        return GenerationTrace::query()->create(array_merge($data, [
            'entity_name' => $entityName,
            'owner_id' => $data['owner_id'] ?? $this->getDefaultOwnerId(),
        ]));
    }

    /**
     * Get the current user ID or the configured default.
     */
    public function getDefaultOwnerId(): int
    {
        return auth()->id() ?? (int) config('aicl.default_owner_id', 1);
    }
}
