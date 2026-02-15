<?php

namespace Aicl\Tests\Unit\Rlm;

use Aicl\Models\DistilledLesson;
use Aicl\Rlm\KpiCalculator;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LessonAutoRetirementTest extends TestCase
{
    use RefreshDatabase;

    private KpiCalculator $calculator;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new KpiCalculator;
        $this->admin = User::factory()->create(['id' => 1]);
    }

    // ─── Boundary: Exactly at 30% effectiveness ────────────────

    public function test_exactly_at_threshold_stays_active(): void
    {
        // effectiveness = 30.0% exactly, which is NOT < 30.0%, so stays active
        DistilledLesson::factory()->create([
            'lesson_code' => 'DL-BOUNDARY-001',
            'is_active' => true,
            'prevented_count' => 3,
            'ignored_count' => 7,
            'owner_id' => $this->admin->id,
        ]);

        $retired = $this->calculator->autoRetireLessons();

        $this->assertNotContains('DL-BOUNDARY-001', $retired);

        $lesson = DistilledLesson::query()->where('lesson_code', 'DL-BOUNDARY-001')->first();
        $this->assertTrue($lesson->is_active);
    }

    // ─── Boundary: Just below 30% ──────────────────────────────

    public function test_just_below_threshold_gets_retired(): void
    {
        // effectiveness = 2/7 = 28.57% < 30%, interactions = 7 >= 5
        DistilledLesson::factory()->create([
            'lesson_code' => 'DL-BELOW-001',
            'is_active' => true,
            'prevented_count' => 2,
            'ignored_count' => 5,
            'owner_id' => $this->admin->id,
        ]);

        $retired = $this->calculator->autoRetireLessons();

        $this->assertContains('DL-BELOW-001', $retired);

        $lesson = DistilledLesson::query()->where('lesson_code', 'DL-BELOW-001')->first();
        $this->assertFalse($lesson->is_active);
    }

    // ─── Boundary: Exactly at interaction threshold ────────────

    public function test_exactly_at_interaction_threshold_eligible(): void
    {
        // interactions = 5 (exactly at threshold), effectiveness = 0/5 = 0% < 30%
        DistilledLesson::factory()->create([
            'lesson_code' => 'DL-EXACT-INT-001',
            'is_active' => true,
            'prevented_count' => 0,
            'ignored_count' => 5,
            'owner_id' => $this->admin->id,
        ]);

        $retired = $this->calculator->autoRetireLessons();

        $this->assertContains('DL-EXACT-INT-001', $retired);
    }

    // ─── Boundary: Just below interaction threshold ────────────

    public function test_just_below_interaction_threshold_not_eligible(): void
    {
        // interactions = 4 (below threshold), effectiveness = 0%
        DistilledLesson::factory()->create([
            'lesson_code' => 'DL-FEW-INT-001',
            'is_active' => true,
            'prevented_count' => 0,
            'ignored_count' => 4,
            'owner_id' => $this->admin->id,
        ]);

        $retired = $this->calculator->autoRetireLessons();

        $this->assertNotContains('DL-FEW-INT-001', $retired);

        $lesson = DistilledLesson::query()->where('lesson_code', 'DL-FEW-INT-001')->first();
        $this->assertTrue($lesson->is_active);
    }

    // ─── Multiple retirements in one call ──────────────────────

    public function test_multiple_retirements_in_one_call(): void
    {
        DistilledLesson::factory()->create([
            'lesson_code' => 'DL-MULTI-001',
            'is_active' => true,
            'prevented_count' => 0,
            'ignored_count' => 10,
            'owner_id' => $this->admin->id,
        ]);

        DistilledLesson::factory()->create([
            'lesson_code' => 'DL-MULTI-002',
            'is_active' => true,
            'prevented_count' => 1,
            'ignored_count' => 9,
            'owner_id' => $this->admin->id,
        ]);

        DistilledLesson::factory()->create([
            'lesson_code' => 'DL-MULTI-003',
            'is_active' => true,
            'prevented_count' => 0,
            'ignored_count' => 6,
            'owner_id' => $this->admin->id,
        ]);

        $retired = $this->calculator->autoRetireLessons();

        $this->assertCount(3, $retired);
        $this->assertContains('DL-MULTI-001', $retired);
        $this->assertContains('DL-MULTI-002', $retired);
        $this->assertContains('DL-MULTI-003', $retired);

        // All should now be inactive
        $activeCount = DistilledLesson::query()
            ->whereIn('lesson_code', ['DL-MULTI-001', 'DL-MULTI-002', 'DL-MULTI-003'])
            ->where('is_active', true)
            ->count();
        $this->assertSame(0, $activeCount);
    }

    // ─── Retirement preserves other fields ─────────────────────

    public function test_retirement_preserves_other_fields(): void
    {
        DistilledLesson::factory()->create([
            'lesson_code' => 'DL-PRESERVE-001',
            'title' => 'Override searchableColumns in models',
            'guidance' => 'Always override searchableColumns to list only existing columns.',
            'target_agent' => 'architect',
            'target_phase' => 3,
            'prevented_count' => 1,
            'ignored_count' => 8,
            'confidence' => 0.75,
            'impact_score' => 15.0,
            'is_active' => true,
            'owner_id' => $this->admin->id,
        ]);

        $this->calculator->autoRetireLessons();

        $lesson = DistilledLesson::query()->where('lesson_code', 'DL-PRESERVE-001')->first();

        $this->assertFalse($lesson->is_active);
        $this->assertSame('DL-PRESERVE-001', $lesson->lesson_code);
        $this->assertSame('Override searchableColumns in models', $lesson->title);
        $this->assertSame('Always override searchableColumns to list only existing columns.', $lesson->guidance);
        $this->assertSame('architect', $lesson->target_agent);
        $this->assertSame(3, $lesson->target_phase);
        $this->assertSame(1, $lesson->prevented_count);
        $this->assertSame(8, $lesson->ignored_count);
    }

    // ─── Mix of eligible and ineligible ────────────────────────

    public function test_mix_of_eligible_and_ineligible(): void
    {
        // Should be retired (low effectiveness, enough interactions)
        DistilledLesson::factory()->create([
            'lesson_code' => 'DL-MIX-RETIRE',
            'is_active' => true,
            'prevented_count' => 0,
            'ignored_count' => 10,
            'owner_id' => $this->admin->id,
        ]);

        // Should stay (high effectiveness)
        DistilledLesson::factory()->create([
            'lesson_code' => 'DL-MIX-KEEP',
            'is_active' => true,
            'prevented_count' => 8,
            'ignored_count' => 2,
            'owner_id' => $this->admin->id,
        ]);

        // Should stay (not enough interactions)
        DistilledLesson::factory()->create([
            'lesson_code' => 'DL-MIX-YOUNG',
            'is_active' => true,
            'prevented_count' => 0,
            'ignored_count' => 3,
            'owner_id' => $this->admin->id,
        ]);

        // Should stay (already inactive)
        DistilledLesson::factory()->create([
            'lesson_code' => 'DL-MIX-INACTIVE',
            'is_active' => false,
            'prevented_count' => 0,
            'ignored_count' => 10,
            'owner_id' => $this->admin->id,
        ]);

        $retired = $this->calculator->autoRetireLessons();

        $this->assertCount(1, $retired);
        $this->assertContains('DL-MIX-RETIRE', $retired);
    }

    // ─── Zero prevented with high ignored ──────────────────────

    public function test_zero_prevented_high_ignored_gets_retired(): void
    {
        DistilledLesson::factory()->create([
            'lesson_code' => 'DL-ZERO-001',
            'is_active' => true,
            'prevented_count' => 0,
            'ignored_count' => 20,
            'owner_id' => $this->admin->id,
        ]);

        $retired = $this->calculator->autoRetireLessons();

        $this->assertContains('DL-ZERO-001', $retired);
    }

    // ─── Empty table ───────────────────────────────────────────

    public function test_empty_table_returns_no_retirements(): void
    {
        $retired = $this->calculator->autoRetireLessons();

        $this->assertSame([], $retired);
    }
}
