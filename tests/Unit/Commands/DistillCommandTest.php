<?php

namespace Aicl\Tests\Unit\Commands;

use Aicl\Enums\FailureCategory;
use Aicl\Enums\FailureSeverity;
use Aicl\Models\DistilledLesson;
use Aicl\Models\RlmFailure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DistillCommandTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['id' => 1]);
    }

    public function test_distill_runs_successfully(): void
    {
        RlmFailure::factory()->create([
            'failure_code' => 'BF-001',
            'category' => FailureCategory::Scaffolding,
            'severity' => FailureSeverity::High,
            'preventive_rule' => 'Override searchableColumns.',
            'owner_id' => $this->admin->id,
        ]);

        $this->artisan('aicl:rlm', ['action' => 'distill'])
            ->assertSuccessful();

        $this->assertGreaterThan(0, DistilledLesson::query()->count());
    }

    public function test_distill_dry_run_does_not_create_lessons(): void
    {
        RlmFailure::factory()->create([
            'failure_code' => 'BF-001',
            'category' => FailureCategory::Scaffolding,
            'severity' => FailureSeverity::High,
            'preventive_rule' => 'Override searchableColumns.',
            'owner_id' => $this->admin->id,
        ]);

        $this->artisan('aicl:rlm', [
            'action' => 'distill',
            '--dry-run' => true,
        ])->assertSuccessful();

        $this->assertSame(0, DistilledLesson::query()->count());
    }

    public function test_distill_stats_shows_coverage(): void
    {
        RlmFailure::factory()->create([
            'failure_code' => 'BF-001',
            'category' => FailureCategory::Scaffolding,
            'severity' => FailureSeverity::High,
            'owner_id' => $this->admin->id,
        ]);

        $this->artisan('aicl:rlm', [
            'action' => 'distill',
            '--stats' => true,
        ])->assertSuccessful();
    }

    public function test_distill_with_agent_filter(): void
    {
        RlmFailure::factory()->create([
            'failure_code' => 'BF-001',
            'category' => FailureCategory::Scaffolding,
            'severity' => FailureSeverity::High,
            'preventive_rule' => 'Override searchableColumns.',
            'owner_id' => $this->admin->id,
        ]);

        $this->artisan('aicl:rlm', [
            'action' => 'distill',
            '--agent' => 'architect',
        ])->assertSuccessful();

        $lessons = DistilledLesson::query()->get();
        foreach ($lessons as $lesson) {
            $this->assertSame('architect', $lesson->target_agent);
        }
    }

    public function test_distill_with_no_failures(): void
    {
        $this->artisan('aicl:rlm', ['action' => 'distill'])
            ->assertSuccessful();

        $this->assertSame(0, DistilledLesson::query()->count());
    }

    public function test_distill_with_multiple_failures_across_categories(): void
    {
        RlmFailure::factory()->create([
            'failure_code' => 'BF-001',
            'category' => FailureCategory::Scaffolding,
            'severity' => FailureSeverity::High,
            'preventive_rule' => 'Override searchableColumns.',
            'owner_id' => $this->admin->id,
        ]);

        RlmFailure::factory()->create([
            'failure_code' => 'BF-012',
            'category' => FailureCategory::Filament,
            'severity' => FailureSeverity::Critical,
            'preventive_rule' => 'Use Schemas namespace.',
            'owner_id' => $this->admin->id,
        ]);

        RlmFailure::factory()->create([
            'failure_code' => 'BF-002',
            'category' => FailureCategory::Process,
            'severity' => FailureSeverity::Critical,
            'preventive_rule' => 'Register after validation.',
            'owner_id' => $this->admin->id,
        ]);

        $this->artisan('aicl:rlm', ['action' => 'distill'])
            ->assertSuccessful();

        // Should have lessons across multiple agents
        $agents = DistilledLesson::query()->pluck('target_agent')->unique()->sort()->values()->all();
        $this->assertGreaterThan(1, count($agents));
    }
}
