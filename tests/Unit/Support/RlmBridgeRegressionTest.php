<?php

declare(strict_types=1);

namespace Aicl\Tests\Unit\Support;

use Aicl\Support\RlmBridge;
use PHPUnit\Framework\TestCase;

/**
 * Regression tests for RlmBridge PHPStan changes.
 *
 * Tests the FQCN-to-import refactoring and type annotation improvements.
 * The RLM package is not installed in this project, so all methods should
 * gracefully return null/false.
 */
class RlmBridgeRegressionTest extends TestCase
{
    /**
     * Test installed() returns false when RLM is not present.
     *
     * PHPStan change: Replaced FQCN \Rlm\RlmServiceProvider::class
     * with imported RlmServiceProvider::class. Behavior should be identical.
     */
    public function test_installed_returns_false_when_rlm_not_present(): void
    {
        // Act
        $result = RlmBridge::installed();

        // Assert: RLM is not installed in this project
        $this->assertFalse($result);
    }

    /**
     * Test validate() returns null when RLM is not installed.
     *
     * Verifies graceful degradation after import refactoring.
     */
    public function test_validate_returns_null_when_rlm_not_installed(): void
    {
        // Act
        $result = RlmBridge::validate('TestEntity');

        // Assert: should return null, not crash
        $this->assertNull($result);
    }

    /**
     * Test recall() returns null when RLM is not installed.
     *
     * PHPStan change: Added int cast to ($phase ?? 0) and replaced
     * FQCN for RecallService with imported class.
     */
    public function test_recall_returns_null_when_rlm_not_installed(): void
    {
        // Act
        $result = RlmBridge::recall('architect', '3');

        // Assert: should return null
        $this->assertNull($result);
    }

    /**
     * Test recall() returns null when agent is null.
     *
     * The null agent short-circuits before checking installation.
     */
    public function test_recall_returns_null_when_agent_is_null(): void
    {
        // Act
        $result = RlmBridge::recall(null, '1');

        // Assert
        $this->assertNull($result);
    }

    /**
     * Test patternRegistry() returns null when RLM is not installed.
     *
     * PHPStan change: Replaced FQCN \Rlm\PatternRegistry::class with
     * imported PatternRegistry::class.
     */
    public function test_pattern_registry_returns_null_when_rlm_not_installed(): void
    {
        // Act
        $result = RlmBridge::patternRegistry();

        // Assert
        $this->assertNull($result);
    }
}
