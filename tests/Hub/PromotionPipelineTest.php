<?php

namespace Aicl\Tests\Hub;

use Aicl\Jobs\CheckPromotionCandidatesJob;
use Aicl\Models\FailureReport;
use Aicl\Models\RlmFailure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class PromotionPipelineTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private RlmFailure $failure;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
        $this->failure = RlmFailure::factory()->create([
            'report_count' => 0,
            'project_count' => 0,
            'resolution_count' => 0,
            'resolution_rate' => 0,
            'last_seen_at' => null,
            'scaffolding_fixed' => false,
            'promoted_to_base' => false,
            'owner_id' => $this->admin->id,
        ]);
    }

    // --- Report count increment ---

    public function test_creating_report_increments_failure_report_count(): void
    {
        FailureReport::factory()->create([
            'rlm_failure_id' => $this->failure->id,
            'owner_id' => $this->admin->id,
        ]);

        $this->failure->refresh();
        $this->assertEquals(1, $this->failure->report_count);
    }

    public function test_multiple_reports_increment_correctly(): void
    {
        FailureReport::factory()->count(3)->create([
            'rlm_failure_id' => $this->failure->id,
            'owner_id' => $this->admin->id,
        ]);

        $this->failure->refresh();
        $this->assertEquals(3, $this->failure->report_count);
    }

    // --- Project count tracking ---

    public function test_unique_project_hash_increments_project_count(): void
    {
        FailureReport::factory()->create([
            'rlm_failure_id' => $this->failure->id,
            'project_hash' => 'hash-project-a',
            'owner_id' => $this->admin->id,
        ]);

        FailureReport::factory()->create([
            'rlm_failure_id' => $this->failure->id,
            'project_hash' => 'hash-project-b',
            'owner_id' => $this->admin->id,
        ]);

        $this->failure->refresh();
        $this->assertEquals(2, $this->failure->project_count);
    }

    public function test_duplicate_project_hash_does_not_increment(): void
    {
        FailureReport::factory()->create([
            'rlm_failure_id' => $this->failure->id,
            'project_hash' => 'hash-same',
            'owner_id' => $this->admin->id,
        ]);

        FailureReport::factory()->create([
            'rlm_failure_id' => $this->failure->id,
            'project_hash' => 'hash-same',
            'owner_id' => $this->admin->id,
        ]);

        $this->failure->refresh();
        $this->assertEquals(1, $this->failure->project_count);
    }

    // --- Resolution tracking ---

    public function test_resolved_report_increments_resolution_count(): void
    {
        FailureReport::factory()->create([
            'rlm_failure_id' => $this->failure->id,
            'resolved' => true,
            'owner_id' => $this->admin->id,
        ]);

        $this->failure->refresh();
        $this->assertEquals(1, $this->failure->resolution_count);
    }

    public function test_unresolved_report_does_not_increment_resolution_count(): void
    {
        FailureReport::factory()->create([
            'rlm_failure_id' => $this->failure->id,
            'resolved' => false,
            'owner_id' => $this->admin->id,
        ]);

        $this->failure->refresh();
        $this->assertEquals(0, $this->failure->resolution_count);
    }

    public function test_resolution_rate_is_recomputed(): void
    {
        // Create 3 reports, 1 resolved
        FailureReport::factory()->create([
            'rlm_failure_id' => $this->failure->id,
            'resolved' => true,
            'owner_id' => $this->admin->id,
        ]);
        FailureReport::factory()->create([
            'rlm_failure_id' => $this->failure->id,
            'resolved' => false,
            'owner_id' => $this->admin->id,
        ]);
        FailureReport::factory()->create([
            'rlm_failure_id' => $this->failure->id,
            'resolved' => false,
            'owner_id' => $this->admin->id,
        ]);

        $this->failure->refresh();
        // 1 resolution / 3 reports = 0.333
        $this->assertGreaterThan(0, (float) $this->failure->resolution_rate);
    }

    // --- last_seen_at ---

    public function test_last_seen_at_is_updated(): void
    {
        $this->assertNull($this->failure->last_seen_at);

        FailureReport::factory()->create([
            'rlm_failure_id' => $this->failure->id,
            'owner_id' => $this->admin->id,
        ]);

        $this->failure->refresh();
        $this->assertNotNull($this->failure->last_seen_at);
    }

    // --- Promotion job dispatch ---

    public function test_promotion_job_dispatched_when_criteria_met(): void
    {
        Bus::fake([CheckPromotionCandidatesJob::class]);

        // Pre-set failure to be close to promotion (2 reports, 1 project)
        $this->failure->update(['report_count' => 2, 'project_count' => 2]);

        FailureReport::factory()->create([
            'rlm_failure_id' => $this->failure->id,
            'project_hash' => 'hash-project-a',
            'owner_id' => $this->admin->id,
        ]);

        Bus::assertDispatched(CheckPromotionCandidatesJob::class);
    }

    public function test_promotion_job_not_dispatched_below_threshold(): void
    {
        Bus::fake([CheckPromotionCandidatesJob::class]);

        FailureReport::factory()->create([
            'rlm_failure_id' => $this->failure->id,
            'owner_id' => $this->admin->id,
        ]);

        Bus::assertNotDispatched(CheckPromotionCandidatesJob::class);
    }

    public function test_promotion_job_not_dispatched_when_already_promoted(): void
    {
        Bus::fake([CheckPromotionCandidatesJob::class]);

        $this->failure->update([
            'report_count' => 5,
            'project_count' => 3,
            'promoted_to_base' => true,
        ]);

        FailureReport::factory()->create([
            'rlm_failure_id' => $this->failure->id,
            'project_hash' => 'new-hash',
            'owner_id' => $this->admin->id,
        ]);

        Bus::assertNotDispatched(CheckPromotionCandidatesJob::class);
    }

    // --- RlmFailure relationships ---

    public function test_failure_has_reports_relationship(): void
    {
        FailureReport::factory()->count(2)->create([
            'rlm_failure_id' => $this->failure->id,
            'owner_id' => $this->admin->id,
        ]);

        $this->assertCount(2, $this->failure->reports);
    }

    public function test_failure_has_prevention_rules_relationship(): void
    {
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class, $this->failure->preventionRules());
    }
}
