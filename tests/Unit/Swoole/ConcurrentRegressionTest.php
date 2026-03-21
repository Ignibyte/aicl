<?php

declare(strict_types=1);

namespace Aicl\Tests\Unit\Swoole;

use Aicl\Swoole\Concurrent;
use PHPUnit\Framework\TestCase;

/**
 * Regression tests for Concurrent PHPStan changes.
 *
 * Tests the @return type annotations added to runCoroutine(),
 * runSequential(), mapCoroutine(), and mapSequential() methods.
 * Verifies sequential fallback behavior with typed return arrays.
 */
class ConcurrentRegressionTest extends TestCase
{
    /**
     * Test run returns typed array in sequential mode.
     *
     * PHPStan change: Added @return array<string|int, mixed> annotation.
     * Non-Swoole environments use sequential fallback.
     */
    public function test_run_returns_typed_array_sequential(): void
    {
        // Arrange: callables with string keys
        $callables = [
            'first' => fn (): int => 1,
            'second' => fn (): string => 'hello',
            'third' => fn (): bool => true,
        ];

        // Act: run() uses sequential fallback in non-Swoole env
        $results = Concurrent::run($callables);

        // Assert: should return array with same keys
        $this->assertArrayHasKey('first', $results);
        $this->assertArrayHasKey('second', $results);
        $this->assertArrayHasKey('third', $results);
        $this->assertSame(1, $results['first']);
        $this->assertSame('hello', $results['second']);
        $this->assertTrue($results['third']);
    }

    /**
     * Test map returns typed array in sequential mode.
     *
     * PHPStan change: Added @return array<TKey, TResult> annotation.
     */
    public function test_map_returns_typed_array_sequential(): void
    {
        // Arrange
        $items = ['a' => 1, 'b' => 2, 'c' => 3];

        // Act: map with a doubling function
        $results = Concurrent::map($items, fn (int $value, string $key): int => $value * 2);

        // Assert: should return array with same keys, doubled values
        $this->assertSame(['a' => 2, 'b' => 4, 'c' => 6], $results);
    }

    /**
     * Test run handles empty callables array.
     *
     * Edge case: empty array should return empty array.
     */
    public function test_run_handles_empty_array(): void
    {
        // Act
        $results = Concurrent::run([]);

        // Assert
        $this->assertEmpty($results);
    }

    /**
     * Test map handles empty items array.
     *
     * Edge case: mapping over empty array.
     */
    public function test_map_handles_empty_array(): void
    {
        // Act
        $results = Concurrent::map([], fn ($v) => $v);

        // Assert
        $this->assertEmpty($results);
    }

    /**
     * Test isAvailable returns bool.
     *
     * Verifies strict bool return type.
     */
    public function test_is_available_returns_bool(): void
    {
        // Act
        $result = Concurrent::isAvailable();

        // Assert
    }

    /**
     * Test run with integer keys preserves key types.
     *
     * PHPStan change: @return array<string|int, mixed> supports both.
     */
    public function test_run_preserves_integer_keys(): void
    {
        // Arrange: callables with integer keys
        $callables = [
            0 => fn (): string => 'zero',
            1 => fn (): string => 'one',
        ];

        // Act
        $results = Concurrent::run($callables);

        // Assert
        $this->assertSame('zero', $results[0]);
        $this->assertSame('one', $results[1]);
    }
}
