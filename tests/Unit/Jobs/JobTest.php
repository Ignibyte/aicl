<?php

namespace Aicl\Tests\Unit\Jobs;

use Aicl\Jobs\CheckPromotionCandidatesJob;
use Aicl\Jobs\CleanStaleDeliveriesJob;
use Aicl\Jobs\GenerateEmbeddingJob;
use Aicl\Jobs\RefreshHealthChecksJob;
use Aicl\Models\RlmPattern;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class JobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        User::factory()->create(['id' => 1]);
        Queue::fake();
    }

    // ── GenerateEmbeddingJob ─────────────────────────────────────────

    public function test_generate_embedding_job_implements_should_queue(): void
    {
        $this->assertTrue(
            in_array(ShouldQueue::class, class_implements(GenerateEmbeddingJob::class))
        );
    }

    public function test_generate_embedding_job_has_handle_method(): void
    {
        $this->assertTrue(method_exists(GenerateEmbeddingJob::class, 'handle'));
    }

    public function test_generate_embedding_job_constructor_accepts_model(): void
    {
        $pattern = RlmPattern::factory()->create(['owner_id' => 1]);
        $job = new GenerateEmbeddingJob($pattern);

        $this->assertSame($pattern->id, $job->model->id);
    }

    public function test_generate_embedding_job_has_tries_property(): void
    {
        $pattern = RlmPattern::factory()->create(['owner_id' => 1]);
        $job = new GenerateEmbeddingJob($pattern);

        $this->assertSame(3, $job->tries);
    }

    public function test_generate_embedding_job_has_backoff_property(): void
    {
        $pattern = RlmPattern::factory()->create(['owner_id' => 1]);
        $job = new GenerateEmbeddingJob($pattern);

        $this->assertSame(10, $job->backoff);
    }

    public function test_generate_embedding_job_can_be_dispatched(): void
    {
        $pattern = RlmPattern::factory()->create(['owner_id' => 1]);

        GenerateEmbeddingJob::dispatch($pattern);

        Queue::assertPushed(GenerateEmbeddingJob::class);
    }

    public function test_generate_embedding_job_uses_dispatchable_trait(): void
    {
        $this->assertTrue(
            in_array(
                \Illuminate\Foundation\Bus\Dispatchable::class,
                class_uses_recursive(GenerateEmbeddingJob::class)
            )
        );
    }

    public function test_generate_embedding_job_uses_queueable_trait(): void
    {
        $this->assertTrue(
            in_array(
                \Illuminate\Bus\Queueable::class,
                class_uses_recursive(GenerateEmbeddingJob::class)
            )
        );
    }

    public function test_generate_embedding_job_uses_serializes_models_trait(): void
    {
        $this->assertTrue(
            in_array(
                \Illuminate\Queue\SerializesModels::class,
                class_uses_recursive(GenerateEmbeddingJob::class)
            )
        );
    }

    // ── CheckPromotionCandidatesJob ──────────────────────────────────

    public function test_check_promotion_candidates_job_implements_should_queue(): void
    {
        $this->assertTrue(
            in_array(ShouldQueue::class, class_implements(CheckPromotionCandidatesJob::class))
        );
    }

    public function test_check_promotion_candidates_job_has_handle_method(): void
    {
        $this->assertTrue(method_exists(CheckPromotionCandidatesJob::class, 'handle'));
    }

    public function test_check_promotion_candidates_job_uses_dispatchable_trait(): void
    {
        $this->assertTrue(
            in_array(
                \Illuminate\Foundation\Bus\Dispatchable::class,
                class_uses_recursive(CheckPromotionCandidatesJob::class)
            )
        );
    }

    public function test_check_promotion_candidates_job_uses_queueable_trait(): void
    {
        $this->assertTrue(
            in_array(
                \Illuminate\Bus\Queueable::class,
                class_uses_recursive(CheckPromotionCandidatesJob::class)
            )
        );
    }

    // ── CleanStaleDeliveriesJob ──────────────────────────────────────

    public function test_clean_stale_deliveries_job_implements_should_queue(): void
    {
        $this->assertTrue(
            in_array(ShouldQueue::class, class_implements(CleanStaleDeliveriesJob::class))
        );
    }

    public function test_clean_stale_deliveries_job_has_handle_method(): void
    {
        $this->assertTrue(method_exists(CleanStaleDeliveriesJob::class, 'handle'));
    }

    public function test_clean_stale_deliveries_job_uses_dispatchable_trait(): void
    {
        $this->assertTrue(
            in_array(
                \Illuminate\Foundation\Bus\Dispatchable::class,
                class_uses_recursive(CleanStaleDeliveriesJob::class)
            )
        );
    }

    public function test_clean_stale_deliveries_job_uses_queueable_trait(): void
    {
        $this->assertTrue(
            in_array(
                \Illuminate\Bus\Queueable::class,
                class_uses_recursive(CleanStaleDeliveriesJob::class)
            )
        );
    }

    // ── RefreshHealthChecksJob ────────────────────────────────────────

    public function test_refresh_health_checks_job_implements_should_queue(): void
    {
        $this->assertTrue(
            in_array(ShouldQueue::class, class_implements(RefreshHealthChecksJob::class))
        );
    }

    public function test_refresh_health_checks_job_has_handle_method(): void
    {
        $this->assertTrue(method_exists(RefreshHealthChecksJob::class, 'handle'));
    }

    public function test_refresh_health_checks_job_uses_dispatchable_trait(): void
    {
        $this->assertTrue(
            in_array(
                \Illuminate\Foundation\Bus\Dispatchable::class,
                class_uses_recursive(RefreshHealthChecksJob::class)
            )
        );
    }

    public function test_refresh_health_checks_job_uses_queueable_trait(): void
    {
        $this->assertTrue(
            in_array(
                \Illuminate\Bus\Queueable::class,
                class_uses_recursive(RefreshHealthChecksJob::class)
            )
        );
    }
}
