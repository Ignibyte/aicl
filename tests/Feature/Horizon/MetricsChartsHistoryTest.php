<?php

namespace Aicl\Tests\Feature\Horizon;

use Aicl\Horizon\Contracts\MetricsRepository;
use Aicl\Horizon\Livewire\MetricsCharts;
use Aicl\Horizon\Models\QueueMetricSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class MetricsChartsHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock MetricsRepository for all tests since Redis may not have data
        $mock = Mockery::mock(MetricsRepository::class);
        $mock->shouldReceive('measuredQueues')->andReturn(['default']);
        $mock->shouldReceive('measuredJobs')->andReturn(['App\\Jobs\\TestJob']);
        $mock->shouldReceive('snapshotsForQueue')->andReturn([]);
        $mock->shouldReceive('snapshotsForJob')->andReturn([]);

        app()->instance(MetricsRepository::class, $mock);
    }

    public function test_component_has_time_range_property_defaulting_to_live(): void
    {
        Livewire::test(MetricsCharts::class)
            ->assertSet('timeRange', 'live');
    }

    public function test_setting_time_range_to_1h_queries_database(): void
    {
        QueueMetricSnapshot::factory()->queue('default')->count(5)->create([
            'recorded_at' => now()->subMinutes(30),
        ]);

        $component = Livewire::test(MetricsCharts::class)
            ->set('timeRange', '1h');

        $component->assertStatus(200);
    }

    public function test_component_renders_time_range_buttons(): void
    {
        config()->set('aicl-horizon.metrics.persist_to_database', true);

        Livewire::test(MetricsCharts::class)
            ->assertSee('Live')
            ->assertSee('1h')
            ->assertSee('6h')
            ->assertSee('24h')
            ->assertSee('7d')
            ->assertSee('30d');
    }

    public function test_live_mode_uses_redis_snapshots(): void
    {
        $mock = Mockery::mock(MetricsRepository::class);
        $mock->shouldReceive('measuredQueues')->andReturn(['default']);
        $mock->shouldReceive('measuredJobs')->andReturn([]);
        $mock->shouldReceive('snapshotsForQueue')->with('default')->once()->andReturn([
            (object) ['throughput' => 10, 'runtime' => 50.5, 'time' => time()],
        ]);

        app()->instance(MetricsRepository::class, $mock);

        Livewire::test(MetricsCharts::class)
            ->assertSet('timeRange', 'live')
            ->assertStatus(200);
    }

    public function test_component_renders_charts_with_database_data(): void
    {
        QueueMetricSnapshot::factory()->queue('default')->create([
            'throughput' => 42.5,
            'runtime' => 150.75,
            'recorded_at' => now()->subMinutes(30),
        ]);

        Livewire::test(MetricsCharts::class)
            ->set('timeRange', '1h')
            ->assertSee('42.5')
            ->assertSee('150.75');
    }

    public function test_component_hides_time_range_when_persistence_disabled(): void
    {
        config()->set('aicl-horizon.metrics.persist_to_database', false);

        Livewire::test(MetricsCharts::class)
            ->assertDontSee('30d');
    }

    public function test_empty_database_shows_empty_state(): void
    {
        Livewire::test(MetricsCharts::class)
            ->set('timeRange', '1h')
            ->assertSee('No metrics data available');
    }

    public function test_summary_stats_calculate_from_database_data(): void
    {
        // Create snapshots with known values
        QueueMetricSnapshot::factory()->queue('default')->create([
            'throughput' => 10.0,
            'runtime' => 100.0,
            'recorded_at' => now()->subMinutes(30),
        ]);
        QueueMetricSnapshot::factory()->queue('default')->create([
            'throughput' => 20.0,
            'runtime' => 200.0,
            'recorded_at' => now()->subMinutes(20),
        ]);

        $component = Livewire::test(MetricsCharts::class)
            ->set('timeRange', '1h');

        // Avg throughput = (10 + 20) / 2 = 15.0
        // Peak throughput = 20.0
        $component->assertSee('15.0')  // Avg Throughput
            ->assertSee('20.0');       // Peak Throughput
    }

    public function test_switching_between_queues_and_jobs(): void
    {
        QueueMetricSnapshot::factory()->queue('default')->create([
            'recorded_at' => now()->subMinutes(30),
        ]);
        QueueMetricSnapshot::factory()->job('App\\Jobs\\TestJob')->create([
            'recorded_at' => now()->subMinutes(30),
        ]);

        Livewire::test(MetricsCharts::class)
            ->set('timeRange', '1h')
            ->set('view', 'jobs')
            ->set('selectedJob', 'App\\Jobs\\TestJob')
            ->assertStatus(200);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
