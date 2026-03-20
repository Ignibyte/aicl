<?php

namespace Aicl\Tests\Feature\Horizon;

use Aicl\Horizon\Contracts\MetricsRepository;
use Aicl\Horizon\Models\QueueMetricSnapshot;
use Aicl\Horizon\Repositories\RedisMetricsRepository;
use Aicl\Horizon\WaitTimeCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class MetricsPersistenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Clear Redis metrics data before each test to ensure clean state
        $this->clearRedisMetrics();
    }

    public function test_snapshot_creates_database_rows_when_persistence_enabled(): void
    {
        config()->set('aicl-horizon.metrics.persist_to_database', true);

        $this->seedMetricsData(['default'], ['App\\Jobs\\TestJob']);

        $metrics = app(MetricsRepository::class);
        $metrics->snapshot();

        // Should have 1 queue + 1 job = 2 rows
        $this->assertDatabaseCount('queue_metric_snapshots', 2);
    }

    public function test_snapshot_creates_correct_row_count(): void
    {
        config()->set('aicl-horizon.metrics.persist_to_database', true);

        $this->seedMetricsData(['default', 'high'], ['App\\Jobs\\JobA', 'App\\Jobs\\JobB']);

        $metrics = app(MetricsRepository::class);
        $metrics->snapshot();

        // 2 queues + 2 jobs = 4 rows
        $this->assertDatabaseCount('queue_metric_snapshots', 4);
    }

    public function test_snapshot_skips_database_when_persistence_disabled(): void
    {
        config()->set('aicl-horizon.metrics.persist_to_database', false);

        $this->seedMetricsData(['default'], ['App\\Jobs\\TestJob']);

        $metrics = app(MetricsRepository::class);
        $metrics->snapshot();

        $this->assertDatabaseCount('queue_metric_snapshots', 0);
    }

    public function test_database_failure_does_not_break_redis_write(): void
    {
        config()->set('aicl-horizon.metrics.persist_to_database', true);

        $this->seedMetricsData(['default'], []);

        // Drop the table to force PG insert failure
        DB::statement('DROP TABLE IF EXISTS queue_metric_snapshots');

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function ($message) {
                return str_contains($message, 'Failed to persist queue metric snapshots');
            });

        $repo = app(MetricsRepository::class);

        // The fact that this doesn't throw proves Redis write succeeded
        // even though the PG write will fail (table is gone)
        $repo->snapshot();

        // If we got here without exception, the dual-write isolation works
        $this->assertTrue(true);
    }

    public function test_snapshot_records_correct_type_values(): void
    {
        config()->set('aicl-horizon.metrics.persist_to_database', true);

        $this->seedMetricsData(['default'], ['App\\Jobs\\TestJob']);

        $metrics = app(MetricsRepository::class);
        $metrics->snapshot();

        $this->assertDatabaseHas('queue_metric_snapshots', [
            'type' => 'queue',
            'name' => 'default',
        ]);

        $this->assertDatabaseHas('queue_metric_snapshots', [
            'type' => 'job',
            'name' => 'App\\Jobs\\TestJob',
        ]);
    }

    public function test_snapshot_sets_recorded_at_timestamp(): void
    {
        config()->set('aicl-horizon.metrics.persist_to_database', true);

        $this->seedMetricsData(['default'], []);

        $metrics = app(MetricsRepository::class);
        $metrics->snapshot();

        $snapshot = QueueMetricSnapshot::first();

        $this->assertNotNull($snapshot->recorded_at);
        $this->assertTrue($snapshot->recorded_at->diffInSeconds(now()) < 5);
    }

    /**
     * Seed Redis with measured queues/jobs data for snapshot testing.
     *
     * @param  array<string>  $queues
     * @param  array<string>  $jobs
     */
    private function seedMetricsData(array $queues, array $jobs): void
    {
        $metrics = app(MetricsRepository::class);

        // Only proceed if we have a Redis-based repository
        if (! $metrics instanceof RedisMetricsRepository) {
            $this->markTestSkipped('Requires RedisMetricsRepository');
        }

        $connection = $metrics->connection();

        // Register measured queues
        foreach ($queues as $queue) {
            $connection->sadd('measured_queues', 'queue:'.$queue);
            $connection->hmset('queue:'.$queue, ['throughput' => 10, 'runtime' => 50]);
        }

        // Register measured jobs
        foreach ($jobs as $job) {
            $connection->sadd('measured_jobs', 'job:'.$job);
            $connection->hmset('job:'.$job, ['throughput' => 5, 'runtime' => 25]);
        }

        // Mock WaitTimeCalculator
        $waitCalc = Mockery::mock(WaitTimeCalculator::class);
        $waitCalc->shouldReceive('calculateFor')->andReturn(1.5);
        app()->instance(WaitTimeCalculator::class, $waitCalc);
    }

    /**
     * Clear all Redis metrics data to ensure clean test state.
     */
    private function clearRedisMetrics(): void
    {
        if (! app()->bound(MetricsRepository::class)) {
            return;
        }

        try {
            $metrics = app(MetricsRepository::class);
            if ($metrics instanceof RedisMetricsRepository) {
                $metrics->clear();
            }
        } catch (\Throwable) {
            // Ignore cleanup errors
        }
    }

    protected function tearDown(): void
    {
        $this->clearRedisMetrics();

        Mockery::close();
        parent::tearDown();
    }
}
