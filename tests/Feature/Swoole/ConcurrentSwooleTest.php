<?php

namespace Aicl\Tests\Feature\Swoole;

use Aicl\Swoole\Concurrent;
use Aicl\Swoole\Exceptions\ConcurrentException;
use Aicl\Swoole\Exceptions\ConcurrentTimeoutException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Swoole\Coroutine;

class ConcurrentSwooleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! extension_loaded('swoole')) {
            $this->markTestSkipped('Swoole extension not available.');
        }
    }

    public function test_run_executes_concurrently(): void
    {
        $elapsed = null;
        $results = null;

        Coroutine\run(function () use (&$elapsed, &$results): void {
            $start = microtime(true);

            $results = Concurrent::run([
                'a' => function () {
                    Coroutine::sleep(0.1);

                    return 'a';
                },
                'b' => function () {
                    Coroutine::sleep(0.1);

                    return 'b';
                },
            ]);

            $elapsed = microtime(true) - $start;
        });

        // Both slept 0.1s concurrently — total should be ~0.1s, not ~0.2s
        $this->assertLessThan(0.18, $elapsed);
        $this->assertSame('a', $results['a']);
        $this->assertSame('b', $results['b']);
    }

    public function test_run_collects_exceptions_from_concurrent_callables(): void
    {
        $caught = null;

        Coroutine\run(function () use (&$caught): void {
            try {
                Concurrent::run([
                    'ok' => function () {
                        Coroutine::sleep(0.01);

                        return 'success';
                    },
                    'fail' => function (): never {
                        throw new RuntimeException('boom');
                    },
                ]);
            } catch (ConcurrentException $e) {
                $caught = $e;
            }
        });

        $this->assertNotNull($caught, 'Expected ConcurrentException was not thrown.');
        $this->assertTrue($caught->hasResult('ok'));
        $this->assertTrue($caught->hasException('fail'));
        $this->assertSame('success', $caught->getResults()['ok']);
    }

    public function test_map_respects_concurrency_limit(): void
    {
        $maxConcurrent = 0;
        $results = null;

        Coroutine\run(function () use (&$maxConcurrent, &$results): void {
            $currentConcurrent = 0;

            $results = Concurrent::map(
                items: range(1, 10),
                fn: function (int $item) use (&$maxConcurrent, &$currentConcurrent): int {
                    $currentConcurrent++;
                    $maxConcurrent = max($maxConcurrent, $currentConcurrent);
                    Coroutine::sleep(0.05);
                    $currentConcurrent--;

                    return $item * 2;
                },
                concurrency: 3,
            );
        });

        $this->assertLessThanOrEqual(3, $maxConcurrent);
        $this->assertCount(10, $results);

        // Verify each key maps to correct result
        foreach (range(1, 10) as $i) {
            $this->assertSame($i * 2, $results[$i - 1]);
        }
    }

    public function test_map_preserves_result_keys(): void
    {
        $results = null;

        Coroutine\run(function () use (&$results): void {
            $results = Concurrent::map(
                items: ['slow' => 0.1, 'fast' => 0.01, 'medium' => 0.05],
                fn: function (float $delay, string $key): string {
                    Coroutine::sleep($delay);

                    return $key;
                },
            );
        });

        // Each key maps to the correct value regardless of completion order
        $this->assertSame('slow', $results['slow']);
        $this->assertSame('fast', $results['fast']);
        $this->assertSame('medium', $results['medium']);
        $this->assertCount(3, $results);
    }

    public function test_race_returns_fastest_result(): void
    {
        $result = null;

        Coroutine\run(function () use (&$result): void {
            $result = Concurrent::race([
                'slow' => function () {
                    Coroutine::sleep(0.5);

                    return 'slow';
                },
                'fast' => function () {
                    Coroutine::sleep(0.05);

                    return 'fast';
                },
            ]);
        });

        $this->assertSame('fast', $result);
    }

    public function test_race_skips_failures_returns_first_success(): void
    {
        $result = null;

        Coroutine\run(function () use (&$result): void {
            $result = Concurrent::race([
                'fail' => function (): never {
                    throw new RuntimeException('nope');
                },
                'ok' => function () {
                    Coroutine::sleep(0.05);

                    return 'recovered';
                },
            ]);
        });

        $this->assertSame('recovered', $result);
    }

    public function test_run_timeout_throws_concurrent_timeout_exception(): void
    {
        $caught = null;

        Coroutine\run(function () use (&$caught): void {
            try {
                Concurrent::run([
                    'slow' => function () {
                        Coroutine::sleep(0.5);

                        return 'never';
                    },
                ], timeout: 0.1);
            } catch (ConcurrentTimeoutException $e) {
                $caught = $e;
            }
        });

        $this->assertNotNull($caught, 'Expected ConcurrentTimeoutException was not thrown.');
        $this->assertStringContainsString('timed out', $caught->getMessage());
        $this->assertStringContainsString('0.1', $caught->getMessage());
    }

    public function test_is_available_returns_true_in_coroutine(): void
    {
        $available = null;

        Coroutine\run(function () use (&$available): void {
            $available = Concurrent::isAvailable();
        });

        $this->assertTrue($available);
    }
}
