<?php

declare(strict_types=1);

namespace Aicl\Swoole;

use Aicl\Swoole\Exceptions\ConcurrentException;
use Aicl\Swoole\Exceptions\ConcurrentTimeoutException;
use Closure;
use InvalidArgumentException;
use Swoole\Coroutine;
use Swoole\Coroutine\Channel;
use Swoole\Coroutine\WaitGroup;
use Throwable;

/**
 * Coroutine-based concurrency utilities for parallel task execution.
 *
 * Provides three patterns for executing callables concurrently using Swoole
 * coroutines: run() for parallel execution with keyed results, map() for
 * applying a closure to items with concurrency limiting, and race() for
 * returning the first successful result.
 *
 * All methods fall back to sequential execution when Swoole coroutine context
 * is not available (e.g., in PHPUnit tests or non-Octane environments).
 *
 * @see SwooleCache  In-worker shared memory cache
 * @see SwooleTimer  Coroutine-aware timer management
 */
final class Concurrent
{
    /**
     * Execute named callables in parallel, returning keyed results.
     *
     * Keys from the input array are preserved in the output. Under Swoole,
     * callables execute as lightweight coroutines within the same worker.
     * Falls back to sequential execution when not in a Swoole coroutine context.
     *
     * @param array<string|int, callable(): mixed> $callables
     * @param float|null                           $timeout   Maximum seconds to wait (null = no timeout)
     *
     * @throws ConcurrentException        If one or more callables threw exceptions
     * @throws ConcurrentTimeoutException If timeout expires before all complete
     *
     * @return array<string|int, mixed> Results keyed by input keys
     */
    public static function run(array $callables, ?float $timeout = null): array
    {
        if (empty($callables)) {
            return [];
        }

        self::validateCallables($callables);

        return self::isAvailable()
            ? self::runCoroutine($callables, $timeout)
            : self::runSequential($callables);
    }

    /**
     * Apply a closure to each item in parallel with a concurrency limit.
     *
     * Results maintain input key order. The closure receives ($item, $key).
     * If concurrency >= count($items), all items execute at once.
     *
     * @template TKey of array-key
     * @template TValue
     * @template TResult
     *
     * @param array<TKey, TValue>            $items
     * @param Closure(TValue, TKey): TResult $fn
     * @param int                            $concurrency Max simultaneous coroutines (minimum 1)
     * @param float|null                     $timeout     Maximum seconds to wait (null = no timeout)
     *
     * @throws ConcurrentException        If one or more items threw exceptions
     * @throws ConcurrentTimeoutException If timeout expires before all complete
     *
     * @return array<TKey, TResult> Results keyed by input keys (order preserved)
     */
    public static function map(array $items, Closure $fn, int $concurrency = 10, ?float $timeout = null): array
    {
        if (empty($items)) {
            return [];
        }

        $concurrency = max(1, $concurrency);

        return self::isAvailable()
            ? self::mapCoroutine($items, $fn, $concurrency, $timeout)
            : self::mapSequential($items, $fn);
    }

    /**
     * Execute callables in parallel, returning the first successful result.
     *
     * "Successful" means completed without throwing. If all callables throw,
     * a ConcurrentException is thrown containing all exceptions. Remaining
     * coroutines are NOT cancelled — they complete naturally but their
     * results are discarded.
     *
     * @param array<string|int, callable(): mixed> $callables
     * @param float|null                           $timeout   Maximum seconds to wait (null = no timeout)
     *
     * @throws InvalidArgumentException   If callables array is empty
     * @throws ConcurrentException        If ALL callables threw exceptions
     * @throws ConcurrentTimeoutException If timeout expires before any succeeds
     *
     * @return mixed The result of the first callable to succeed
     */
    public static function race(array $callables, ?float $timeout = null): mixed
    {
        if (empty($callables)) {
            throw new InvalidArgumentException('race() requires at least one callable.');
        }

        self::validateCallables($callables);

        return self::isAvailable()
            ? self::raceCoroutine($callables, $timeout)
            : self::raceSequential($callables);
    }

    /**
     * Check whether Swoole coroutine context is available.
     *
     * Returns true when the Swoole extension is loaded AND we are inside
     * an active coroutine context (getCid() > 0). The extension being loaded
     * alone is insufficient — coroutine primitives require an active scheduler.
     */
    public static function isAvailable(): bool
    {
        return extension_loaded('swoole') && Coroutine::getCid() > 0;
    }

    /**
     * Execute callables as Swoole coroutines using WaitGroup for synchronization.
     *
     * @param array<string|int, callable(): mixed> $callables
     *
     * @throws ConcurrentException|ConcurrentTimeoutException
     *
     * @return array<string|int, mixed>
     *
     * @codeCoverageIgnore Requires Swoole coroutine runtime — sequential fallback is tested
     */
    private static function runCoroutine(array $callables, ?float $timeout): array
    {
        $results = [];
        $exceptions = [];
        $wg = new WaitGroup(count($callables));

        foreach ($callables as $key => $callable) {
            Coroutine::create(function () use ($wg, &$results, &$exceptions, $key, $callable): void {
                try {
                    $results[$key] = $callable();
                } catch (Throwable $e) {
                    $exceptions[$key] = $e;
                } finally {
                    $wg->done();
                }
            });
        }

        $completed = $wg->wait($timeout ?? -1);

        if (! $completed) {
            throw ConcurrentTimeoutException::after(
                $timeout ?? 0,
                $results,
                $exceptions,
            );
        }

        if (! empty($exceptions)) {
            throw new ConcurrentException($results, $exceptions);
        }

        return $results;
    }

    /**
     * Map items through coroutines with a Channel-based semaphore for concurrency control.
     *
     * The semaphore pop() is OUTSIDE the created coroutine, gating creation on-demand
     * so only $concurrency coroutines exist at any time (memory-efficient).
     *
     * @template TKey of array-key
     * @template TValue
     * @template TResult
     *
     * @param array<TKey, TValue>            $items
     * @param Closure(TValue, TKey): TResult $fn
     *
     * @throws ConcurrentException|ConcurrentTimeoutException
     *
     * @return array<TKey, TResult>
     *
     * @codeCoverageIgnore Requires Swoole coroutine runtime — sequential fallback is tested
     */
    private static function mapCoroutine(array $items, Closure $fn, int $concurrency, ?float $timeout): array
    {
        $results = [];
        $exceptions = [];
        $wg = new WaitGroup(count($items));
        $semaphore = new Channel($concurrency);

        for ($i = 0; $i < $concurrency; $i++) {
            $semaphore->push(true);
        }

        foreach ($items as $key => $item) {
            $semaphore->pop();

            Coroutine::create(function () use ($wg, $semaphore, &$results, &$exceptions, $key, $item, $fn): void {
                try {
                    $results[$key] = $fn($item, $key);
                } catch (Throwable $e) {
                    $exceptions[$key] = $e;
                } finally {
                    $wg->done();
                    $semaphore->push(true);
                }
            });
        }

        $completed = $wg->wait($timeout ?? -1);

        if (! $completed) {
            throw ConcurrentTimeoutException::after(
                $timeout ?? 0,
                $results,
                $exceptions,
            );
        }

        if (! empty($exceptions)) {
            throw new ConcurrentException($results, $exceptions);
        }

        return $results;
    }

    /**
     * Race callables using a Channel(1) — only the first successful push is accepted.
     *
     * @param array<string|int, callable(): mixed> $callables
     *
     * @throws ConcurrentException|ConcurrentTimeoutException
     *
     * @codeCoverageIgnore Requires Swoole coroutine runtime — sequential fallback is tested
     */
    private static function raceCoroutine(array $callables, ?float $timeout): mixed
    {
        $winner = new Channel(1);
        $exceptions = [];
        $callableCount = count($callables);
        $finishedCount = 0;
        $allDone = new Channel(1);

        foreach ($callables as $key => $callable) {
            Coroutine::create(function () use ($winner, &$exceptions, &$finishedCount, $callableCount, $allDone, $key, $callable): void {
                try {
                    $result = $callable();
                    $winner->push(['key' => $key, 'result' => $result]);
                } catch (Throwable $e) {
                    $exceptions[$key] = $e;
                } finally {
                    $finishedCount++;

                    if ($finishedCount === $callableCount) {
                        $allDone->push(true);
                    }
                }
            });
        }

        $data = $winner->pop($timeout ?? -1);

        if ($data !== false) {
            return $data['result'];
        }

        // No winner — either all failed or timed out. Wait briefly for all to finish
        // so we can collect their exceptions for the error report.
        $allDone->pop(0.1);

        if (count($exceptions) === $callableCount) {
            throw new ConcurrentException([], $exceptions);
        }

        throw ConcurrentTimeoutException::after($timeout ?? 0, [], $exceptions);
    }

    /**
     * Sequential fallback for run() when Swoole is unavailable.
     *
     * @param array<string|int, callable(): mixed> $callables
     *
     * @throws ConcurrentException
     *
     * @return array<string|int, mixed>
     */
    private static function runSequential(array $callables): array
    {
        $results = [];
        $exceptions = [];

        foreach ($callables as $key => $callable) {
            try {
                $results[$key] = $callable();
            } catch (Throwable $e) {
                $exceptions[$key] = $e;
            }
        }

        if (! empty($exceptions)) {
            throw new ConcurrentException($results, $exceptions);
        }

        return $results;
    }

    /**
     * Sequential fallback for map() when Swoole is unavailable.
     *
     * Timeout is not enforced in sequential mode — there is no mechanism
     * to interrupt a running callable mid-execution.
     *
     * @template TKey of array-key
     * @template TValue
     * @template TResult
     *
     * @param array<TKey, TValue>            $items
     * @param Closure(TValue, TKey): TResult $fn
     *
     * @throws ConcurrentException
     *
     * @return array<TKey, TResult>
     */
    private static function mapSequential(array $items, Closure $fn): array
    {
        $results = [];
        $exceptions = [];

        foreach ($items as $key => $item) {
            try {
                $results[$key] = $fn($item, $key);
            } catch (Throwable $e) {
                $exceptions[$key] = $e;
            }
        }

        if (! empty($exceptions)) {
            throw new ConcurrentException($results, $exceptions);
        }

        return $results;
    }

    /**
     * Sequential fallback for race() when Swoole is unavailable.
     *
     * Executes callables one by one, returning the first successful result.
     *
     * @param array<string|int, callable(): mixed> $callables
     *
     * @throws ConcurrentException
     */
    private static function raceSequential(array $callables): mixed
    {
        $exceptions = [];

        foreach ($callables as $key => $callable) {
            try {
                return $callable();
            } catch (Throwable $e) {
                $exceptions[$key] = $e;
            }
        }

        throw new ConcurrentException([], $exceptions);
    }

    /**
     * Validate that all values in the array are callable.
     *
     * @param array<string|int, mixed> $callables
     *
     * @throws InvalidArgumentException
     */
    private static function validateCallables(array $callables): void
    {
        foreach ($callables as $key => $callable) {
            if (! is_callable($callable)) {
                throw new InvalidArgumentException(
                    "Value at key [{$key}] is not callable."
                );
            }
        }
    }
}
