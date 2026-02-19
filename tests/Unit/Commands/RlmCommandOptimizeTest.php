<?php

namespace Aicl\Tests\Unit\Commands;

use Aicl\Models\DistilledLesson;
use Aicl\Models\GenerationTrace;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RlmCommandOptimizeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['id' => 1]);
    }

    public function test_optimize_requires_minimum_traces(): void
    {
        // Only 3 traces — below minimum of 5
        GenerationTrace::factory()->count(3)->create([
            'surfaced_lesson_codes' => ['DL-001-A3'],
            'structural_score' => 100,
            'fix_iterations' => 0,
            'owner_id' => $this->admin->id,
        ]);

        $this->artisan('aicl:rlm', ['action' => 'optimize'])
            ->expectsOutputToContain('Insufficient traces')
            ->assertSuccessful();
    }

    public function test_optimize_dry_run_shows_changes_without_persisting(): void
    {
        $lesson = DistilledLesson::factory()->create([
            'lesson_code' => 'DL-001-A3',
            'impact_score' => 8.0,
            'confidence' => 0.8,
            'is_active' => true,
            'owner_id' => $this->admin->id,
        ]);

        // Create 5 GOOD traces that reference this lesson
        GenerationTrace::factory()->count(5)->create([
            'surfaced_lesson_codes' => ['DL-001-A3'],
            'structural_score' => 100,
            'fix_iterations' => 0,
            'owner_id' => $this->admin->id,
        ]);

        // Default is dry-run (no --apply)
        $this->artisan('aicl:rlm', ['action' => 'optimize'])
            ->expectsOutputToContain('dry-run')
            ->assertSuccessful();

        // Score should NOT have changed (dry-run)
        $lesson->refresh();
        $this->assertEquals(8.0, (float) $lesson->impact_score);
        $this->assertNull($lesson->base_impact_score);
    }

    public function test_optimize_apply_persists_changes(): void
    {
        $lesson = DistilledLesson::factory()->create([
            'lesson_code' => 'DL-001-A3',
            'impact_score' => 8.0,
            'confidence' => 0.8,
            'is_active' => true,
            'owner_id' => $this->admin->id,
        ]);

        // Create 5 GOOD traces — all good means boost > 0.3 → +10%
        GenerationTrace::factory()->count(5)->create([
            'surfaced_lesson_codes' => ['DL-001-A3'],
            'structural_score' => 100,
            'fix_iterations' => 0,
            'owner_id' => $this->admin->id,
        ]);

        $this->artisan('aicl:rlm', ['action' => 'optimize', '--apply' => true])
            ->expectsOutputToContain('optimized')
            ->assertSuccessful();

        $lesson->refresh();
        // 8.0 * 1.10 = 8.80
        $this->assertEquals(8.80, (float) $lesson->impact_score);
        $this->assertEquals(8.0, (float) $lesson->base_impact_score);
    }

    public function test_optimize_reset_restores_base_scores(): void
    {
        $lesson = DistilledLesson::factory()->create([
            'lesson_code' => 'DL-001-A3',
            'impact_score' => 8.80,
            'base_impact_score' => 8.0,
            'confidence' => 0.8,
            'is_active' => true,
            'owner_id' => $this->admin->id,
        ]);

        $this->artisan('aicl:rlm', ['action' => 'optimize', '--reset' => true])
            ->expectsOutputToContain('reset')
            ->assertSuccessful();

        $lesson->refresh();
        $this->assertEquals(8.0, (float) $lesson->impact_score);
        $this->assertNull($lesson->base_impact_score);
    }

    public function test_optimize_classifies_good_fair_poor(): void
    {
        $lesson = DistilledLesson::factory()->create([
            'lesson_code' => 'DL-001-A3',
            'impact_score' => 8.0,
            'is_active' => true,
            'owner_id' => $this->admin->id,
        ]);

        // 3 GOOD traces (score=100, fix=0)
        GenerationTrace::factory()->count(3)->create([
            'surfaced_lesson_codes' => ['DL-001-A3'],
            'structural_score' => 100,
            'fix_iterations' => 0,
            'owner_id' => $this->admin->id,
        ]);

        // 1 FAIR trace (score=95, fix=2)
        GenerationTrace::factory()->create([
            'surfaced_lesson_codes' => ['DL-001-A3'],
            'structural_score' => 95,
            'fix_iterations' => 2,
            'owner_id' => $this->admin->id,
        ]);

        // 1 POOR trace (score=80, fix=5)
        GenerationTrace::factory()->create([
            'surfaced_lesson_codes' => ['DL-001-A3'],
            'structural_score' => 80,
            'fix_iterations' => 5,
            'owner_id' => $this->admin->id,
        ]);

        // boost = 3/5 - 1/5 = 0.4 > 0.3 → +10%
        $this->artisan('aicl:rlm', ['action' => 'optimize', '--apply' => true])
            ->assertSuccessful();

        $lesson->refresh();
        $this->assertEquals(8.80, (float) $lesson->impact_score);
    }

    public function test_optimize_clamps_to_max_double_base(): void
    {
        // Already near the max — clamping should prevent going over 2x base
        $lesson = DistilledLesson::factory()->create([
            'lesson_code' => 'DL-001-A3',
            'impact_score' => 15.0,
            'base_impact_score' => 8.0,
            'is_active' => true,
            'owner_id' => $this->admin->id,
        ]);

        // 5 GOOD traces → +10% would give 16.5, but max is 8.0 * 2 = 16.0
        GenerationTrace::factory()->count(5)->create([
            'surfaced_lesson_codes' => ['DL-001-A3'],
            'structural_score' => 100,
            'fix_iterations' => 0,
            'owner_id' => $this->admin->id,
        ]);

        $this->artisan('aicl:rlm', ['action' => 'optimize', '--apply' => true])
            ->assertSuccessful();

        $lesson->refresh();
        $this->assertLessThanOrEqual(16.0, (float) $lesson->impact_score);
    }

    public function test_optimize_decreases_score_for_poor_outcomes(): void
    {
        $lesson = DistilledLesson::factory()->create([
            'lesson_code' => 'DL-001-A3',
            'impact_score' => 8.0,
            'is_active' => true,
            'owner_id' => $this->admin->id,
        ]);

        // 5 POOR traces — all poor means boost = 0 - 1.0 = -1.0 < -0.2 → -10%
        GenerationTrace::factory()->count(5)->create([
            'surfaced_lesson_codes' => ['DL-001-A3'],
            'structural_score' => 70,
            'fix_iterations' => 5,
            'owner_id' => $this->admin->id,
        ]);

        $this->artisan('aicl:rlm', ['action' => 'optimize', '--apply' => true])
            ->assertSuccessful();

        $lesson->refresh();
        // 8.0 * 0.90 = 7.20
        $this->assertEquals(7.20, (float) $lesson->impact_score);
    }

    public function test_optimize_reset_with_no_base_scores(): void
    {
        DistilledLesson::factory()->create([
            'lesson_code' => 'DL-001-A3',
            'impact_score' => 8.0,
            'base_impact_score' => null,
            'is_active' => true,
            'owner_id' => $this->admin->id,
        ]);

        $this->artisan('aicl:rlm', ['action' => 'optimize', '--reset' => true])
            ->expectsOutputToContain('No lessons have base impact scores')
            ->assertSuccessful();
    }
}
