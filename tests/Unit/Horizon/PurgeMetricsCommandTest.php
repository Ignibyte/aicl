<?php

namespace Aicl\Tests\Unit\Horizon;

use Aicl\Horizon\Models\QueueMetricSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurgeMetricsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_exists_with_correct_signature(): void
    {
        $this->artisan('aicl:horizon:purge-metrics')
            /** @phpstan-ignore-next-line */
            ->assertSuccessful();
    }

    public function test_command_deletes_old_snapshots(): void
    {
        QueueMetricSnapshot::factory()->create([
            'recorded_at' => now()->subDays(31),
        ]);
        QueueMetricSnapshot::factory()->create([
            'recorded_at' => now()->subDays(35),
        ]);
        QueueMetricSnapshot::factory()->create([
            'recorded_at' => now()->subDays(5),
        ]);

        $this->artisan('aicl:horizon:purge-metrics')
            /** @phpstan-ignore-next-line */
            ->assertSuccessful();

        $this->assertDatabaseCount('queue_metric_snapshots', 1);
    }

    public function test_command_respects_days_option(): void
    {
        QueueMetricSnapshot::factory()->create([
            'recorded_at' => now()->subDays(8),
        ]);
        QueueMetricSnapshot::factory()->create([
            'recorded_at' => now()->subDays(5),
        ]);

        $this->artisan('aicl:horizon:purge-metrics', ['--days' => 7])
            /** @phpstan-ignore-next-line */
            ->assertSuccessful();

        $this->assertDatabaseCount('queue_metric_snapshots', 1);
    }

    public function test_command_respects_config_override(): void
    {
        config()->set('aicl-horizon.metrics.retention_days', 10);

        QueueMetricSnapshot::factory()->create([
            'recorded_at' => now()->subDays(15),
        ]);
        QueueMetricSnapshot::factory()->create([
            'recorded_at' => now()->subDays(5),
        ]);

        $this->artisan('aicl:horizon:purge-metrics')
            /** @phpstan-ignore-next-line */
            ->assertSuccessful();

        $this->assertDatabaseCount('queue_metric_snapshots', 1);
    }

    public function test_command_outputs_deletion_count(): void
    {
        QueueMetricSnapshot::factory()->count(3)->create([
            'recorded_at' => now()->subDays(31),
        ]);

        $this->artisan('aicl:horizon:purge-metrics')
            /** @phpstan-ignore-next-line */
            ->expectsOutputToContain('3')
            ->assertSuccessful();
    }

    public function test_command_returns_success_exit_code(): void
    {
        $this->artisan('aicl:horizon:purge-metrics')
            /** @phpstan-ignore-next-line */
            ->assertExitCode(0);
    }

    public function test_command_handles_empty_table(): void
    {
        $this->assertDatabaseCount('queue_metric_snapshots', 0);

        $this->artisan('aicl:horizon:purge-metrics')
            /** @phpstan-ignore-next-line */
            ->assertSuccessful();

        $this->assertDatabaseCount('queue_metric_snapshots', 0);
    }
}
