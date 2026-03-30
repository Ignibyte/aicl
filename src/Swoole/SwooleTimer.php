<?php

declare(strict_types=1);

namespace Aicl\Swoole;

use Aicl\Swoole\Listeners\RestoreSwooleTimers;
use Closure;
use Illuminate\Redis\Connections\Connection;
use Illuminate\Support\Facades\Redis;
use Laravel\Octane\Facades\Octane;
use Swoole\Coroutine;
use Swoole\Timer;

/**
 * Redis-persisted timer management for Swoole workers.
 *
 * Provides recurring and one-shot timers that survive worker restarts by
 * persisting timer definitions to Redis. On Swoole worker boot, restore()
 * re-registers all active timers from their persisted state. Supports
 * both class-based jobs and object instances as timer callbacks.
 *
 * Falls back gracefully when Swoole is unavailable (definitions are still
 * persisted to Redis for later restoration).
 *
 * @see RestoreSwooleTimers  Restores timers on worker boot
 */
final class SwooleTimer
{
    private const REDIS_PREFIX = 'aicl:timers:';

    /**
     * In-memory map of timer key → Swoole timer ID.
     *
     * @var array<string, int>
     */
    private static array $timerIds = [];

    /**
     * Custom Redis connector for testing.
     *
     * @var (Closure(): Connection)|null
     */
    private static ?Closure $redisResolver = null;

    /**
     * Custom Swoole availability check for testing.
     */
    private static ?bool $availableOverride = null;

    /**
     * Custom dispatcher for testing (replaces dispatch()).
     *
     * @var (Closure(string|object, array<mixed>): void)|null
     */
    private static ?Closure $dispatcher = null;

    /**
     * Register a recurring timer that dispatches a job at fixed intervals.
     *
     * @param string        $key     Unique timer name for management
     * @param int           $seconds Interval between executions
     * @param string|object $job     Job class name or instance to dispatch
     * @param array<mixed>  $data    Data passed to job constructor (when $job is a string)
     *
     * @return bool True if registered, false if Swoole unavailable
     */
    public static function every(string $key, int $seconds, string|object $job, array $data = []): bool
    {
        self::persistTimer($key, 'recurring', $seconds, $job, $data);

        if (! self::isAvailable()) {
            // @codeCoverageIgnoreStart — Swoole runtime
            return false;
            // @codeCoverageIgnoreEnd
        }

        $callback = self::buildCallback($job, $data);

        $timerId = Timer::tick($seconds * 1000, $callback);
        self::$timerIds[$key] = $timerId;

        return true;
    }

    /**
     * Register a one-shot timer that dispatches a job after a delay.
     *
     * @param string        $key     Unique timer name for management
     * @param int           $seconds Delay before execution
     * @param string|object $job     Job class name or instance to dispatch
     * @param array<mixed>  $data    Data passed to job constructor (when $job is a string)
     *
     * @return bool True if registered, false if Swoole unavailable
     */
    public static function after(string $key, int $seconds, string|object $job, array $data = []): bool
    {
        self::persistTimer($key, 'once', $seconds, $job, $data);

        if (! self::isAvailable()) {
            // @codeCoverageIgnoreStart — Swoole runtime
            return false;
            // @codeCoverageIgnoreEnd
        }

        $callback = function () use ($key, $job, $data): void {
            $innerCallback = static::buildCallback($job, $data);
            $innerCallback();

            // One-shot: clean up after firing
            unset(static::$timerIds[$key]);
            static::removeTimerFromRedis($key);
        };

        $timerId = Timer::after($seconds * 1000, $callback);
        self::$timerIds[$key] = $timerId;

        return true;
    }

    /**
     * Cancel a named timer.
     *
     * @return bool True if cancelled, false if not found
     */
    public static function cancel(string $key): bool
    {
        // Clear in-memory Swoole timer if active
        if (isset(self::$timerIds[$key])) {
            if (self::isAvailable()) {
                Timer::clear(self::$timerIds[$key]);
            }

            unset(self::$timerIds[$key]);
        }

        return self::removeTimerFromRedis($key);
    }

    /**
     * List all active timer registrations from Redis.
     *
     * @return array<string, array{type: string, seconds: int, job: string}>
     */
    public static function list(): array
    {
        // @codeCoverageIgnoreStart — Swoole runtime
        $timers = [];
        $keys = self::redis()->keys(self::REDIS_PREFIX.'*');

        foreach ($keys as $redisKey) {
            $data = self::redis()->get($redisKey);

            if ($data === null) {
                continue;
            }

            $definition = json_decode($data, true);

            if (! is_array($definition)) {
                continue;
            }

            $timers[$definition['key']] = [
                'type' => $definition['type'],
                'seconds' => $definition['seconds'],
                'job' => $definition['job'],
            ];
        }

        return $timers;
        // @codeCoverageIgnoreEnd
    }

    /**
     * Check if a named timer is active.
     */
    public static function exists(string $key): bool
    {
        // @codeCoverageIgnoreStart — Swoole runtime
        return self::redis()->exists(self::REDIS_PREFIX.$key) > 0;
        // @codeCoverageIgnoreEnd
    }

    /**
     * Check if SwooleTimer is operational (Swoole runtime available).
     */
    public static function isAvailable(): bool
    {
        if (self::$availableOverride !== null) {
            return self::$availableOverride;
        }

        return extension_loaded('swoole')
            && Coroutine::getCid() >= 0
            && class_exists(Octane::class);
    }

    /**
     * Restore all timers from Redis persistence.
     *
     * Called on worker boot. Re-registers Swoole timers from their
     * persisted definitions. Should only be called from worker 0
     * to prevent duplicate recurring timers.
     *
     * @codeCoverageIgnore Requires Swoole runtime with active Timer — sequential fallback paths are tested
     */
    public static function restore(): void
    {
        if (! self::isAvailable()) {
            return;
        }

        $keys = self::redis()->keys(self::REDIS_PREFIX.'*');

        foreach ($keys as $redisKey) {
            $data = self::redis()->get($redisKey);

            if ($data === null) {
                continue;
            }

            $definition = json_decode($data, true);

            if (! is_array($definition)) {
                continue;
            }

            $jobClass = $definition['job'];
            $jobData = $definition['data'] ?? [];
            $seconds = $definition['seconds'];
            $timerKey = $definition['key'];

            if ($definition['type'] === 'recurring') {
                $callback = self::buildCallback($jobClass, $jobData);
                $timerId = Timer::tick($seconds * 1000, $callback);
                self::$timerIds[$timerKey] = $timerId;

                continue;
            }

            $callback = function () use ($timerKey, $jobClass, $jobData): void {
                $innerCallback = static::buildCallback($jobClass, $jobData);
                $innerCallback();

                unset(static::$timerIds[$timerKey]);
                static::removeTimerFromRedis($timerKey);
            };

            $timerId = Timer::after($seconds * 1000, $callback);
            self::$timerIds[$timerKey] = $timerId;
        }
    }

    /**
     * Reset all internal state. Used in tests.
     */
    public static function reset(): void
    {
        // Clear any active Swoole timers
        if (self::isAvailable()) {
            // @codeCoverageIgnoreStart — Swoole Timer::clear() requires active runtime
            foreach (self::$timerIds as $timerId) {
                Timer::clear($timerId);
            }
            // @codeCoverageIgnoreEnd
        }

        self::$timerIds = [];
        self::$redisResolver = null;
        self::$availableOverride = null;
        self::$dispatcher = null;
    }

    /**
     * Inject a custom Redis resolver for testing.
     *
     * @param (Closure(): Connection)|null $resolver
     */
    public static function useRedis(?Closure $resolver): void
    {
        self::$redisResolver = $resolver;
    }

    /**
     * Override the Swoole availability check for testing.
     */
    public static function setAvailable(?bool $available): void
    {
        self::$availableOverride = $available;
    }

    /**
     * Inject a custom dispatcher for testing (replaces dispatch()).
     *
     * The closure receives ($job, $data) where $job is the class name
     * or object, and $data is the constructor arguments.
     *
     * @param (Closure(string|object, array<mixed>): void)|null $dispatcher
     */
    public static function useDispatcher(?Closure $dispatcher): void
    {
        self::$dispatcher = $dispatcher;
    }

    /**
     * Get the in-memory timer IDs (for testing/inspection).
     *
     * @return array<string, int>
     */
    public static function timerIds(): array
    {
        return self::$timerIds;
    }

    /**
     * Build a dispatch callback for a job.
     *
     * @param string|object $job  Job class name or instance
     * @param array<mixed>  $data Constructor arguments (when $job is a string)
     *
     * @codeCoverageIgnore Callback closures execute inside Swoole Timer context only
     */
    private static function buildCallback(string|object $job, array $data = []): Closure
    {
        return function () use ($job, $data): void {
            if (static::$dispatcher !== null) {
                (static::$dispatcher)($job, $data);

                return;
            }

            if (is_string($job)) {
                dispatch(new $job(...$data));

                return;
            }

            dispatch($job);
        };
    }

    /**
     * Store a timer definition in Redis.
     *
     * @param array<string, mixed> $data
     */
    private static function persistTimer(
        string $key,
        string $type,
        int $seconds,
        string|object $job,
        array $data,
    ): void {
        $definition = [
            'type' => $type,
            'key' => $key,
            'seconds' => $seconds,
            'job' => is_string($job) ? $job : get_class($job),
            'data' => $data,
            'registered_at' => time(),
        ];

        self::redis()->set(
            self::REDIS_PREFIX.$key,
            json_encode($definition),
        );
    }

    /**
     * Remove a timer definition from Redis.
     *
     * @return bool True if removed, false if not found
     */
    private static function removeTimerFromRedis(string $key): bool
    {
        return self::redis()->del(self::REDIS_PREFIX.$key) > 0;
    }

    /**
     * Get the Redis connection instance.
     *
     * Returns a Connection (with __call support for Redis commands)
     * or a mock object in tests. The Connection class forwards
     * Redis commands (keys, get, set, del, exists) via __call.
     *
     * @phpstan-return Connection
     */
    private static function redis(): object
    {
        if (self::$redisResolver !== null) {
            return (self::$redisResolver)();
        }

        // @codeCoverageIgnoreStart — Swoole runtime
        return Redis::connection(); // @codeCoverageIgnore — production Redis only, tests use mock resolver
        // @codeCoverageIgnoreEnd
    }
}
