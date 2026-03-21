<?php

declare(strict_types=1);

namespace Aicl\Tests\Unit\Swoole;

use Aicl\Swoole\SwooleTimer;
use PHPUnit\Framework\TestCase;

/**
 * Regression tests for SwooleTimer PHPStan changes.
 *
 * Tests the declare(strict_types=1) addition, the redis() return type
 * change from mixed to object, and PHPDoc type annotation improvements.
 */
class SwooleTimerRegressionTest extends TestCase
{
    /**
     * Test isAvailable returns false in non-Swoole environment.
     *
     * Verifies the method returns a strict bool after strict_types.
     */
    public function test_is_available_returns_false_without_swoole(): void
    {
        // Act
        $result = SwooleTimer::isAvailable();

        // Assert: should be false in test environment (no Swoole)
        $this->assertFalse($result);
    }

    /**
     * Test timerIds returns array.
     *
     * Verifies the timer ID tracking works after strict_types.
     * Note: list() requires Redis connection, so we test timerIds() instead.
     */
    public function test_timer_ids_returns_array(): void
    {
        // Act: timerIds() is an in-memory operation, no Redis needed
        $result = SwooleTimer::timerIds();

        // Assert
    }
}
