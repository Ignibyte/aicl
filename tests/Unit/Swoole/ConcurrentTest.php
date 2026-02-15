<?php

namespace Aicl\Tests\Unit\Swoole;

use Aicl\Swoole\Concurrent;
use Aicl\Swoole\Exceptions\ConcurrentException;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ConcurrentTest extends TestCase
{
    // ── run() ────────────────────────────────────────────────────

    public function test_run_returns_keyed_results(): void
    {
        $results = Concurrent::run([
            'a' => fn () => 1,
            'b' => fn () => 2,
            'c' => fn () => 3,
        ]);

        $this->assertSame(['a' => 1, 'b' => 2, 'c' => 3], $results);
    }

    public function test_run_empty_array_returns_empty(): void
    {
        $results = Concurrent::run([]);

        $this->assertSame([], $results);
    }

    public function test_run_single_callable(): void
    {
        $results = Concurrent::run([
            'only' => fn () => 'value',
        ]);

        $this->assertSame(['only' => 'value'], $results);
    }

    public function test_run_preserves_integer_keys(): void
    {
        $results = Concurrent::run([
            0 => fn () => 'zero',
            5 => fn () => 'five',
            10 => fn () => 'ten',
        ]);

        $this->assertSame([0 => 'zero', 5 => 'five', 10 => 'ten'], $results);
    }

    public function test_run_callable_returning_null(): void
    {
        $results = Concurrent::run([
            'a' => fn () => null,
            'b' => fn () => 'value',
        ]);

        $this->assertNull($results['a']);
        $this->assertArrayHasKey('a', $results);
        $this->assertSame('value', $results['b']);
    }

    public function test_run_throws_concurrent_exception_on_partial_failure(): void
    {
        try {
            Concurrent::run([
                'ok' => fn () => 'success',
                'fail' => fn () => throw new RuntimeException('boom'),
            ]);

            $this->fail('Expected ConcurrentException was not thrown.');
        } catch (ConcurrentException $e) {
            $this->assertSame(['ok' => 'success'], $e->getResults());
            $this->assertCount(1, $e->getExceptions());
            $this->assertArrayHasKey('fail', $e->getExceptions());
            $this->assertInstanceOf(RuntimeException::class, $e->getExceptions()['fail']);

            $this->assertTrue($e->hasResult('ok'));
            $this->assertFalse($e->hasResult('fail'));
            $this->assertTrue($e->hasException('fail'));
            $this->assertFalse($e->hasException('ok'));
        }
    }

    public function test_run_all_fail_has_empty_results(): void
    {
        try {
            Concurrent::run([
                'a' => fn () => throw new RuntimeException('fail a'),
                'b' => fn () => throw new RuntimeException('fail b'),
            ]);

            $this->fail('Expected ConcurrentException was not thrown.');
        } catch (ConcurrentException $e) {
            $this->assertSame([], $e->getResults());
            $this->assertCount(2, $e->getExceptions());
            $this->assertStringContainsString('2 concurrent callable(s) failed', $e->getMessage());
        }
    }

    // ── map() ────────────────────────────────────────────────────

    public function test_map_applies_closure_to_items(): void
    {
        $results = Concurrent::map([1, 2, 3], fn (int $item) => $item * 2);

        $this->assertSame([0 => 2, 1 => 4, 2 => 6], $results);
    }

    public function test_map_preserves_key_order(): void
    {
        $results = Concurrent::map(
            ['x' => 10, 'y' => 20, 'z' => 30],
            fn (int $item) => $item + 1,
        );

        $this->assertSame(['x' => 11, 'y' => 21, 'z' => 31], $results);
    }

    public function test_map_passes_key_to_closure(): void
    {
        $results = Concurrent::map(
            ['first' => 'a', 'second' => 'b'],
            fn (string $item, string $key) => "{$key}:{$item}",
        );

        $this->assertSame(['first' => 'first:a', 'second' => 'second:b'], $results);
    }

    public function test_map_throws_on_partial_failure(): void
    {
        try {
            Concurrent::map(
                [1, 2, 3],
                function (int $item): int {
                    if ($item === 2) {
                        throw new RuntimeException('bad item');
                    }

                    return $item * 10;
                },
            );

            $this->fail('Expected ConcurrentException was not thrown.');
        } catch (ConcurrentException $e) {
            $this->assertSame([0 => 10, 2 => 30], $e->getResults());
            $this->assertCount(1, $e->getExceptions());
            $this->assertArrayHasKey(1, $e->getExceptions());
        }
    }

    public function test_map_empty_array_returns_empty(): void
    {
        $results = Concurrent::map([], fn ($item) => $item);

        $this->assertSame([], $results);
    }

    public function test_map_clamps_concurrency_to_minimum_one(): void
    {
        $results = Concurrent::map([1, 2], fn (int $item) => $item * 3, concurrency: 0);

        $this->assertSame([0 => 3, 1 => 6], $results);
    }

    // ── race() ───────────────────────────────────────────────────

    public function test_race_returns_first_success(): void
    {
        $result = Concurrent::race([
            'a' => fn () => 'winner',
            'b' => fn () => 'loser',
        ]);

        $this->assertSame('winner', $result);
    }

    public function test_race_skips_failures_and_returns_first_success(): void
    {
        $result = Concurrent::race([
            'fail' => fn () => throw new RuntimeException('nope'),
            'ok' => fn () => 'recovered',
        ]);

        $this->assertSame('recovered', $result);
    }

    public function test_race_throws_when_all_fail(): void
    {
        try {
            Concurrent::race([
                'a' => fn () => throw new RuntimeException('fail a'),
                'b' => fn () => throw new RuntimeException('fail b'),
            ]);

            $this->fail('Expected ConcurrentException was not thrown.');
        } catch (ConcurrentException $e) {
            $this->assertSame([], $e->getResults());
            $this->assertCount(2, $e->getExceptions());
        }
    }

    public function test_race_empty_array_throws_invalid_argument(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('race() requires at least one callable.');

        Concurrent::race([]);
    }

    public function test_race_returns_null_if_first_success_is_null(): void
    {
        $result = Concurrent::race([
            'a' => fn () => null,
        ]);

        $this->assertNull($result);
    }

    // ── isAvailable() ────────────────────────────────────────────

    public function test_is_available_returns_false_outside_swoole(): void
    {
        $this->assertFalse(Concurrent::isAvailable());
    }

    // ── Input Validation ─────────────────────────────────────────

    public function test_run_throws_on_non_callable(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Value at key [bad] is not callable.');

        Concurrent::run([
            'good' => fn () => 1,
            'bad' => 'not a callable',
        ]);
    }

    public function test_race_throws_on_non_callable(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Value at key [0] is not callable.');

        Concurrent::race([42]);
    }

    // ── ConcurrentException ──────────────────────────────────────

    public function test_concurrent_exception_auto_generates_message(): void
    {
        $e = new ConcurrentException(
            results: ['a' => 1],
            exceptions: ['b' => new RuntimeException('x'), 'c' => new RuntimeException('y')],
        );

        $this->assertSame('2 concurrent callable(s) failed.', $e->getMessage());
    }

    public function test_concurrent_exception_allows_custom_message(): void
    {
        $e = new ConcurrentException(
            results: [],
            exceptions: [],
            message: 'Custom error',
        );

        $this->assertSame('Custom error', $e->getMessage());
    }
}
