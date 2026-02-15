<?php

namespace Aicl\Tests\Unit\Rlm;

use Aicl\Models\GoldenAnnotation;
use Aicl\Models\PreventionRule;
use Aicl\Models\RlmFailure;
use Aicl\Models\RlmLesson;
use Aicl\Models\RlmScore;
use Aicl\Rlm\KnowledgeService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * S-006a: Feature tests for KnowledgeService::recall().
 *
 * Tests cheatsheet, full, and json formats via the recall method.
 * ES is unavailable in tests, so deterministic fallback is exercised.
 */
class KnowledgeServiceRecallTest extends TestCase
{
    use RefreshDatabase;

    protected KnowledgeService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        Http::fake(['*' => Http::response('', 500)]); // ES unavailable

        User::factory()->create(['id' => 1]);

        $this->service = app(KnowledgeService::class);
        $this->service->resetAvailabilityCache();
    }

    // ========================================================================
    // recall() returns expected structure
    // ========================================================================

    public function test_recall_returns_array_with_expected_keys(): void
    {
        $result = $this->service->recall('architect', 3);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('failures', $result);
        $this->assertArrayHasKey('lessons', $result);
        $this->assertArrayHasKey('scores', $result);
        $this->assertArrayHasKey('prevention_rules', $result);
        $this->assertArrayHasKey('golden_annotations', $result);
        $this->assertArrayHasKey('risk_briefing', $result);
    }

    public function test_recall_returns_collections_for_data_keys(): void
    {
        $result = $this->service->recall('architect', 3);

        $this->assertInstanceOf(Collection::class, $result['failures']);
        $this->assertInstanceOf(Collection::class, $result['lessons']);
        $this->assertInstanceOf(Collection::class, $result['scores']);
        $this->assertInstanceOf(Collection::class, $result['prevention_rules']);
        $this->assertInstanceOf(Collection::class, $result['golden_annotations']);
    }

    public function test_recall_risk_briefing_has_expected_structure(): void
    {
        $result = $this->service->recall('architect', 3);

        $this->assertArrayHasKey('high_risk', $result['risk_briefing']);
        $this->assertArrayHasKey('prevention_rules', $result['risk_briefing']);
        $this->assertArrayHasKey('recent_outcomes', $result['risk_briefing']);
    }

    // ========================================================================
    // recall() with failures
    // ========================================================================

    public function test_recall_includes_active_failures(): void
    {
        RlmFailure::factory()->create([
            'is_active' => true,
            'title' => 'Active failure for recall test',
            'owner_id' => 1,
        ]);
        RlmFailure::factory()->inactive()->create([
            'title' => 'Inactive failure should not appear',
            'owner_id' => 1,
        ]);

        $result = $this->service->recall('architect', 3);

        $this->assertGreaterThanOrEqual(1, $result['failures']->count());
        $titles = $result['failures']->pluck('title')->toArray();
        $this->assertContains('Active failure for recall test', $titles);
    }

    // ========================================================================
    // recall() with lessons — topic-based retrieval
    // ========================================================================

    public function test_recall_includes_lessons_matching_agent_topics(): void
    {
        // architect agent maps to topics: scaffolder, filament, laravel, testing, octane
        RlmLesson::factory()->create([
            'topic' => 'scaffolder',
            'summary' => 'Scaffolder lesson for architect recall',
            'is_active' => true,
            'owner_id' => 1,
        ]);
        RlmLesson::factory()->create([
            'topic' => 'unrelated_topic',
            'summary' => 'Unrelated topic lesson',
            'is_active' => true,
            'owner_id' => 1,
        ]);

        $result = $this->service->recall('architect', 3);

        $summaries = $result['lessons']->pluck('summary')->toArray();
        $this->assertContains('Scaffolder lesson for architect recall', $summaries);
    }

    public function test_recall_tester_agent_gets_testing_topic_lessons(): void
    {
        RlmLesson::factory()->create([
            'topic' => 'testing',
            'summary' => 'Testing lesson for tester agent',
            'is_active' => true,
            'owner_id' => 1,
        ]);

        $result = $this->service->recall('tester', 4);

        $summaries = $result['lessons']->pluck('summary')->toArray();
        $this->assertContains('Testing lesson for tester agent', $summaries);
    }

    // ========================================================================
    // recall() with phase filtering
    // ========================================================================

    public function test_recall_phase_3_includes_generation_topics(): void
    {
        // Phase 3 maps to: scaffolder, generation, filament, models, migrations
        RlmLesson::factory()->create([
            'topic' => 'filament',
            'summary' => 'Filament lesson for phase 3',
            'is_active' => true,
            'owner_id' => 1,
        ]);

        $result = $this->service->recall('architect', 3);

        $summaries = $result['lessons']->pluck('summary')->toArray();
        $this->assertContains('Filament lesson for phase 3', $summaries);
    }

    public function test_recall_phase_7_includes_testing_topics(): void
    {
        // Phase 7 maps to: testing, regression, integration
        RlmLesson::factory()->create([
            'topic' => 'testing',
            'summary' => 'Testing lesson for phase 7',
            'is_active' => true,
            'owner_id' => 1,
        ]);

        $result = $this->service->recall('architect', 7);

        $summaries = $result['lessons']->pluck('summary')->toArray();
        $this->assertContains('Testing lesson for phase 7', $summaries);
    }

    // ========================================================================
    // recall() with entity context
    // ========================================================================

    public function test_recall_with_entity_context_returns_matching_prevention_rules(): void
    {
        PreventionRule::factory()->create([
            'trigger_context' => ['has_states' => true],
            'rule_text' => 'Verify state transitions match design',
            'is_active' => true,
            'confidence' => 0.95,
            'priority' => 8,
            'owner_id' => 1,
        ]);
        PreventionRule::factory()->create([
            'trigger_context' => ['has_media' => true],
            'rule_text' => 'Media rule should not match',
            'is_active' => true,
            'confidence' => 0.85,
            'owner_id' => 1,
        ]);

        $result = $this->service->recall('architect', 3, ['has_states' => true]);

        $ruleTexts = $result['prevention_rules']->pluck('rule_text')->toArray();
        $this->assertContains('Verify state transitions match design', $ruleTexts);
    }

    public function test_recall_with_entity_context_returns_golden_annotations(): void
    {
        GoldenAnnotation::factory()->create([
            'feature_tags' => ['states'],
            'annotation_text' => 'States annotation for recall test',
            'is_active' => true,
            'owner_id' => 1,
        ]);

        $result = $this->service->recall('architect', 3, ['has_states' => true]);

        $annotations = $result['golden_annotations']->pluck('annotation_text')->toArray();
        $this->assertContains('States annotation for recall test', $annotations);
    }

    public function test_recall_always_includes_universal_golden_annotations(): void
    {
        GoldenAnnotation::factory()->universal()->create([
            'annotation_text' => 'Universal annotation always included',
            'is_active' => true,
            'owner_id' => 1,
        ]);

        $result = $this->service->recall('architect', 3);

        $annotations = $result['golden_annotations']->pluck('annotation_text')->toArray();
        $this->assertContains('Universal annotation always included', $annotations);
    }

    // ========================================================================
    // recall() with entity name — scores
    // ========================================================================

    public function test_recall_with_entity_name_includes_scores(): void
    {
        RlmScore::factory()->create([
            'entity_name' => 'Invoice',
            'percentage' => 95.24,
            'owner_id' => 1,
        ]);
        RlmScore::factory()->create([
            'entity_name' => 'OtherEntity',
            'percentage' => 80.00,
            'owner_id' => 1,
        ]);

        $result = $this->service->recall('architect', 3, null, 'Invoice');

        $this->assertGreaterThanOrEqual(1, $result['scores']->count());
        $entityNames = $result['scores']->pluck('entity_name')->toArray();
        $this->assertContains('Invoice', $entityNames);
        $this->assertNotContains('OtherEntity', $entityNames);
    }

    public function test_recall_without_entity_name_returns_empty_scores(): void
    {
        RlmScore::factory()->create(['entity_name' => 'Invoice', 'owner_id' => 1]);

        $result = $this->service->recall('architect', 3);

        $this->assertTrue($result['scores']->isEmpty());
    }

    // ========================================================================
    // recall() risk briefing population
    // ========================================================================

    public function test_recall_risk_briefing_populates_high_risk_from_failures(): void
    {
        RlmFailure::factory()->create([
            'is_active' => true,
            'title' => 'High risk failure',
            'report_count' => 25,
            'owner_id' => 1,
        ]);

        $result = $this->service->recall('architect', 3);

        $this->assertNotEmpty($result['risk_briefing']['high_risk']);
        $titles = array_column($result['risk_briefing']['high_risk'], 'title');
        $this->assertContains('High risk failure', $titles);
    }

    public function test_recall_risk_briefing_populates_prevention_rules(): void
    {
        PreventionRule::factory()->create([
            'rule_text' => 'Rule for risk briefing',
            'is_active' => true,
            'confidence' => 0.9,
            'priority' => 5,
            'owner_id' => 1,
        ]);

        $result = $this->service->recall('architect', 3);

        $this->assertNotEmpty($result['risk_briefing']['prevention_rules']);
        $ruleTexts = array_column($result['risk_briefing']['prevention_rules'], 'rule_text');
        $this->assertContains('Rule for risk briefing', $ruleTexts);
    }

    // ========================================================================
    // recall() with different agents
    // ========================================================================

    public function test_recall_solutions_agent_gets_architecture_topics(): void
    {
        RlmLesson::factory()->create([
            'topic' => 'architecture',
            'summary' => 'Architecture lesson for solutions agent',
            'is_active' => true,
            'owner_id' => 1,
        ]);

        $result = $this->service->recall('solutions', 2);

        $summaries = $result['lessons']->pluck('summary')->toArray();
        $this->assertContains('Architecture lesson for solutions agent', $summaries);
    }

    public function test_recall_designer_agent_gets_tailwind_topics(): void
    {
        RlmLesson::factory()->create([
            'topic' => 'tailwind',
            'summary' => 'Tailwind lesson for designer agent',
            'is_active' => true,
            'owner_id' => 1,
        ]);

        $result = $this->service->recall('designer', 3);

        $summaries = $result['lessons']->pluck('summary')->toArray();
        $this->assertContains('Tailwind lesson for designer agent', $summaries);
    }
}
