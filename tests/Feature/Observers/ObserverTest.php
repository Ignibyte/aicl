<?php

namespace Aicl\Tests\Feature\Observers;

use Aicl\Jobs\GenerateEmbeddingJob;
use Aicl\Models\FailureReport;
use Aicl\Models\GenerationTrace;
use Aicl\Models\GoldenAnnotation;
use Aicl\Models\PreventionRule;
use Aicl\Models\RlmFailure;
use Aicl\Models\RlmLesson;
use Aicl\Models\RlmPattern;
use Aicl\States\RlmFailure\Confirmed;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class ObserverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        User::factory()->create(['id' => 1]);
    }

    /**
     * Helper: find observer-created activity log (has entity identifier in description).
     * Observer logs use quoted identifiers like: RlmPattern "my_pattern" was created
     * HasAuditTrail logs use: RlmPattern was created (no identifier)
     */
    private function findObserverActivity(string $subjectType, string $descriptionLike, ?string $subjectId = null): ?Activity
    {
        $query = Activity::where('description', 'like', $descriptionLike)
            ->where('subject_type', $subjectType);

        if ($subjectId) {
            $query->where('subject_id', $subjectId);
        }

        return $query->latest()->first();
    }

    /**
     * Helper: assert HasAuditTrail activity was logged (generic format: "{ClassName} was {event}").
     */
    private function assertAuditTrailLogged(string $subjectType, string $event, string $subjectId): void
    {
        $className = class_basename($subjectType);
        $activity = Activity::where('description', "{$className} was {$event}")
            ->where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)
            ->first();

        $this->assertNotNull($activity, "Expected HasAuditTrail log: '{$className} was {$event}'");
    }

    // ─── RlmPatternObserver ──────────────────────────────────────

    public function test_creating_rlm_pattern_logs_activity(): void
    {
        $pattern = RlmPattern::factory()->create(['owner_id' => 1]);

        // Observer creates a log with the quoted name
        $activity = $this->findObserverActivity(
            RlmPattern::class,
            '%"'.$pattern->name.'"%',
            $pattern->id,
        );

        $this->assertNotNull($activity, 'Observer should log activity with pattern name in quotes');
        $this->assertStringContainsString('was created', $activity->description);
    }

    public function test_creating_rlm_pattern_dispatches_embedding_job(): void
    {
        RlmPattern::factory()->create(['owner_id' => 1]);

        Queue::assertPushed(GenerateEmbeddingJob::class);
    }

    public function test_updating_rlm_pattern_dispatches_embedding_job(): void
    {
        $pattern = RlmPattern::factory()->create(['owner_id' => 1]);

        Queue::fake();

        $pattern->update(['description' => 'Updated description']);

        Queue::assertPushed(GenerateEmbeddingJob::class);
    }

    public function test_deleting_rlm_pattern_logs_activity(): void
    {
        $pattern = RlmPattern::factory()->create(['owner_id' => 1]);
        $patternName = $pattern->name;

        $pattern->delete();

        $activity = $this->findObserverActivity(
            RlmPattern::class,
            '%"'.$patternName.'" was deleted%',
        );

        $this->assertNotNull($activity, 'Observer should log activity with pattern name on delete');
    }

    // ─── RlmFailureObserver ──────────────────────────────────────

    public function test_creating_rlm_failure_logs_activity(): void
    {
        $failure = RlmFailure::factory()->create(['owner_id' => 1]);

        $activity = $this->findObserverActivity(
            RlmFailure::class,
            '%"'.$failure->failure_code.'"%',
            $failure->id,
        );

        $this->assertNotNull($activity, 'Observer should log activity with failure_code in quotes');
        $this->assertStringContainsString('was created', $activity->description);
    }

    public function test_creating_rlm_failure_dispatches_embedding_job(): void
    {
        RlmFailure::factory()->create(['owner_id' => 1]);

        Queue::assertPushed(GenerateEmbeddingJob::class);
    }

    public function test_updating_rlm_failure_dispatches_embedding_job(): void
    {
        $failure = RlmFailure::factory()->create(['owner_id' => 1]);

        Queue::fake();

        $failure->update(['description' => 'Updated failure description']);

        Queue::assertPushed(GenerateEmbeddingJob::class);
    }

    public function test_updating_rlm_failure_status_logs_change(): void
    {
        $failure = RlmFailure::factory()->reported()->create(['owner_id' => 1]);

        $failure->status->transitionTo(Confirmed::class);

        $activity = $this->findObserverActivity(
            RlmFailure::class,
            '%status changed%',
            $failure->id,
        );

        $this->assertNotNull($activity, 'Observer should log status change');
        $this->assertStringContainsString($failure->failure_code, $activity->description);
    }

    public function test_deleting_rlm_failure_logs_activity(): void
    {
        $failure = RlmFailure::factory()->create(['owner_id' => 1]);
        $failureCode = $failure->failure_code;

        $failure->delete();

        $activity = $this->findObserverActivity(
            RlmFailure::class,
            '%"'.$failureCode.'" was deleted%',
        );

        $this->assertNotNull($activity, 'Observer should log activity with failure_code on delete');
    }

    // ─── RlmLessonObserver ───────────────────────────────────────

    public function test_creating_rlm_lesson_logs_activity(): void
    {
        $lesson = RlmLesson::factory()->create(['owner_id' => 1]);

        // Observer logs: Lesson "{summary}" (topic: {topic}) was created
        $activity = $this->findObserverActivity(
            RlmLesson::class,
            '%topic:%',
            $lesson->id,
        );

        $this->assertNotNull($activity, 'Observer should log lesson creation with topic');
        $this->assertStringContainsString('was created', $activity->description);
    }

    public function test_creating_rlm_lesson_dispatches_embedding_job(): void
    {
        RlmLesson::factory()->create(['owner_id' => 1]);

        Queue::assertPushed(GenerateEmbeddingJob::class);
    }

    public function test_updating_rlm_lesson_dispatches_embedding_job(): void
    {
        $lesson = RlmLesson::factory()->create(['owner_id' => 1]);

        Queue::fake();

        $lesson->update(['detail' => 'Updated detail text']);

        Queue::assertPushed(GenerateEmbeddingJob::class);
    }

    public function test_deleting_rlm_lesson_logs_activity(): void
    {
        $lesson = RlmLesson::factory()->create(['owner_id' => 1]);
        $topic = $lesson->topic;

        $lesson->delete();

        $activity = $this->findObserverActivity(
            RlmLesson::class,
            '%topic: '.$topic.') was deleted%',
        );

        $this->assertNotNull($activity, 'Observer should log lesson deletion with topic');
    }

    // ─── PreventionRuleObserver ──────────────────────────────────

    public function test_creating_prevention_rule_logs_activity(): void
    {
        $rule = PreventionRule::factory()->withoutFailure()->create(['owner_id' => 1]);

        // Observer logs: PreventionRule "{rule_text truncated}" was created
        $activity = $this->findObserverActivity(
            PreventionRule::class,
            '%PreventionRule "%',
            $rule->id,
        );

        $this->assertNotNull($activity, 'Observer should log prevention rule creation');
        $this->assertStringContainsString('was created', $activity->description);
    }

    public function test_creating_prevention_rule_dispatches_embedding_job(): void
    {
        PreventionRule::factory()->withoutFailure()->create(['owner_id' => 1]);

        Queue::assertPushed(GenerateEmbeddingJob::class);
    }

    public function test_updating_prevention_rule_dispatches_embedding_job(): void
    {
        $rule = PreventionRule::factory()->withoutFailure()->create(['owner_id' => 1]);

        Queue::fake();

        $rule->update(['rule_text' => 'Updated prevention rule text']);

        Queue::assertPushed(GenerateEmbeddingJob::class);
    }

    public function test_deleting_prevention_rule_logs_activity(): void
    {
        $rule = PreventionRule::factory()->withoutFailure()->create(['owner_id' => 1]);

        $rule->delete();

        $activity = $this->findObserverActivity(
            PreventionRule::class,
            '%PreventionRule "%',
        );

        // Filter to only the "deleted" entry
        $deletedActivity = Activity::where('description', 'like', '%PreventionRule "%')
            ->where('description', 'like', '%was deleted%')
            ->where('subject_type', PreventionRule::class)
            ->latest()
            ->first();

        $this->assertNotNull($deletedActivity, 'Observer should log prevention rule deletion');
    }

    // ─── GoldenAnnotationObserver ────────────────────────────────

    public function test_creating_golden_annotation_dispatches_embedding_job(): void
    {
        GoldenAnnotation::factory()->create(['owner_id' => 1]);

        Queue::assertPushed(GenerateEmbeddingJob::class);
    }

    public function test_updating_golden_annotation_dispatches_embedding_job(): void
    {
        $annotation = GoldenAnnotation::factory()->create(['owner_id' => 1]);

        Queue::fake();

        $annotation->update(['annotation_text' => 'Updated annotation text']);

        Queue::assertPushed(GenerateEmbeddingJob::class);
    }

    // ─── GenerationTraceObserver ─────────────────────────────────

    public function test_creating_generation_trace_logs_activity(): void
    {
        $trace = GenerationTrace::factory()->create(['owner_id' => 1]);

        $activity = $this->findObserverActivity(
            GenerationTrace::class,
            '%"'.$trace->entity_name.'"%',
            $trace->id,
        );

        $this->assertNotNull($activity, 'Observer should log trace creation with entity_name');
        $this->assertStringContainsString('was created', $activity->description);
    }

    public function test_deleting_generation_trace_logs_activity(): void
    {
        $trace = GenerationTrace::factory()->create(['owner_id' => 1]);
        $entityName = $trace->entity_name;

        $trace->delete();

        $activity = $this->findObserverActivity(
            GenerationTrace::class,
            '%"'.$entityName.'" was deleted%',
        );

        $this->assertNotNull($activity, 'Observer should log trace deletion with entity_name');
    }

    // ─── FailureReportObserver ───────────────────────────────────

    public function test_creating_failure_report_logs_activity(): void
    {
        $failure = RlmFailure::factory()->create(['owner_id' => 1]);
        $report = FailureReport::factory()->create([
            'rlm_failure_id' => $failure->id,
            'owner_id' => 1,
        ]);

        // HasAuditTrail logs: FailureReport was created
        $this->assertAuditTrailLogged(FailureReport::class, 'created', $report->id);
    }

    public function test_creating_failure_report_increments_parent_report_count(): void
    {
        $failure = RlmFailure::factory()->create([
            'owner_id' => 1,
            'report_count' => 5,
        ]);

        FailureReport::factory()->create([
            'rlm_failure_id' => $failure->id,
            'owner_id' => 1,
        ]);

        $this->assertEquals(6, $failure->fresh()->report_count);
    }

    public function test_creating_failure_report_updates_parent_last_seen_at(): void
    {
        $failure = RlmFailure::factory()->create([
            'owner_id' => 1,
            'last_seen_at' => null,
        ]);

        FailureReport::factory()->create([
            'rlm_failure_id' => $failure->id,
            'owner_id' => 1,
        ]);

        $updatedFailure = $failure->fresh();
        $this->assertNotNull($updatedFailure->last_seen_at);
    }

    public function test_deleting_failure_report_logs_activity(): void
    {
        $failure = RlmFailure::factory()->create(['owner_id' => 1]);
        $report = FailureReport::factory()->create([
            'rlm_failure_id' => $failure->id,
            'owner_id' => 1,
        ]);

        $report->delete();

        // HasAuditTrail logs the soft-delete
        $activity = Activity::where('description', 'like', '%FailureReport was%')
            ->where('subject_type', FailureReport::class)
            ->latest()
            ->first();

        $this->assertNotNull($activity, 'Activity should be logged for failure report deletion');
    }
}
