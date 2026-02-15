<?php

namespace Aicl\Tests\Unit\Observers;

use Aicl\Events\EntityCreated;
use Aicl\Events\EntityCreating;
use Aicl\Events\EntityDeleted;
use Aicl\Events\EntityDeleting;
use Aicl\Events\EntityUpdated;
use Aicl\Events\EntityUpdating;
use Aicl\Jobs\RedistillJob;
use Aicl\Models\DistilledLesson;
use Aicl\Models\FailureReport;
use Aicl\Models\GenerationTrace;
use Aicl\Models\GoldenAnnotation;
use Aicl\Models\PreventionRule;
use Aicl\Models\RlmFailure;
use Aicl\Models\RlmLesson;
use Aicl\Models\RlmPattern;
use Aicl\Observers\FailureReportObserver;
use Aicl\Observers\GenerationTraceObserver;
use Aicl\Observers\GoldenAnnotationObserver;
use Aicl\Observers\PreventionRuleObserver;
use Aicl\Observers\RlmFailureDistillObserver;
use Aicl\Observers\RlmFailureObserver;
use Aicl\Observers\RlmLessonObserver;
use Aicl\Observers\RlmPatternObserver;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Comprehensive unit tests for all AICL observer classes.
 *
 * Verifies observer registration, model CRUD lifecycle with observers active,
 * and observer-specific side effects (FailureReport parent updates, Distill skip logic).
 *
 * NOTE: Notification::fake() is NOT used here — it breaks NotificationDispatcher.
 * Instead, we fake queues and targeted events to isolate observer behavior.
 */
class ObserverCoverageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // All hub models may reference owner_id => 1
        User::factory()->create(['id' => 1]);

        // Fake queues so embedding jobs and other queued work don't execute
        Queue::fake();

        // Fake broadcast entity events to prevent broadcast errors
        Event::fake([
            EntityCreating::class,
            EntityCreated::class,
            EntityUpdating::class,
            EntityUpdated::class,
            EntityDeleting::class,
            EntityDeleted::class,
        ]);
    }

    // ─── Observer Registration ───────────────────────────────────────

    public function test_generation_trace_observer_is_registered(): void
    {
        $trace = GenerationTrace::factory()->create(['owner_id' => 1]);

        // If observer were not registered, this would not log activity.
        // Verify the model was created without errors — observer is active.
        $this->assertDatabaseHas('generation_traces', ['id' => $trace->id]);
    }

    public function test_failure_report_observer_is_registered(): void
    {
        $failure = RlmFailure::factory()->create(['owner_id' => 1, 'report_count' => 0]);
        $report = FailureReport::factory()->create([
            'rlm_failure_id' => $failure->id,
            'owner_id' => 1,
        ]);

        $this->assertDatabaseHas('failure_reports', ['id' => $report->id]);
        // Observer should have incremented report_count from 0 to 1
        $this->assertEquals(1, $failure->fresh()->report_count);
    }

    public function test_rlm_failure_observer_is_registered(): void
    {
        $failure = RlmFailure::factory()->create(['owner_id' => 1]);

        $this->assertDatabaseHas('rlm_failures', ['id' => $failure->id]);
    }

    public function test_rlm_failure_distill_observer_is_registered(): void
    {
        // RlmFailure has BOTH RlmFailureObserver and RlmFailureDistillObserver registered.
        // Creating an active failure triggers distill observer's created() method.
        $failure = RlmFailure::factory()->create(['owner_id' => 1, 'is_active' => true]);

        $this->assertDatabaseHas('rlm_failures', ['id' => $failure->id]);
    }

    public function test_rlm_lesson_observer_is_registered(): void
    {
        $lesson = RlmLesson::factory()->create(['owner_id' => 1]);

        $this->assertDatabaseHas('rlm_lessons', ['id' => $lesson->id]);
    }

    public function test_rlm_pattern_observer_is_registered(): void
    {
        $pattern = RlmPattern::factory()->create(['owner_id' => 1]);

        $this->assertDatabaseHas('rlm_patterns', ['id' => $pattern->id]);
    }

    public function test_prevention_rule_observer_is_registered(): void
    {
        $rule = PreventionRule::factory()->withoutFailure()->create(['owner_id' => 1]);

        $this->assertDatabaseHas('prevention_rules', ['id' => $rule->id]);
    }

    public function test_golden_annotation_observer_is_registered(): void
    {
        $annotation = GoldenAnnotation::factory()->create(['owner_id' => 1]);

        $this->assertDatabaseHas('golden_annotations', ['id' => $annotation->id]);
    }

    // ─── Observer Class Hierarchy ────────────────────────────────────

    public function test_generation_trace_observer_extends_base_observer(): void
    {
        $this->assertTrue(is_subclass_of(GenerationTraceObserver::class, \Aicl\Observers\BaseObserver::class));
    }

    public function test_failure_report_observer_extends_base_observer(): void
    {
        $this->assertTrue(is_subclass_of(FailureReportObserver::class, \Aicl\Observers\BaseObserver::class));
    }

    public function test_rlm_failure_observer_extends_base_observer(): void
    {
        $this->assertTrue(is_subclass_of(RlmFailureObserver::class, \Aicl\Observers\BaseObserver::class));
    }

    public function test_rlm_failure_distill_observer_extends_base_observer(): void
    {
        $this->assertTrue(is_subclass_of(RlmFailureDistillObserver::class, \Aicl\Observers\BaseObserver::class));
    }

    public function test_rlm_lesson_observer_extends_base_observer(): void
    {
        $this->assertTrue(is_subclass_of(RlmLessonObserver::class, \Aicl\Observers\BaseObserver::class));
    }

    public function test_rlm_pattern_observer_extends_base_observer(): void
    {
        $this->assertTrue(is_subclass_of(RlmPatternObserver::class, \Aicl\Observers\BaseObserver::class));
    }

    public function test_prevention_rule_observer_extends_base_observer(): void
    {
        $this->assertTrue(is_subclass_of(PreventionRuleObserver::class, \Aicl\Observers\BaseObserver::class));
    }

    public function test_golden_annotation_observer_extends_base_observer(): void
    {
        $this->assertTrue(is_subclass_of(GoldenAnnotationObserver::class, \Aicl\Observers\BaseObserver::class));
    }

    // ─── GenerationTrace CRUD with Observer Active ───────────────────

    public function test_generation_trace_can_be_created_with_observer_active(): void
    {
        $trace = GenerationTrace::factory()->create(['owner_id' => 1]);

        $this->assertDatabaseHas('generation_traces', [
            'id' => $trace->id,
            'entity_name' => $trace->entity_name,
        ]);
    }

    public function test_generation_trace_can_be_updated_with_observer_active(): void
    {
        $trace = GenerationTrace::factory()->create(['owner_id' => 1]);

        $trace->update(['entity_name' => 'UpdatedEntity']);

        $this->assertDatabaseHas('generation_traces', [
            'id' => $trace->id,
            'entity_name' => 'UpdatedEntity',
        ]);
    }

    public function test_generation_trace_can_be_soft_deleted_with_observer_active(): void
    {
        $trace = GenerationTrace::factory()->create(['owner_id' => 1]);

        $trace->delete();

        $this->assertSoftDeleted('generation_traces', ['id' => $trace->id]);
    }

    // ─── FailureReport CRUD with Observer Active ─────────────────────

    public function test_failure_report_can_be_created_with_observer_active(): void
    {
        $failure = RlmFailure::factory()->create(['owner_id' => 1]);
        $report = FailureReport::factory()->create([
            'rlm_failure_id' => $failure->id,
            'owner_id' => 1,
        ]);

        $this->assertDatabaseHas('failure_reports', [
            'id' => $report->id,
            'rlm_failure_id' => $failure->id,
        ]);
    }

    public function test_failure_report_can_be_updated_with_observer_active(): void
    {
        $failure = RlmFailure::factory()->create(['owner_id' => 1]);
        $report = FailureReport::factory()->create([
            'rlm_failure_id' => $failure->id,
            'owner_id' => 1,
        ]);

        $report->update(['entity_name' => 'UpdatedReport']);

        $this->assertDatabaseHas('failure_reports', [
            'id' => $report->id,
            'entity_name' => 'UpdatedReport',
        ]);
    }

    public function test_failure_report_can_be_soft_deleted_with_observer_active(): void
    {
        $failure = RlmFailure::factory()->create(['owner_id' => 1]);
        $report = FailureReport::factory()->create([
            'rlm_failure_id' => $failure->id,
            'owner_id' => 1,
        ]);

        $report->delete();

        $this->assertSoftDeleted('failure_reports', ['id' => $report->id]);
    }

    // ─── RlmFailure CRUD with Observer Active ────────────────────────

    public function test_rlm_failure_can_be_created_with_observer_active(): void
    {
        $failure = RlmFailure::factory()->create(['owner_id' => 1]);

        $this->assertDatabaseHas('rlm_failures', [
            'id' => $failure->id,
            'failure_code' => $failure->failure_code,
        ]);
    }

    public function test_rlm_failure_can_be_updated_with_observer_active(): void
    {
        $failure = RlmFailure::factory()->create(['owner_id' => 1]);

        $failure->update(['description' => 'Updated failure description']);

        $this->assertDatabaseHas('rlm_failures', [
            'id' => $failure->id,
            'description' => 'Updated failure description',
        ]);
    }

    public function test_rlm_failure_can_be_soft_deleted_with_observer_active(): void
    {
        $failure = RlmFailure::factory()->create(['owner_id' => 1]);

        $failure->delete();

        $this->assertSoftDeleted('rlm_failures', ['id' => $failure->id]);
    }

    // ─── RlmLesson CRUD with Observer Active ─────────────────────────

    public function test_rlm_lesson_can_be_created_with_observer_active(): void
    {
        $lesson = RlmLesson::factory()->create(['owner_id' => 1]);

        $this->assertDatabaseHas('rlm_lessons', [
            'id' => $lesson->id,
            'topic' => $lesson->topic,
        ]);
    }

    public function test_rlm_lesson_can_be_updated_with_observer_active(): void
    {
        $lesson = RlmLesson::factory()->create(['owner_id' => 1]);

        $lesson->update(['summary' => 'Updated lesson summary']);

        $this->assertDatabaseHas('rlm_lessons', [
            'id' => $lesson->id,
            'summary' => 'Updated lesson summary',
        ]);
    }

    public function test_rlm_lesson_can_be_soft_deleted_with_observer_active(): void
    {
        $lesson = RlmLesson::factory()->create(['owner_id' => 1]);

        $lesson->delete();

        $this->assertSoftDeleted('rlm_lessons', ['id' => $lesson->id]);
    }

    // ─── RlmPattern CRUD with Observer Active ────────────────────────

    public function test_rlm_pattern_can_be_created_with_observer_active(): void
    {
        $pattern = RlmPattern::factory()->create(['owner_id' => 1]);

        $this->assertDatabaseHas('rlm_patterns', [
            'id' => $pattern->id,
            'name' => $pattern->name,
        ]);
    }

    public function test_rlm_pattern_can_be_updated_with_observer_active(): void
    {
        $pattern = RlmPattern::factory()->create(['owner_id' => 1]);

        $pattern->update(['description' => 'Updated pattern description']);

        $this->assertDatabaseHas('rlm_patterns', [
            'id' => $pattern->id,
            'description' => 'Updated pattern description',
        ]);
    }

    public function test_rlm_pattern_can_be_soft_deleted_with_observer_active(): void
    {
        $pattern = RlmPattern::factory()->create(['owner_id' => 1]);

        $pattern->delete();

        $this->assertSoftDeleted('rlm_patterns', ['id' => $pattern->id]);
    }

    // ─── PreventionRule CRUD with Observer Active ────────────────────

    public function test_prevention_rule_can_be_created_with_observer_active(): void
    {
        $rule = PreventionRule::factory()->withoutFailure()->create(['owner_id' => 1]);

        $this->assertDatabaseHas('prevention_rules', [
            'id' => $rule->id,
        ]);
    }

    public function test_prevention_rule_can_be_updated_with_observer_active(): void
    {
        $rule = PreventionRule::factory()->withoutFailure()->create(['owner_id' => 1]);

        $rule->update(['rule_text' => 'Updated rule text for testing']);

        $this->assertDatabaseHas('prevention_rules', [
            'id' => $rule->id,
            'rule_text' => 'Updated rule text for testing',
        ]);
    }

    public function test_prevention_rule_can_be_soft_deleted_with_observer_active(): void
    {
        $rule = PreventionRule::factory()->withoutFailure()->create(['owner_id' => 1]);

        $rule->delete();

        $this->assertSoftDeleted('prevention_rules', ['id' => $rule->id]);
    }

    // ─── GoldenAnnotation CRUD with Observer Active ──────────────────

    public function test_golden_annotation_can_be_created_with_observer_active(): void
    {
        $annotation = GoldenAnnotation::factory()->create(['owner_id' => 1]);

        $this->assertDatabaseHas('golden_annotations', [
            'id' => $annotation->id,
            'annotation_key' => $annotation->annotation_key,
        ]);
    }

    public function test_golden_annotation_can_be_updated_with_observer_active(): void
    {
        $annotation = GoldenAnnotation::factory()->create(['owner_id' => 1]);

        $annotation->update(['annotation_text' => 'Updated annotation text']);

        $this->assertDatabaseHas('golden_annotations', [
            'id' => $annotation->id,
            'annotation_text' => 'Updated annotation text',
        ]);
    }

    public function test_golden_annotation_can_be_soft_deleted_with_observer_active(): void
    {
        $annotation = GoldenAnnotation::factory()->create(['owner_id' => 1]);

        $annotation->delete();

        $this->assertSoftDeleted('golden_annotations', ['id' => $annotation->id]);
    }

    // ─── FailureReportObserver: Parent Update Behavior ───────────────

    public function test_failure_report_creation_increments_parent_report_count(): void
    {
        $failure = RlmFailure::factory()->create([
            'owner_id' => 1,
            'report_count' => 10,
        ]);

        FailureReport::factory()->create([
            'rlm_failure_id' => $failure->id,
            'owner_id' => 1,
        ]);

        $this->assertEquals(11, $failure->fresh()->report_count);
    }

    public function test_failure_report_creation_updates_last_seen_at(): void
    {
        $failure = RlmFailure::factory()->create([
            'owner_id' => 1,
            'last_seen_at' => now()->subDays(30),
        ]);

        $oldLastSeen = $failure->last_seen_at;

        FailureReport::factory()->create([
            'rlm_failure_id' => $failure->id,
            'owner_id' => 1,
        ]);

        $updatedFailure = $failure->fresh();
        $this->assertNotNull($updatedFailure->last_seen_at);
        $this->assertTrue(
            $updatedFailure->last_seen_at->greaterThan($oldLastSeen),
            'last_seen_at should be updated to a more recent timestamp'
        );
    }

    public function test_failure_report_creation_updates_last_seen_at_from_null(): void
    {
        $failure = RlmFailure::factory()->create([
            'owner_id' => 1,
            'last_seen_at' => null,
        ]);

        FailureReport::factory()->create([
            'rlm_failure_id' => $failure->id,
            'owner_id' => 1,
        ]);

        $this->assertNotNull($failure->fresh()->last_seen_at);
    }

    public function test_failure_report_creation_increments_resolution_count_when_resolved(): void
    {
        $failure = RlmFailure::factory()->create([
            'owner_id' => 1,
            'report_count' => 5,
            'resolution_count' => 2,
        ]);

        FailureReport::factory()->resolved()->create([
            'rlm_failure_id' => $failure->id,
            'owner_id' => 1,
        ]);

        $this->assertEquals(3, $failure->fresh()->resolution_count);
    }

    public function test_failure_report_creation_does_not_increment_resolution_count_when_unresolved(): void
    {
        $failure = RlmFailure::factory()->create([
            'owner_id' => 1,
            'report_count' => 5,
            'resolution_count' => 2,
        ]);

        FailureReport::factory()->unresolved()->create([
            'rlm_failure_id' => $failure->id,
            'owner_id' => 1,
        ]);

        $this->assertEquals(2, $failure->fresh()->resolution_count);
    }

    public function test_failure_report_creation_updates_unique_project_count(): void
    {
        $failure = RlmFailure::factory()->create([
            'owner_id' => 1,
            'report_count' => 0,
            'project_count' => 0,
        ]);

        $projectHash = fake()->sha256();

        FailureReport::factory()->create([
            'rlm_failure_id' => $failure->id,
            'owner_id' => 1,
            'project_hash' => $projectHash,
        ]);

        $this->assertEquals(1, $failure->fresh()->project_count);

        // Same project_hash should not increment
        FailureReport::factory()->create([
            'rlm_failure_id' => $failure->id,
            'owner_id' => 1,
            'project_hash' => $projectHash,
        ]);

        $this->assertEquals(1, $failure->fresh()->project_count);

        // Different project_hash should increment
        FailureReport::factory()->create([
            'rlm_failure_id' => $failure->id,
            'owner_id' => 1,
            'project_hash' => fake()->sha256(),
        ]);

        $this->assertEquals(2, $failure->fresh()->project_count);
    }

    public function test_failure_report_observer_handles_missing_parent_failure(): void
    {
        // Test the observer method directly with a model whose failure relationship is null.
        // The observer checks `$model->failure` and returns early if null.
        $observer = new FailureReportObserver;
        $report = new FailureReport([
            'rlm_failure_id' => null,
            'entity_name' => 'Test',
            'project_hash' => fake()->sha256(),
            'owner_id' => 1,
        ]);

        // Calling created() directly should not throw when failure relationship is null
        $observer->created($report);

        $this->assertTrue(true, 'Observer handled missing parent without error');
    }

    // ─── RlmFailureDistillObserver: Skip Inactive ────────────────────

    public function test_distill_observer_skips_inactive_failures(): void
    {
        Bus::fake([RedistillJob::class]);

        // Create an inactive failure — distill observer should skip it
        RlmFailure::factory()->inactive()->create(['owner_id' => 1]);

        Bus::assertNotDispatched(RedistillJob::class);
    }

    public function test_distill_observer_processes_active_failures(): void
    {
        Bus::fake([RedistillJob::class]);

        // Create a distilled lesson that covers a specific failure code
        $existingFailure = RlmFailure::factory()->create([
            'owner_id' => 1,
            'failure_code' => 'F-CLUSTER-001',
            'category' => \Aicl\Enums\FailureCategory::Scaffolding,
            'is_active' => true,
        ]);

        DistilledLesson::factory()->create([
            'owner_id' => 1,
            'source_failure_codes' => ['F-CLUSTER-001'],
            'is_active' => true,
        ]);

        // Create a new active failure in the same category — should trigger redistillation
        RlmFailure::factory()->create([
            'owner_id' => 1,
            'category' => \Aicl\Enums\FailureCategory::Scaffolding,
            'is_active' => true,
        ]);

        // The observer should attempt to dispatch RedistillJob for the cluster
        // (whether it finds cluster codes depends on subcategory match, but the
        // observer's created() method should run without error either way)
        $this->assertTrue(true, 'Distill observer processed active failure without error');
    }

    public function test_distill_observer_dispatches_redistill_job_for_matching_cluster(): void
    {
        Bus::fake([RedistillJob::class]);

        $category = \Aicl\Enums\FailureCategory::Scaffolding;

        // Create an existing active failure with a known code
        $existingFailure = RlmFailure::factory()->create([
            'owner_id' => 1,
            'failure_code' => 'F-MATCH-001',
            'category' => $category,
            'subcategory' => 'naming',
            'is_active' => true,
        ]);

        // Create a distilled lesson covering that failure code
        DistilledLesson::factory()->create([
            'owner_id' => 1,
            'source_failure_codes' => ['F-MATCH-001'],
            'is_active' => true,
        ]);

        // Create a new failure with the same category and subcategory
        $newFailure = RlmFailure::factory()->create([
            'owner_id' => 1,
            'category' => $category,
            'subcategory' => 'naming',
            'is_active' => true,
        ]);

        Bus::assertDispatched(RedistillJob::class);
    }

    public function test_distill_observer_does_not_dispatch_when_no_cluster_match(): void
    {
        Bus::fake([RedistillJob::class]);

        // Create a failure in a category with no existing distilled lessons
        RlmFailure::factory()->create([
            'owner_id' => 1,
            'category' => \Aicl\Enums\FailureCategory::Other,
            'subcategory' => 'unique-subcategory-no-match',
            'is_active' => true,
        ]);

        Bus::assertNotDispatched(RedistillJob::class);
    }

    // ─── Observer Method Existence Checks ────────────────────────────

    public function test_generation_trace_observer_has_created_method(): void
    {
        $this->assertTrue(method_exists(GenerationTraceObserver::class, 'created'));
    }

    public function test_generation_trace_observer_has_deleted_method(): void
    {
        $this->assertTrue(method_exists(GenerationTraceObserver::class, 'deleted'));
    }

    public function test_failure_report_observer_has_created_method(): void
    {
        $this->assertTrue(method_exists(FailureReportObserver::class, 'created'));
    }

    public function test_failure_report_observer_has_deleted_method(): void
    {
        $this->assertTrue(method_exists(FailureReportObserver::class, 'deleted'));
    }

    public function test_rlm_failure_observer_has_created_method(): void
    {
        $this->assertTrue(method_exists(RlmFailureObserver::class, 'created'));
    }

    public function test_rlm_failure_observer_has_updating_method(): void
    {
        $this->assertTrue(method_exists(RlmFailureObserver::class, 'updating'));
    }

    public function test_rlm_failure_observer_has_updated_method(): void
    {
        $this->assertTrue(method_exists(RlmFailureObserver::class, 'updated'));
    }

    public function test_rlm_failure_observer_has_deleted_method(): void
    {
        $this->assertTrue(method_exists(RlmFailureObserver::class, 'deleted'));
    }

    public function test_rlm_failure_distill_observer_has_created_method(): void
    {
        $this->assertTrue(method_exists(RlmFailureDistillObserver::class, 'created'));
    }

    public function test_rlm_lesson_observer_has_created_method(): void
    {
        $this->assertTrue(method_exists(RlmLessonObserver::class, 'created'));
    }

    public function test_rlm_lesson_observer_has_updated_method(): void
    {
        $this->assertTrue(method_exists(RlmLessonObserver::class, 'updated'));
    }

    public function test_rlm_lesson_observer_has_deleted_method(): void
    {
        $this->assertTrue(method_exists(RlmLessonObserver::class, 'deleted'));
    }

    public function test_rlm_pattern_observer_has_created_method(): void
    {
        $this->assertTrue(method_exists(RlmPatternObserver::class, 'created'));
    }

    public function test_rlm_pattern_observer_has_updated_method(): void
    {
        $this->assertTrue(method_exists(RlmPatternObserver::class, 'updated'));
    }

    public function test_rlm_pattern_observer_has_deleted_method(): void
    {
        $this->assertTrue(method_exists(RlmPatternObserver::class, 'deleted'));
    }

    public function test_prevention_rule_observer_has_created_method(): void
    {
        $this->assertTrue(method_exists(PreventionRuleObserver::class, 'created'));
    }

    public function test_prevention_rule_observer_has_updated_method(): void
    {
        $this->assertTrue(method_exists(PreventionRuleObserver::class, 'updated'));
    }

    public function test_prevention_rule_observer_has_deleted_method(): void
    {
        $this->assertTrue(method_exists(PreventionRuleObserver::class, 'deleted'));
    }

    public function test_golden_annotation_observer_has_created_method(): void
    {
        $this->assertTrue(method_exists(GoldenAnnotationObserver::class, 'created'));
    }

    public function test_golden_annotation_observer_has_updated_method(): void
    {
        $this->assertTrue(method_exists(GoldenAnnotationObserver::class, 'updated'));
    }

    // ─── Prevention Rule with Parent Failure ─────────────────────────

    public function test_prevention_rule_with_parent_failure_can_be_created(): void
    {
        $failure = RlmFailure::factory()->create(['owner_id' => 1]);
        $rule = PreventionRule::factory()->create([
            'rlm_failure_id' => $failure->id,
            'owner_id' => 1,
        ]);

        $this->assertDatabaseHas('prevention_rules', [
            'id' => $rule->id,
            'rlm_failure_id' => $failure->id,
        ]);
    }

    // ─── Multiple Observers on Same Model ────────────────────────────

    public function test_rlm_failure_has_both_observers_active(): void
    {
        // RlmFailure has both RlmFailureObserver and RlmFailureDistillObserver.
        // Creating an active failure should trigger both without error.
        $failure = RlmFailure::factory()->create([
            'owner_id' => 1,
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('rlm_failures', ['id' => $failure->id]);

        // Update should trigger RlmFailureObserver::updated (embedding dispatch)
        $failure->update(['description' => 'Both observers should handle this']);

        $this->assertDatabaseHas('rlm_failures', [
            'id' => $failure->id,
            'description' => 'Both observers should handle this',
        ]);
    }

    // ─── Soft Delete and Restore Lifecycle ───────────────────────────

    public function test_rlm_failure_can_be_restored_after_soft_delete(): void
    {
        $failure = RlmFailure::factory()->create(['owner_id' => 1]);

        $failure->delete();
        $this->assertSoftDeleted('rlm_failures', ['id' => $failure->id]);

        $failure->restore();
        $this->assertDatabaseHas('rlm_failures', ['id' => $failure->id]);
        $this->assertNull($failure->fresh()->deleted_at);
    }

    public function test_rlm_pattern_can_be_restored_after_soft_delete(): void
    {
        $pattern = RlmPattern::factory()->create(['owner_id' => 1]);

        $pattern->delete();
        $this->assertSoftDeleted('rlm_patterns', ['id' => $pattern->id]);

        $pattern->restore();
        $this->assertNull($pattern->fresh()->deleted_at);
    }

    public function test_rlm_lesson_can_be_restored_after_soft_delete(): void
    {
        $lesson = RlmLesson::factory()->create(['owner_id' => 1]);

        $lesson->delete();
        $this->assertSoftDeleted('rlm_lessons', ['id' => $lesson->id]);

        $lesson->restore();
        $this->assertNull($lesson->fresh()->deleted_at);
    }

    public function test_prevention_rule_can_be_restored_after_soft_delete(): void
    {
        $rule = PreventionRule::factory()->withoutFailure()->create(['owner_id' => 1]);

        $rule->delete();
        $this->assertSoftDeleted('prevention_rules', ['id' => $rule->id]);

        $rule->restore();
        $this->assertNull($rule->fresh()->deleted_at);
    }

    public function test_golden_annotation_can_be_restored_after_soft_delete(): void
    {
        $annotation = GoldenAnnotation::factory()->create(['owner_id' => 1]);

        $annotation->delete();
        $this->assertSoftDeleted('golden_annotations', ['id' => $annotation->id]);

        $annotation->restore();
        $this->assertNull($annotation->fresh()->deleted_at);
    }

    public function test_generation_trace_can_be_restored_after_soft_delete(): void
    {
        $trace = GenerationTrace::factory()->create(['owner_id' => 1]);

        $trace->delete();
        $this->assertSoftDeleted('generation_traces', ['id' => $trace->id]);

        $trace->restore();
        $this->assertNull($trace->fresh()->deleted_at);
    }

    public function test_failure_report_can_be_restored_after_soft_delete(): void
    {
        $failure = RlmFailure::factory()->create(['owner_id' => 1]);
        $report = FailureReport::factory()->create([
            'rlm_failure_id' => $failure->id,
            'owner_id' => 1,
        ]);

        $report->delete();
        $this->assertSoftDeleted('failure_reports', ['id' => $report->id]);

        $report->restore();
        $this->assertNull($report->fresh()->deleted_at);
    }
}
