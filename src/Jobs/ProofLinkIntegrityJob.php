<?php

namespace Aicl\Jobs;

use Aicl\Enums\KnowledgeLinkType;
use Aicl\Enums\LessonType;
use Aicl\Models\KnowledgeLink;
use Aicl\Models\RlmLesson;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Scheduled job that validates proof links still resolve.
 *
 * For each KnowledgeLink with a link_type (proof link), checks whether
 * the referenced resource still exists:
 * - golden_entity_file / doc_anchor → file_exists()
 * - test_case → class_exists() + method_exists()
 * - commit_sha → always valid (immutable)
 *
 * Broken links flag the parent lesson as needs_review.
 * Instructions with ALL broken proof links are demoted to observations.
 */
class ProofLinkIntegrityJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public function handle(): void
    {
        $proofLinks = KnowledgeLink::query()
            ->proofLinks()
            ->get();

        if ($proofLinks->isEmpty()) {
            Log::debug('ProofLinkIntegrityJob: No proof links to check.');

            return;
        }

        $brokenCount = 0;
        $brokenByLesson = [];

        foreach ($proofLinks as $link) {
            if (! $this->isLinkValid($link)) {
                $brokenCount++;

                // Track broken links per source lesson
                $key = $link->source_type.'|'.$link->source_id;
                $brokenByLesson[$key] = ($brokenByLesson[$key] ?? 0) + 1;
            }
        }

        if ($brokenCount === 0) {
            Log::info('ProofLinkIntegrityJob: All proof links valid.', [
                'checked' => $proofLinks->count(),
            ]);

            return;
        }

        Log::warning('ProofLinkIntegrityJob: Found broken proof links.', [
            'checked' => $proofLinks->count(),
            'broken' => $brokenCount,
        ]);

        // For each lesson with broken links, check if ALL proof links are broken
        $lessonMorphClass = (new RlmLesson)->getMorphClass();

        foreach ($brokenByLesson as $compositeKey => $brokenLinkCount) {
            [$sourceType, $sourceId] = explode('|', $compositeKey, 2);

            if ($sourceType !== $lessonMorphClass) {
                continue;
            }

            $lesson = RlmLesson::query()->find($sourceId);
            if ($lesson === null) {
                continue;
            }

            $totalProofLinks = KnowledgeLink::query()
                ->where('source_type', $sourceType)
                ->where('source_id', $sourceId)
                ->proofLinks()
                ->count();

            if ($brokenLinkCount >= $totalProofLinks) {
                // ALL proof links broken → demote to observation
                if ($lesson->lesson_type === LessonType::Instruction) {
                    $lesson->update([
                        'lesson_type' => LessonType::Observation,
                        'needs_review' => true,
                        'promotion_reason' => ($lesson->promotion_reason ? $lesson->promotion_reason.' | ' : '')
                            .'Demoted: all proof links broken ('.now()->toDateString().')',
                    ]);

                    Log::info('ProofLinkIntegrityJob: Demoted instruction to observation.', [
                        'lesson_id' => $lesson->id,
                    ]);
                }
            } else {
                // Some proof links broken → flag for review
                $lesson->update(['needs_review' => true]);
            }
        }
    }

    /**
     * Check if a proof link's reference still resolves.
     */
    private function isLinkValid(KnowledgeLink $link): bool
    {
        if ($link->reference === null) {
            return true; // No reference to validate
        }

        return match ($link->link_type) {
            KnowledgeLinkType::GoldenEntityFile,
            KnowledgeLinkType::DocAnchor => file_exists(base_path($link->reference)),
            KnowledgeLinkType::TestCase => $this->isTestCaseValid($link->reference),
            KnowledgeLinkType::CommitSha => true, // Commits are immutable
            default => true,
        };
    }

    /**
     * Check if a test case reference (Class::method) still exists.
     */
    private function isTestCaseValid(string $reference): bool
    {
        if (str_contains($reference, '::')) {
            [$class, $method] = explode('::', $reference, 2);

            return class_exists($class) && method_exists($class, $method);
        }

        return class_exists($reference);
    }
}
