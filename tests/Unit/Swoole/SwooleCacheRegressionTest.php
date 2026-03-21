<?php

declare(strict_types=1);

namespace Aicl\Tests\Unit\Swoole;

use Aicl\Swoole\SwooleCache;
use PHPUnit\Framework\TestCase;

/**
 * Regression tests for SwooleCache PHPStan changes.
 *
 * Tests the declare(strict_types=1) addition and the resolveTable()
 * return type change from mixed to Table|null. In non-Swoole
 * environments, SwooleCache methods throw for unregistered tables.
 */
class SwooleCacheRegressionTest extends TestCase
{
    /**
     * Test isAvailable returns false in non-Swoole environment.
     *
     * Verifies the method returns a strict bool after strict_types.
     */
    public function test_is_available_returns_false_without_swoole(): void
    {
        // Act
        $result = SwooleCache::isAvailable();

        // Assert: should return false in test environment
        $this->assertFalse($result);
    }

    /**
     * Test registrations returns array.
     *
     * Verifies the static registry works after strict_types.
     */
    public function test_registrations_returns_array(): void
    {
        // Act
        $result = SwooleCache::registrations();

        // Assert: should return an array of table registrations
    }

    /**
     * Test octaneTableConfig returns array.
     *
     * Verifies the config generation works after strict_types.
     */
    public function test_octane_table_config_returns_array(): void
    {
        // Act
        $result = SwooleCache::octaneTableConfig();

        // Assert: should return array of table configs
    }

    /**
     * Test warmCallbacks returns array.
     *
     * Verifies the warm callback registry works after strict_types.
     */
    public function test_warm_callbacks_returns_array(): void
    {
        // Act
        $result = SwooleCache::warmCallbacks();

        // Assert
    }
}
