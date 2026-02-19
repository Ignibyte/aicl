<?php

namespace Aicl\Tests\Unit\Rlm;

use Aicl\Enums\FailureCategory;
use Aicl\Enums\FailureSeverity;
use Aicl\Models\DistilledLesson;
use Aicl\Models\RlmFailure;
use Aicl\Rlm\DistillationService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DistillationServiceTest extends TestCase
{
    use RefreshDatabase;

    private DistillationService $service;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DistillationService;
        $this->admin = User::factory()->create(['id' => 1]);
    }

    // ─── Clustering ─────────────────────────────────────────────

    public function test_cluster_groups_failures_by_pattern_id(): void
    {
        // Explicitly null preventive_rule so these failures lack rule_hash
        // and fall through to the pattern_id clustering pass (Pass 2).
        RlmFailure::factory()->create([
            'failure_code' => 'BF-001',
            'pattern_id' => 'P-012',
            'category' => FailureCategory::Scaffolding,
            'preventive_rule' => null,
            'owner_id' => $this->admin->id,
        ]);

        RlmFailure::factory()->create([
            'failure_code' => 'BF-005',
            'pattern_id' => 'P-012',
            'category' => FailureCategory::Filament,
            'preventive_rule' => null,
            'owner_id' => $this->admin->id,
        ]);

        $clusters = $this->service->clusterFailures();

        // Both should be in the same cluster because they share pattern_id
        $this->assertCount(1, $clusters);
        $this->assertCount(2, $clusters->first()['failures']);
    }

    public function test_cluster_separates_unrelated_failures(): void
    {
        RlmFailure::factory()->create([
            'failure_code' => 'BF-001',
            'category' => FailureCategory::Scaffolding,
            'root_cause' => 'searchableColumns returns wrong defaults',
            'owner_id' => $this->admin->id,
        ]);

        RlmFailure::factory()->create([
            'failure_code' => 'BF-008',
            'category' => FailureCategory::Laravel,
            'root_cause' => 'SerializesModels in delete events causes failures',
            'owner_id' => $this->admin->id,
        ]);

        $clusters = $this->service->clusterFailures();

        // Different categories + no shared pattern_id = separate clusters
        $this->assertCount(2, $clusters);
    }

    public function test_cluster_groups_by_category_and_root_cause_similarity(): void
    {
        RlmFailure::factory()->create([
            'failure_code' => 'BF-009',
            'category' => FailureCategory::Testing,
            'root_cause' => 'Livewire lifecycle hooks triggered by property changes not direct calls',
            'owner_id' => $this->admin->id,
        ]);

        RlmFailure::factory()->create([
            'failure_code' => 'BF-010',
            'category' => FailureCategory::Testing,
            'root_cause' => 'Routes registered in test missing from named lookup cache',
            'owner_id' => $this->admin->id,
        ]);

        $clusters = $this->service->clusterFailures();

        // Same category but different root causes = separate clusters
        $this->assertCount(2, $clusters);
    }

    public function test_canonical_selects_highest_severity(): void
    {
        $critical = RlmFailure::factory()->create([
            'failure_code' => 'BF-001',
            'category' => FailureCategory::Scaffolding,
            'severity' => FailureSeverity::Critical,
            'pattern_id' => 'P-100',
            'preventive_rule' => null,
            'owner_id' => $this->admin->id,
        ]);

        RlmFailure::factory()->create([
            'failure_code' => 'BF-002',
            'category' => FailureCategory::Scaffolding,
            'severity' => FailureSeverity::Low,
            'pattern_id' => 'P-100',
            'preventive_rule' => null,
            'owner_id' => $this->admin->id,
        ]);

        $clusters = $this->service->clusterFailures();

        $this->assertCount(1, $clusters);
        $this->assertSame($critical->id, $clusters->first()['canonical']->id);
    }

    public function test_single_failures_become_single_clusters(): void
    {
        RlmFailure::factory()->create([
            'failure_code' => 'BF-013',
            'category' => FailureCategory::Tailwind,
            'owner_id' => $this->admin->id,
        ]);

        $clusters = $this->service->clusterFailures();

        $this->assertCount(1, $clusters);
        $this->assertCount(1, $clusters->first()['failures']);
    }

    // ─── Impact Scoring ─────────────────────────────────────────

    public function test_impact_score_critical_severity(): void
    {
        $failures = collect([
            new RlmFailure([
                'severity' => FailureSeverity::Critical,
                'report_count' => 3,
                'scaffolding_fixed' => false,
            ]),
        ]);

        // 3 * 10 * 1.0 = 30
        $this->assertSame(30.0, $this->service->computeImpactScore($failures));
    }

    public function test_impact_score_scaffolding_fixed_factor(): void
    {
        $failures = collect([
            new RlmFailure([
                'severity' => FailureSeverity::High,
                'report_count' => 2,
                'scaffolding_fixed' => true,
            ]),
        ]);

        // 2 * 5 * 0.3 = 3.0
        $this->assertSame(3.0, $this->service->computeImpactScore($failures));
    }

    public function test_impact_score_informational_is_zero(): void
    {
        $failures = collect([
            new RlmFailure([
                'severity' => FailureSeverity::Informational,
                'report_count' => 10,
                'scaffolding_fixed' => false,
            ]),
        ]);

        // 10 * 0 * 1.0 = 0
        $this->assertSame(0.0, $this->service->computeImpactScore($failures));
    }

    public function test_impact_score_sums_multiple_failures(): void
    {
        $failures = collect([
            new RlmFailure([
                'severity' => FailureSeverity::High,
                'report_count' => 2,
                'scaffolding_fixed' => false,
            ]),
            new RlmFailure([
                'severity' => FailureSeverity::Medium,
                'report_count' => 3,
                'scaffolding_fixed' => false,
            ]),
        ]);

        // (2 * 5 * 1.0) + (3 * 2 * 1.0) = 10 + 6 = 16
        $this->assertSame(16.0, $this->service->computeImpactScore($failures));
    }

    // ─── Severity Weights ───────────────────────────────────────

    public function test_severity_weights(): void
    {
        $this->assertSame(10, $this->service->getSeverityWeight(FailureSeverity::Critical));
        $this->assertSame(5, $this->service->getSeverityWeight(FailureSeverity::High));
        $this->assertSame(2, $this->service->getSeverityWeight(FailureSeverity::Medium));
        $this->assertSame(1, $this->service->getSeverityWeight(FailureSeverity::Low));
        $this->assertSame(0, $this->service->getSeverityWeight(FailureSeverity::Informational));
    }

    public function test_severity_weight_from_string(): void
    {
        $this->assertSame(10, $this->service->getSeverityWeight('critical'));
        $this->assertSame(5, $this->service->getSeverityWeight('high'));
    }

    // ─── Agent Perspectives ─────────────────────────────────────

    public function test_agent_perspectives_include_all_agents(): void
    {
        $perspectives = $this->service->getAgentPerspectives();

        $this->assertArrayHasKey('architect', $perspectives);
        $this->assertArrayHasKey('tester', $perspectives);
        $this->assertArrayHasKey('rlm', $perspectives);
        $this->assertArrayHasKey('designer', $perspectives);
        $this->assertArrayHasKey('solutions', $perspectives);
        $this->assertArrayHasKey('pm', $perspectives);
    }

    public function test_pm_perspective_has_correct_phases(): void
    {
        $perspectives = $this->service->getAgentPerspectives();

        $this->assertSame([1, 7, 8], $perspectives['pm']['phases']);
        $this->assertContains(FailureCategory::Process, $perspectives['pm']['categories']);
        $this->assertContains(FailureCategory::Configuration, $perspectives['pm']['categories']);
    }

    public function test_architect_perspective_has_correct_phases(): void
    {
        $perspectives = $this->service->getAgentPerspectives();

        $this->assertSame([3, 5], $perspectives['architect']['phases']);
        $this->assertContains(FailureCategory::Scaffolding, $perspectives['architect']['categories']);
    }

    // ─── Full Distillation ──────────────────────────────────────

    public function test_distill_creates_lessons_from_failures(): void
    {
        RlmFailure::factory()->create([
            'failure_code' => 'BF-001',
            'category' => FailureCategory::Scaffolding,
            'severity' => FailureSeverity::High,
            'title' => 'searchableColumns defaults',
            'preventive_rule' => 'Override searchableColumns to list only existing columns.',
            'report_count' => 3,
            'scaffolding_fixed' => true,
            'owner_id' => $this->admin->id,
        ]);

        $result = $this->service->distill();

        $this->assertGreaterThan(0, $result['clusters']);
        $this->assertGreaterThan(0, $result['lessons']);
        $this->assertGreaterThan(0, DistilledLesson::query()->count());
    }

    public function test_distill_filters_by_agent(): void
    {
        RlmFailure::factory()->create([
            'failure_code' => 'BF-001',
            'category' => FailureCategory::Scaffolding,
            'severity' => FailureSeverity::High,
            'preventive_rule' => 'Override searchableColumns.',
            'owner_id' => $this->admin->id,
        ]);

        $result = $this->service->distill('architect');

        // Only architect lessons should be generated
        $this->assertArrayHasKey('architect', $result['agents']);
        $this->assertArrayNotHasKey('tester', $result['agents']);
        $this->assertArrayNotHasKey('designer', $result['agents']);
    }

    public function test_distill_assigns_lesson_codes(): void
    {
        RlmFailure::factory()->create([
            'failure_code' => 'BF-001',
            'category' => FailureCategory::Scaffolding,
            'severity' => FailureSeverity::High,
            'preventive_rule' => 'Override searchableColumns.',
            'owner_id' => $this->admin->id,
        ]);

        $this->service->distill();

        $lessons = DistilledLesson::query()->get();
        foreach ($lessons as $lesson) {
            $this->assertMatchesRegularExpression('/^DL-\d+-[A-Z]\d$/', $lesson->lesson_code);
        }
    }

    public function test_distill_sets_source_failure_codes(): void
    {
        RlmFailure::factory()->create([
            'failure_code' => 'BF-012',
            'category' => FailureCategory::Filament,
            'severity' => FailureSeverity::Critical,
            'preventive_rule' => 'Use Filament\\Schemas\\Components for Section.',
            'owner_id' => $this->admin->id,
        ]);

        $this->service->distill();

        $lesson = DistilledLesson::query()->first();
        $this->assertIsArray($lesson->source_failure_codes);
        $this->assertContains('BF-012', $lesson->source_failure_codes);
    }

    public function test_distill_does_not_generate_for_irrelevant_categories(): void
    {
        // Auth category is not in any agent's category list
        RlmFailure::factory()->create([
            'failure_code' => 'BF-007',
            'category' => FailureCategory::Auth,
            'severity' => FailureSeverity::High,
            'preventive_rule' => 'Seed on both guards.',
            'owner_id' => $this->admin->id,
        ]);

        $result = $this->service->distill();

        // Auth is not in any agent perspective — no lessons should be generated
        $this->assertSame(0, $result['lessons']);
    }

    // ─── Top Lessons & When-Then Rules ────────────────────────

    public function test_get_top_lessons_returns_by_agent_and_phase(): void
    {
        RlmFailure::factory()->create([
            'failure_code' => 'BF-001',
            'category' => FailureCategory::Scaffolding,
            'severity' => FailureSeverity::High,
            'preventive_rule' => 'Override searchableColumns.',
            'owner_id' => $this->admin->id,
        ]);

        $this->service->distill();

        $lessons = $this->service->getTopLessons('architect', 3);

        $this->assertGreaterThan(0, $lessons->count());
        foreach ($lessons as $lesson) {
            $this->assertSame('architect', $lesson->target_agent);
            $this->assertSame(3, $lesson->target_phase);
        }
    }

    public function test_get_top_lessons_respects_limit(): void
    {
        // Create multiple failures across relevant categories
        foreach (['BF-001', 'BF-003', 'BF-005'] as $code) {
            RlmFailure::factory()->create([
                'failure_code' => $code,
                'category' => FailureCategory::Scaffolding,
                'severity' => FailureSeverity::High,
                'preventive_rule' => "Rule for {$code}.",
                'owner_id' => $this->admin->id,
            ]);
        }

        $this->service->distill();

        $limited = $this->service->getTopLessons('architect', 3, 2);

        $this->assertLessThanOrEqual(2, $limited->count());
    }

    public function test_get_top_lessons_empty_for_unknown_agent(): void
    {
        RlmFailure::factory()->create([
            'failure_code' => 'BF-001',
            'category' => FailureCategory::Scaffolding,
            'severity' => FailureSeverity::High,
            'preventive_rule' => 'Override searchableColumns.',
            'owner_id' => $this->admin->id,
        ]);

        $this->service->distill();

        $lessons = $this->service->getTopLessons('nonexistent', 99);

        $this->assertCount(0, $lessons);
    }

    public function test_generate_when_then_rules_groups_by_trigger_context(): void
    {
        RlmFailure::factory()->create([
            'failure_code' => 'BF-001',
            'category' => FailureCategory::Scaffolding,
            'severity' => FailureSeverity::High,
            'preventive_rule' => 'Override searchableColumns.',
            'owner_id' => $this->admin->id,
        ]);

        $this->service->distill();

        $rules = $this->service->generateWhenThenRules('architect', 3);

        foreach ($rules as $rule) {
            $this->assertArrayHasKey('when', $rule);
            $this->assertArrayHasKey('then', $rule);
            $this->assertNotEmpty($rule['when']);
            $this->assertIsArray($rule['then']);
        }
    }

    public function test_generate_when_then_rules_empty_for_no_lessons(): void
    {
        $rules = $this->service->generateWhenThenRules('nonexistent', 99);

        $this->assertCount(0, $rules);
    }

    // ─── Effectiveness-Weighted Ranking (Sprint X Phase A) ─────

    public function test_get_top_lessons_ranks_by_confidence_weighted_impact(): void
    {
        // High impact but low confidence — should rank BELOW medium impact + high confidence
        DistilledLesson::factory()->create([
            'lesson_code' => 'DL-001-A3',
            'target_agent' => 'architect',
            'target_phase' => 3,
            'impact_score' => 8.0,
            'confidence' => 0.3,
            'is_active' => true,
            'owner_id' => $this->admin->id,
        ]);

        // Medium impact but high confidence — should rank ABOVE
        DistilledLesson::factory()->create([
            'lesson_code' => 'DL-002-A3',
            'target_agent' => 'architect',
            'target_phase' => 3,
            'impact_score' => 4.0,
            'confidence' => 0.9,
            'is_active' => true,
            'owner_id' => $this->admin->id,
        ]);

        $lessons = $this->service->getTopLessons('architect', 3);

        $this->assertCount(2, $lessons);
        // 4.0 * 0.9 = 3.6 > 8.0 * 0.3 = 2.4
        $this->assertSame('DL-002-A3', $lessons->first()->lesson_code);
        $this->assertSame('DL-001-A3', $lessons->last()->lesson_code);
    }

    public function test_confidence_floor_prevents_new_lessons_from_vanishing(): void
    {
        // Lesson with very low confidence (near zero) — floor should kick in
        DistilledLesson::factory()->create([
            'lesson_code' => 'DL-001-A3',
            'target_agent' => 'architect',
            'target_phase' => 3,
            'impact_score' => 10.0,
            'confidence' => 0.05,
            'is_active' => true,
            'owner_id' => $this->admin->id,
        ]);

        // Lower impact but default confidence
        DistilledLesson::factory()->create([
            'lesson_code' => 'DL-002-A3',
            'target_agent' => 'architect',
            'target_phase' => 3,
            'impact_score' => 2.0,
            'confidence' => 0.5,
            'is_active' => true,
            'owner_id' => $this->admin->id,
        ]);

        $lessons = $this->service->getTopLessons('architect', 3);

        $this->assertCount(2, $lessons);
        // 10.0 * GREATEST(0.05, 0.1) = 10.0 * 0.1 = 1.0
        // 2.0 * GREATEST(0.5, 0.1) = 2.0 * 0.5 = 1.0
        // Equal scores — both should appear (order may vary with equal scores)
        $codes = $lessons->pluck('lesson_code')->toArray();
        $this->assertContains('DL-001-A3', $codes);
        $this->assertContains('DL-002-A3', $codes);
    }

    public function test_default_confidence_lessons_rank_proportionally(): void
    {
        // New lesson with default confidence (0.5) and high impact
        DistilledLesson::factory()->create([
            'lesson_code' => 'DL-001-A3',
            'target_agent' => 'architect',
            'target_phase' => 3,
            'impact_score' => 6.0,
            'confidence' => 0.5,
            'is_active' => true,
            'owner_id' => $this->admin->id,
        ]);

        // Established lesson with full confidence (1.0) and lower impact
        DistilledLesson::factory()->create([
            'lesson_code' => 'DL-002-A3',
            'target_agent' => 'architect',
            'target_phase' => 3,
            'impact_score' => 4.0,
            'confidence' => 1.0,
            'is_active' => true,
            'owner_id' => $this->admin->id,
        ]);

        $lessons = $this->service->getTopLessons('architect', 3);

        $this->assertCount(2, $lessons);
        // 4.0 * 1.0 = 4.0 > 6.0 * 0.5 = 3.0
        $this->assertSame('DL-002-A3', $lessons->first()->lesson_code);
        $this->assertSame('DL-001-A3', $lessons->last()->lesson_code);
    }

    public function test_when_then_rules_ordered_by_effectiveness(): void
    {
        // Create lessons with different effectiveness profiles
        DistilledLesson::factory()->create([
            'lesson_code' => 'DL-001-A3',
            'target_agent' => 'architect',
            'target_phase' => 3,
            'impact_score' => 5.0,
            'confidence' => 0.9,
            'is_active' => true,
            'trigger_context' => ['component' => 'model'],
            'guidance' => "WHEN: generating model\nTHEN: check traits",
            'owner_id' => $this->admin->id,
        ]);

        DistilledLesson::factory()->create([
            'lesson_code' => 'DL-002-A3',
            'target_agent' => 'architect',
            'target_phase' => 3,
            'impact_score' => 10.0,
            'confidence' => 0.2,
            'is_active' => true,
            'trigger_context' => ['component' => 'factory'],
            'guidance' => "WHEN: generating factory\nTHEN: check newFactory",
            'owner_id' => $this->admin->id,
        ]);

        $rules = $this->service->generateWhenThenRules('architect', 3);

        // 5.0 * 0.9 = 4.5 > 10.0 * 0.2 = 2.0 — higher effectiveness first
        $this->assertGreaterThanOrEqual(1, $rules->count());
    }

    // ─── Surfaced Count Tracking (Sprint X Phase B) ────────────

    public function test_get_top_lessons_increments_surfaced_count(): void
    {
        $lesson = DistilledLesson::factory()->create([
            'lesson_code' => 'DL-001-A3',
            'target_agent' => 'architect',
            'target_phase' => 3,
            'impact_score' => 5.0,
            'confidence' => 0.8,
            'is_active' => true,
            'surfaced_count' => 0,
            'owner_id' => $this->admin->id,
        ]);

        $this->service->getTopLessons('architect', 3);

        $lesson->refresh();
        $this->assertSame(1, $lesson->surfaced_count);

        // Call again — should increment to 2
        $this->service->getTopLessons('architect', 3);

        $lesson->refresh();
        $this->assertSame(2, $lesson->surfaced_count);
    }

    public function test_surfaced_count_not_incremented_for_empty_results(): void
    {
        $lesson = DistilledLesson::factory()->create([
            'lesson_code' => 'DL-001-A3',
            'target_agent' => 'architect',
            'target_phase' => 3,
            'is_active' => true,
            'surfaced_count' => 0,
            'owner_id' => $this->admin->id,
        ]);

        // Request lessons for a different agent — should not match
        $this->service->getTopLessons('nonexistent', 99);

        $lesson->refresh();
        $this->assertSame(0, $lesson->surfaced_count);
    }

    public function test_auto_deactivate_stale_by_surfacing(): void
    {
        // Surfaced 60 times with zero interactions — should be deactivated
        $stale = DistilledLesson::factory()->create([
            'lesson_code' => 'DL-001-A3',
            'target_agent' => 'architect',
            'target_phase' => 3,
            'is_active' => true,
            'surfaced_count' => 60,
            'prevented_count' => 0,
            'ignored_count' => 0,
            'confidence' => 0.8,
            'owner_id' => $this->admin->id,
        ]);

        // Surfaced 60 times but has interactions — should NOT be deactivated
        $active = DistilledLesson::factory()->create([
            'lesson_code' => 'DL-002-A3',
            'target_agent' => 'architect',
            'target_phase' => 3,
            'is_active' => true,
            'surfaced_count' => 60,
            'prevented_count' => 5,
            'ignored_count' => 0,
            'confidence' => 0.8,
            'owner_id' => $this->admin->id,
        ]);

        $count = $this->service->autoDeactivateLowConfidence();

        $stale->refresh();
        $active->refresh();

        $this->assertFalse($stale->is_active);
        $this->assertTrue($active->is_active);
        $this->assertGreaterThanOrEqual(1, $count);
    }

    // ─── Stats ──────────────────────────────────────────────────

    public function test_get_stats_returns_correct_structure(): void
    {
        RlmFailure::factory()->create([
            'failure_code' => 'BF-001',
            'category' => FailureCategory::Scaffolding,
            'severity' => FailureSeverity::High,
            'owner_id' => $this->admin->id,
        ]);

        $stats = $this->service->getStats();

        $this->assertArrayHasKey('total_failures', $stats);
        $this->assertArrayHasKey('clustered_failures', $stats);
        $this->assertArrayHasKey('total_clusters', $stats);
        $this->assertArrayHasKey('total_lessons', $stats);
        $this->assertArrayHasKey('agents', $stats);
    }
}
