<?php

namespace Aicl\Tests\Unit\Jobs;

use Aicl\Enums\FailureCategory;
use Aicl\Enums\FailureSeverity;
use Aicl\Jobs\RedistillJob;
use Aicl\Models\DistilledLesson;
use Aicl\Models\RlmFailure;
use Aicl\Rlm\DistillationService;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RedistillJobTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['id' => 1]);
    }

    // -- Job Structure Tests ------------------------------------------------

    public function test_redistill_job_implements_should_queue(): void
    {
        $this->assertTrue(
            in_array(ShouldQueue::class, class_implements(RedistillJob::class))
        );
    }

    public function test_redistill_job_has_handle_method(): void
    {
        $this->assertTrue(method_exists(RedistillJob::class, 'handle'));
    }

    public function test_redistill_job_uses_dispatchable_trait(): void
    {
        $this->assertTrue(
            in_array(
                \Illuminate\Foundation\Bus\Dispatchable::class,
                class_uses_recursive(RedistillJob::class)
            )
        );
    }

    public function test_redistill_job_uses_queueable_trait(): void
    {
        $this->assertTrue(
            in_array(
                \Illuminate\Bus\Queueable::class,
                class_uses_recursive(RedistillJob::class)
            )
        );
    }

    public function test_redistill_job_constructor_stores_failure_codes(): void
    {
        $codes = ['BF-001', 'BF-002'];
        $job = new RedistillJob($codes);

        $this->assertSame($codes, $job->failureCodes);
    }

    public function test_redistill_job_has_tries_property(): void
    {
        $job = new RedistillJob(['BF-001']);

        $this->assertSame(3, $job->tries);
    }

    public function test_redistill_job_has_backoff_property(): void
    {
        $job = new RedistillJob(['BF-001']);

        $this->assertSame(10, $job->backoff);
    }

    // -- Job Execution Tests ------------------------------------------------

    public function test_job_calls_distill_cluster_with_failure_codes(): void
    {
        $failureCodes = ['BF-001', 'BF-002'];

        $service = $this->mock(DistillationService::class);
        $service->shouldReceive('distillCluster')
            ->once()
            ->with($failureCodes)
            ->andReturn(['clusters' => 1, 'lessons' => 2, 'agents' => ['architect' => 2]]);

        $job = new RedistillJob($failureCodes);
        $job->handle($service);
    }

    public function test_job_with_empty_failure_codes_returns_early(): void
    {
        $service = $this->mock(DistillationService::class);
        $service->shouldNotReceive('distillCluster');

        Log::shouldReceive('debug')
            ->once()
            ->withArgs(function (string $message) {
                return str_contains($message, 'No failure codes');
            });

        $job = new RedistillJob([]);
        $job->handle($service);
    }

    // -- Observer Integration Tests -----------------------------------------

    public function test_observer_dispatches_redistill_job_on_new_failure_with_cluster(): void
    {
        Queue::fake();

        // Create an existing failure with a distilled lesson covering it
        $existingFailure = RlmFailure::factory()->create([
            'failure_code' => 'BF-001',
            'category' => FailureCategory::Scaffolding,
            'subcategory' => null,
            'severity' => FailureSeverity::High,
            'is_active' => true,
            'owner_id' => $this->admin->id,
        ]);

        DistilledLesson::factory()->create([
            'lesson_code' => 'DL-001-A3',
            'source_failure_codes' => ['BF-001'],
            'is_active' => true,
            'owner_id' => $this->admin->id,
        ]);

        // Create a new failure in the same category — should trigger observer
        RlmFailure::factory()->create([
            'failure_code' => 'BF-050',
            'category' => FailureCategory::Scaffolding,
            'subcategory' => null,
            'severity' => FailureSeverity::Medium,
            'is_active' => true,
            'owner_id' => $this->admin->id,
        ]);

        Queue::assertPushed(RedistillJob::class, function (RedistillJob $job) {
            return in_array('BF-001', $job->failureCodes) && in_array('BF-050', $job->failureCodes);
        });
    }

    public function test_observer_skips_inactive_failure(): void
    {
        Queue::fake();

        // Create an existing failure with a distilled lesson covering it
        RlmFailure::factory()->create([
            'failure_code' => 'BF-001',
            'category' => FailureCategory::Scaffolding,
            'severity' => FailureSeverity::High,
            'is_active' => true,
            'owner_id' => $this->admin->id,
        ]);

        DistilledLesson::factory()->create([
            'lesson_code' => 'DL-001-A3',
            'source_failure_codes' => ['BF-001'],
            'is_active' => true,
            'owner_id' => $this->admin->id,
        ]);

        // Create an INACTIVE failure — observer should skip it
        RlmFailure::factory()->inactive()->create([
            'failure_code' => 'BF-051',
            'category' => FailureCategory::Scaffolding,
            'severity' => FailureSeverity::Medium,
            'owner_id' => $this->admin->id,
        ]);

        Queue::assertNotPushed(RedistillJob::class);
    }

    public function test_observer_handles_no_cluster_match(): void
    {
        Queue::fake();

        // Create a failure in a category with no existing distilled lessons
        RlmFailure::factory()->create([
            'failure_code' => 'BF-060',
            'category' => FailureCategory::Auth,
            'severity' => FailureSeverity::Low,
            'is_active' => true,
            'owner_id' => $this->admin->id,
        ]);

        // No distilled lessons exist that cover Auth failures, so no job dispatched
        Queue::assertNotPushed(RedistillJob::class);
    }
}
