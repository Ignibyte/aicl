<?php

namespace Aicl\Tests\Unit\Rlm;

use Aicl\Models\DistilledLesson;
use Aicl\Rlm\DistillationService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeedbackCalculationTest extends TestCase
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

    // -- Prevention Counting ------------------------------------------------

    public function test_prevention_counting_increments_when_source_failures_absent(): void
    {
        $lesson = DistilledLesson::factory()->create([
            'source_failure_codes' => ['BF-001', 'BF-002'],
            'prevented_count' => 0,
            'ignored_count' => 0,
            'confidence' => 0.80,
            'owner_id' => $this->admin->id,
        ]);

        // Simulate: lesson's source failures did NOT appear in actual failures
        $actualFailures = ['BF-099']; // unrelated failure
        $overlap = array_intersect($lesson->source_failure_codes, $actualFailures);

        $this->assertEmpty($overlap);

        $lesson->increment('prevented_count');
        $lesson->refresh();

        $this->assertSame(1, $lesson->prevented_count);
    }

    // -- Ignore Counting ----------------------------------------------------

    public function test_ignore_counting_increments_when_source_failures_present(): void
    {
        $lesson = DistilledLesson::factory()->create([
            'source_failure_codes' => ['BF-001', 'BF-002'],
            'prevented_count' => 0,
            'ignored_count' => 0,
            'confidence' => 0.80,
            'owner_id' => $this->admin->id,
        ]);

        // Simulate: lesson's source failure BF-001 DID appear in actual failures
        $actualFailures = ['BF-001', 'BF-099'];
        $overlap = array_intersect($lesson->source_failure_codes, $actualFailures);

        $this->assertNotEmpty($overlap);

        $lesson->increment('ignored_count');
        $lesson->refresh();

        $this->assertSame(1, $lesson->ignored_count);
    }

    // -- Mixed Scenario -----------------------------------------------------

    public function test_mixed_scenario_some_prevented_some_ignored(): void
    {
        $preventedLesson = DistilledLesson::factory()->create([
            'lesson_code' => 'DL-001-A3',
            'source_failure_codes' => ['BF-001'],
            'prevented_count' => 0,
            'ignored_count' => 0,
            'confidence' => 0.80,
            'owner_id' => $this->admin->id,
        ]);

        $ignoredLesson = DistilledLesson::factory()->create([
            'lesson_code' => 'DL-002-T4',
            'source_failure_codes' => ['BF-005'],
            'prevented_count' => 0,
            'ignored_count' => 0,
            'confidence' => 0.80,
            'owner_id' => $this->admin->id,
        ]);

        $actualFailures = ['BF-005']; // Only BF-005 occurred

        // Process each lesson
        foreach ([$preventedLesson, $ignoredLesson] as $lesson) {
            $overlap = array_intersect($lesson->source_failure_codes, $actualFailures);
            if (empty($overlap)) {
                $lesson->increment('prevented_count');
            } else {
                $lesson->increment('ignored_count');
            }
        }

        $preventedLesson->refresh();
        $ignoredLesson->refresh();

        $this->assertSame(1, $preventedLesson->prevented_count);
        $this->assertSame(0, $preventedLesson->ignored_count);
        $this->assertSame(0, $ignoredLesson->prevented_count);
        $this->assertSame(1, $ignoredLesson->ignored_count);
    }

    // -- Confidence Growth --------------------------------------------------

    public function test_confidence_grows_with_preventions(): void
    {
        $lesson = DistilledLesson::factory()->create([
            'confidence' => 0.80,
            'prevented_count' => 5,
            'ignored_count' => 0,
            'owner_id' => $this->admin->id,
        ]);

        // Formula: confidence + (prevented * 0.02) - (ignored * 0.05)
        // = 0.80 + (5 * 0.02) - (0 * 0.05) = 0.80 + 0.10 = 0.90
        $result = $this->service->recalculateConfidence($lesson);

        $this->assertEqualsWithDelta(0.90, $result, 0.001);
        $lesson->refresh();
        $this->assertEqualsWithDelta(0.90, (float) $lesson->confidence, 0.001);
    }

    // -- Confidence Decay ---------------------------------------------------

    public function test_confidence_decays_with_ignores(): void
    {
        $lesson = DistilledLesson::factory()->create([
            'confidence' => 0.80,
            'prevented_count' => 0,
            'ignored_count' => 5,
            'owner_id' => $this->admin->id,
        ]);

        // Formula: 0.80 + (0 * 0.02) - (5 * 0.05) = 0.80 - 0.25 = 0.55
        $result = $this->service->recalculateConfidence($lesson);

        $this->assertEqualsWithDelta(0.55, $result, 0.001);
        $lesson->refresh();
        $this->assertEqualsWithDelta(0.55, (float) $lesson->confidence, 0.001);
    }

    // -- Confidence Clamping at 1.0 -----------------------------------------

    public function test_confidence_clamped_at_one(): void
    {
        $lesson = DistilledLesson::factory()->create([
            'confidence' => 0.95,
            'prevented_count' => 10,
            'ignored_count' => 0,
            'owner_id' => $this->admin->id,
        ]);

        // Formula: 0.95 + (10 * 0.02) - (0 * 0.05) = 0.95 + 0.20 = 1.15 -> clamped to 1.0
        $result = $this->service->recalculateConfidence($lesson);

        $this->assertEqualsWithDelta(1.0, $result, 0.001);
        $lesson->refresh();
        $this->assertEqualsWithDelta(1.0, (float) $lesson->confidence, 0.001);
    }

    // -- Confidence Clamping at 0.0 -----------------------------------------

    public function test_confidence_clamped_at_zero(): void
    {
        $lesson = DistilledLesson::factory()->create([
            'confidence' => 0.10,
            'prevented_count' => 0,
            'ignored_count' => 20,
            'owner_id' => $this->admin->id,
        ]);

        // Formula: 0.10 + (0 * 0.02) - (20 * 0.05) = 0.10 - 1.00 = -0.90 -> clamped to 0.0
        $result = $this->service->recalculateConfidence($lesson);

        $this->assertEqualsWithDelta(0.0, $result, 0.001);
        $lesson->refresh();
        $this->assertEqualsWithDelta(0.0, (float) $lesson->confidence, 0.001);
    }
}
