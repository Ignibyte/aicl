<?php

namespace Aicl\Tests\Unit\Models;

use Aicl\Enums\AnnotationCategory;
use Aicl\Enums\FailureCategory;
use Aicl\Enums\FailureSeverity;
use Aicl\Enums\KnowledgeLinkRelationship;
use Aicl\Enums\ResolutionMethod;
use Aicl\Enums\ScoreType;
use Aicl\Events\Enums\ActorType;
use Aicl\Models\DomainEventRecord;
use Aicl\Models\FailureReport;
use Aicl\Models\GenerationTrace;
use Aicl\Models\GoldenAnnotation;
use Aicl\Models\KnowledgeLink;
use Aicl\Models\NotificationLog;
use Aicl\Models\PreventionRule;
use Aicl\Models\RlmFailure;
use Aicl\Models\RlmLesson;
use Aicl\Models\RlmScore;
use Aicl\Models\RlmSemanticCache;
use Aicl\States\RlmFailureState;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class HubModelCoverageTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected RlmFailure $failure;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake([
            \Aicl\Events\EntityCreated::class,
            \Aicl\Events\EntityCreating::class,
            \Aicl\Events\EntityUpdated::class,
            \Aicl\Events\EntityUpdating::class,
            \Aicl\Events\EntityDeleted::class,
            \Aicl\Events\EntityDeleting::class,
        ]);
        Queue::fake();

        $this->user = User::factory()->create(['id' => 1]);

        // Advance PG sequence past the explicitly-inserted id=1 so nested
        // factories that call User::factory() without an explicit id don't collide.
        \Illuminate\Support\Facades\DB::statement("SELECT setval(pg_get_serial_sequence('users', 'id'), (SELECT MAX(id) FROM users))");

        $this->failure = RlmFailure::factory()->promoted()->create(['owner_id' => $this->user->id]);
    }

    // =========================================================================
    // FailureReport
    // =========================================================================

    public function test_failure_report_factory_creates_valid_model(): void
    {
        $report = FailureReport::factory()->create([
            'rlm_failure_id' => $this->failure->id,
            'owner_id' => $this->user->id,
        ]);

        $this->assertNotNull($report->id);
        $this->assertDatabaseHas('failure_reports', ['id' => $report->id]);
    }

    public function test_failure_report_failure_relationship(): void
    {
        $failure = RlmFailure::factory()->create(['owner_id' => $this->user->id]);
        $report = FailureReport::factory()->create([
            'rlm_failure_id' => $failure->id,
            'owner_id' => $this->user->id,
        ]);

        $this->assertInstanceOf(BelongsTo::class, $report->failure());
        $this->assertEquals($failure->id, $report->failure->id);
    }

    public function test_failure_report_rlm_failure_alias(): void
    {
        $failure = RlmFailure::factory()->create(['owner_id' => $this->user->id]);
        $report = FailureReport::factory()->create([
            'rlm_failure_id' => $failure->id,
            'owner_id' => $this->user->id,
        ]);

        $this->assertEquals($failure->id, $report->rlmFailure->id);
    }

    public function test_failure_report_owner_relationship(): void
    {
        $report = FailureReport::factory()->create(['owner_id' => $this->user->id]);

        $this->assertInstanceOf(BelongsTo::class, $report->owner());
        $this->assertEquals($this->user->id, $report->owner->id);
    }

    public function test_failure_report_scope_resolved(): void
    {
        FailureReport::factory()->resolved()->create(['owner_id' => $this->user->id]);
        FailureReport::factory()->unresolved()->create(['owner_id' => $this->user->id]);

        $this->assertSame(1, FailureReport::resolved()->count());
    }

    public function test_failure_report_scope_unresolved(): void
    {
        FailureReport::factory()->resolved()->create(['owner_id' => $this->user->id]);
        FailureReport::factory()->unresolved()->create(['owner_id' => $this->user->id]);

        $this->assertSame(1, FailureReport::unresolved()->count());
    }

    public function test_failure_report_scope_by_project(): void
    {
        FailureReport::factory()->create([
            'project_hash' => 'abc123',
            'owner_id' => $this->user->id,
        ]);
        FailureReport::factory()->create([
            'project_hash' => 'xyz789',
            'owner_id' => $this->user->id,
        ]);

        $this->assertSame(1, FailureReport::byProject('abc123')->count());
    }

    public function test_failure_report_scope_by_phase(): void
    {
        FailureReport::factory()->create([
            'phase' => 'Phase 3: Generate',
            'owner_id' => $this->user->id,
        ]);
        FailureReport::factory()->create([
            'phase' => 'Phase 4: Validate',
            'owner_id' => $this->user->id,
        ]);

        $this->assertSame(1, FailureReport::byPhase('Phase 3: Generate')->count());
    }

    public function test_failure_report_scope_by_agent(): void
    {
        FailureReport::factory()->create([
            'agent' => '/architect',
            'owner_id' => $this->user->id,
        ]);
        FailureReport::factory()->create([
            'agent' => '/tester',
            'owner_id' => $this->user->id,
        ]);

        $this->assertSame(1, FailureReport::byAgent('/architect')->count());
    }

    public function test_failure_report_casts(): void
    {
        $report = FailureReport::factory()->resolved()->create([
            'scaffolder_args' => ['fields' => 'name:string'],
            'owner_id' => $this->user->id,
        ]);
        $report->refresh();

        $this->assertIsArray($report->scaffolder_args);
        $this->assertIsBool($report->resolved);
        $this->assertInstanceOf(ResolutionMethod::class, $report->resolution_method);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $report->reported_at);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $report->resolved_at);
    }

    public function test_failure_report_soft_deletes(): void
    {
        $report = FailureReport::factory()->create(['owner_id' => $this->user->id]);
        $report->delete();

        $this->assertSame(0, FailureReport::query()->count());
        $this->assertSame(1, FailureReport::withTrashed()->count());
    }

    public function test_failure_report_searchable_columns(): void
    {
        $report = new FailureReport;
        $method = new \ReflectionMethod($report, 'searchableColumns');

        $columns = $method->invoke($report);

        $this->assertContains('entity_name', $columns);
        $this->assertContains('project_hash', $columns);
        $this->assertContains('phase', $columns);
        $this->assertContains('agent', $columns);
    }

    // =========================================================================
    // RlmFailure
    // =========================================================================

    public function test_rlm_failure_factory_creates_valid_model(): void
    {
        $failure = RlmFailure::factory()->create(['owner_id' => $this->user->id]);

        $this->assertNotNull($failure->id);
        $this->assertDatabaseHas('rlm_failures', ['id' => $failure->id]);
    }

    public function test_rlm_failure_owner_relationship(): void
    {
        $failure = RlmFailure::factory()->create(['owner_id' => $this->user->id]);

        $this->assertInstanceOf(BelongsTo::class, $failure->owner());
        $this->assertEquals($this->user->id, $failure->owner->id);
    }

    public function test_rlm_failure_reports_relationship(): void
    {
        $failure = RlmFailure::factory()->create(['owner_id' => $this->user->id]);
        FailureReport::factory()->count(2)->create([
            'rlm_failure_id' => $failure->id,
            'owner_id' => $this->user->id,
        ]);

        $this->assertInstanceOf(HasMany::class, $failure->reports());
        $this->assertCount(2, $failure->reports);
    }

    public function test_rlm_failure_prevention_rules_relationship(): void
    {
        $failure = RlmFailure::factory()->create(['owner_id' => $this->user->id]);
        PreventionRule::factory()->count(3)->create([
            'rlm_failure_id' => $failure->id,
            'owner_id' => $this->user->id,
        ]);

        $this->assertInstanceOf(HasMany::class, $failure->preventionRules());
        $this->assertCount(3, $failure->preventionRules);
    }

    public function test_rlm_failure_computed_resolution_rate_with_reports(): void
    {
        $failure = RlmFailure::factory()->create([
            'report_count' => 10,
            'resolution_count' => 7,
            'owner_id' => $this->user->id,
        ]);

        $this->assertEquals(0.7, $failure->computed_resolution_rate);
    }

    public function test_rlm_failure_computed_resolution_rate_zero_reports(): void
    {
        $failure = RlmFailure::factory()->create([
            'report_count' => 0,
            'resolution_count' => 0,
            'owner_id' => $this->user->id,
        ]);

        $this->assertEquals(0.0, $failure->computed_resolution_rate);
    }

    public function test_rlm_failure_scope_promotable(): void
    {
        RlmFailure::factory()->promotable()->create(['owner_id' => $this->user->id]);
        RlmFailure::factory()->create([
            'report_count' => 1,
            'project_count' => 1,
            'promoted_to_base' => false,
            'owner_id' => $this->user->id,
        ]);
        RlmFailure::factory()->promoted()->create([
            'report_count' => 5,
            'project_count' => 3,
            'owner_id' => $this->user->id,
        ]);

        $results = RlmFailure::promotable()->get();
        $this->assertSame(1, $results->count());
    }

    public function test_rlm_failure_scope_by_entity_context(): void
    {
        RlmFailure::factory()->create([
            'entity_context' => ['has_states' => true, 'field_types' => ['enum']],
            'owner_id' => $this->user->id,
        ]);
        RlmFailure::factory()->create([
            'entity_context' => ['has_uuid' => true],
            'owner_id' => $this->user->id,
        ]);

        $results = RlmFailure::byEntityContext(['has_states' => true])->get();
        $this->assertSame(1, $results->count());
    }

    public function test_rlm_failure_casts(): void
    {
        $failure = RlmFailure::factory()->create([
            'category' => FailureCategory::Scaffolding,
            'severity' => FailureSeverity::High,
            'entity_context' => ['test' => true],
            'owner_id' => $this->user->id,
        ]);
        $failure->refresh();

        $this->assertInstanceOf(FailureCategory::class, $failure->category);
        $this->assertInstanceOf(FailureSeverity::class, $failure->severity);
        $this->assertIsArray($failure->entity_context);
        $this->assertIsBool($failure->scaffolding_fixed);
        $this->assertIsBool($failure->is_active);
        $this->assertIsBool($failure->promoted_to_base);
        $this->assertInstanceOf(RlmFailureState::class, $failure->status);
    }

    public function test_rlm_failure_embedding_text(): void
    {
        $failure = RlmFailure::factory()->create([
            'title' => 'Test Failure',
            'description' => 'A description',
            'root_cause' => 'The root cause',
            'preventive_rule' => 'Prevent this',
            'owner_id' => $this->user->id,
        ]);

        $text = $failure->embeddingText();

        $this->assertStringContainsString('Test Failure', $text);
        $this->assertStringContainsString('A description', $text);
        $this->assertStringContainsString('The root cause', $text);
        $this->assertStringContainsString('Prevent this', $text);
    }

    public function test_rlm_failure_to_searchable_array(): void
    {
        $failure = RlmFailure::factory()->create([
            'failure_code' => 'F-999',
            'title' => 'Search Test',
            'owner_id' => $this->user->id,
        ]);

        $array = $failure->toSearchableArray();

        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('failure_code', $array);
        $this->assertArrayHasKey('category', $array);
        $this->assertArrayHasKey('severity', $array);
        $this->assertArrayHasKey('title', $array);
        $this->assertArrayHasKey('description', $array);
        $this->assertArrayHasKey('is_active', $array);
        $this->assertEquals('F-999', $array['failure_code']);
    }

    public function test_rlm_failure_searchable_as(): void
    {
        $failure = RlmFailure::factory()->create(['owner_id' => $this->user->id]);

        $this->assertSame('aicl_rlm_failures', $failure->searchableAs());
    }

    public function test_rlm_failure_should_be_searchable(): void
    {
        $failure = RlmFailure::factory()->create(['owner_id' => $this->user->id]);

        $this->assertTrue($failure->shouldBeSearchable());
    }

    public function test_rlm_failure_trashed_not_searchable(): void
    {
        $failure = RlmFailure::factory()->create(['owner_id' => $this->user->id]);
        $failure->delete();

        $this->assertFalse($failure->shouldBeSearchable());
    }

    public function test_rlm_failure_soft_deletes(): void
    {
        $failure = RlmFailure::factory()->create(['owner_id' => $this->user->id]);
        $initialCount = RlmFailure::query()->count();
        $failure->delete();

        $this->assertSame($initialCount - 1, RlmFailure::query()->count());
        $this->assertSoftDeleted('rlm_failures', ['id' => $failure->id]);
    }

    // =========================================================================
    // RlmLesson
    // =========================================================================

    public function test_rlm_lesson_factory_creates_valid_model(): void
    {
        $lesson = RlmLesson::factory()->create(['owner_id' => $this->user->id]);

        $this->assertNotNull($lesson->id);
        $this->assertDatabaseHas('rlm_lessons', ['id' => $lesson->id]);
    }

    public function test_rlm_lesson_owner_relationship(): void
    {
        $lesson = RlmLesson::factory()->create(['owner_id' => $this->user->id]);

        $this->assertInstanceOf(BelongsTo::class, $lesson->owner());
        $this->assertEquals($this->user->id, $lesson->owner->id);
    }

    public function test_rlm_lesson_scope_verified(): void
    {
        RlmLesson::factory()->verified()->create(['owner_id' => $this->user->id]);
        RlmLesson::factory()->create(['is_verified' => false, 'owner_id' => $this->user->id]);

        $this->assertSame(1, RlmLesson::verified()->count());
    }

    public function test_rlm_lesson_scope_unverified(): void
    {
        RlmLesson::factory()->verified()->create(['owner_id' => $this->user->id]);
        RlmLesson::factory()->create(['is_verified' => false, 'owner_id' => $this->user->id]);

        $this->assertSame(1, RlmLesson::unverified()->count());
    }

    public function test_rlm_lesson_scope_by_topic(): void
    {
        RlmLesson::factory()->create(['topic' => 'Testing', 'owner_id' => $this->user->id]);
        RlmLesson::factory()->create(['topic' => 'Filament', 'owner_id' => $this->user->id]);

        $this->assertSame(1, RlmLesson::byTopic('Testing')->count());
    }

    public function test_rlm_lesson_scope_by_context_tag(): void
    {
        RlmLesson::factory()->create([
            'context_tags' => ['entity', 'entity:states'],
            'owner_id' => $this->user->id,
        ]);
        RlmLesson::factory()->create([
            'context_tags' => ['service'],
            'owner_id' => $this->user->id,
        ]);

        $this->assertSame(1, RlmLesson::byContextTag('entity')->count());
    }

    public function test_rlm_lesson_casts(): void
    {
        $lesson = RlmLesson::factory()->create([
            'context_tags' => ['test'],
            'confidence' => 0.85,
            'owner_id' => $this->user->id,
        ]);
        $lesson->refresh();

        $this->assertIsArray($lesson->context_tags);
        $this->assertIsBool($lesson->is_verified);
        $this->assertIsBool($lesson->is_active);
        $this->assertIsInt($lesson->view_count);
    }

    public function test_rlm_lesson_embedding_text(): void
    {
        $lesson = RlmLesson::factory()->create([
            'summary' => 'Summary text',
            'detail' => 'Detail text',
            'tags' => 'tag1 tag2',
            'owner_id' => $this->user->id,
        ]);

        $text = $lesson->embeddingText();

        $this->assertStringContainsString('Summary text', $text);
        $this->assertStringContainsString('Detail text', $text);
        $this->assertStringContainsString('tag1 tag2', $text);
    }

    public function test_rlm_lesson_to_searchable_array(): void
    {
        $lesson = RlmLesson::factory()->create([
            'topic' => 'PostgreSQL',
            'summary' => 'Use LOWER for case-insensitive search',
            'owner_id' => $this->user->id,
        ]);

        $array = $lesson->toSearchableArray();

        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('topic', $array);
        $this->assertArrayHasKey('summary', $array);
        $this->assertArrayHasKey('detail', $array);
        $this->assertArrayHasKey('confidence', $array);
        $this->assertArrayHasKey('is_verified', $array);
        $this->assertArrayHasKey('is_active', $array);
        $this->assertEquals('PostgreSQL', $array['topic']);
    }

    public function test_rlm_lesson_searchable_as(): void
    {
        $lesson = RlmLesson::factory()->create(['owner_id' => $this->user->id]);

        $this->assertSame('aicl_rlm_lessons', $lesson->searchableAs());
    }

    public function test_rlm_lesson_should_be_searchable(): void
    {
        $lesson = RlmLesson::factory()->create(['owner_id' => $this->user->id]);

        $this->assertTrue($lesson->shouldBeSearchable());
    }

    public function test_rlm_lesson_trashed_not_searchable(): void
    {
        $lesson = RlmLesson::factory()->create(['owner_id' => $this->user->id]);
        $lesson->delete();

        $this->assertFalse($lesson->shouldBeSearchable());
    }

    public function test_rlm_lesson_soft_deletes(): void
    {
        $lesson = RlmLesson::factory()->create(['owner_id' => $this->user->id]);
        $lesson->delete();

        $this->assertSame(0, RlmLesson::query()->count());
        $this->assertSame(1, RlmLesson::withTrashed()->count());
    }

    // =========================================================================
    // PreventionRule
    // =========================================================================

    public function test_prevention_rule_factory_creates_valid_model(): void
    {
        $rule = PreventionRule::factory()->create(['owner_id' => $this->user->id]);

        $this->assertNotNull($rule->id);
        $this->assertDatabaseHas('prevention_rules', ['id' => $rule->id]);
    }

    public function test_prevention_rule_failure_relationship(): void
    {
        $failure = RlmFailure::factory()->create(['owner_id' => $this->user->id]);
        $rule = PreventionRule::factory()->create([
            'rlm_failure_id' => $failure->id,
            'owner_id' => $this->user->id,
        ]);

        $this->assertInstanceOf(BelongsTo::class, $rule->failure());
        $this->assertEquals($failure->id, $rule->failure->id);
    }

    public function test_prevention_rule_rlm_failure_alias(): void
    {
        $failure = RlmFailure::factory()->create(['owner_id' => $this->user->id]);
        $rule = PreventionRule::factory()->create([
            'rlm_failure_id' => $failure->id,
            'owner_id' => $this->user->id,
        ]);

        $this->assertEquals($failure->id, $rule->rlmFailure->id);
    }

    public function test_prevention_rule_owner_relationship(): void
    {
        $rule = PreventionRule::factory()->create(['owner_id' => $this->user->id]);

        $this->assertInstanceOf(BelongsTo::class, $rule->owner());
        $this->assertEquals($this->user->id, $rule->owner->id);
    }

    public function test_prevention_rule_scope_for_context(): void
    {
        PreventionRule::factory()->create([
            'trigger_context' => ['has_states' => true],
            'owner_id' => $this->user->id,
        ]);
        PreventionRule::factory()->create([
            'trigger_context' => ['has_enums' => true],
            'owner_id' => $this->user->id,
        ]);

        $results = PreventionRule::forContext(['has_states' => true])->get();
        $this->assertSame(1, $results->count());
    }

    public function test_prevention_rule_scope_high_confidence_default_threshold(): void
    {
        PreventionRule::factory()->create([
            'confidence' => 0.90,
            'owner_id' => $this->user->id,
        ]);
        PreventionRule::factory()->create([
            'confidence' => 0.50,
            'owner_id' => $this->user->id,
        ]);

        $this->assertSame(1, PreventionRule::highConfidence()->count());
    }

    public function test_prevention_rule_scope_high_confidence_custom_threshold(): void
    {
        PreventionRule::factory()->create([
            'confidence' => 0.90,
            'owner_id' => $this->user->id,
        ]);
        PreventionRule::factory()->create([
            'confidence' => 0.50,
            'owner_id' => $this->user->id,
        ]);

        $this->assertSame(2, PreventionRule::highConfidence(0.4)->count());
    }

    public function test_prevention_rule_casts(): void
    {
        $rule = PreventionRule::factory()->create([
            'trigger_context' => ['key' => 'value'],
            'owner_id' => $this->user->id,
        ]);
        $rule->refresh();

        $this->assertIsArray($rule->trigger_context);
        $this->assertIsBool($rule->is_active);
        $this->assertIsInt($rule->applied_count);
        $this->assertIsInt($rule->priority);
    }

    public function test_prevention_rule_embedding_text(): void
    {
        $rule = PreventionRule::factory()->create([
            'rule_text' => 'Always override searchableColumns',
            'trigger_context' => ['has_uuid' => true],
            'owner_id' => $this->user->id,
        ]);

        $text = $rule->embeddingText();

        $this->assertStringContainsString('Always override searchableColumns', $text);
        $this->assertStringContainsString('has_uuid', $text);
    }

    public function test_prevention_rule_embedding_text_without_context(): void
    {
        $rule = PreventionRule::factory()->withoutFailure()->create([
            'rule_text' => 'Simple rule text',
            'trigger_context' => null,
            'owner_id' => $this->user->id,
        ]);

        $text = $rule->embeddingText();

        $this->assertSame('Simple rule text', $text);
    }

    public function test_prevention_rule_to_searchable_array(): void
    {
        $rule = PreventionRule::factory()->create([
            'rule_text' => 'Test rule',
            'confidence' => 0.85,
            'owner_id' => $this->user->id,
        ]);

        $array = $rule->toSearchableArray();

        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('rule_text', $array);
        $this->assertArrayHasKey('confidence', $array);
        $this->assertArrayHasKey('priority', $array);
        $this->assertArrayHasKey('is_active', $array);
        $this->assertEquals('Test rule', $array['rule_text']);
    }

    public function test_prevention_rule_searchable_as(): void
    {
        $rule = PreventionRule::factory()->create(['owner_id' => $this->user->id]);

        $this->assertSame('aicl_prevention_rules', $rule->searchableAs());
    }

    public function test_prevention_rule_should_be_searchable(): void
    {
        $rule = PreventionRule::factory()->create(['owner_id' => $this->user->id]);

        $this->assertTrue($rule->shouldBeSearchable());
    }

    public function test_prevention_rule_trashed_not_searchable(): void
    {
        $rule = PreventionRule::factory()->create(['owner_id' => $this->user->id]);
        $rule->delete();

        $this->assertFalse($rule->shouldBeSearchable());
    }

    public function test_prevention_rule_soft_deletes(): void
    {
        $rule = PreventionRule::factory()->create(['owner_id' => $this->user->id]);
        $rule->delete();

        $this->assertSame(0, PreventionRule::query()->count());
        $this->assertSame(1, PreventionRule::withTrashed()->count());
    }

    // =========================================================================
    // RlmScore
    // =========================================================================

    public function test_rlm_score_factory_creates_valid_model(): void
    {
        $score = RlmScore::factory()->create(['owner_id' => $this->user->id]);

        $this->assertNotNull($score->id);
        $this->assertDatabaseHas('rlm_scores', ['id' => $score->id]);
    }

    public function test_rlm_score_owner_relationship(): void
    {
        $score = RlmScore::factory()->create(['owner_id' => $this->user->id]);

        $this->assertInstanceOf(BelongsTo::class, $score->owner());
        $this->assertEquals($this->user->id, $score->owner->id);
    }

    public function test_rlm_score_scope_for_entity(): void
    {
        RlmScore::factory()->forEntity('Invoice')->create(['owner_id' => $this->user->id]);
        RlmScore::factory()->forEntity('Order')->create(['owner_id' => $this->user->id]);

        $this->assertSame(1, RlmScore::forEntity('Invoice')->count());
    }

    public function test_rlm_score_scope_of_type_with_enum(): void
    {
        RlmScore::factory()->structural()->create(['owner_id' => $this->user->id]);
        RlmScore::factory()->semantic()->create(['owner_id' => $this->user->id]);

        $this->assertSame(1, RlmScore::ofType(ScoreType::Structural)->count());
    }

    public function test_rlm_score_scope_of_type_with_string(): void
    {
        RlmScore::factory()->combined()->create(['owner_id' => $this->user->id]);
        RlmScore::factory()->structural()->create(['owner_id' => $this->user->id]);

        $this->assertSame(1, RlmScore::ofType('combined')->count());
    }

    public function test_rlm_score_scope_perfect(): void
    {
        RlmScore::factory()->create([
            'passed' => 42,
            'total' => 42,
            'owner_id' => $this->user->id,
        ]);
        RlmScore::factory()->create([
            'passed' => 35,
            'total' => 42,
            'owner_id' => $this->user->id,
        ]);

        $this->assertSame(1, RlmScore::perfect()->count());
    }

    public function test_rlm_score_casts(): void
    {
        $score = RlmScore::factory()->withDetails()->create(['owner_id' => $this->user->id]);
        $score->refresh();

        $this->assertInstanceOf(ScoreType::class, $score->score_type);
        $this->assertIsInt($score->passed);
        $this->assertIsInt($score->total);
        $this->assertIsInt($score->errors);
        $this->assertIsInt($score->warnings);
        $this->assertIsArray($score->details);
    }

    public function test_rlm_score_soft_deletes(): void
    {
        $score = RlmScore::factory()->create(['owner_id' => $this->user->id]);
        $score->delete();

        $this->assertSame(0, RlmScore::query()->count());
        $this->assertSame(1, RlmScore::withTrashed()->count());
    }

    // =========================================================================
    // GoldenAnnotation
    // =========================================================================

    public function test_golden_annotation_factory_creates_valid_model(): void
    {
        $annotation = GoldenAnnotation::factory()->create(['owner_id' => $this->user->id]);

        $this->assertNotNull($annotation->id);
        $this->assertDatabaseHas('golden_annotations', ['id' => $annotation->id]);
    }

    public function test_golden_annotation_owner_relationship(): void
    {
        $annotation = GoldenAnnotation::factory()->create(['owner_id' => $this->user->id]);

        $this->assertInstanceOf(BelongsTo::class, $annotation->owner());
        $this->assertEquals($this->user->id, $annotation->owner->id);
    }

    public function test_golden_annotation_source_links_relationship(): void
    {
        $annotation = GoldenAnnotation::factory()->create(['owner_id' => $this->user->id]);

        $this->assertInstanceOf(MorphMany::class, $annotation->sourceLinks());
    }

    public function test_golden_annotation_target_links_relationship(): void
    {
        $annotation = GoldenAnnotation::factory()->create(['owner_id' => $this->user->id]);

        $this->assertInstanceOf(MorphMany::class, $annotation->targetLinks());
    }

    public function test_golden_annotation_scope_active(): void
    {
        GoldenAnnotation::factory()->create(['is_active' => true, 'owner_id' => $this->user->id]);
        GoldenAnnotation::factory()->inactive()->create(['owner_id' => $this->user->id]);

        $this->assertSame(1, GoldenAnnotation::active()->count());
    }

    public function test_golden_annotation_scope_for_file(): void
    {
        GoldenAnnotation::factory()->create(['file_path' => 'app/Models/User.php', 'owner_id' => $this->user->id]);
        GoldenAnnotation::factory()->create(['file_path' => 'app/Models/Post.php', 'owner_id' => $this->user->id]);

        $this->assertSame(1, GoldenAnnotation::forFile('app/Models/User.php')->count());
    }

    public function test_golden_annotation_scope_in_category_with_enum(): void
    {
        GoldenAnnotation::factory()->forCategory(AnnotationCategory::Model)->create(['owner_id' => $this->user->id]);
        GoldenAnnotation::factory()->forCategory(AnnotationCategory::Api)->create(['owner_id' => $this->user->id]);

        $this->assertSame(1, GoldenAnnotation::inCategory(AnnotationCategory::Model)->count());
    }

    public function test_golden_annotation_scope_in_category_with_string(): void
    {
        GoldenAnnotation::factory()->forCategory(AnnotationCategory::Api)->create(['owner_id' => $this->user->id]);

        $this->assertSame(1, GoldenAnnotation::inCategory('api')->count());
    }

    public function test_golden_annotation_scope_with_feature_tag(): void
    {
        GoldenAnnotation::factory()->withFeatureTags(['media', 'search'])->create(['owner_id' => $this->user->id]);
        GoldenAnnotation::factory()->withFeatureTags(['audit'])->create(['owner_id' => $this->user->id]);

        $this->assertSame(1, GoldenAnnotation::withFeatureTag('media')->count());
    }

    public function test_golden_annotation_scope_with_any_feature_tag(): void
    {
        GoldenAnnotation::factory()->withFeatureTags(['media'])->create(['owner_id' => $this->user->id]);
        GoldenAnnotation::factory()->withFeatureTags(['search'])->create(['owner_id' => $this->user->id]);
        GoldenAnnotation::factory()->withFeatureTags(['audit'])->create(['owner_id' => $this->user->id]);

        $this->assertSame(2, GoldenAnnotation::withAnyFeatureTag(['media', 'search'])->count());
    }

    public function test_golden_annotation_scope_for_pattern(): void
    {
        GoldenAnnotation::factory()->forPattern('model-fillable')->create(['owner_id' => $this->user->id]);
        GoldenAnnotation::factory()->forPattern('other-pattern')->create(['owner_id' => $this->user->id]);

        $this->assertSame(1, GoldenAnnotation::forPattern('model-fillable')->count());
    }

    public function test_golden_annotation_embedding_text(): void
    {
        $annotation = GoldenAnnotation::factory()->create([
            'annotation_text' => 'Annotation here',
            'rationale' => 'Because reasons',
            'owner_id' => $this->user->id,
        ]);

        $text = $annotation->embeddingText();

        $this->assertStringContainsString('Annotation here', $text);
        $this->assertStringContainsString('Because reasons', $text);
    }

    public function test_golden_annotation_to_searchable_array(): void
    {
        $annotation = GoldenAnnotation::factory()->create([
            'annotation_key' => 'model.test',
            'owner_id' => $this->user->id,
        ]);

        $array = $annotation->toSearchableArray();

        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('annotation_key', $array);
        $this->assertArrayHasKey('annotation_text', $array);
        $this->assertArrayHasKey('category', $array);
        $this->assertArrayHasKey('is_active', $array);
    }

    public function test_golden_annotation_searchable_as(): void
    {
        $annotation = GoldenAnnotation::factory()->create(['owner_id' => $this->user->id]);

        $this->assertSame('aicl_golden_annotations', $annotation->searchableAs());
    }

    public function test_golden_annotation_should_be_searchable_when_active(): void
    {
        $annotation = GoldenAnnotation::factory()->create(['is_active' => true, 'owner_id' => $this->user->id]);

        $this->assertTrue($annotation->shouldBeSearchable());
    }

    public function test_golden_annotation_not_searchable_when_inactive(): void
    {
        $annotation = GoldenAnnotation::factory()->inactive()->create(['owner_id' => $this->user->id]);

        $this->assertFalse($annotation->shouldBeSearchable());
    }

    public function test_golden_annotation_not_searchable_when_trashed(): void
    {
        $annotation = GoldenAnnotation::factory()->create(['owner_id' => $this->user->id]);
        $annotation->delete();

        $this->assertFalse($annotation->shouldBeSearchable());
    }

    public function test_golden_annotation_soft_deletes(): void
    {
        $annotation = GoldenAnnotation::factory()->create(['owner_id' => $this->user->id]);
        $annotation->delete();

        $this->assertSame(0, GoldenAnnotation::query()->count());
        $this->assertSame(1, GoldenAnnotation::withTrashed()->count());
    }

    public function test_golden_annotation_casts(): void
    {
        $annotation = GoldenAnnotation::factory()->create([
            'feature_tags' => ['media', 'api'],
            'category' => AnnotationCategory::Model,
            'owner_id' => $this->user->id,
        ]);
        $annotation->refresh();

        $this->assertIsArray($annotation->feature_tags);
        $this->assertInstanceOf(AnnotationCategory::class, $annotation->category);
        $this->assertIsBool($annotation->is_active);
        $this->assertIsInt($annotation->line_number);
    }

    // =========================================================================
    // KnowledgeLink
    // =========================================================================

    public function test_knowledge_link_can_be_created(): void
    {
        $source = GoldenAnnotation::factory()->create(['owner_id' => $this->user->id]);
        $target = GoldenAnnotation::factory()->create(['owner_id' => $this->user->id]);

        $link = KnowledgeLink::create([
            'source_type' => $source->getMorphClass(),
            'source_id' => $source->id,
            'target_type' => $target->getMorphClass(),
            'target_id' => $target->id,
            'relationship' => KnowledgeLinkRelationship::RelatedTo,
            'confidence' => 0.85,
        ]);

        $this->assertDatabaseHas('knowledge_links', ['id' => $link->id]);
    }

    public function test_knowledge_link_source_relationship(): void
    {
        $source = GoldenAnnotation::factory()->create(['owner_id' => $this->user->id]);
        $target = GoldenAnnotation::factory()->create(['owner_id' => $this->user->id]);

        $link = KnowledgeLink::create([
            'source_type' => $source->getMorphClass(),
            'source_id' => $source->id,
            'target_type' => $target->getMorphClass(),
            'target_id' => $target->id,
            'relationship' => KnowledgeLinkRelationship::ViolatedBy,
            'confidence' => 0.80,
        ]);

        $this->assertInstanceOf(MorphTo::class, $link->source());
        $this->assertEquals($source->id, $link->source->id);
    }

    public function test_knowledge_link_target_relationship(): void
    {
        $source = GoldenAnnotation::factory()->create(['owner_id' => $this->user->id]);
        $target = GoldenAnnotation::factory()->create(['owner_id' => $this->user->id]);

        $link = KnowledgeLink::create([
            'source_type' => $source->getMorphClass(),
            'source_id' => $source->id,
            'target_type' => $target->getMorphClass(),
            'target_id' => $target->id,
            'relationship' => KnowledgeLinkRelationship::Prevents,
            'confidence' => 0.75,
        ]);

        $this->assertInstanceOf(MorphTo::class, $link->target());
        $this->assertEquals($target->id, $link->target->id);
    }

    public function test_knowledge_link_scope_of_relationship_with_enum(): void
    {
        $source = GoldenAnnotation::factory()->create(['owner_id' => $this->user->id]);
        $target = GoldenAnnotation::factory()->create(['owner_id' => $this->user->id]);

        KnowledgeLink::create([
            'source_type' => $source->getMorphClass(),
            'source_id' => $source->id,
            'target_type' => $target->getMorphClass(),
            'target_id' => $target->id,
            'relationship' => KnowledgeLinkRelationship::LearnedFrom,
            'confidence' => 0.80,
        ]);
        KnowledgeLink::create([
            'source_type' => $source->getMorphClass(),
            'source_id' => $source->id,
            'target_type' => $target->getMorphClass(),
            'target_id' => $target->id,
            'relationship' => KnowledgeLinkRelationship::Prevents,
            'confidence' => 0.70,
        ]);

        $this->assertSame(1, KnowledgeLink::ofRelationship(KnowledgeLinkRelationship::LearnedFrom)->count());
    }

    public function test_knowledge_link_scope_of_relationship_with_string(): void
    {
        $source = GoldenAnnotation::factory()->create(['owner_id' => $this->user->id]);
        $target = GoldenAnnotation::factory()->create(['owner_id' => $this->user->id]);

        KnowledgeLink::create([
            'source_type' => $source->getMorphClass(),
            'source_id' => $source->id,
            'target_type' => $target->getMorphClass(),
            'target_id' => $target->id,
            'relationship' => KnowledgeLinkRelationship::DerivedFrom,
            'confidence' => 0.80,
        ]);

        $this->assertSame(1, KnowledgeLink::ofRelationship('derived_from')->count());
    }

    public function test_knowledge_link_scope_high_confidence(): void
    {
        $source = GoldenAnnotation::factory()->create(['owner_id' => $this->user->id]);
        $target = GoldenAnnotation::factory()->create(['owner_id' => $this->user->id]);

        KnowledgeLink::create([
            'source_type' => $source->getMorphClass(),
            'source_id' => $source->id,
            'target_type' => $target->getMorphClass(),
            'target_id' => $target->id,
            'relationship' => KnowledgeLinkRelationship::RelatedTo,
            'confidence' => 0.90,
        ]);
        KnowledgeLink::create([
            'source_type' => $source->getMorphClass(),
            'source_id' => $source->id,
            'target_type' => $target->getMorphClass(),
            'target_id' => $target->id,
            'relationship' => KnowledgeLinkRelationship::ViolatedBy,
            'confidence' => 0.40,
        ]);

        $this->assertSame(1, KnowledgeLink::highConfidence()->count());
    }

    public function test_knowledge_link_scope_for_source(): void
    {
        $source = GoldenAnnotation::factory()->create(['owner_id' => $this->user->id]);
        $target = GoldenAnnotation::factory()->create(['owner_id' => $this->user->id]);

        KnowledgeLink::create([
            'source_type' => $source->getMorphClass(),
            'source_id' => $source->id,
            'target_type' => $target->getMorphClass(),
            'target_id' => $target->id,
            'relationship' => KnowledgeLinkRelationship::RelatedTo,
            'confidence' => 0.80,
        ]);

        $this->assertSame(1, KnowledgeLink::forSource($source)->count());
        $this->assertSame(0, KnowledgeLink::forSource($target)->count());
    }

    public function test_knowledge_link_scope_for_target(): void
    {
        $source = GoldenAnnotation::factory()->create(['owner_id' => $this->user->id]);
        $target = GoldenAnnotation::factory()->create(['owner_id' => $this->user->id]);

        KnowledgeLink::create([
            'source_type' => $source->getMorphClass(),
            'source_id' => $source->id,
            'target_type' => $target->getMorphClass(),
            'target_id' => $target->id,
            'relationship' => KnowledgeLinkRelationship::RelatedTo,
            'confidence' => 0.80,
        ]);

        $this->assertSame(1, KnowledgeLink::forTarget($target)->count());
        $this->assertSame(0, KnowledgeLink::forTarget($source)->count());
    }

    public function test_knowledge_link_scope_involving(): void
    {
        $a = GoldenAnnotation::factory()->create(['owner_id' => $this->user->id]);
        $b = GoldenAnnotation::factory()->create(['owner_id' => $this->user->id]);
        $c = GoldenAnnotation::factory()->create(['owner_id' => $this->user->id]);

        KnowledgeLink::create([
            'source_type' => $a->getMorphClass(),
            'source_id' => $a->id,
            'target_type' => $b->getMorphClass(),
            'target_id' => $b->id,
            'relationship' => KnowledgeLinkRelationship::RelatedTo,
            'confidence' => 0.80,
        ]);
        KnowledgeLink::create([
            'source_type' => $b->getMorphClass(),
            'source_id' => $b->id,
            'target_type' => $c->getMorphClass(),
            'target_id' => $c->id,
            'relationship' => KnowledgeLinkRelationship::RelatedTo,
            'confidence' => 0.70,
        ]);

        $this->assertSame(2, KnowledgeLink::involving($b)->count());
        $this->assertSame(1, KnowledgeLink::involving($a)->count());
        $this->assertSame(1, KnowledgeLink::involving($c)->count());
    }

    public function test_knowledge_link_casts(): void
    {
        $source = GoldenAnnotation::factory()->create(['owner_id' => $this->user->id]);
        $target = GoldenAnnotation::factory()->create(['owner_id' => $this->user->id]);

        $link = KnowledgeLink::create([
            'source_type' => $source->getMorphClass(),
            'source_id' => $source->id,
            'target_type' => $target->getMorphClass(),
            'target_id' => $target->id,
            'relationship' => KnowledgeLinkRelationship::ViolatedBy,
            'confidence' => 0.85,
        ]);
        $link->refresh();

        $this->assertInstanceOf(KnowledgeLinkRelationship::class, $link->relationship);
    }

    // =========================================================================
    // RlmSemanticCache
    // =========================================================================

    public function test_rlm_semantic_cache_can_be_created(): void
    {
        $cache = RlmSemanticCache::create([
            'cache_key' => 'test-key-'.uniqid(),
            'check_name' => 'model.fillable',
            'entity_name' => 'Invoice',
            'passed' => true,
            'message' => 'All checks passed',
            'confidence' => 0.95,
            'files_hash' => hash('sha256', 'test'),
            'expires_at' => now()->addHour(),
        ]);

        $this->assertDatabaseHas('rlm_semantic_cache', ['id' => $cache->id]);
    }

    public function test_rlm_semantic_cache_uses_correct_table(): void
    {
        $model = new RlmSemanticCache;

        $this->assertSame('rlm_semantic_cache', $model->getTable());
    }

    public function test_rlm_semantic_cache_casts(): void
    {
        $cache = RlmSemanticCache::create([
            'cache_key' => 'cast-test-'.uniqid(),
            'check_name' => 'model.uuid',
            'entity_name' => 'Order',
            'passed' => false,
            'message' => 'Failed',
            'confidence' => 0.60,
            'files_hash' => hash('sha256', 'test'),
            'expires_at' => now()->addHour(),
        ]);
        $cache->refresh();

        $this->assertIsBool($cache->passed);
        $this->assertFalse($cache->passed);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $cache->expires_at);
    }

    // =========================================================================
    // NotificationLog
    // =========================================================================

    public function test_notification_log_can_be_created(): void
    {
        $log = NotificationLog::create([
            'type' => 'App\\Notifications\\TestNotification',
            'notifiable_type' => $this->user->getMorphClass(),
            'notifiable_id' => $this->user->getKey(),
            'channels' => ['mail', 'database'],
            'channel_status' => ['mail' => 'sent', 'database' => 'sent'],
            'data' => ['message' => 'Hello'],
        ]);

        $this->assertDatabaseHas('notification_logs', ['id' => $log->id]);
    }

    public function test_notification_log_uses_correct_table(): void
    {
        $model = new NotificationLog;

        $this->assertSame('notification_logs', $model->getTable());
    }

    public function test_notification_log_notifiable_relationship(): void
    {
        $log = NotificationLog::create([
            'type' => 'App\\Notifications\\TestNotification',
            'notifiable_type' => $this->user->getMorphClass(),
            'notifiable_id' => $this->user->getKey(),
            'channels' => ['mail'],
            'channel_status' => ['mail' => 'sent'],
        ]);

        $this->assertInstanceOf(MorphTo::class, $log->notifiable());
        $this->assertEquals($this->user->id, $log->notifiable->id);
    }

    public function test_notification_log_sender_relationship(): void
    {
        $log = NotificationLog::create([
            'type' => 'App\\Notifications\\TestNotification',
            'notifiable_type' => $this->user->getMorphClass(),
            'notifiable_id' => $this->user->getKey(),
            'sender_type' => $this->user->getMorphClass(),
            'sender_id' => $this->user->getKey(),
            'channels' => ['mail'],
            'channel_status' => ['mail' => 'sent'],
        ]);

        $this->assertInstanceOf(MorphTo::class, $log->sender());
        $this->assertEquals($this->user->id, $log->sender->id);
    }

    public function test_notification_log_scope_for_user(): void
    {
        $otherUser = User::factory()->create();

        NotificationLog::create([
            'type' => 'App\\Notifications\\TestNotification',
            'notifiable_type' => $this->user->getMorphClass(),
            'notifiable_id' => $this->user->getKey(),
            'channels' => ['mail'],
            'channel_status' => ['mail' => 'sent'],
        ]);
        NotificationLog::create([
            'type' => 'App\\Notifications\\TestNotification',
            'notifiable_type' => $otherUser->getMorphClass(),
            'notifiable_id' => $otherUser->getKey(),
            'channels' => ['mail'],
            'channel_status' => ['mail' => 'sent'],
        ]);

        $this->assertSame(1, NotificationLog::forUser($this->user)->count());
    }

    public function test_notification_log_scope_of_type(): void
    {
        NotificationLog::create([
            'type' => 'App\\Notifications\\InvoiceCreated',
            'notifiable_type' => $this->user->getMorphClass(),
            'notifiable_id' => $this->user->getKey(),
            'channels' => ['mail'],
            'channel_status' => ['mail' => 'sent'],
        ]);
        NotificationLog::create([
            'type' => 'App\\Notifications\\OrderShipped',
            'notifiable_type' => $this->user->getMorphClass(),
            'notifiable_id' => $this->user->getKey(),
            'channels' => ['mail'],
            'channel_status' => ['mail' => 'sent'],
        ]);

        $this->assertSame(1, NotificationLog::ofType('App\\Notifications\\InvoiceCreated')->count());
    }

    public function test_notification_log_scope_unread(): void
    {
        NotificationLog::create([
            'type' => 'App\\Notifications\\Test',
            'notifiable_type' => $this->user->getMorphClass(),
            'notifiable_id' => $this->user->getKey(),
            'channels' => ['database'],
            'channel_status' => ['database' => 'sent'],
            'read_at' => null,
        ]);
        NotificationLog::create([
            'type' => 'App\\Notifications\\Test',
            'notifiable_type' => $this->user->getMorphClass(),
            'notifiable_id' => $this->user->getKey(),
            'channels' => ['database'],
            'channel_status' => ['database' => 'sent'],
            'read_at' => now(),
        ]);

        $this->assertSame(1, NotificationLog::unread()->count());
    }

    public function test_notification_log_scope_failed(): void
    {
        NotificationLog::create([
            'type' => 'App\\Notifications\\Test',
            'notifiable_type' => $this->user->getMorphClass(),
            'notifiable_id' => $this->user->getKey(),
            'channels' => ['mail'],
            'channel_status' => ['mail' => 'failed'],
        ]);
        NotificationLog::create([
            'type' => 'App\\Notifications\\Test',
            'notifiable_type' => $this->user->getMorphClass(),
            'notifiable_id' => $this->user->getKey(),
            'channels' => ['mail'],
            'channel_status' => ['mail' => 'sent'],
        ]);

        $this->assertSame(1, NotificationLog::failed()->count());
    }

    public function test_notification_log_type_label_accessor(): void
    {
        $log = NotificationLog::create([
            'type' => 'App\\Notifications\\InvoiceCreatedNotification',
            'notifiable_type' => $this->user->getMorphClass(),
            'notifiable_id' => $this->user->getKey(),
            'channels' => ['mail'],
            'channel_status' => ['mail' => 'sent'],
        ]);

        $this->assertSame('Invoice Created', $log->type_label);
    }

    public function test_notification_log_type_label_null_type(): void
    {
        $log = new NotificationLog;
        $log->type = null;

        $this->assertSame('Unknown', $log->type_label);
    }

    public function test_notification_log_mark_as_read(): void
    {
        $log = NotificationLog::create([
            'type' => 'App\\Notifications\\Test',
            'notifiable_type' => $this->user->getMorphClass(),
            'notifiable_id' => $this->user->getKey(),
            'channels' => ['database'],
            'channel_status' => ['database' => 'sent'],
            'read_at' => null,
        ]);

        $this->assertNull($log->read_at);

        $log->markAsRead();
        $log->refresh();

        $this->assertNotNull($log->read_at);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $log->read_at);
    }

    public function test_notification_log_mark_as_read_idempotent(): void
    {
        $log = NotificationLog::create([
            'type' => 'App\\Notifications\\Test',
            'notifiable_type' => $this->user->getMorphClass(),
            'notifiable_id' => $this->user->getKey(),
            'channels' => ['database'],
            'channel_status' => ['database' => 'sent'],
            'read_at' => now()->subHour(),
        ]);

        $originalReadAt = $log->read_at;
        $log->markAsRead();
        $log->refresh();

        $this->assertEquals($originalReadAt->timestamp, $log->read_at->timestamp);
    }

    public function test_notification_log_mark_as_unread(): void
    {
        $log = NotificationLog::create([
            'type' => 'App\\Notifications\\Test',
            'notifiable_type' => $this->user->getMorphClass(),
            'notifiable_id' => $this->user->getKey(),
            'channels' => ['database'],
            'channel_status' => ['database' => 'sent'],
            'read_at' => now(),
        ]);

        $log->markAsUnread();
        $log->refresh();

        $this->assertNull($log->read_at);
    }

    public function test_notification_log_mark_as_unread_idempotent(): void
    {
        $log = NotificationLog::create([
            'type' => 'App\\Notifications\\Test',
            'notifiable_type' => $this->user->getMorphClass(),
            'notifiable_id' => $this->user->getKey(),
            'channels' => ['database'],
            'channel_status' => ['database' => 'sent'],
            'read_at' => null,
        ]);

        $log->markAsUnread();
        $log->refresh();

        $this->assertNull($log->read_at);
    }

    public function test_notification_log_casts(): void
    {
        $log = NotificationLog::create([
            'type' => 'App\\Notifications\\Test',
            'notifiable_type' => $this->user->getMorphClass(),
            'notifiable_id' => $this->user->getKey(),
            'channels' => ['mail', 'database'],
            'channel_status' => ['mail' => 'sent', 'database' => 'sent'],
            'data' => ['key' => 'value'],
            'read_at' => now(),
        ]);
        $log->refresh();

        $this->assertIsArray($log->channels);
        $this->assertIsArray($log->channel_status);
        $this->assertIsArray($log->data);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $log->read_at);
    }

    // =========================================================================
    // DomainEventRecord
    // =========================================================================

    public function test_domain_event_record_can_be_created(): void
    {
        $record = DomainEventRecord::create([
            'event_type' => 'order.created',
            'actor_type' => ActorType::User->value,
            'actor_id' => $this->user->id,
            'entity_type' => $this->user->getMorphClass(),
            'entity_id' => (string) $this->user->getKey(),
            'payload' => ['key' => 'value'],
            'metadata' => ['source' => 'test'],
            'occurred_at' => now(),
        ]);

        $this->assertDatabaseHas('domain_events', ['id' => $record->id]);
    }

    public function test_domain_event_record_uses_correct_table(): void
    {
        $model = new DomainEventRecord;

        $this->assertSame('domain_events', $model->getTable());
    }

    public function test_domain_event_record_has_timestamps_disabled(): void
    {
        $model = new DomainEventRecord;

        $this->assertFalse($model->timestamps);
    }

    public function test_domain_event_record_auto_sets_created_at(): void
    {
        $record = DomainEventRecord::create([
            'event_type' => 'test.boot',
            'actor_type' => ActorType::System->value,
            'payload' => [],
            'metadata' => [],
            'occurred_at' => now(),
        ]);

        $this->assertNotNull($record->created_at);
    }

    public function test_domain_event_record_preserves_explicit_created_at(): void
    {
        $explicit = Carbon::parse('2025-01-01 12:00:00');

        $record = new DomainEventRecord([
            'event_type' => 'test.explicit',
            'actor_type' => ActorType::System->value,
            'payload' => [],
            'metadata' => [],
            'occurred_at' => now(),
        ]);
        $record->created_at = $explicit;
        $record->save();

        $record->refresh();
        $this->assertEquals($explicit->timestamp, $record->created_at->timestamp);
    }

    public function test_domain_event_record_scope_for_entity(): void
    {
        DomainEventRecord::query()->delete();

        DomainEventRecord::create([
            'event_type' => 'user.updated',
            'actor_type' => ActorType::User->value,
            'entity_type' => $this->user->getMorphClass(),
            'entity_id' => (string) $this->user->getKey(),
            'payload' => [],
            'metadata' => [],
            'occurred_at' => now(),
        ]);
        DomainEventRecord::create([
            'event_type' => 'other.event',
            'actor_type' => ActorType::System->value,
            'entity_type' => 'App\\Models\\Other',
            'entity_id' => '999',
            'payload' => [],
            'metadata' => [],
            'occurred_at' => now(),
        ]);

        $this->assertSame(1, DomainEventRecord::forEntity($this->user)->count());
    }

    public function test_domain_event_record_scope_of_type_exact(): void
    {
        DomainEventRecord::create([
            'event_type' => 'order.created',
            'actor_type' => ActorType::System->value,
            'payload' => [],
            'metadata' => [],
            'occurred_at' => now(),
        ]);
        DomainEventRecord::create([
            'event_type' => 'order.fulfilled',
            'actor_type' => ActorType::System->value,
            'payload' => [],
            'metadata' => [],
            'occurred_at' => now(),
        ]);

        $this->assertSame(1, DomainEventRecord::ofType('order.created')->count());
    }

    public function test_domain_event_record_scope_of_type_wildcard(): void
    {
        DomainEventRecord::create([
            'event_type' => 'order.created',
            'actor_type' => ActorType::System->value,
            'payload' => [],
            'metadata' => [],
            'occurred_at' => now(),
        ]);
        DomainEventRecord::create([
            'event_type' => 'order.fulfilled',
            'actor_type' => ActorType::System->value,
            'payload' => [],
            'metadata' => [],
            'occurred_at' => now(),
        ]);
        DomainEventRecord::create([
            'event_type' => 'user.created',
            'actor_type' => ActorType::System->value,
            'payload' => [],
            'metadata' => [],
            'occurred_at' => now(),
        ]);

        $this->assertSame(2, DomainEventRecord::ofType('order.*')->count());
    }

    public function test_domain_event_record_scope_of_type_suffix_wildcard(): void
    {
        DomainEventRecord::create([
            'event_type' => 'order.escalated',
            'actor_type' => ActorType::System->value,
            'payload' => [],
            'metadata' => [],
            'occurred_at' => now(),
        ]);
        DomainEventRecord::create([
            'event_type' => 'ticket.escalated',
            'actor_type' => ActorType::System->value,
            'payload' => [],
            'metadata' => [],
            'occurred_at' => now(),
        ]);
        DomainEventRecord::create([
            'event_type' => 'order.created',
            'actor_type' => ActorType::System->value,
            'payload' => [],
            'metadata' => [],
            'occurred_at' => now(),
        ]);

        $this->assertSame(2, DomainEventRecord::ofType('*.escalated')->count());
    }

    public function test_domain_event_record_scope_since(): void
    {
        DomainEventRecord::query()->delete();

        DomainEventRecord::create([
            'event_type' => 'old.event',
            'actor_type' => ActorType::System->value,
            'payload' => [],
            'metadata' => [],
            'occurred_at' => Carbon::now()->subDays(10),
        ]);
        DomainEventRecord::create([
            'event_type' => 'new.event',
            'actor_type' => ActorType::System->value,
            'payload' => [],
            'metadata' => [],
            'occurred_at' => Carbon::now(),
        ]);

        $this->assertSame(1, DomainEventRecord::since(Carbon::now()->subDays(5))->count());
    }

    public function test_domain_event_record_scope_between(): void
    {
        DomainEventRecord::query()->delete();

        DomainEventRecord::create([
            'event_type' => 'outside',
            'actor_type' => ActorType::System->value,
            'payload' => [],
            'metadata' => [],
            'occurred_at' => Carbon::now()->subDays(20),
        ]);
        DomainEventRecord::create([
            'event_type' => 'inside',
            'actor_type' => ActorType::System->value,
            'payload' => [],
            'metadata' => [],
            'occurred_at' => Carbon::now()->subDays(5),
        ]);

        $results = DomainEventRecord::between(Carbon::now()->subDays(10), Carbon::now())->get();
        $this->assertSame(1, $results->count());
    }

    public function test_domain_event_record_scope_by_actor_type_only(): void
    {
        DomainEventRecord::create([
            'event_type' => 'user.action',
            'actor_type' => ActorType::User->value,
            'actor_id' => $this->user->id,
            'payload' => [],
            'metadata' => [],
            'occurred_at' => now(),
        ]);
        DomainEventRecord::create([
            'event_type' => 'system.action',
            'actor_type' => ActorType::System->value,
            'payload' => [],
            'metadata' => [],
            'occurred_at' => now(),
        ]);

        $this->assertSame(1, DomainEventRecord::byActor(ActorType::User)->count());
    }

    public function test_domain_event_record_scope_by_actor_with_id(): void
    {
        DomainEventRecord::create([
            'event_type' => 'user.action',
            'actor_type' => ActorType::User->value,
            'actor_id' => $this->user->id,
            'payload' => [],
            'metadata' => [],
            'occurred_at' => now(),
        ]);

        $this->assertSame(1, DomainEventRecord::byActor(ActorType::User, $this->user->id)->count());
        $this->assertSame(0, DomainEventRecord::byActor(ActorType::User, 999)->count());
    }

    public function test_domain_event_record_scope_timeline(): void
    {
        DomainEventRecord::query()->delete();

        DomainEventRecord::create([
            'event_type' => 'first',
            'actor_type' => ActorType::System->value,
            'entity_type' => $this->user->getMorphClass(),
            'entity_id' => (string) $this->user->getKey(),
            'payload' => [],
            'metadata' => [],
            'occurred_at' => Carbon::now()->subHour(),
        ]);
        DomainEventRecord::create([
            'event_type' => 'second',
            'actor_type' => ActorType::System->value,
            'entity_type' => $this->user->getMorphClass(),
            'entity_id' => (string) $this->user->getKey(),
            'payload' => [],
            'metadata' => [],
            'occurred_at' => Carbon::now(),
        ]);

        $results = DomainEventRecord::timeline($this->user)->get();
        $this->assertSame(2, $results->count());
        $this->assertSame('second', $results->first()->event_type);
    }

    public function test_domain_event_record_prune(): void
    {
        DomainEventRecord::create([
            'event_type' => 'old',
            'actor_type' => ActorType::System->value,
            'payload' => [],
            'metadata' => [],
            'occurred_at' => Carbon::now()->subDays(30),
        ]);
        DomainEventRecord::create([
            'event_type' => 'recent',
            'actor_type' => ActorType::System->value,
            'payload' => [],
            'metadata' => [],
            'occurred_at' => Carbon::now(),
        ]);

        $deleted = DomainEventRecord::prune(Carbon::now()->subDays(7));

        $this->assertEquals(1, $deleted);
        $this->assertDatabaseHas('domain_events', ['event_type' => 'recent']);
    }

    public function test_domain_event_record_actor_type_enum_accessor(): void
    {
        $record = DomainEventRecord::create([
            'event_type' => 'test',
            'actor_type' => ActorType::Agent->value,
            'payload' => [],
            'metadata' => [],
            'occurred_at' => now(),
        ]);

        $this->assertEquals(ActorType::Agent, $record->actor_type_enum);
    }

    public function test_domain_event_record_casts(): void
    {
        $record = DomainEventRecord::create([
            'event_type' => 'cast.test',
            'actor_type' => ActorType::System->value,
            'payload' => ['key' => 'value'],
            'metadata' => ['source' => 'test'],
            'occurred_at' => now(),
        ]);
        $record->refresh();

        $this->assertIsArray($record->payload);
        $this->assertIsArray($record->metadata);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $record->occurred_at);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $record->created_at);
    }

    // =========================================================================
    // GenerationTrace
    // =========================================================================

    public function test_generation_trace_factory_creates_valid_model(): void
    {
        $trace = GenerationTrace::factory()->create(['owner_id' => $this->user->id]);

        $this->assertNotNull($trace->id);
        $this->assertDatabaseHas('generation_traces', ['id' => $trace->id]);
    }

    public function test_generation_trace_owner_relationship(): void
    {
        $trace = GenerationTrace::factory()->create(['owner_id' => $this->user->id]);

        $this->assertInstanceOf(BelongsTo::class, $trace->owner());
        $this->assertEquals($this->user->id, $trace->owner->id);
    }

    public function test_generation_trace_scope_unprocessed(): void
    {
        GenerationTrace::factory()->create([
            'is_processed' => false,
            'owner_id' => $this->user->id,
        ]);
        GenerationTrace::factory()->processed()->create(['owner_id' => $this->user->id]);

        $this->assertSame(1, GenerationTrace::unprocessed()->count());
    }

    public function test_generation_trace_scope_by_entity(): void
    {
        GenerationTrace::factory()->create([
            'entity_name' => 'Invoice',
            'owner_id' => $this->user->id,
        ]);
        GenerationTrace::factory()->create([
            'entity_name' => 'Order',
            'owner_id' => $this->user->id,
        ]);

        $this->assertSame(1, GenerationTrace::byEntity('Invoice')->count());
    }

    public function test_generation_trace_scope_by_project(): void
    {
        GenerationTrace::factory()->create([
            'project_hash' => 'hash-abc',
            'owner_id' => $this->user->id,
        ]);
        GenerationTrace::factory()->create([
            'project_hash' => 'hash-xyz',
            'owner_id' => $this->user->id,
        ]);

        $this->assertSame(1, GenerationTrace::byProject('hash-abc')->count());
    }

    public function test_generation_trace_casts(): void
    {
        $trace = GenerationTrace::factory()->withFixes()->create([
            'file_manifest' => ['app/Models/Test.php'],
            'agent_versions' => ['architect' => 'v1'],
            'surfaced_lesson_codes' => ['DL-001'],
            'failure_codes_hit' => ['BF-001'],
            'owner_id' => $this->user->id,
        ]);
        $trace->refresh();

        $this->assertIsArray($trace->file_manifest);
        $this->assertIsArray($trace->fixes_applied);
        $this->assertIsArray($trace->agent_versions);
        $this->assertIsArray($trace->surfaced_lesson_codes);
        $this->assertIsArray($trace->failure_codes_hit);
        $this->assertIsBool($trace->is_processed);
        $this->assertIsBool($trace->is_active);
        $this->assertIsInt($trace->fix_iterations);
    }

    public function test_generation_trace_soft_deletes(): void
    {
        $trace = GenerationTrace::factory()->create(['owner_id' => $this->user->id]);
        $trace->delete();

        $this->assertSame(0, GenerationTrace::query()->count());
        $this->assertSame(1, GenerationTrace::withTrashed()->count());
    }

    public function test_generation_trace_searchable_columns(): void
    {
        $trace = new GenerationTrace;
        $method = new \ReflectionMethod($trace, 'searchableColumns');

        $columns = $method->invoke($trace);

        $this->assertContains('entity_name', $columns);
        $this->assertContains('scaffolder_args', $columns);
    }

    public function test_generation_trace_kpi_fields(): void
    {
        $trace = GenerationTrace::factory()->create([
            'known_failure_count' => 5,
            'novel_failure_count' => 2,
            'surfaced_lesson_codes' => ['DL-001', 'DL-003'],
            'failure_codes_hit' => ['BF-001'],
            'owner_id' => $this->user->id,
        ]);
        $trace->refresh();

        $this->assertSame(5, $trace->known_failure_count);
        $this->assertSame(2, $trace->novel_failure_count);
        $this->assertCount(2, $trace->surfaced_lesson_codes);
        $this->assertCount(1, $trace->failure_codes_hit);
    }
}
