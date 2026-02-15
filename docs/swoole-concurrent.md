# Swoole Concurrent

Lightweight coroutine-based concurrency primitives with automatic sequential fallback.

**Namespace:** `Aicl\Swoole\Concurrent`
**Location:** `packages/aicl/src/Swoole/Concurrent.php`

## Why Not `Octane::concurrently()`?

| | `Octane::concurrently()` | `Concurrent` |
|---|---|---|
| **Mechanism** | Task workers (cross-process) | Coroutines (same worker) |
| **Overhead** | Closure serialization required | No serialization |
| **Concurrency limit** | No | `map()` with backpressure |
| **Race semantics** | No | `race()` first-to-succeed |
| **Timeout** | Milliseconds (int) | Seconds (float) |

Use `Concurrent` for I/O-bound parallel work within a single request. Use `Octane::concurrently()` for CPU-bound work that benefits from separate worker processes.

---

## API Reference

### `run(array $callables, ?float $timeout = null): array`

Execute named callables in parallel. Returns results keyed by input keys.

```php
use Aicl\Swoole\Concurrent;

$results = Concurrent::run([
    'users' => fn () => Http::get('https://api.example.com/users')->json(),
    'posts' => fn () => Http::get('https://api.example.com/posts')->json(),
    'tags'  => fn () => Http::get('https://api.example.com/tags')->json(),
], timeout: 5.0);

// $results['users'], $results['posts'], $results['tags']
```

**Throws:** `ConcurrentException` if any callable fails, `ConcurrentTimeoutException` on timeout.

---

### `map(array $items, Closure $fn, int $concurrency = 10, ?float $timeout = null): array`

Fan-out processing with a concurrency limit. The closure receives `($item, $key)`. Results preserve input key order.

```php
$userIds = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10];

$profiles = Concurrent::map(
    items: $userIds,
    fn: fn (int $id) => Http::get("https://api.example.com/users/{$id}")->json(),
    concurrency: 3,  // max 3 concurrent requests
    timeout: 10.0,
);

// $profiles[0] through $profiles[9] — same order as input
```

Concurrency is clamped to a minimum of 1. If `concurrency >= count($items)`, all items execute at once.

**Throws:** `ConcurrentException` if any item fails, `ConcurrentTimeoutException` on timeout.

---

### `race(array $callables, ?float $timeout = null): mixed`

Return the first successful result. Failures from other callables are silently discarded. Throws only if ALL callables fail.

```php
$result = Concurrent::race([
    'primary'  => fn () => Http::get('https://primary-api.example.com/data')->json(),
    'fallback' => fn () => Http::get('https://fallback-api.example.com/data')->json(),
    'cache'    => fn () => Cache::get('data'),
], timeout: 3.0);

// Whichever responds first without throwing wins
```

Remaining coroutines are NOT cancelled (Swoole cannot reliably cancel mid-I/O coroutines). They complete naturally but results are discarded.

**Throws:** `InvalidArgumentException` if empty array. `ConcurrentException` if ALL fail. `ConcurrentTimeoutException` on timeout.

---

### `isAvailable(): bool`

Check if Swoole coroutine context is active. Returns `true` only when inside an Octane worker's coroutine scheduler.

```php
if (Concurrent::isAvailable()) {
    // Swoole coroutines will be used
} else {
    // Sequential fallback will be used
}
```

---

## Exception Handling

All methods throw `ConcurrentException` on failure. The exception carries both successful results and per-key exceptions.

```php
use Aicl\Swoole\Exceptions\ConcurrentException;
use Aicl\Swoole\Exceptions\ConcurrentTimeoutException;

try {
    $results = Concurrent::run([
        'fast_api' => fn () => Http::get('https://fast.example.com')->json(),
        'slow_api' => fn () => Http::get('https://slow.example.com')->throw()->json(),
    ]);
} catch (ConcurrentTimeoutException $e) {
    // Timeout — check partial results
    $completed = $e->getResults();    // Results that finished before timeout
    $failed = $e->getExceptions();    // Exceptions from before timeout
} catch (ConcurrentException $e) {
    // One or more callables failed (all completed within timeout)
    $successes = $e->getResults();    // ['fast_api' => [...]]
    $failures = $e->getExceptions();  // ['slow_api' => HttpException]

    // Check specific keys
    if ($e->hasResult('fast_api')) {
        // Use the partial result
    }
    if ($e->hasException('slow_api')) {
        // Handle or log the failure
    }
}
```

`ConcurrentTimeoutException` extends `ConcurrentException` — catching `ConcurrentException` catches both.

---

## Fallback Behavior

When Swoole is unavailable (PHPUnit, artisan commands, queue workers, non-Octane deployments):

- All methods execute **sequentially** in the current process
- **Same exception behavior** — callers don't need to branch on `isAvailable()`
- **Timeout is ignored** — sequential execution has no mechanism to interrupt mid-callable
- **No log warnings** — silent fallback to avoid test noise

Detection uses `extension_loaded('swoole') && Coroutine::getCid() > 0`. The Swoole extension being loaded alone is insufficient — coroutine primitives require an active scheduler (only present inside Octane workers).

---

## Testing Code That Uses Concurrent

### Unit tests (no Swoole)

PHPUnit runs outside Swoole — `isAvailable()` returns `false`. Tests exercise the sequential fallback automatically:

```php
public function test_fetches_data_concurrently(): void
{
    Http::fake([
        'api.example.com/users' => Http::response(['users' => []]),
        'api.example.com/posts' => Http::response(['posts' => []]),
    ]);

    $results = Concurrent::run([
        'users' => fn () => Http::get('https://api.example.com/users')->json(),
        'posts' => fn () => Http::get('https://api.example.com/posts')->json(),
    ]);

    $this->assertArrayHasKey('users', $results);
    $this->assertArrayHasKey('posts', $results);
}
```

### Swoole integration tests

Wrap test bodies in `Coroutine\run()`. **All assertions must be OUTSIDE** the coroutine block — PHPUnit assertions thrown inside `Coroutine\run()` cause fatal errors instead of clean test failures.

```php
use Swoole\Coroutine;

public function test_executes_in_parallel(): void
{
    $elapsed = null;
    $results = null;

    Coroutine\run(function () use (&$elapsed, &$results): void {
        $start = microtime(true);
        $results = Concurrent::run([
            'a' => function () { Coroutine::sleep(0.1); return 'a'; },
            'b' => function () { Coroutine::sleep(0.1); return 'b'; },
        ]);
        $elapsed = microtime(true) - $start;
    });

    // Assert OUTSIDE Coroutine\run()
    $this->assertLessThan(0.18, $elapsed);
    $this->assertSame('a', $results['a']);
}
```

---

## Edge Cases

| Input | Behavior |
|-------|----------|
| Empty array | `run()`/`map()` return `[]`. `race()` throws `InvalidArgumentException`. |
| Non-callable value | `InvalidArgumentException` before any execution. |
| Callable returns `null` | Stored as `null`. Use `array_key_exists()` to distinguish from missing. |
| `map()` concurrency = 0 | Clamped to 1. |
| All callables throw | `ConcurrentException` with empty `getResults()`, all exceptions in `getExceptions()`. |

---

## Files

| File | Purpose |
|------|---------|
| `packages/aicl/src/Swoole/Concurrent.php` | Main class |
| `packages/aicl/src/Swoole/Exceptions/ConcurrentException.php` | Aggregate exception |
| `packages/aicl/src/Swoole/Exceptions/ConcurrentTimeoutException.php` | Timeout subclass |
| `packages/aicl/tests/Unit/Swoole/ConcurrentTest.php` | Sequential fallback tests (23 tests) |
| `packages/aicl/tests/Feature/Swoole/ConcurrentSwooleTest.php` | Swoole coroutine tests (8 tests) |
