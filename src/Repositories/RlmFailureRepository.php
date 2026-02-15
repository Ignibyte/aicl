<?php

namespace Aicl\Repositories;

use Aicl\Models\RlmFailure;

class RlmFailureRepository
{
    /**
     * Upsert a failure by failure_code.
     *
     * If a failure with the given failure_code exists:
     *   - Updates all fields except failure_code
     *   - Optionally increments report_count
     *   - Updates last_seen_at
     *   - Returns {record, created: false}
     *
     * If no failure exists:
     *   - Creates a new record with defaults (report_count=1, project_count=1, timestamps)
     *   - Returns {record, created: true}
     *
     * @param  array<string, mixed>  $attributes  Validated failure attributes (must include failure_code)
     * @param  int  $ownerId  The owner ID for new records
     * @param  bool  $incrementReportCount  Whether to increment report_count on update
     * @return array{record: RlmFailure, created: bool}
     */
    public function upsertByCode(array $attributes, int $ownerId, bool $incrementReportCount = true): array
    {
        $failureCode = $attributes['failure_code'];

        $existing = $this->findByCode($failureCode);

        if ($existing) {
            $existing->update(
                collect($attributes)->except('failure_code')->toArray()
            );

            if ($incrementReportCount) {
                $existing->increment('report_count');
            }

            return ['record' => $existing->fresh(), 'created' => false];
        }

        $record = RlmFailure::query()->create([
            ...$attributes,
            'owner_id' => $ownerId,
            'report_count' => 1,
            'project_count' => 1,
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        return ['record' => $record, 'created' => true];
    }

    /**
     * Find a failure by its unique failure_code.
     */
    public function findByCode(string $failureCode): ?RlmFailure
    {
        return RlmFailure::query()
            ->where('failure_code', $failureCode)
            ->first();
    }
}
