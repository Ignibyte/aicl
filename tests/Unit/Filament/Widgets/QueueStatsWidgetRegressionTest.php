<?php

declare(strict_types=1);

namespace Aicl\Tests\Unit\Filament\Widgets;

use Aicl\Filament\Widgets\QueueStatsWidget;
use Aicl\Horizon\Contracts\MetricsRepository;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Regression tests for QueueStatsWidget PHPStan changes.
 *
 * Covers the Cache::remember wrapping, the Carbon::parse for cached
 * ISO 8601 timestamps, the null coalescing on last_failed_name, and
 * the safe fallback when Redis queue size fails.
 */
class QueueStatsWidgetRegressionTest extends TestCase
{
    use DatabaseTransactions;

    // -- Cache TTL constant --

    /**
     * Test CACHE_TTL constant is set to 30 seconds.
     *
     * PHPStan change: Added private const CACHE_TTL = 30 for the
     * Cache::remember wrapper on failed job queries.
     */
    public function test_cache_ttl_constant_is_30_seconds(): void
    {
        // Arrange
        $reflection = new \ReflectionClass(QueueStatsWidget::class);
        $constant = $reflection->getReflectionConstant('CACHE_TTL');

        // Assert: constant exists and has value 30
        $this->assertNotFalse($constant);
        $this->assertSame(30, $constant->getValue());
    }

    // -- getQueueSize fallback --

    /**
     * Test getQueueSize returns non-negative value.
     *
     * The method wraps Queue::size() in a try/catch to handle Redis
     * connection failures gracefully.
     */
    public function test_get_queue_size_returns_non_negative(): void
    {
        // Arrange: invoke protected method via reflection
        $widget = new QueueStatsWidget;
        $method = new \ReflectionMethod($widget, 'getQueueSize');
        $method->setAccessible(true);

        // Act: call with a valid queue name (should work or fail gracefully)
        $result = $method->invoke($widget, 'nonexistent-queue-name');

        // Assert: returns non-negative value (0 on failure, or actual size)
        $this->assertGreaterThanOrEqual(0, $result);
    }

    // -- getJobsPerMinute with empty snapshots --

    /**
     * Test getJobsPerMinute returns 0.0 when no snapshots available.
     *
     * Edge case: when Horizon has no metrics data, the method should
     * return 0.0 instead of accessing an undefined array key.
     */
    public function test_get_jobs_per_minute_returns_zero_without_metrics(): void
    {
        // Arrange: create a mock MetricsRepository that returns empty snapshots
        $mockMetrics = \Mockery::mock(MetricsRepository::class);
        $mockMetrics->shouldReceive('snapshotsForQueue')->with('default')->andReturn([]); // @phpstan-ignore method.notFound

        $widget = new QueueStatsWidget;
        $method = new \ReflectionMethod($widget, 'getJobsPerMinute');
        $method->setAccessible(true);

        // Act
        $result = $method->invoke($widget, $mockMetrics);

        // Assert: returns 0.0 for empty snapshots
        $this->assertSame(0.0, $result);
    }

    // -- getStats return structure --

    /**
     * Test getStats returns an array of Stat instances.
     *
     * PHPStan change: Cache::remember wrapping and Carbon::parse for
     * cached ISO 8601 timestamp strings.
     */
    public function test_get_stats_returns_stat_array(): void
    {
        // Arrange
        $widget = new QueueStatsWidget;
        $method = new \ReflectionMethod($widget, 'getStats');
        $method->setAccessible(true);

        // Act
        $stats = $method->invoke($widget);

        // Assert: returns non-empty array of Stat instances
        $this->assertNotEmpty($stats);
        foreach ($stats as $stat) {
            $this->assertInstanceOf(Stat::class, $stat);
        }
    }

    // -- Class hierarchy --

    /**
     * Test widget extends StatsOverviewWidget.
     */
    public function test_extends_stats_overview_widget(): void
    {
        // Assert: verify parent class via reflection
        $reflection = new \ReflectionClass(QueueStatsWidget::class);
        $this->assertSame(StatsOverviewWidget::class, $reflection->getParentClass()->getName()); // @phpstan-ignore method.nonObject
    }

    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }
}
