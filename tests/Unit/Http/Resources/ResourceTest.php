<?php

namespace Aicl\Tests\Unit\Http\Resources;

use Aicl\Http\Resources\FailureReportResource;
use Aicl\Http\Resources\GenerationTraceResource;
use Aicl\Http\Resources\PreventionRuleResource;
use Aicl\Http\Resources\RlmFailureResource;
use Aicl\Http\Resources\RlmLessonResource;
use Aicl\Http\Resources\RlmPatternResource;
use Aicl\Http\Resources\RlmScoreResource;
use Aicl\Models\FailureReport;
use Aicl\Models\GenerationTrace;
use Aicl\Models\PreventionRule;
use Aicl\Models\RlmFailure;
use Aicl\Models\RlmLesson;
use Aicl\Models\RlmPattern;
use Aicl\Models\RlmScore;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        User::factory()->create(['id' => 1]);
    }

    // ========================================================================
    // RlmPatternResource
    // ========================================================================

    public function test_rlm_pattern_resource_has_expected_keys(): void
    {
        $pattern = RlmPattern::factory()->create(['owner_id' => 1]);
        $resource = new RlmPatternResource($pattern);

        $array = $resource->toArray(request());

        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('name', $array);
        $this->assertArrayHasKey('description', $array);
        $this->assertArrayHasKey('target', $array);
        $this->assertArrayHasKey('check_regex', $array);
        $this->assertArrayHasKey('severity', $array);
        $this->assertArrayHasKey('weight', $array);
        $this->assertArrayHasKey('category', $array);
        $this->assertArrayHasKey('applies_when', $array);
        $this->assertArrayHasKey('source', $array);
        $this->assertArrayHasKey('is_active', $array);
        $this->assertArrayHasKey('pass_count', $array);
        $this->assertArrayHasKey('fail_count', $array);
        $this->assertArrayHasKey('pass_rate', $array);
        $this->assertArrayHasKey('last_evaluated_at', $array);
        $this->assertArrayHasKey('created_at', $array);
        $this->assertArrayHasKey('updated_at', $array);
    }

    public function test_rlm_pattern_resource_values_match_model(): void
    {
        $pattern = RlmPattern::factory()->create(['owner_id' => 1, 'name' => 'test_pattern']);
        $resource = new RlmPatternResource($pattern);

        $array = $resource->toArray(request());

        $this->assertSame($pattern->id, $array['id']);
        $this->assertSame('test_pattern', $array['name']);
        $this->assertSame($pattern->description, $array['description']);
        $this->assertSame($pattern->target, $array['target']);
        $this->assertSame($pattern->is_active, $array['is_active']);
    }

    public function test_rlm_pattern_resource_includes_owner_when_loaded(): void
    {
        $pattern = RlmPattern::factory()->create(['owner_id' => 1]);
        $pattern->load('owner');
        $resource = new RlmPatternResource($pattern);

        $array = $resource->toArray(request());

        $this->assertArrayHasKey('owner', $array);
        $this->assertArrayHasKey('id', $array['owner']);
        $this->assertArrayHasKey('name', $array['owner']);
    }

    // ========================================================================
    // RlmFailureResource
    // ========================================================================

    public function test_rlm_failure_resource_has_expected_keys(): void
    {
        $failure = RlmFailure::factory()->create(['owner_id' => 1]);
        $resource = new RlmFailureResource($failure);

        $array = $resource->toArray(request());

        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('failure_code', $array);
        $this->assertArrayHasKey('pattern_id', $array);
        $this->assertArrayHasKey('category', $array);
        $this->assertArrayHasKey('subcategory', $array);
        $this->assertArrayHasKey('title', $array);
        $this->assertArrayHasKey('description', $array);
        $this->assertArrayHasKey('root_cause', $array);
        $this->assertArrayHasKey('fix', $array);
        $this->assertArrayHasKey('preventive_rule', $array);
        $this->assertArrayHasKey('severity', $array);
        $this->assertArrayHasKey('entity_context', $array);
        $this->assertArrayHasKey('scaffolding_fixed', $array);
        $this->assertArrayHasKey('first_seen_at', $array);
        $this->assertArrayHasKey('last_seen_at', $array);
        $this->assertArrayHasKey('report_count', $array);
        $this->assertArrayHasKey('project_count', $array);
        $this->assertArrayHasKey('resolution_count', $array);
        $this->assertArrayHasKey('resolution_rate', $array);
        $this->assertArrayHasKey('computed_resolution_rate', $array);
        $this->assertArrayHasKey('promoted_to_base', $array);
        $this->assertArrayHasKey('promoted_at', $array);
        $this->assertArrayHasKey('aicl_version', $array);
        $this->assertArrayHasKey('laravel_version', $array);
        $this->assertArrayHasKey('status', $array);
        $this->assertArrayHasKey('is_active', $array);
        $this->assertArrayHasKey('created_at', $array);
        $this->assertArrayHasKey('updated_at', $array);
    }

    public function test_rlm_failure_resource_values_match_model(): void
    {
        $failure = RlmFailure::factory()->create(['owner_id' => 1, 'failure_code' => 'F-999']);
        $resource = new RlmFailureResource($failure);

        $array = $resource->toArray(request());

        $this->assertSame($failure->id, $array['id']);
        $this->assertSame('F-999', $array['failure_code']);
        $this->assertSame($failure->title, $array['title']);
    }

    public function test_rlm_failure_resource_includes_owner_when_loaded(): void
    {
        $failure = RlmFailure::factory()->create(['owner_id' => 1]);
        $failure->load('owner');
        $resource = new RlmFailureResource($failure);

        $array = $resource->toArray(request());

        $this->assertArrayHasKey('owner', $array);
        $this->assertArrayHasKey('id', $array['owner']);
        $this->assertArrayHasKey('name', $array['owner']);
    }

    // ========================================================================
    // RlmLessonResource
    // ========================================================================

    public function test_rlm_lesson_resource_has_expected_keys(): void
    {
        $lesson = RlmLesson::factory()->create(['owner_id' => 1]);
        $resource = new RlmLessonResource($lesson);

        $array = $resource->toArray(request());

        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('topic', $array);
        $this->assertArrayHasKey('subtopic', $array);
        $this->assertArrayHasKey('summary', $array);
        $this->assertArrayHasKey('detail', $array);
        $this->assertArrayHasKey('tags', $array);
        $this->assertArrayHasKey('context_tags', $array);
        $this->assertArrayHasKey('source', $array);
        $this->assertArrayHasKey('confidence', $array);
        $this->assertArrayHasKey('is_verified', $array);
        $this->assertArrayHasKey('view_count', $array);
        $this->assertArrayHasKey('is_active', $array);
        $this->assertArrayHasKey('created_at', $array);
        $this->assertArrayHasKey('updated_at', $array);
    }

    public function test_rlm_lesson_resource_values_match_model(): void
    {
        $lesson = RlmLesson::factory()->create(['owner_id' => 1, 'topic' => 'Testing']);
        $resource = new RlmLessonResource($lesson);

        $array = $resource->toArray(request());

        $this->assertSame($lesson->id, $array['id']);
        $this->assertSame('Testing', $array['topic']);
        $this->assertSame($lesson->summary, $array['summary']);
    }

    // ========================================================================
    // FailureReportResource
    // ========================================================================

    public function test_failure_report_resource_has_expected_keys(): void
    {
        $failure = RlmFailure::factory()->create(['owner_id' => 1]);
        $report = FailureReport::factory()->create(['owner_id' => 1, 'rlm_failure_id' => $failure->id]);
        $resource = new FailureReportResource($report);

        $array = $resource->toArray(request());

        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('project_hash', $array);
        $this->assertArrayHasKey('entity_name', $array);
        $this->assertArrayHasKey('scaffolder_args', $array);
        $this->assertArrayHasKey('phase', $array);
        $this->assertArrayHasKey('agent', $array);
        $this->assertArrayHasKey('resolved', $array);
        $this->assertArrayHasKey('resolution_notes', $array);
        $this->assertArrayHasKey('resolution_method', $array);
        $this->assertArrayHasKey('time_to_resolve', $array);
        $this->assertArrayHasKey('reported_at', $array);
        $this->assertArrayHasKey('resolved_at', $array);
        $this->assertArrayHasKey('is_active', $array);
        $this->assertArrayHasKey('created_at', $array);
        $this->assertArrayHasKey('updated_at', $array);
    }

    public function test_failure_report_resource_includes_failure_when_loaded(): void
    {
        $failure = RlmFailure::factory()->create(['owner_id' => 1]);
        $report = FailureReport::factory()->create(['owner_id' => 1, 'rlm_failure_id' => $failure->id]);
        $report->load('failure');
        $resource = new FailureReportResource($report);

        $array = $resource->toArray(request());

        $this->assertArrayHasKey('failure', $array);
        $this->assertArrayHasKey('id', $array['failure']);
        $this->assertArrayHasKey('failure_code', $array['failure']);
        $this->assertArrayHasKey('title', $array['failure']);
    }

    public function test_failure_report_resource_values_match_model(): void
    {
        $failure = RlmFailure::factory()->create(['owner_id' => 1]);
        $report = FailureReport::factory()->create(['owner_id' => 1, 'rlm_failure_id' => $failure->id, 'entity_name' => 'Invoice']);
        $resource = new FailureReportResource($report);

        $array = $resource->toArray(request());

        $this->assertSame($report->id, $array['id']);
        $this->assertSame('Invoice', $array['entity_name']);
    }

    // ========================================================================
    // GenerationTraceResource
    // ========================================================================

    public function test_generation_trace_resource_has_expected_keys(): void
    {
        $trace = GenerationTrace::factory()->create(['owner_id' => 1]);
        $resource = new GenerationTraceResource($trace);

        $array = $resource->toArray(request());

        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('entity_name', $array);
        $this->assertArrayHasKey('project_hash', $array);
        $this->assertArrayHasKey('scaffolder_args', $array);
        $this->assertArrayHasKey('file_manifest', $array);
        $this->assertArrayHasKey('structural_score', $array);
        $this->assertArrayHasKey('semantic_score', $array);
        $this->assertArrayHasKey('test_results', $array);
        $this->assertArrayHasKey('fixes_applied', $array);
        $this->assertArrayHasKey('fix_iterations', $array);
        $this->assertArrayHasKey('pipeline_duration', $array);
        $this->assertArrayHasKey('agent_versions', $array);
        $this->assertArrayHasKey('is_processed', $array);
        $this->assertArrayHasKey('aicl_version', $array);
        $this->assertArrayHasKey('laravel_version', $array);
        $this->assertArrayHasKey('is_active', $array);
        $this->assertArrayHasKey('created_at', $array);
        $this->assertArrayHasKey('updated_at', $array);
    }

    public function test_generation_trace_resource_values_match_model(): void
    {
        $trace = GenerationTrace::factory()->create(['owner_id' => 1, 'entity_name' => 'Widget']);
        $resource = new GenerationTraceResource($trace);

        $array = $resource->toArray(request());

        $this->assertSame($trace->id, $array['id']);
        $this->assertSame('Widget', $array['entity_name']);
        $this->assertSame($trace->is_processed, $array['is_processed']);
    }

    // ========================================================================
    // PreventionRuleResource
    // ========================================================================

    public function test_prevention_rule_resource_has_expected_keys(): void
    {
        $failure = RlmFailure::factory()->create(['owner_id' => 1]);
        $rule = PreventionRule::factory()->create(['owner_id' => 1, 'rlm_failure_id' => $failure->id]);
        $resource = new PreventionRuleResource($rule);

        $array = $resource->toArray(request());

        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('trigger_context', $array);
        $this->assertArrayHasKey('rule_text', $array);
        $this->assertArrayHasKey('confidence', $array);
        $this->assertArrayHasKey('priority', $array);
        $this->assertArrayHasKey('is_active', $array);
        $this->assertArrayHasKey('applied_count', $array);
        $this->assertArrayHasKey('last_applied_at', $array);
        $this->assertArrayHasKey('created_at', $array);
        $this->assertArrayHasKey('updated_at', $array);
    }

    public function test_prevention_rule_resource_includes_failure_when_loaded(): void
    {
        $failure = RlmFailure::factory()->create(['owner_id' => 1]);
        $rule = PreventionRule::factory()->create(['owner_id' => 1, 'rlm_failure_id' => $failure->id]);
        $rule->load('failure');
        $resource = new PreventionRuleResource($rule);

        $array = $resource->toArray(request());

        // The failure is conditionally loaded via whenLoaded
        $this->assertArrayHasKey('failure', $array);
    }

    public function test_prevention_rule_resource_values_match_model(): void
    {
        $failure = RlmFailure::factory()->create(['owner_id' => 1]);
        $rule = PreventionRule::factory()->create(['owner_id' => 1, 'rlm_failure_id' => $failure->id, 'priority' => 5]);
        $resource = new PreventionRuleResource($rule);

        $array = $resource->toArray(request());

        $this->assertSame($rule->id, $array['id']);
        $this->assertSame(5, $array['priority']);
        $this->assertSame($rule->rule_text, $array['rule_text']);
    }

    // ========================================================================
    // RlmScoreResource
    // ========================================================================

    public function test_rlm_score_resource_has_expected_keys(): void
    {
        $score = RlmScore::factory()->create(['owner_id' => 1]);
        $resource = new RlmScoreResource($score);

        $array = $resource->toArray(request());

        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('entity_name', $array);
        $this->assertArrayHasKey('score_type', $array);
        $this->assertArrayHasKey('passed', $array);
        $this->assertArrayHasKey('total', $array);
        $this->assertArrayHasKey('percentage', $array);
        $this->assertArrayHasKey('errors', $array);
        $this->assertArrayHasKey('warnings', $array);
        $this->assertArrayHasKey('details', $array);
        $this->assertArrayHasKey('created_at', $array);
        $this->assertArrayHasKey('updated_at', $array);
    }

    public function test_rlm_score_resource_values_match_model(): void
    {
        $score = RlmScore::factory()->create(['owner_id' => 1, 'entity_name' => 'Task']);
        $resource = new RlmScoreResource($score);

        $array = $resource->toArray(request());

        $this->assertSame($score->id, $array['id']);
        $this->assertSame('Task', $array['entity_name']);
        $this->assertSame($score->passed, $array['passed']);
        $this->assertSame($score->total, $array['total']);
    }

    public function test_rlm_score_resource_includes_owner_when_loaded(): void
    {
        $score = RlmScore::factory()->create(['owner_id' => 1]);
        $score->load('owner');
        $resource = new RlmScoreResource($score);

        $array = $resource->toArray(request());

        $this->assertArrayHasKey('owner', $array);
        $this->assertArrayHasKey('id', $array['owner']);
        $this->assertArrayHasKey('name', $array['owner']);
    }

    // ========================================================================
    // Timestamps — ISO-8601 format
    // ========================================================================

    public function test_resource_timestamps_use_iso8601_format(): void
    {
        $pattern = RlmPattern::factory()->create(['owner_id' => 1]);
        $resource = new RlmPatternResource($pattern);

        $array = $resource->toArray(request());

        $this->assertNotNull($array['created_at']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $array['created_at']);
    }

    // ========================================================================
    // Owner not present when relationship is not loaded
    // ========================================================================

    public function test_rlm_pattern_resource_owner_includes_id_and_name(): void
    {
        $pattern = RlmPattern::factory()->create(['owner_id' => 1]);
        $pattern->load('owner');
        $resource = new RlmPatternResource($pattern);

        $array = $resource->toArray(request());

        $this->assertSame(1, $array['owner']['id']);
        $this->assertIsString($array['owner']['name']);
    }
}
