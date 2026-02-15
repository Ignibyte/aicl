# Swoole Timers for Business Workflows

Managed Swoole timers with Redis persistence, named keys, and job dispatch.

**Namespace:** `Aicl\Swoole\SwooleTimer`
**Location:** `packages/aicl/src/Swoole/SwooleTimer.php`

## Overview

`SwooleTimer` wraps Swoole's native `Timer::tick()` and `Timer::after()` with a management layer:

- **Named keys** for listing, cancellation, and introspection
- **Redis persistence** so timer definitions survive Octane restarts
- **Job dispatch** (never inline execution) for non-blocking operation
- **Worker 0 coordination** to prevent duplicate recurring timers
- **Graceful fallback** when not running under Swoole

Timers always dispatch jobs — they never execute business logic inline. This is critical because timer callbacks run in the Swoole event loop and blocking kills all workers.

## API Reference

### `every(string $key, int $seconds, string|object $job, array $data = []): bool`

Register a recurring timer that dispatches a job at fixed intervals.

```php
use Aicl\Swoole\SwooleTimer;

// Dispatch CleanupJob every 5 minutes
SwooleTimer::every('cleanup', 300, App\Jobs\CleanupJob::class);

// With constructor arguments
SwooleTimer::every('sync', 60, App\Jobs\SyncJob::class, ['source' => 'api']);

// With a job instance
SwooleTimer::every('report', 3600, new App\Jobs\ReportJob('daily'));
```

Returns `true` if registered under Swoole, `false` if unavailable. The timer definition is always persisted in Redis regardless.

### `after(string $key, int $seconds, string|object $job, array $data = []): bool`

Register a one-shot timer that dispatches a job after a delay.

```php
// Send a reminder in 10 minutes
SwooleTimer::after('reminder-42', 600, App\Jobs\SendReminderJob::class, [42]);

// Auto-expire a session in 30 minutes
SwooleTimer::after('session-abc', 1800, App\Jobs\ExpireSessionJob::class, ['abc']);
```

One-shot timers automatically clean up from Redis and in-memory tracking after firing.

### `cancel(string $key): bool`

Cancel a named timer.

```php
// Cancel before it fires
SwooleTimer::cancel('reminder-42');
```

Returns `true` if the Redis entry was removed, `false` if not found. Also clears the in-memory Swoole timer if active.

### `list(): array`

List all active timer registrations from Redis.

```php
$timers = SwooleTimer::list();
// [
//     'cleanup' => ['type' => 'recurring', 'seconds' => 300, 'job' => 'App\Jobs\CleanupJob'],
//     'reminder-42' => ['type' => 'once', 'seconds' => 600, 'job' => 'App\Jobs\SendReminderJob'],
// ]
```

Works even when Swoole is unavailable (reads from Redis).

### `exists(string $key): bool`

Check if a named timer is registered.

```php
if (SwooleTimer::exists('cleanup')) {
    // Timer is active
}
```

### `isAvailable(): bool`

Check if the Swoole runtime is available for timer operations.

```php
if (SwooleTimer::isAvailable()) {
    SwooleTimer::every('bg-task', 60, BackgroundJob::class);
} else {
    // Fall back to scheduled task or skip
    Log::info('SwooleTimer unavailable, skipping background timer');
}
```

### `restore(): void`

Restore all timers from Redis persistence. Called automatically on worker boot by the `RestoreSwooleTimers` listener — you should not need to call this manually.

### `reset(): void`

Reset all internal state. Used in tests only.

---

## Redis Persistence

Timer definitions are stored in Redis at `aicl:timers:{key}`:

```json
{
    "type": "recurring",
    "key": "cleanup",
    "seconds": 300,
    "job": "App\\Jobs\\CleanupJob",
    "data": {},
    "registered_at": 1739354400
}
```

- **On registration:** Definition saved to Redis, Swoole timer started (if available)
- **On worker boot:** Worker 0 reads all definitions and re-registers Swoole timers
- **On cancellation:** Redis key deleted, Swoole timer cleared
- **On one-shot fire:** Redis key deleted automatically

## Worker Coordination

Only worker 0 registers timers on boot. Without this, N workers would each fire the same recurring timer, resulting in N job dispatches per interval.

The `RestoreSwooleTimers` listener checks `$event->workerId === 0` before calling `restore()`.

## Fallback Behavior

| Method | Without Swoole |
|--------|---------------|
| `every()` | Persists to Redis, returns `false` |
| `after()` | Persists to Redis, returns `false` |
| `cancel()` | Removes from Redis only |
| `list()` | Returns definitions from Redis |
| `exists()` | Checks Redis |
| `isAvailable()` | Returns `false` |

There is no scheduled task fallback. When Swoole is unavailable, callers should handle the `false` return value and use alternative mechanisms if needed.

## Testing

### Unit Tests (no Swoole required)

Inject mock Redis and set availability to false:

```php
SwooleTimer::reset();
SwooleTimer::setAvailable(false);
SwooleTimer::useRedis(fn () => $mockRedis);

SwooleTimer::every('test', 60, 'App\\Jobs\\TestJob');
$this->assertFalse(SwooleTimer::isAvailable());
$this->assertTrue(SwooleTimer::exists('test'));
```

### Feature Tests (Swoole required)

Wrap test body in `Coroutine\run()` and inject a mock dispatcher:

```php
\Swoole\Coroutine\run(function () use ($mockRedis): void {
    SwooleTimer::setAvailable(true);
    SwooleTimer::useRedis(fn () => $mockRedis);
    SwooleTimer::useDispatcher(function (string|object $job, array $data): void {
        // Track dispatched jobs
    });

    SwooleTimer::after('test', 1, 'App\\Jobs\\TestJob');
    \Swoole\Coroutine::sleep(1.5);
    // Timer has fired
});
```

### Test Helpers

| Method | Purpose |
|--------|---------|
| `setAvailable(?bool)` | Override Swoole availability check |
| `useRedis(?Closure)` | Inject custom Redis connection |
| `useDispatcher(?Closure)` | Inject custom job dispatcher |
| `timerIds(): array` | Inspect active Swoole timer IDs |
| `reset()` | Clear all internal state |

---

## Files

| File | Purpose |
|------|---------|
| `packages/aicl/src/Swoole/SwooleTimer.php` | Main static class |
| `packages/aicl/src/Swoole/Listeners/RestoreSwooleTimers.php` | WorkerStarting listener (worker 0 only) |
| `packages/aicl/tests/Unit/Swoole/SwooleTimerTest.php` | Unit tests (18 tests) |
| `packages/aicl/tests/Feature/Swoole/SwooleTimerSwooleTest.php` | Swoole integration tests (5 tests) |
