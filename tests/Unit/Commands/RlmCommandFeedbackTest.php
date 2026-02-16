<?php

namespace Aicl\Tests\Unit\Commands;

use Aicl\Models\DistilledLesson;
use Aicl\Models\GenerationTrace;
use Aicl\Models\RlmFailure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests for the `aicl:rlm feedback` and `aicl:rlm health` actions.
 */
class RlmCommandFeedbackTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['id' => 1]);
    }

    // ─── Feedback Action ────────────────────────────────────────

    public function test_feedback_requires_entity(): void
    {
        $this->artisan('aicl:rlm', [
            'action' => 'feedback',
            '--surfaced' => 'DL-001',
        ])
            ->assertFailed()
            ->expectsOutputToContain('--entity');
    }

    public function test_feedback_requires_surfaced(): void
    {
        $this->artisan('aicl:rlm', [
            'action' => 'feedback',
            '--entity' => 'TestEntity',
        ])
            ->assertFailed()
            ->expectsOutputToContain('--surfaced');
    }

    public function test_feedback_fails_when_no_lessons_found(): void
    {
        $this->artisan('aicl:rlm', [
            'action' => 'feedback',
            '--entity' => 'TestEntity',
            '--surfaced' => 'DL-999',
        ])
            ->assertFailed()
            ->expectsOutputToContain('No distilled lessons found');
    }

    public function test_feedback_records_prevented_lessons(): void
    {
        $lesson = DistilledLesson::factory()->create([
            'lesson_code' => 'DL-100',
            'title' => 'Override searchableColumns',
            'source_failure_codes' => ['BF-001'],
            'prevented_count' => 0,
            'ignored_count' => 0,
            'owner_id' => $this->admin->id,
        ]);

        $this->artisan('aicl:rlm', [
            'action' => 'feedback',
            '--entity' => 'TestEntity',
            '--surfaced' => 'DL-100',
            '--failures' => 'none',
        ])
            ->assertSuccessful()
            ->expectsOutputToContain('PREVENTED')
            ->expectsOutputToContain('1 prevented');

        $lesson->refresh();
        $this->assertEquals(1, $lesson->prevented_count);
    }

    public function test_feedback_records_ignored_lessons(): void
    {
        $lesson = DistilledLesson::factory()->create([
            'lesson_code' => 'DL-101',
            'title' => 'Use Schemas namespace',
            'source_failure_codes' => ['BF-012'],
            'prevented_count' => 0,
            'ignored_count' => 0,
            'owner_id' => $this->admin->id,
        ]);

        $this->artisan('aicl:rlm', [
            'action' => 'feedback',
            '--entity' => 'TestEntity',
            '--surfaced' => 'DL-101',
            '--failures' => 'BF-012',
        ])
            ->assertSuccessful()
            ->expectsOutputToContain('IGNORED')
            ->expectsOutputToContain('1 ignored');

        $lesson->refresh();
        $this->assertEquals(1, $lesson->ignored_count);
    }

    public function test_feedback_identifies_uncovered_failures(): void
    {
        $lesson = DistilledLesson::factory()->create([
            'lesson_code' => 'DL-102',
            'title' => 'Test lesson',
            'source_failure_codes' => ['BF-001'],
            'prevented_count' => 0,
            'ignored_count' => 0,
            'owner_id' => $this->admin->id,
        ]);

        RlmFailure::factory()->create([
            'failure_code' => 'BF-099',
            'title' => 'Unknown new failure',
            'owner_id' => $this->admin->id,
        ]);

        $this->artisan('aicl:rlm', [
            'action' => 'feedback',
            '--entity' => 'TestEntity',
            '--surfaced' => 'DL-102',
            '--failures' => 'BF-099',
        ])
            ->assertSuccessful()
            ->expectsOutputToContain('UNCOVERED')
            ->expectsOutputToContain('BF-099');
    }

    public function test_feedback_populates_trace_kpi_fields(): void
    {
        $lesson = DistilledLesson::factory()->create([
            'lesson_code' => 'DL-103',
            'title' => 'Test lesson',
            'source_failure_codes' => ['BF-001'],
            'owner_id' => $this->admin->id,
        ]);

        $trace = GenerationTrace::factory()->create([
            'entity_name' => 'TestEntity',
            'owner_id' => $this->admin->id,
        ]);

        $this->artisan('aicl:rlm', [
            'action' => 'feedback',
            '--entity' => 'TestEntity',
            '--surfaced' => 'DL-103',
            '--failures' => 'BF-001',
        ])
            ->assertSuccessful();

        $trace->refresh();
        $this->assertEquals(1, $trace->known_failure_count);
        $this->assertEquals(0, $trace->novel_failure_count);
        $this->assertEquals(['DL-103'], $trace->surfaced_lesson_codes);
        $this->assertEquals(['BF-001'], $trace->failure_codes_hit);
    }

    public function test_feedback_with_multiple_lessons(): void
    {
        $lesson1 = DistilledLesson::factory()->create([
            'lesson_code' => 'DL-110',
            'title' => 'First lesson',
            'source_failure_codes' => ['BF-001'],
            'owner_id' => $this->admin->id,
        ]);

        $lesson2 = DistilledLesson::factory()->create([
            'lesson_code' => 'DL-111',
            'title' => 'Second lesson',
            'source_failure_codes' => ['BF-012'],
            'owner_id' => $this->admin->id,
        ]);

        $this->artisan('aicl:rlm', [
            'action' => 'feedback',
            '--entity' => 'TestEntity',
            '--surfaced' => 'DL-110,DL-111',
            '--failures' => 'BF-012',
        ])
            ->assertSuccessful()
            ->expectsOutputToContain('Surfaced lessons: 2');
    }

    public function test_feedback_includes_phase_in_output(): void
    {
        $lesson = DistilledLesson::factory()->create([
            'lesson_code' => 'DL-120',
            'title' => 'Phase test',
            'source_failure_codes' => ['BF-001'],
            'owner_id' => $this->admin->id,
        ]);

        $this->artisan('aicl:rlm', [
            'action' => 'feedback',
            '--entity' => 'TestEntity',
            '--phase' => '4',
            '--surfaced' => 'DL-120',
        ])
            ->assertSuccessful()
            ->expectsOutputToContain('phase 4');
    }

    // ─── Health Action ──────────────────────────────────────────

    public function test_health_runs_successfully(): void
    {
        $this->artisan('aicl:rlm', ['action' => 'health'])
            ->assertSuccessful()
            ->expectsOutputToContain('RLM SYSTEM HEALTH')
            ->expectsOutputToContain('PIPELINE VELOCITY')
            ->expectsOutputToContain('FAILURE PROFILE')
            ->expectsOutputToContain('LESSON EFFECTIVENESS');
    }

    public function test_health_shows_insufficient_data_message(): void
    {
        // No traces seeded, so should show insufficient data
        $this->artisan('aicl:rlm', ['action' => 'health'])
            ->assertSuccessful()
            ->expectsOutputToContain('Insufficient data');
    }

    public function test_health_with_verdict_flag(): void
    {
        $this->artisan('aicl:rlm', [
            'action' => 'health',
            '--verdict' => true,
        ])
            ->assertSuccessful()
            ->expectsOutputToContain('SYSTEM VERDICT');
    }

    public function test_health_without_verdict_flag(): void
    {
        \Illuminate\Support\Facades\Artisan::call('aicl:rlm', ['action' => 'health']);
        $output = \Illuminate\Support\Facades\Artisan::output();

        $this->assertStringNotContainsString('SYSTEM VERDICT', $output);
    }
}
