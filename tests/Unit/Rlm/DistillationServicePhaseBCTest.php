<?php

namespace Aicl\Tests\Unit\Rlm;

use Aicl\Enums\FailureCategory;
use Aicl\Enums\KnowledgeLinkRelationship;
use Aicl\Enums\KnowledgeLinkType;
use Aicl\Enums\LessonType;
use Aicl\Models\DistilledLesson;
use Aicl\Models\KnowledgeLink;
use Aicl\Models\RlmFailure;
use Aicl\Models\RlmLesson;
use Aicl\Rlm\DistillationService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DistillationServicePhaseBCTest extends TestCase
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

    // ─── B.1: rule_hash clustering ──────────────────────────────

    public function test_rule_hash_clusters_failures_with_same_normalized_rule(): void
    {
        // Two failures with different wording but same normalized rule
        RlmFailure::factory()->create([
            'failure_code' => 'BF-001',
            'category' => FailureCategory::Scaffolding,
            'preventive_rule' => 'Always override searchableColumns() to list real columns.',
            'owner_id' => $this->admin->id,
        ]);

        RlmFailure::factory()->create([
            'failure_code' => 'BF-002',
            'category' => FailureCategory::Filament,
            'preventive_rule' => 'always override searchableColumns() to list real columns',
            'owner_id' => $this->admin->id,
        ]);

        $clusters = $this->service->clusterFailures();

        // Both should cluster together by rule_hash
        $this->assertCount(1, $clusters);
        $this->assertCount(2, $clusters->first()['failures']);
    }

    public function test_rule_hash_clustering_takes_priority_over_pattern_id(): void
    {
        $rule = 'Always check model columns before accepting defaults.';

        // Same rule_hash, different pattern_id
        RlmFailure::factory()->create([
            'failure_code' => 'BF-001',
            'pattern_id' => 'P-012',
            'category' => FailureCategory::Scaffolding,
            'preventive_rule' => $rule,
            'owner_id' => $this->admin->id,
        ]);

        RlmFailure::factory()->create([
            'failure_code' => 'BF-002',
            'pattern_id' => 'P-099',
            'category' => FailureCategory::Scaffolding,
            'preventive_rule' => $rule,
            'owner_id' => $this->admin->id,
        ]);

        $clusters = $this->service->clusterFailures();

        // rule_hash match overrides different pattern_id
        $this->assertCount(1, $clusters);
        $this->assertCount(2, $clusters->first()['failures']);
    }

    public function test_different_rule_hashes_create_separate_clusters(): void
    {
        RlmFailure::factory()->create([
            'failure_code' => 'BF-001',
            'category' => FailureCategory::Scaffolding,
            'preventive_rule' => 'Always override searchableColumns.',
            'owner_id' => $this->admin->id,
        ]);

        RlmFailure::factory()->create([
            'failure_code' => 'BF-002',
            'category' => FailureCategory::Scaffolding,
            'preventive_rule' => 'Never use static state in Octane.',
            'owner_id' => $this->admin->id,
        ]);

        $clusters = $this->service->clusterFailures();

        // Different rules = different clusters
        $this->assertCount(2, $clusters);
    }

    public function test_rule_hash_cluster_includes_single_item_groups(): void
    {
        // Single failure with rule_hash still becomes a cluster (unlike pattern_id which needs 2+)
        RlmFailure::factory()->create([
            'failure_code' => 'BF-001',
            'category' => FailureCategory::Scaffolding,
            'preventive_rule' => 'Unique rule with no duplicates.',
            'pattern_id' => null,
            'owner_id' => $this->admin->id,
        ]);

        $clusters = $this->service->clusterFailures();

        $this->assertCount(1, $clusters);
        $this->assertCount(1, $clusters->first()['failures']);
    }

    public function test_minor_wording_variations_produce_same_cluster(): void
    {
        $variations = [
            'Override searchableColumns() to list only real columns.',
            'override searchablecolumns() to list only real columns',
            'Override searchableColumns() to list only real columns!',
        ];

        foreach ($variations as $i => $rule) {
            RlmFailure::factory()->create([
                'failure_code' => 'BF-'.str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT),
                'category' => FailureCategory::Scaffolding,
                'preventive_rule' => $rule,
                'owner_id' => $this->admin->id,
            ]);
        }

        $clusters = $this->service->clusterFailures();

        // All 3 should be in one cluster
        $this->assertCount(1, $clusters);
        $this->assertCount(3, $clusters->first()['failures']);
    }

    // ─── B.2: WHEN/THEN guidance derivation ─────────────────────

    public function test_structured_guidance_generates_when_then_rule_format(): void
    {
        $failure = RlmFailure::factory()->create([
            'failure_code' => 'BF-001',
            'category' => FailureCategory::Scaffolding,
            'preventive_rule' => 'Always override searchableColumns.',
            'feedback' => 'Pattern P-012 failed: searchableColumns references name but model has no name column.',
            'fix' => 'Override searchableColumns() to return actual column names.',
            'owner_id' => $this->admin->id,
        ]);

        $result = $this->service->distill();

        // Find a generated lesson
        $lesson = DistilledLesson::query()->first();
        $this->assertNotNull($lesson);

        // Guidance should be in WHEN/THEN/RULE format
        $this->assertStringContainsString('WHEN:', $lesson->guidance);
        $this->assertStringContainsString('THEN:', $lesson->guidance);
        $this->assertStringContainsString('RULE:', $lesson->guidance);
    }

    public function test_structured_guidance_when_only_feedback_present(): void
    {
        RlmFailure::factory()->create([
            'failure_code' => 'BF-001',
            'category' => FailureCategory::Scaffolding,
            'preventive_rule' => 'Always override searchableColumns.',
            'feedback' => 'P-012 failed on entity Ticket.',
            'fix' => null,
            'owner_id' => $this->admin->id,
        ]);

        $result = $this->service->distill();
        $lesson = DistilledLesson::query()->first();
        $this->assertNotNull($lesson);

        // Should have WHEN + RULE but no THEN
        $this->assertStringContainsString('WHEN:', $lesson->guidance);
        $this->assertStringContainsString('RULE:', $lesson->guidance);
        $this->assertStringNotContainsString('THEN:', $lesson->guidance);
    }

    public function test_structured_guidance_when_only_fix_present(): void
    {
        RlmFailure::factory()->create([
            'failure_code' => 'BF-001',
            'category' => FailureCategory::Scaffolding,
            'preventive_rule' => 'Always override searchableColumns.',
            'feedback' => null,
            'fix' => 'Override searchableColumns().',
            'owner_id' => $this->admin->id,
        ]);

        $result = $this->service->distill();
        $lesson = DistilledLesson::query()->first();
        $this->assertNotNull($lesson);

        // Should have RULE + FIX but no WHEN
        $this->assertStringContainsString('RULE:', $lesson->guidance);
        $this->assertStringContainsString('FIX:', $lesson->guidance);
        $this->assertStringNotContainsString('WHEN:', $lesson->guidance);
    }

    public function test_legacy_guidance_uses_template_when_no_structured_fields(): void
    {
        RlmFailure::factory()->create([
            'failure_code' => 'BF-001',
            'category' => FailureCategory::Scaffolding,
            'preventive_rule' => 'Always check columns.',
            'feedback' => null,
            'fix' => null,
            'owner_id' => $this->admin->id,
        ]);

        $result = $this->service->distill();
        $lesson = DistilledLesson::query()->first();
        $this->assertNotNull($lesson);

        // Legacy format — uses prompt template, not WHEN/THEN
        $this->assertStringNotContainsString('WHEN:', $lesson->guidance);
        $this->assertStringContainsString('Always check columns.', $lesson->guidance);
    }

    public function test_generate_when_then_rules_extracts_from_structured_guidance(): void
    {
        // Create a distilled lesson with structured WHEN/THEN guidance
        DistilledLesson::query()->create([
            'lesson_code' => 'DL-001-A3',
            'title' => 'Build: Test lesson',
            'guidance' => "WHEN: P-012 failed\nTHEN: Override searchableColumns\nRULE: Always override searchableColumns",
            'target_agent' => 'architect',
            'target_phase' => 3,
            'trigger_context' => ['component' => 'scaffolded-entity'],
            'source_failure_codes' => ['BF-001'],
            'impact_score' => 5.0,
            'confidence' => 0.8,
            'is_active' => true,
            'last_distilled_at' => now(),
            'owner_id' => $this->admin->id,
        ]);

        $rules = $this->service->generateWhenThenRules('architect', 3);

        $this->assertCount(1, $rules);
        $this->assertSame('P-012 failed', $rules->first()['when']);
        $this->assertContains('Override searchableColumns', $rules->first()['then']);
        $this->assertContains('Always override searchableColumns', $rules->first()['then']);
    }

    public function test_generate_when_then_rules_handles_mixed_structured_and_legacy(): void
    {
        // Structured lesson
        DistilledLesson::query()->create([
            'lesson_code' => 'DL-001-A3',
            'title' => 'Build: Structured lesson',
            'guidance' => "WHEN: P-012 failed\nTHEN: Override columns\nRULE: Always override",
            'target_agent' => 'architect',
            'target_phase' => 3,
            'trigger_context' => ['component' => 'scaffolded-entity'],
            'source_failure_codes' => ['BF-001'],
            'impact_score' => 5.0,
            'confidence' => 0.8,
            'is_active' => true,
            'last_distilled_at' => now(),
            'owner_id' => $this->admin->id,
        ]);

        // Legacy lesson (trigger_context-based)
        DistilledLesson::query()->create([
            'lesson_code' => 'DL-002-A3',
            'title' => 'Build: Legacy lesson',
            'guidance' => 'When generating scaffolded entity code: Always check traits.',
            'target_agent' => 'architect',
            'target_phase' => 3,
            'trigger_context' => ['component' => 'filament-resource'],
            'source_failure_codes' => ['BF-002'],
            'impact_score' => 3.0,
            'confidence' => 0.8,
            'is_active' => true,
            'last_distilled_at' => now(),
            'owner_id' => $this->admin->id,
        ]);

        $rules = $this->service->generateWhenThenRules('architect', 3);

        // Should have both: 1 from structured, 1 from legacy trigger_context
        $this->assertCount(2, $rules);
    }

    // ─── C.3: RecallService lesson type filtering ───────────────

    public function test_lesson_surfaceable_scope_excludes_observations(): void
    {
        RlmLesson::factory()->create([
            'lesson_type' => LessonType::Observation,
            'topic' => 'testing',
            'is_active' => true,
            'owner_id' => $this->admin->id,
        ]);

        RlmLesson::factory()->instruction()->create([
            'topic' => 'testing',
            'is_active' => true,
            'owner_id' => $this->admin->id,
        ]);

        $surfaceable = RlmLesson::query()->surfaceable()->get();

        $this->assertCount(1, $surfaceable);
        $this->assertSame(LessonType::Instruction, $surfaceable->first()->lesson_type);
    }

    public function test_lesson_surfaceable_scope_includes_prevention_rules(): void
    {
        RlmLesson::factory()->preventionRule()->create([
            'topic' => 'testing',
            'is_active' => true,
            'owner_id' => $this->admin->id,
        ]);

        $surfaceable = RlmLesson::query()->surfaceable()->get();

        $this->assertCount(1, $surfaceable);
        $this->assertSame(LessonType::PreventionRule, $surfaceable->first()->lesson_type);
    }

    public function test_lesson_verified_scope_filters_unverified(): void
    {
        RlmLesson::factory()->create([
            'is_verified' => false,
            'topic' => 'testing',
            'owner_id' => $this->admin->id,
        ]);

        RlmLesson::factory()->verified()->create([
            'topic' => 'testing',
            'owner_id' => $this->admin->id,
        ]);

        $verified = RlmLesson::query()->verified()->get();

        $this->assertCount(1, $verified);
        $this->assertTrue($verified->first()->is_verified);
    }

    // ─── C.4: Auto-deactivation ─────────────────────────────────

    public function test_auto_deactivate_low_confidence_deactivates_below_threshold(): void
    {
        DistilledLesson::query()->create([
            'lesson_code' => 'DL-LOW-A3',
            'title' => 'Low confidence lesson',
            'guidance' => 'Test guidance',
            'target_agent' => 'architect',
            'target_phase' => 3,
            'source_failure_codes' => ['BF-001'],
            'impact_score' => 1.0,
            'confidence' => 0.1,
            'is_active' => true,
            'last_distilled_at' => now(),
            'owner_id' => $this->admin->id,
        ]);

        DistilledLesson::query()->create([
            'lesson_code' => 'DL-HIGH-A3',
            'title' => 'High confidence lesson',
            'guidance' => 'Test guidance',
            'target_agent' => 'architect',
            'target_phase' => 3,
            'source_failure_codes' => ['BF-002'],
            'impact_score' => 5.0,
            'confidence' => 0.8,
            'is_active' => true,
            'last_distilled_at' => now(),
            'owner_id' => $this->admin->id,
        ]);

        $deactivated = $this->service->autoDeactivateLowConfidence(0.2);

        $this->assertSame(1, $deactivated);
        $this->assertFalse(DistilledLesson::query()->where('lesson_code', 'DL-LOW-A3')->first()->is_active);
        $this->assertTrue(DistilledLesson::query()->where('lesson_code', 'DL-HIGH-A3')->first()->is_active);
    }

    public function test_auto_deactivate_returns_zero_when_none_below_threshold(): void
    {
        DistilledLesson::query()->create([
            'lesson_code' => 'DL-OK-A3',
            'title' => 'OK confidence',
            'guidance' => 'Test',
            'target_agent' => 'architect',
            'target_phase' => 3,
            'source_failure_codes' => ['BF-001'],
            'impact_score' => 1.0,
            'confidence' => 0.5,
            'is_active' => true,
            'last_distilled_at' => now(),
            'owner_id' => $this->admin->id,
        ]);

        $deactivated = $this->service->autoDeactivateLowConfidence(0.2);
        $this->assertSame(0, $deactivated);
    }

    public function test_auto_deactivate_custom_threshold(): void
    {
        DistilledLesson::query()->create([
            'lesson_code' => 'DL-MED-A3',
            'title' => 'Medium confidence',
            'guidance' => 'Test',
            'target_agent' => 'architect',
            'target_phase' => 3,
            'source_failure_codes' => ['BF-001'],
            'impact_score' => 1.0,
            'confidence' => 0.4,
            'is_active' => true,
            'last_distilled_at' => now(),
            'owner_id' => $this->admin->id,
        ]);

        // With threshold 0.5, the 0.4 lesson should be deactivated
        $deactivated = $this->service->autoDeactivateLowConfidence(0.5);
        $this->assertSame(1, $deactivated);
    }

    public function test_flag_stale_lessons_flags_old_zero_view_instructions(): void
    {
        $stale = RlmLesson::factory()->instruction()->create([
            'topic' => 'testing',
            'view_count' => 0,
            'needs_review' => false,
            'created_at' => now()->subDays(100),
            'owner_id' => $this->admin->id,
        ]);

        $fresh = RlmLesson::factory()->instruction()->create([
            'topic' => 'testing',
            'view_count' => 0,
            'needs_review' => false,
            'created_at' => now()->subDay(),
            'owner_id' => $this->admin->id,
        ]);

        $flagged = $this->service->flagStaleLessons(10);

        $this->assertSame(1, $flagged);
        $this->assertTrue($stale->fresh()->needs_review);
        $this->assertFalse($fresh->fresh()->needs_review);
    }

    public function test_flag_stale_lessons_skips_observations(): void
    {
        RlmLesson::factory()->create([
            'lesson_type' => LessonType::Observation,
            'topic' => 'testing',
            'view_count' => 0,
            'needs_review' => false,
            'created_at' => now()->subDays(100),
            'owner_id' => $this->admin->id,
        ]);

        $flagged = $this->service->flagStaleLessons(10);
        $this->assertSame(0, $flagged);
    }

    public function test_flag_stale_lessons_skips_already_flagged(): void
    {
        RlmLesson::factory()->instruction()->create([
            'topic' => 'testing',
            'view_count' => 0,
            'needs_review' => true,
            'created_at' => now()->subDays(100),
            'owner_id' => $this->admin->id,
        ]);

        $flagged = $this->service->flagStaleLessons(10);
        $this->assertSame(0, $flagged);
    }

    // ─── C.5: Promotion with proof hooks ────────────────────────

    public function test_promote_observation_with_proof_hook_succeeds(): void
    {
        $lesson = RlmLesson::factory()->create([
            'lesson_type' => LessonType::Observation,
            'topic' => 'testing',
            'owner_id' => $this->admin->id,
        ]);

        // Create a proof hook (KnowledgeLink)
        KnowledgeLink::factory()->create([
            'source_type' => $lesson->getMorphClass(),
            'source_id' => $lesson->id,
            'target_type' => $lesson->getMorphClass(),
            'target_id' => $lesson->id,
            'relationship' => KnowledgeLinkRelationship::LearnedFrom,
            'link_type' => KnowledgeLinkType::TestCase,
            'reference' => 'Aicl\Tests\Unit\Rlm\DistillationServicePhaseBC Test::test_example',
        ]);

        $result = $this->service->promoteObservation($lesson, 5);

        $this->assertTrue($result['promoted']);
        $this->assertSame(LessonType::Instruction, $lesson->fresh()->lesson_type);
        $this->assertTrue($lesson->fresh()->is_verified);
        $this->assertStringContainsString('Cluster size: 5', $lesson->fresh()->promotion_reason);
    }

    public function test_promote_observation_without_proof_flags_for_review(): void
    {
        $lesson = RlmLesson::factory()->create([
            'lesson_type' => LessonType::Observation,
            'topic' => 'testing',
            'owner_id' => $this->admin->id,
        ]);

        $result = $this->service->promoteObservation($lesson, 5);

        $this->assertFalse($result['promoted']);
        $this->assertStringContainsString('Missing proof hooks', $result['reason']);
        $this->assertTrue($lesson->fresh()->needs_review);
        $this->assertSame(LessonType::Observation, $lesson->fresh()->lesson_type);
    }

    public function test_promote_below_threshold_is_rejected(): void
    {
        $lesson = RlmLesson::factory()->create([
            'lesson_type' => LessonType::Observation,
            'topic' => 'testing',
            'owner_id' => $this->admin->id,
        ]);

        $result = $this->service->promoteObservation($lesson, 2);

        $this->assertFalse($result['promoted']);
        $this->assertStringContainsString('below threshold', $result['reason']);
    }

    public function test_promote_non_observation_is_rejected(): void
    {
        $lesson = RlmLesson::factory()->instruction()->create([
            'topic' => 'testing',
            'owner_id' => $this->admin->id,
        ]);

        $result = $this->service->promoteObservation($lesson, 5);

        $this->assertFalse($result['promoted']);
        $this->assertSame('Not an observation', $result['reason']);
    }

    // ─── C.6: KnowledgeLink types ───────────────────────────────

    public function test_knowledge_link_type_proof_strength_ranking(): void
    {
        $this->assertGreaterThan(
            KnowledgeLinkType::GoldenEntityFile->proofStrength(),
            KnowledgeLinkType::TestCase->proofStrength()
        );

        $this->assertGreaterThan(
            KnowledgeLinkType::CommitSha->proofStrength(),
            KnowledgeLinkType::GoldenEntityFile->proofStrength()
        );

        $this->assertGreaterThan(
            KnowledgeLinkType::DocAnchor->proofStrength(),
            KnowledgeLinkType::CommitSha->proofStrength()
        );
    }

    public function test_knowledge_link_proof_links_scope(): void
    {
        $failure = RlmFailure::factory()->create(['owner_id' => $this->admin->id]);
        $lesson = RlmLesson::factory()->create(['owner_id' => $this->admin->id]);

        KnowledgeLink::factory()->create([
            'source_type' => $failure->getMorphClass(),
            'source_id' => $failure->id,
            'target_type' => $lesson->getMorphClass(),
            'target_id' => $lesson->id,
            'relationship' => KnowledgeLinkRelationship::LearnedFrom,
            'link_type' => KnowledgeLinkType::TestCase,
            'reference' => 'SomeTest::test_method',
        ]);

        KnowledgeLink::factory()->create([
            'source_type' => $failure->getMorphClass(),
            'source_id' => $failure->id,
            'target_type' => $lesson->getMorphClass(),
            'target_id' => $lesson->id,
            'relationship' => KnowledgeLinkRelationship::DerivedFrom,
            'link_type' => null,
            'reference' => null,
        ]);

        $proofLinks = KnowledgeLink::query()->proofLinks()->get();
        $this->assertCount(1, $proofLinks);
    }

    public function test_lesson_type_is_surfaceable(): void
    {
        $this->assertFalse(LessonType::Observation->isSurfaceable());
        $this->assertTrue(LessonType::Instruction->isSurfaceable());
        $this->assertTrue(LessonType::PreventionRule->isSurfaceable());
    }

    public function test_lesson_type_requires_proof(): void
    {
        $this->assertFalse(LessonType::Observation->requiresProof());
        $this->assertTrue(LessonType::Instruction->requiresProof());
        $this->assertTrue(LessonType::PreventionRule->requiresProof());
    }

    // ─── C.2: KnowledgeWriter lesson_type ───────────────────────

    public function test_knowledge_writer_creates_observation_by_default(): void
    {
        $writer = app(\Aicl\Rlm\KnowledgeWriter::class);

        $lesson = $writer->addLesson('testing', 'Test observation', 'Detail text');

        $this->assertSame(LessonType::Observation, $lesson->lesson_type);
        $this->assertFalse($lesson->is_verified);
    }

    public function test_knowledge_writer_creates_instruction_with_auto_verified(): void
    {
        $writer = app(\Aicl\Rlm\KnowledgeWriter::class);

        $lesson = $writer->addLesson(
            'testing',
            'Test instruction',
            'Always apply rule X. Fix by adding Y.',
            lessonType: LessonType::Instruction,
        );

        $this->assertSame(LessonType::Instruction, $lesson->lesson_type);
        $this->assertTrue($lesson->is_verified);
    }

    public function test_knowledge_writer_creates_prevention_rule(): void
    {
        $writer = app(\Aicl\Rlm\KnowledgeWriter::class);

        $lesson = $writer->addLesson(
            'testing',
            'Test prevention rule',
            'Rule: always check X. Fix: do Y.',
            lessonType: LessonType::PreventionRule,
        );

        $this->assertSame(LessonType::PreventionRule, $lesson->lesson_type);
        $this->assertTrue($lesson->is_verified);
    }
}
