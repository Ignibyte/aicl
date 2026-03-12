<?php

namespace Aicl\Tests\Feature\Horizon;

use Aicl\Filament\Widgets\QueueStatsWidget;
use Aicl\Horizon\Contracts\MetricsRepository;
use Filament\Widgets\StatsOverviewWidget;
use Mockery;
use Tests\TestCase;

class QueueStatsWidgetTest extends TestCase
{
    public function test_widget_extends_stats_overview(): void
    {
        $this->assertTrue(is_subclass_of(QueueStatsWidget::class, StatsOverviewWidget::class));
    }

    public function test_widget_includes_horizon_throughput_when_available(): void
    {
        config(['aicl.features.horizon' => true]);

        $snapshot = (object) ['throughput' => 15.5];

        $metricsRepo = Mockery::mock(MetricsRepository::class);
        $metricsRepo->shouldReceive('snapshotsForQueue')
            ->with('default')
            ->andReturn([$snapshot]);
        app()->instance(MetricsRepository::class, $metricsRepo);

        $widget = new QueueStatsWidget;

        // Access protected method via reflection
        $method = new \ReflectionMethod($widget, 'getStats');
        $stats = $method->invoke($widget);

        // Should have 4 stats: Pending, Failed, Last Failure, Jobs/Min
        $this->assertCount(4, $stats);

        // The last stat should be the Horizon throughput
        $lastStat = end($stats);
        $this->assertStringContainsString('15.5', $lastStat->getValue());
    }

    public function test_widget_has_three_stats_without_horizon(): void
    {
        config(['aicl.features.horizon' => false]);

        $widget = new QueueStatsWidget;

        $method = new \ReflectionMethod($widget, 'getStats');
        $stats = $method->invoke($widget);

        // Without Horizon: Pending, Failed, Last Failure
        $this->assertCount(3, $stats);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
