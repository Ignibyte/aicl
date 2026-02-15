<?php

namespace Aicl\Tests\Feature\Console;

use Aicl\Enums\FailureCategory;
use Aicl\Enums\FailureSeverity;
use Aicl\Models\DistilledLesson;
use Aicl\Models\RlmFailure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeedbackCommandTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['id' => 1]);
    }

    // -- Full Feedback Flow -------------------------------------------------

    public function test_full_feedback_flow_updates_counts(): void
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

        // BF-005 occurred, BF-001 did not
        $this->artisan('aicl:rlm', [
            'action' => 'feedback',
            '--entity' => 'Project',
            '--surfaced' => 'DL-001-A3,DL-002-T4',
            '--failures' => 'BF-005',
        ])
            ->assertSuccessful()
            ->expectsOutputToContain('FEEDBACK SUMMARY')
            ->expectsOutputToContain('1 prevented, 1 ignored');

        $preventedLesson->refresh();
        $ignoredLesson->refresh();

        $this->assertSame(1, $preventedLesson->prevented_count);
        $this->assertSame(0, $preventedLesson->ignored_count);
        $this->assertSame(0, $ignoredLesson->prevented_count);
        $this->assertSame(1, $ignoredLesson->ignored_count);
    }

    // -- Prevention Only ----------------------------------------------------

    public function test_prevention_only_all_surfaced_failures_absent(): void
    {
        DistilledLesson::factory()->create([
            'lesson_code' => 'DL-001-A3',
            'source_failure_codes' => ['BF-001'],
            'prevented_count' => 0,
            'ignored_count' => 0,
            'confidence' => 0.80,
            'owner_id' => $this->admin->id,
        ]);

        DistilledLesson::factory()->create([
            'lesson_code' => 'DL-002-T4',
            'source_failure_codes' => ['BF-005'],
            'prevented_count' => 0,
            'ignored_count' => 0,
            'confidence' => 0.80,
            'owner_id' => $this->admin->id,
        ]);

        // No actual failures occurred
        $this->artisan('aicl:rlm', [
            'action' => 'feedback',
            '--entity' => 'Project',
            '--surfaced' => 'DL-001-A3,DL-002-T4',
            '--failures' => '',
        ])
            ->assertSuccessful()
            ->expectsOutputToContain('2 prevented, 0 ignored');

        $this->assertSame(1, DistilledLesson::query()->where('lesson_code', 'DL-001-A3')->first()->prevented_count);
        $this->assertSame(1, DistilledLesson::query()->where('lesson_code', 'DL-002-T4')->first()->prevented_count);
    }

    // -- Ignore Only --------------------------------------------------------

    public function test_ignore_only_all_surfaced_failures_present(): void
    {
        DistilledLesson::factory()->create([
            'lesson_code' => 'DL-001-A3',
            'source_failure_codes' => ['BF-001'],
            'prevented_count' => 0,
            'ignored_count' => 0,
            'confidence' => 0.80,
            'owner_id' => $this->admin->id,
        ]);

        DistilledLesson::factory()->create([
            'lesson_code' => 'DL-002-T4',
            'source_failure_codes' => ['BF-005'],
            'prevented_count' => 0,
            'ignored_count' => 0,
            'confidence' => 0.80,
            'owner_id' => $this->admin->id,
        ]);

        // Both source failures occurred
        $this->artisan('aicl:rlm', [
            'action' => 'feedback',
            '--entity' => 'Project',
            '--surfaced' => 'DL-001-A3,DL-002-T4',
            '--failures' => 'BF-001,BF-005',
        ])
            ->assertSuccessful()
            ->expectsOutputToContain('0 prevented, 2 ignored');

        $this->assertSame(1, DistilledLesson::query()->where('lesson_code', 'DL-001-A3')->first()->ignored_count);
        $this->assertSame(1, DistilledLesson::query()->where('lesson_code', 'DL-002-T4')->first()->ignored_count);
    }

    // -- Uncovered Failures -------------------------------------------------

    public function test_uncovered_failures_are_reported(): void
    {
        DistilledLesson::factory()->create([
            'lesson_code' => 'DL-001-A3',
            'source_failure_codes' => ['BF-001'],
            'prevented_count' => 0,
            'ignored_count' => 0,
            'confidence' => 0.80,
            'owner_id' => $this->admin->id,
        ]);

        // BF-099 is not covered by any surfaced lesson
        RlmFailure::factory()->create([
            'failure_code' => 'BF-099',
            'title' => 'Uncovered failure scenario',
            'category' => FailureCategory::Scaffolding,
            'severity' => FailureSeverity::High,
            'owner_id' => $this->admin->id,
        ]);

        $this->artisan('aicl:rlm', [
            'action' => 'feedback',
            '--entity' => 'Project',
            '--surfaced' => 'DL-001-A3',
            '--failures' => 'BF-099',
        ])
            ->assertSuccessful()
            ->expectsOutputToContain('UNCOVERED FAILURES')
            ->expectsOutputToContain('BF-099');
    }

    // -- Missing Entity Option ----------------------------------------------

    public function test_missing_entity_option_fails(): void
    {
        $this->artisan('aicl:rlm', [
            'action' => 'feedback',
            '--surfaced' => 'DL-001-A3',
        ])
            ->assertFailed()
            ->expectsOutputToContain('--entity');
    }

    // -- Missing Surfaced Option --------------------------------------------

    public function test_missing_surfaced_option_fails(): void
    {
        $this->artisan('aicl:rlm', [
            'action' => 'feedback',
            '--entity' => 'Project',
        ])
            ->assertFailed()
            ->expectsOutputToContain('--surfaced');
    }

    // -- Invalid Lesson Codes -----------------------------------------------

    public function test_invalid_lesson_codes_fails_gracefully(): void
    {
        $this->artisan('aicl:rlm', [
            'action' => 'feedback',
            '--entity' => 'Project',
            '--surfaced' => 'DL-NONEXISTENT-001,DL-NONEXISTENT-002',
        ])
            ->assertFailed()
            ->expectsOutputToContain('No distilled lessons found');
    }

    // -- Empty Failures Marks All as Prevented ------------------------------

    public function test_empty_failures_marks_all_as_prevented(): void
    {
        DistilledLesson::factory()->create([
            'lesson_code' => 'DL-001-A3',
            'source_failure_codes' => ['BF-001'],
            'prevented_count' => 0,
            'ignored_count' => 0,
            'confidence' => 0.80,
            'owner_id' => $this->admin->id,
        ]);

        // --failures not provided at all
        $this->artisan('aicl:rlm', [
            'action' => 'feedback',
            '--entity' => 'Project',
            '--surfaced' => 'DL-001-A3',
        ])
            ->assertSuccessful()
            ->expectsOutputToContain('1 prevented, 0 ignored');

        $lesson = DistilledLesson::query()->where('lesson_code', 'DL-001-A3')->first();
        $this->assertSame(1, $lesson->prevented_count);
        $this->assertSame(0, $lesson->ignored_count);
    }
}
