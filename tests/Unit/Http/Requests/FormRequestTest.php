<?php

namespace Aicl\Tests\Unit\Http\Requests;

use Aicl\Http\Requests\StoreFailureReportRequest;
use Aicl\Http\Requests\StoreGenerationTraceRequest;
use Aicl\Http\Requests\StorePreventionRuleRequest;
use Aicl\Http\Requests\StoreRlmFailureRequest;
use Aicl\Http\Requests\StoreRlmLessonRequest;
use Aicl\Http\Requests\StoreRlmPatternRequest;
use Aicl\Http\Requests\StoreRlmScoreRequest;
use Aicl\Http\Requests\UpdateFailureReportRequest;
use Aicl\Http\Requests\UpdateGenerationTraceRequest;
use Aicl\Http\Requests\UpdatePreventionRuleRequest;
use Aicl\Http\Requests\UpdateRlmFailureRequest;
use Aicl\Http\Requests\UpdateRlmLessonRequest;
use Aicl\Http\Requests\UpdateRlmPatternRequest;
use Aicl\Http\Requests\UpdateRlmScoreRequest;
use Tests\TestCase;

class FormRequestTest extends TestCase
{
    // ========================================================================
    // StoreRlmPatternRequest
    // ========================================================================

    public function test_store_rlm_pattern_request_has_required_rules(): void
    {
        $request = new StoreRlmPatternRequest;
        $rules = $request->rules();

        $this->assertArrayHasKey('name', $rules);
        $this->assertArrayHasKey('description', $rules);
        $this->assertArrayHasKey('target', $rules);
        $this->assertArrayHasKey('check_regex', $rules);
        $this->assertArrayHasKey('severity', $rules);
        $this->assertArrayHasKey('category', $rules);
        $this->assertArrayHasKey('source', $rules);
    }

    public function test_store_rlm_pattern_request_name_is_required_unique_string(): void
    {
        $request = new StoreRlmPatternRequest;
        $rules = $request->rules();

        $this->assertContains('required', $rules['name']);
        $this->assertContains('string', $rules['name']);
        $this->assertContains('max:255', $rules['name']);
        $this->assertContains('unique:rlm_patterns,name', $rules['name']);
    }

    public function test_store_rlm_pattern_request_weight_is_nullable_numeric(): void
    {
        $request = new StoreRlmPatternRequest;
        $rules = $request->rules();

        $this->assertArrayHasKey('weight', $rules);
        $this->assertContains('nullable', $rules['weight']);
        $this->assertContains('numeric', $rules['weight']);
        $this->assertContains('min:0', $rules['weight']);
        $this->assertContains('max:10', $rules['weight']);
    }

    // ========================================================================
    // UpdateRlmPatternRequest
    // ========================================================================

    public function test_update_rlm_pattern_request_has_rules(): void
    {
        $request = new UpdateRlmPatternRequest;
        $rules = $request->rules();

        $this->assertArrayHasKey('name', $rules);
        $this->assertArrayHasKey('description', $rules);
        $this->assertArrayHasKey('target', $rules);
        $this->assertArrayHasKey('check_regex', $rules);
        $this->assertArrayHasKey('severity', $rules);
        $this->assertArrayHasKey('category', $rules);
        $this->assertArrayHasKey('source', $rules);
    }

    public function test_update_rlm_pattern_request_fields_use_sometimes(): void
    {
        $request = new UpdateRlmPatternRequest;
        $rules = $request->rules();

        $this->assertContains('sometimes', $rules['name']);
        $this->assertContains('sometimes', $rules['description']);
        $this->assertContains('sometimes', $rules['target']);
        $this->assertContains('sometimes', $rules['check_regex']);
        $this->assertContains('sometimes', $rules['severity']);
        $this->assertContains('sometimes', $rules['category']);
        $this->assertContains('sometimes', $rules['source']);
    }

    // ========================================================================
    // StoreRlmFailureRequest
    // ========================================================================

    public function test_store_rlm_failure_request_has_required_rules(): void
    {
        $request = new StoreRlmFailureRequest;
        $rules = $request->rules();

        $this->assertArrayHasKey('failure_code', $rules);
        $this->assertArrayHasKey('category', $rules);
        $this->assertArrayHasKey('title', $rules);
        $this->assertArrayHasKey('description', $rules);
        $this->assertArrayHasKey('severity', $rules);
    }

    public function test_store_rlm_failure_request_failure_code_is_required_unique(): void
    {
        $request = new StoreRlmFailureRequest;
        $rules = $request->rules();

        $this->assertContains('required', $rules['failure_code']);
        $this->assertContains('string', $rules['failure_code']);
        $this->assertContains('unique:rlm_failures,failure_code', $rules['failure_code']);
    }

    public function test_store_rlm_failure_request_has_optional_fields(): void
    {
        $request = new StoreRlmFailureRequest;
        $rules = $request->rules();

        $this->assertArrayHasKey('pattern_id', $rules);
        $this->assertArrayHasKey('subcategory', $rules);
        $this->assertArrayHasKey('root_cause', $rules);
        $this->assertArrayHasKey('fix', $rules);
        $this->assertArrayHasKey('preventive_rule', $rules);
        $this->assertArrayHasKey('entity_context', $rules);
        $this->assertArrayHasKey('scaffolding_fixed', $rules);
        $this->assertArrayHasKey('first_seen_at', $rules);
        $this->assertArrayHasKey('last_seen_at', $rules);
        $this->assertArrayHasKey('aicl_version', $rules);
        $this->assertArrayHasKey('laravel_version', $rules);
        $this->assertArrayHasKey('status', $rules);
        $this->assertArrayHasKey('is_active', $rules);
    }

    // ========================================================================
    // UpdateRlmFailureRequest
    // ========================================================================

    public function test_update_rlm_failure_request_has_rules(): void
    {
        $request = new UpdateRlmFailureRequest;
        $rules = $request->rules();

        $this->assertArrayHasKey('failure_code', $rules);
        $this->assertArrayHasKey('category', $rules);
        $this->assertArrayHasKey('title', $rules);
        $this->assertArrayHasKey('description', $rules);
        $this->assertArrayHasKey('severity', $rules);
    }

    public function test_update_rlm_failure_request_fields_use_sometimes(): void
    {
        $request = new UpdateRlmFailureRequest;
        $rules = $request->rules();

        $this->assertContains('sometimes', $rules['failure_code']);
        $this->assertContains('sometimes', $rules['category']);
        $this->assertContains('sometimes', $rules['title']);
        $this->assertContains('sometimes', $rules['description']);
        $this->assertContains('sometimes', $rules['severity']);
    }

    // ========================================================================
    // StoreRlmLessonRequest
    // ========================================================================

    public function test_store_rlm_lesson_request_has_required_rules(): void
    {
        $request = new StoreRlmLessonRequest;
        $rules = $request->rules();

        $this->assertArrayHasKey('topic', $rules);
        $this->assertArrayHasKey('summary', $rules);
        $this->assertArrayHasKey('detail', $rules);
        $this->assertContains('required', $rules['topic']);
        $this->assertContains('required', $rules['summary']);
        $this->assertContains('required', $rules['detail']);
    }

    public function test_store_rlm_lesson_request_has_optional_fields(): void
    {
        $request = new StoreRlmLessonRequest;
        $rules = $request->rules();

        $this->assertArrayHasKey('subtopic', $rules);
        $this->assertArrayHasKey('tags', $rules);
        $this->assertArrayHasKey('context_tags', $rules);
        $this->assertArrayHasKey('context_tags.*', $rules);
        $this->assertArrayHasKey('source', $rules);
        $this->assertArrayHasKey('confidence', $rules);
        $this->assertArrayHasKey('is_verified', $rules);
        $this->assertArrayHasKey('is_active', $rules);
    }

    public function test_store_rlm_lesson_request_confidence_is_bounded(): void
    {
        $request = new StoreRlmLessonRequest;
        $rules = $request->rules();

        $this->assertContains('numeric', $rules['confidence']);
        $this->assertContains('min:0', $rules['confidence']);
        $this->assertContains('max:1', $rules['confidence']);
    }

    // ========================================================================
    // UpdateRlmLessonRequest
    // ========================================================================

    public function test_update_rlm_lesson_request_has_rules(): void
    {
        $request = new UpdateRlmLessonRequest;
        $rules = $request->rules();

        $this->assertArrayHasKey('topic', $rules);
        $this->assertArrayHasKey('summary', $rules);
        $this->assertArrayHasKey('detail', $rules);
    }

    public function test_update_rlm_lesson_request_fields_use_sometimes(): void
    {
        $request = new UpdateRlmLessonRequest;
        $rules = $request->rules();

        $this->assertContains('sometimes', $rules['topic']);
        $this->assertContains('sometimes', $rules['summary']);
        $this->assertContains('sometimes', $rules['detail']);
    }

    // ========================================================================
    // StoreFailureReportRequest
    // ========================================================================

    public function test_store_failure_report_request_has_required_rules(): void
    {
        $request = new StoreFailureReportRequest;
        $rules = $request->rules();

        $this->assertArrayHasKey('rlm_failure_id', $rules);
        $this->assertArrayHasKey('project_hash', $rules);
        $this->assertArrayHasKey('entity_name', $rules);
        $this->assertArrayHasKey('reported_at', $rules);
        $this->assertContains('required', $rules['rlm_failure_id']);
        $this->assertContains('required', $rules['project_hash']);
        $this->assertContains('required', $rules['entity_name']);
        $this->assertContains('required', $rules['reported_at']);
    }

    public function test_store_failure_report_request_has_optional_fields(): void
    {
        $request = new StoreFailureReportRequest;
        $rules = $request->rules();

        $this->assertArrayHasKey('scaffolder_args', $rules);
        $this->assertArrayHasKey('phase', $rules);
        $this->assertArrayHasKey('agent', $rules);
        $this->assertArrayHasKey('resolved', $rules);
        $this->assertArrayHasKey('resolution_notes', $rules);
        $this->assertArrayHasKey('resolution_method', $rules);
        $this->assertArrayHasKey('time_to_resolve', $rules);
        $this->assertArrayHasKey('resolved_at', $rules);
        $this->assertArrayHasKey('is_active', $rules);
    }

    public function test_store_failure_report_request_resolved_at_must_be_after_reported_at(): void
    {
        $request = new StoreFailureReportRequest;
        $rules = $request->rules();

        $this->assertContains('after_or_equal:reported_at', $rules['resolved_at']);
    }

    // ========================================================================
    // UpdateFailureReportRequest
    // ========================================================================

    public function test_update_failure_report_request_has_rules(): void
    {
        $request = new UpdateFailureReportRequest;
        $rules = $request->rules();

        $this->assertArrayHasKey('rlm_failure_id', $rules);
        $this->assertArrayHasKey('project_hash', $rules);
        $this->assertArrayHasKey('entity_name', $rules);
        $this->assertArrayHasKey('reported_at', $rules);
    }

    public function test_update_failure_report_request_fields_use_sometimes(): void
    {
        $request = new UpdateFailureReportRequest;
        $rules = $request->rules();

        $this->assertContains('sometimes', $rules['rlm_failure_id']);
        $this->assertContains('sometimes', $rules['project_hash']);
        $this->assertContains('sometimes', $rules['entity_name']);
        $this->assertContains('sometimes', $rules['reported_at']);
    }

    // ========================================================================
    // StoreGenerationTraceRequest
    // ========================================================================

    public function test_store_generation_trace_request_has_required_rules(): void
    {
        $request = new StoreGenerationTraceRequest;
        $rules = $request->rules();

        $this->assertArrayHasKey('entity_name', $rules);
        $this->assertArrayHasKey('scaffolder_args', $rules);
        $this->assertContains('required', $rules['entity_name']);
        $this->assertContains('required', $rules['scaffolder_args']);
    }

    public function test_store_generation_trace_request_has_optional_fields(): void
    {
        $request = new StoreGenerationTraceRequest;
        $rules = $request->rules();

        $this->assertArrayHasKey('project_hash', $rules);
        $this->assertArrayHasKey('file_manifest', $rules);
        $this->assertArrayHasKey('file_manifest.*', $rules);
        $this->assertArrayHasKey('structural_score', $rules);
        $this->assertArrayHasKey('semantic_score', $rules);
        $this->assertArrayHasKey('test_results', $rules);
        $this->assertArrayHasKey('fixes_applied', $rules);
        $this->assertArrayHasKey('fixes_applied.*', $rules);
        $this->assertArrayHasKey('fix_iterations', $rules);
        $this->assertArrayHasKey('pipeline_duration', $rules);
        $this->assertArrayHasKey('agent_versions', $rules);
        $this->assertArrayHasKey('is_processed', $rules);
        $this->assertArrayHasKey('aicl_version', $rules);
        $this->assertArrayHasKey('laravel_version', $rules);
        $this->assertArrayHasKey('is_active', $rules);
    }

    public function test_store_generation_trace_request_scores_are_bounded(): void
    {
        $request = new StoreGenerationTraceRequest;
        $rules = $request->rules();

        $this->assertContains('min:0', $rules['structural_score']);
        $this->assertContains('max:100', $rules['structural_score']);
        $this->assertContains('min:0', $rules['semantic_score']);
        $this->assertContains('max:100', $rules['semantic_score']);
    }

    // ========================================================================
    // UpdateGenerationTraceRequest
    // ========================================================================

    public function test_update_generation_trace_request_has_rules(): void
    {
        $request = new UpdateGenerationTraceRequest;
        $rules = $request->rules();

        $this->assertArrayHasKey('entity_name', $rules);
        $this->assertArrayHasKey('scaffolder_args', $rules);
    }

    public function test_update_generation_trace_request_fields_use_sometimes(): void
    {
        $request = new UpdateGenerationTraceRequest;
        $rules = $request->rules();

        $this->assertContains('sometimes', $rules['entity_name']);
        $this->assertContains('sometimes', $rules['scaffolder_args']);
        $this->assertContains('sometimes', $rules['fix_iterations']);
    }

    // ========================================================================
    // StorePreventionRuleRequest
    // ========================================================================

    public function test_store_prevention_rule_request_has_required_rules(): void
    {
        $request = new StorePreventionRuleRequest;
        $rules = $request->rules();

        $this->assertArrayHasKey('rule_text', $rules);
        $this->assertArrayHasKey('priority', $rules);
        $this->assertContains('required', $rules['rule_text']);
        $this->assertContains('required', $rules['priority']);
    }

    public function test_store_prevention_rule_request_has_optional_fields(): void
    {
        $request = new StorePreventionRuleRequest;
        $rules = $request->rules();

        $this->assertArrayHasKey('rlm_failure_id', $rules);
        $this->assertArrayHasKey('trigger_context', $rules);
        $this->assertArrayHasKey('confidence', $rules);
        $this->assertArrayHasKey('is_active', $rules);
        $this->assertArrayHasKey('applied_count', $rules);
        $this->assertArrayHasKey('last_applied_at', $rules);
    }

    public function test_store_prevention_rule_request_confidence_is_bounded(): void
    {
        $request = new StorePreventionRuleRequest;
        $rules = $request->rules();

        $this->assertContains('min:0', $rules['confidence']);
        $this->assertContains('max:1', $rules['confidence']);
    }

    // ========================================================================
    // UpdatePreventionRuleRequest
    // ========================================================================

    public function test_update_prevention_rule_request_has_rules(): void
    {
        $request = new UpdatePreventionRuleRequest;
        $rules = $request->rules();

        $this->assertArrayHasKey('rule_text', $rules);
        $this->assertArrayHasKey('priority', $rules);
    }

    public function test_update_prevention_rule_request_fields_use_sometimes(): void
    {
        $request = new UpdatePreventionRuleRequest;
        $rules = $request->rules();

        $this->assertContains('sometimes', $rules['rule_text']);
        $this->assertContains('sometimes', $rules['priority']);
        $this->assertContains('sometimes', $rules['applied_count']);
    }

    // ========================================================================
    // StoreRlmScoreRequest
    // ========================================================================

    public function test_store_rlm_score_request_has_required_rules(): void
    {
        $request = new StoreRlmScoreRequest;
        $rules = $request->rules();

        $this->assertArrayHasKey('entity_name', $rules);
        $this->assertArrayHasKey('score_type', $rules);
        $this->assertArrayHasKey('passed', $rules);
        $this->assertArrayHasKey('total', $rules);
        $this->assertArrayHasKey('percentage', $rules);
        $this->assertContains('required', $rules['entity_name']);
        $this->assertContains('required', $rules['score_type']);
        $this->assertContains('required', $rules['passed']);
        $this->assertContains('required', $rules['total']);
        $this->assertContains('required', $rules['percentage']);
    }

    public function test_store_rlm_score_request_percentage_is_bounded(): void
    {
        $request = new StoreRlmScoreRequest;
        $rules = $request->rules();

        $this->assertContains('numeric', $rules['percentage']);
        $this->assertContains('min:0', $rules['percentage']);
        $this->assertContains('max:100', $rules['percentage']);
    }

    public function test_store_rlm_score_request_has_optional_fields(): void
    {
        $request = new StoreRlmScoreRequest;
        $rules = $request->rules();

        $this->assertArrayHasKey('errors', $rules);
        $this->assertArrayHasKey('warnings', $rules);
        $this->assertArrayHasKey('details', $rules);
    }

    // ========================================================================
    // UpdateRlmScoreRequest
    // ========================================================================

    public function test_update_rlm_score_request_has_rules(): void
    {
        $request = new UpdateRlmScoreRequest;
        $rules = $request->rules();

        $this->assertArrayHasKey('entity_name', $rules);
        $this->assertArrayHasKey('score_type', $rules);
        $this->assertArrayHasKey('passed', $rules);
        $this->assertArrayHasKey('total', $rules);
        $this->assertArrayHasKey('percentage', $rules);
    }

    public function test_update_rlm_score_request_fields_use_sometimes(): void
    {
        $request = new UpdateRlmScoreRequest;
        $rules = $request->rules();

        $this->assertContains('sometimes', $rules['entity_name']);
        $this->assertContains('sometimes', $rules['score_type']);
        $this->assertContains('sometimes', $rules['passed']);
        $this->assertContains('sometimes', $rules['total']);
        $this->assertContains('sometimes', $rules['percentage']);
        $this->assertContains('sometimes', $rules['errors']);
        $this->assertContains('sometimes', $rules['warnings']);
    }

    // ========================================================================
    // messages() — all requests return an array
    // ========================================================================

    public function test_all_store_requests_return_messages_array(): void
    {
        $requests = [
            new StoreRlmPatternRequest,
            new StoreRlmFailureRequest,
            new StoreRlmLessonRequest,
            new StoreFailureReportRequest,
            new StoreGenerationTraceRequest,
            new StorePreventionRuleRequest,
            new StoreRlmScoreRequest,
        ];

        foreach ($requests as $request) {
            $this->assertIsArray($request->messages(), get_class($request).' messages() should return an array');
        }
    }

    public function test_all_update_requests_return_messages_array(): void
    {
        $requests = [
            new UpdateRlmPatternRequest,
            new UpdateRlmFailureRequest,
            new UpdateRlmLessonRequest,
            new UpdateFailureReportRequest,
            new UpdateGenerationTraceRequest,
            new UpdatePreventionRuleRequest,
            new UpdateRlmScoreRequest,
        ];

        foreach ($requests as $request) {
            $this->assertIsArray($request->messages(), get_class($request).' messages() should return an array');
        }
    }
}
