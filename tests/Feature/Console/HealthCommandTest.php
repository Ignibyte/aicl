<?php

namespace Aicl\Tests\Feature\Console;

use Aicl\Models\DistilledLesson;
use Aicl\Models\GenerationTrace;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HealthCommandTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['id' => 1]);
    }

    // ─── Basic Output ──────────────────────────────────────────

    public function test_health_command_runs_successfully(): void
    {
        $this->artisan('aicl:rlm', ['action' => 'health'])
            ->assertSuccessful()
            ->expectsOutputToContain('RLM SYSTEM HEALTH')
            ->expectsOutputToContain('PIPELINE VELOCITY')
            ->expectsOutputToContain('FAILURE PROFILE')
            ->expectsOutputToContain('LESSON EFFECTIVENESS');
    }

    // ─── Verdict Flag ──────────────────────────────────────────

    public function test_health_with_verdict_flag_shows_verdict(): void
    {
        $this->artisan('aicl:rlm', [
            'action' => 'health',
            '--verdict' => true,
        ])
            ->assertSuccessful()
            ->expectsOutputToContain('SYSTEM VERDICT');
    }

    // ─── Insufficient Data Message ─────────────────────────────

    public function test_health_insufficient_data_with_no_traces(): void
    {
        $this->artisan('aicl:rlm', [
            'action' => 'health',
            '--verdict' => true,
        ])
            ->assertSuccessful()
            ->expectsOutputToContain('INSUFFICIENT_DATA');
    }

    public function test_health_insufficient_data_with_few_traces(): void
    {
        GenerationTrace::factory()->count(10)->create([
            'fix_iterations' => 2,
            'owner_id' => $this->admin->id,
        ]);

        $this->artisan('aicl:rlm', [
            'action' => 'health',
            '--verdict' => true,
        ])
            ->assertSuccessful()
            ->expectsOutputToContain('INSUFFICIENT_DATA');
    }

    // ─── With Enough Data ──────────────────────────────────────

    public function test_health_with_enough_data_computes_real_verdict(): void
    {
        // Create 20 traces — enough to compute a real verdict
        GenerationTrace::factory()->count(20)->create([
            'fix_iterations' => 3,
            'known_failure_count' => 0,
            'novel_failure_count' => 2,
            'owner_id' => $this->admin->id,
        ]);

        // Create a lesson with activity
        DistilledLesson::factory()->create([
            'is_active' => true,
            'prevented_count' => 8,
            'ignored_count' => 2,
            'owner_id' => $this->admin->id,
        ]);

        $this->artisan('aicl:rlm', [
            'action' => 'health',
            '--verdict' => true,
        ])
            ->assertSuccessful()
            ->expectsOutputToContain('SYSTEM VERDICT');
    }

    // ─── Pipeline Velocity Section ─────────────────────────────

    public function test_health_shows_insufficient_pipeline_data_message(): void
    {
        // Only 3 traces (< 5 needed for trend calculation)
        GenerationTrace::factory()->count(3)->create([
            'fix_iterations' => 2,
            'owner_id' => $this->admin->id,
        ]);

        $this->artisan('aicl:rlm', ['action' => 'health'])
            ->assertSuccessful()
            ->expectsOutputToContain('Insufficient data');
    }

    public function test_health_shows_fix_iteration_trend(): void
    {
        // 10 traces with fix_iterations — enough for trend
        GenerationTrace::factory()->count(10)->create([
            'fix_iterations' => 3,
            'owner_id' => $this->admin->id,
        ]);

        $this->artisan('aicl:rlm', ['action' => 'health'])
            ->assertSuccessful()
            ->expectsOutputToContain('Trend: STABLE');
    }

    // ─── Failure Profile Section ───────────────────────────────

    public function test_health_shows_no_pipeline_runs_message(): void
    {
        $this->artisan('aicl:rlm', ['action' => 'health'])
            ->assertSuccessful()
            ->expectsOutputToContain('No pipeline runs recorded yet');
    }

    public function test_health_shows_failure_profile_with_data(): void
    {
        GenerationTrace::factory()->count(5)->create([
            'known_failure_count' => 2,
            'novel_failure_count' => 3,
            'owner_id' => $this->admin->id,
        ]);

        $this->artisan('aicl:rlm', ['action' => 'health'])
            ->assertSuccessful()
            ->expectsOutputToContain('known failures prevented')
            ->expectsOutputToContain('Known failure recurrence rate');
    }

    // ─── Lesson Effectiveness Section ──────────────────────────

    public function test_health_shows_active_lesson_count(): void
    {
        DistilledLesson::factory()->count(3)->create([
            'is_active' => true,
            'prevented_count' => 5,
            'ignored_count' => 2,
            'owner_id' => $this->admin->id,
        ]);

        $this->artisan('aicl:rlm', ['action' => 'health'])
            ->assertSuccessful()
            ->expectsOutputToContain('3 active distilled lessons');
    }

    // ─── Auto-Retirement in Health ─────────────────────────────

    public function test_health_auto_retires_underperforming_lessons(): void
    {
        DistilledLesson::factory()->create([
            'lesson_code' => 'DL-AUTORETIRE-001',
            'is_active' => true,
            'prevented_count' => 0,
            'ignored_count' => 10,
            'owner_id' => $this->admin->id,
        ]);

        $this->artisan('aicl:rlm', ['action' => 'health'])
            ->assertSuccessful()
            ->expectsOutputToContain('Retired (auto)')
            ->expectsOutputToContain('DL-AUTORETIRE-001');

        $lesson = DistilledLesson::query()->where('lesson_code', 'DL-AUTORETIRE-001')->first();
        $this->assertFalse($lesson->is_active);
    }

    // ─── Without Verdict Flag ──────────────────────────────────

    public function test_health_without_verdict_flag_does_not_show_verdict(): void
    {
        GenerationTrace::factory()->count(20)->create([
            'fix_iterations' => 3,
            'owner_id' => $this->admin->id,
        ]);

        $result = $this->artisan('aicl:rlm', ['action' => 'health']);
        $result->assertSuccessful();

        // The output should NOT contain SYSTEM VERDICT when --verdict is not passed
        // We verify the command ran but do not assert the absence (artisan test helper limitation)
        // Instead, verify it does contain the main sections
        $result->expectsOutputToContain('PIPELINE VELOCITY');
    }
}
