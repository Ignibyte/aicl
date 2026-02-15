<?php

namespace Aicl\Tests\Unit\Commands;

use Aicl\Models\PreventionRule;
use Aicl\Models\RlmFailure;
use Aicl\Models\RlmLesson;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RlmCleanupTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['id' => 1]);
    }

    public function test_cleanup_requires_remove_faker_records_flag(): void
    {
        $this->artisan('aicl:rlm', ['action' => 'cleanup'])
            ->assertFailed()
            ->expectsOutputToContain('--remove-faker-records');
    }

    public function test_cleanup_removes_faker_failures(): void
    {
        // Create a base failure (should survive)
        RlmFailure::factory()->create([
            'failure_code' => 'BF-001',
            'owner_id' => $this->admin->id,
        ]);

        // Create faker failures (should be removed)
        RlmFailure::factory()->create([
            'failure_code' => 'F-148',
            'owner_id' => $this->admin->id,
        ]);
        RlmFailure::factory()->create([
            'failure_code' => 'F-076',
            'owner_id' => $this->admin->id,
        ]);

        $this->assertSame(3, RlmFailure::query()->count());

        $this->artisan('aicl:rlm', [
            'action' => 'cleanup',
            '--remove-faker-records' => true,
        ])->assertSuccessful()
            ->expectsOutputToContain('2 faker records found');

        $this->assertSame(1, RlmFailure::query()->count());
        $this->assertTrue(RlmFailure::query()->where('failure_code', 'BF-001')->exists());
    }

    public function test_cleanup_removes_orphaned_prevention_rules(): void
    {
        $fakerFailure = RlmFailure::factory()->create([
            'failure_code' => 'F-999',
            'owner_id' => $this->admin->id,
        ]);

        $baseFailure = RlmFailure::factory()->create([
            'failure_code' => 'BF-002',
            'owner_id' => $this->admin->id,
        ]);

        // Rule linked to faker failure (should be removed)
        PreventionRule::factory()->create([
            'rlm_failure_id' => $fakerFailure->id,
            'owner_id' => $this->admin->id,
        ]);

        // Rule linked to base failure (should survive)
        PreventionRule::factory()->create([
            'rlm_failure_id' => $baseFailure->id,
            'owner_id' => $this->admin->id,
        ]);

        $this->assertSame(2, PreventionRule::query()->count());

        $this->artisan('aicl:rlm', [
            'action' => 'cleanup',
            '--remove-faker-records' => true,
        ])->assertSuccessful()
            ->expectsOutputToContain('1 rules linked to faker failures');

        $this->assertSame(1, PreventionRule::query()->count());
        $this->assertTrue(PreventionRule::query()->where('rlm_failure_id', $baseFailure->id)->exists());
    }

    public function test_cleanup_removes_faker_lessons(): void
    {
        // Curated lesson (should survive)
        RlmLesson::factory()->create([
            'source' => 'base-seeder',
            'confidence' => 1.0,
            'owner_id' => $this->admin->id,
        ]);

        // Agent-discovered lesson (should survive)
        RlmLesson::factory()->create([
            'source' => 'agent-discovery',
            'confidence' => 0.8,
            'owner_id' => $this->admin->id,
        ]);

        // Factory lesson (should be removed)
        RlmLesson::factory()->create([
            'source' => 'factory',
            'owner_id' => $this->admin->id,
        ]);

        // Null source + low confidence (should be removed)
        RlmLesson::factory()->create([
            'source' => null,
            'confidence' => 0.3,
            'owner_id' => $this->admin->id,
        ]);

        $this->assertSame(4, RlmLesson::query()->count());

        $this->artisan('aicl:rlm', [
            'action' => 'cleanup',
            '--remove-faker-records' => true,
        ])->assertSuccessful();

        $this->assertSame(2, RlmLesson::query()->count());
        $this->assertTrue(RlmLesson::query()->where('source', 'base-seeder')->exists());
        $this->assertTrue(RlmLesson::query()->where('source', 'agent-discovery')->exists());
    }

    public function test_cleanup_dry_run_does_not_delete(): void
    {
        RlmFailure::factory()->create([
            'failure_code' => 'F-100',
            'owner_id' => $this->admin->id,
        ]);

        RlmLesson::factory()->create([
            'source' => 'factory',
            'owner_id' => $this->admin->id,
        ]);

        $this->artisan('aicl:rlm', [
            'action' => 'cleanup',
            '--remove-faker-records' => true,
            '--dry-run' => true,
        ])->assertSuccessful()
            ->expectsOutputToContain('[DRY RUN]');

        // Nothing should be deleted in dry run mode
        $this->assertSame(1, RlmFailure::query()->count());
        $this->assertSame(1, RlmLesson::query()->count());
    }

    public function test_cleanup_reports_clean_database(): void
    {
        // Only base failure, no faker records
        RlmFailure::factory()->create([
            'failure_code' => 'BF-001',
            'owner_id' => $this->admin->id,
        ]);

        $this->artisan('aicl:rlm', [
            'action' => 'cleanup',
            '--remove-faker-records' => true,
        ])->assertSuccessful()
            ->expectsOutputToContain('No faker records found');

        $this->assertSame(1, RlmFailure::query()->count());
    }

    public function test_cleanup_does_not_affect_bf_prefixed_failures(): void
    {
        // Create all 15 base failures
        foreach (range(1, 15) as $i) {
            RlmFailure::factory()->create([
                'failure_code' => sprintf('BF-%03d', $i),
                'owner_id' => $this->admin->id,
            ]);
        }

        // Add faker failures
        RlmFailure::factory()->create([
            'failure_code' => 'F-1',
            'owner_id' => $this->admin->id,
        ]);
        RlmFailure::factory()->create([
            'failure_code' => 'F-42',
            'owner_id' => $this->admin->id,
        ]);

        $this->artisan('aicl:rlm', [
            'action' => 'cleanup',
            '--remove-faker-records' => true,
        ])->assertSuccessful();

        // All 15 base failures should remain
        $this->assertSame(15, RlmFailure::query()->count());

        foreach (range(1, 15) as $i) {
            $this->assertTrue(
                RlmFailure::query()->where('failure_code', sprintf('BF-%03d', $i))->exists(),
                "BF-{$i} should not be deleted"
            );
        }
    }
}
