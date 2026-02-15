# Swoole Cache

Cross-worker shared memory cache with TTL enforcement and event-driven invalidation.

**Namespace:** `Aicl\Swoole\SwooleCache`
**Location:** `packages/aicl/src/Swoole/SwooleCache.php`

## What It Does

SwooleCache is an L1 cache layer that sits in front of Redis (L2) and PostgreSQL (source of truth). It stores frequently-accessed, rarely-changing data in Swoole Tables — shared memory that all workers can read/write without network round-trips.

| | OctaneStore (`Cache::store('octane')`) | SwooleCache |
|---|---|---|
| **Purpose** | Laravel cache driver (generic) | Domain-specific L1 hot cache |
| **Schema** | Single table, PHP serialize | Per-domain tables, JSON |
| **TTL** | Stored but not enforced on get | Enforced with lazy expiration |
| **Invalidation** | Manual `Cache::forget()` | Event-driven auto-listeners |
| **Warm** | No | Yes (boot-time population) |

---

## API Reference

### `register(string $name, int $rows = 1000, int $ttl = 60, int $valueSize = 10000): void`

Define a cache table. Call this in your service provider's `boot()` method.

```php
use Aicl\Swoole\SwooleCache;

// In a service provider boot():
SwooleCache::register('permissions', rows: 500, ttl: 30, valueSize: 2000);
SwooleCache::register('config', rows: 100, ttl: 300, valueSize: 5000);
```

Parameters:
- `$name` — Table identifier used in all other methods
- `$rows` — Maximum row count (Swoole Table size, cannot grow)
- `$ttl` — Default TTL in seconds for this table
- `$valueSize` — Max bytes for the JSON-encoded value column

---

### `set(string $table, string $key, mixed $value, ?int $ttl = null): bool`

Store a value. Returns `false` if Swoole unavailable, table full, or value not JSON-serializable.

```php
SwooleCache::set('permissions', "user:{$userId}", [
    'roles' => ['admin', 'editor'],
    'permissions' => ['ViewAny:User', 'Create:Post'],
]);

// Override TTL for this specific entry
SwooleCache::set('permissions', "user:{$userId}", $perms, ttl: 10);
```

---

### `get(string $table, string $key): mixed`

Retrieve a value. Returns `null` if missing, expired, or Swoole unavailable. Expired rows are lazily deleted on access.

```php
$permissions = SwooleCache::get('permissions', "user:{$userId}");

if ($permissions === null) {
    // Cache miss — fall through to Redis or DB
    $permissions = $this->loadPermissionsFromDb($userId);
    SwooleCache::set('permissions', "user:{$userId}", $permissions);
}
```

---

### `forget(string $table, string $key): bool`

Remove a specific row.

```php
SwooleCache::forget('permissions', "user:{$userId}");
```

---

### `flush(string $table): bool`

Clear all rows from a cache table.

```php
SwooleCache::flush('permissions');
```

---

### `count(string $table): int`

Get the number of rows currently in a table. Returns 0 if unavailable.

```php
$usage = SwooleCache::count('permissions');
```

---

### `warm(string $table, Closure $loader): void`

Bulk populate a table from a data source. The loader returns `[key => value]` pairs.

```php
SwooleCache::warm('config', fn () => [
    'site_name' => config('app.name'),
    'timezone' => config('app.timezone'),
    'features' => config('aicl.features'),
]);
```

---

### `registerWarm(string $table, Closure $loader): void`

Register a warm callback to be executed automatically when Swoole workers start. Callbacks are replayed on every `WorkerStarting` event.

```php
// In service provider boot():
SwooleCache::registerWarm('permissions', function () {
    return User::with('roles.permissions')
        ->get()
        ->mapWithKeys(fn ($user) => [
            "user:{$user->id}" => $user->getAllPermissions()->pluck('name')->toArray(),
        ])
        ->toArray();
});
```

---

### `invalidateOn(string $table, string $event, Closure $resolver): void`

Register event-driven invalidation. When the event fires, the resolver extracts cache key(s) to forget.

```php
// Single key invalidation
SwooleCache::invalidateOn(
    'permissions',
    PermissionUpdated::class,
    fn (PermissionUpdated $e) => "user:{$e->userId}",
);

// Multiple key invalidation
SwooleCache::invalidateOn(
    'permissions',
    RoleChanged::class,
    fn (RoleChanged $e) => $e->affectedUserIds
        ->map(fn ($id) => "user:{$id}")
        ->toArray(),
);
```

---

### `isAvailable(): bool`

Check if SwooleCache is operational.

```php
if (SwooleCache::isAvailable()) {
    // Swoole Tables are available
}
```

---

### `octaneTableConfig(): array`

Get the Octane config format for all registered tables. Used by the service provider to merge into `config('octane.tables')`.

```php
// Returns format compatible with config/octane.php 'tables' key
$config = SwooleCache::octaneTableConfig();
// ['permissions:500' => ['value' => 'string:2000', 'expires_at' => 'int'], ...]
```

---

## Invalidation Strategy

**TTL is the safety net.** Even if event-driven invalidation fails, data expires.

For security-critical data (permissions, roles):
- Use short TTL (30-60 seconds)
- Register invalidation on permission/role change events

```php
// Service provider boot():
SwooleCache::register('permissions', rows: 500, ttl: 30, valueSize: 2000);

SwooleCache::invalidateOn('permissions', PermissionUpdated::class,
    fn ($e) => "user:{$e->userId}");

SwooleCache::invalidateOn('permissions', RoleChanged::class,
    fn ($e) => collect($e->affectedUserIds)
        ->map(fn ($id) => "user:{$id}")
        ->toArray());
```

---

## Fallback Behavior

When Swoole is unavailable (PHPUnit, artisan commands, queue workers, non-Octane deployments):

| Method | Returns |
|--------|---------|
| `set()` | `false` |
| `get()` | `null` |
| `forget()` | `false` |
| `flush()` | `false` |
| `count()` | `0` |
| `warm()` | No-op |

A `null` return from `get()` naturally triggers the fallback path (Redis/DB). Callers don't need to check `isAvailable()` — just handle `null` as a cache miss.

---

## Testing Code That Uses SwooleCache

### Unit tests (mock resolver)

Use `useResolver()` to inject a mock table implementation:

```php
use Aicl\Swoole\SwooleCache;
use Carbon\Carbon;

protected function setUp(): void
{
    parent::setUp();

    SwooleCache::reset();
    SwooleCache::register('test_cache', rows: 100, ttl: 60);

    // Use Carbon clock for time travel
    SwooleCache::useClock(fn () => Carbon::now()->timestamp);

    // Mock table using in-memory array
    $this->tableData = [];
    SwooleCache::useResolver(fn () => $this->createMockTable());
}

protected function tearDown(): void
{
    SwooleCache::reset();
    parent::tearDown();
}

public function test_ttl_expiration(): void
{
    SwooleCache::set('test_cache', 'key', 'value', ttl: 1);

    Carbon::setTestNow(Carbon::now()->addSeconds(2));

    $this->assertNull(SwooleCache::get('test_cache', 'key'));

    Carbon::setTestNow();
}
```

### Feature tests (real Swoole Table)

For tests that need real Swoole Table behavior:

```php
use Swoole\Table;

public function test_with_real_table(): void
{
    $table = new Table(100);
    $table->column('value', Table::TYPE_STRING, 5000);
    $table->column('expires_at', Table::TYPE_INT, 8);
    $table->create();

    SwooleCache::register('test', rows: 100, ttl: 60, valueSize: 5000);
    SwooleCache::useResolver(fn () => $table);

    SwooleCache::set('test', 'key', ['data' => 'value']);
    $this->assertSame(['data' => 'value'], SwooleCache::get('test', 'key'));
}
```

---

## Edge Cases

| Scenario | Behavior |
|----------|----------|
| Table full | `set()` returns `false`, caller falls through to Redis |
| Unregistered table name | `InvalidArgumentException` thrown |
| Value not JSON-serializable | `set()` returns `false` |
| `null` value stored | Returns `null` on `get()` (indistinguishable from miss) |
| Concurrent writes to same key | Last-write-wins (Swoole row-level atomic) |
| Worker restart | Tables preserved (shared memory), warm callbacks replay |
| Process restart | Tables lost (not persistent), warm re-populates |

---

## Cache Wiring Layer (Sprint E)

Sprint E wires SwooleCache into real data paths via dedicated **Cache Manager** classes. Each manager encapsulates table registration, data computation, event-driven invalidation, and warm callbacks for a specific domain.

All managers live in `Aicl\Swoole\Cache\` and are registered in `AiclServiceProvider::boot()`.

### Table Budget

| Table | Manager | Rows | Value Size | TTL | Warm | Invalidation |
|-------|---------|------|-----------|-----|------|-------------|
| `permissions` | `PermissionCacheManager` | 2,000 | 5 KB | 300s | No (lazy) | Spatie events + Eloquent |
| `widget_stats` | `WidgetStatsCacheManager` | 100 | 2 KB | 60s | Yes | Eloquent model events |
| `rlm_stats` | `KnowledgeStatsCacheManager` | 10 | 5 KB | 300s | Yes | 7 RLM model events |
| `notification_badges` | `NotificationBadgeCacheManager` | 1,000 | 100 B | 60s | No (lazy) | DatabaseNotification events |
| `service_health` | `ServiceHealthCacheManager` | 10 | 200 B | 30s | No (lazy) | TTL-only |
| **Total** | — | **3,120** | — | — | — | ~10.4 MB shared memory |

### Three-Tier Cache Hierarchy

```
L0: Per-instance  →  L1: SwooleCache (cross-worker)  →  L2: Redis/PG/HTTP
    (request scope)       (shared memory, sub-μs)           (network I/O)
```

- **L0** — PHP property on the service instance (`$this->esAvailable`, Spatie's `PermissionRegistrar`). Lives for one request.
- **L1** — SwooleCache (Swoole Tables). Shared across all workers. Sub-microsecond reads. TTL + event invalidation.
- **L2** — Source of truth. PostgreSQL for data, Redis for sessions/queues, HTTP for external service health.

---

### E.1 Permission & Role Cache

**Manager:** `Aicl\Swoole\Cache\PermissionCacheManager`

Caches per-user permission sets via a `Gate::before()` interceptor. On cache miss, builds and stores the user's full permission/role set from Spatie's path.

**Cache key pattern:** `user:{id}`

**Invalidation:**
- `RoleAttached` / `RoleDetached` → invalidate `user:{model.id}`
- `PermissionAttached` / `PermissionDetached` → invalidate `user:{model.id}`
- Permission/Role model created/deleted/updated → flush entire table

```php
// Usage is transparent — Gate::before() handles everything
$user->can('ViewAny:User'); // Checks SwooleCache first, falls through to Spatie on miss
```

---

### E.2 Dashboard Widget Statistics Cache

**Manager:** `Aicl\Swoole\Cache\WidgetStatsCacheManager`

Caches aggregate query results for 10 dashboard widget groups. Widgets use a read-through `getOrCompute()` pattern.

**Cache key pattern:** Widget group name (e.g., `rlm_failure_stats`, `project_health`)

**Widget groups:** `rlm_failure_stats`, `rlm_pattern_stats`, `generation_trace_stats`, `project_health`, `failure_report_stats`, `rlm_lesson_stats`, `prevention_rule_stats`, `failure_by_status`, `failure_by_category`, `failure_trend`

**Invalidation:** Per-model-type → per-widget-group mapping. Creating an `RlmFailure` invalidates `rlm_failure_stats`, `failure_by_status`, `failure_by_category` but not `rlm_pattern_stats`.

```php
// In any widget's getData() or getStats() method:
$stats = WidgetStatsCacheManager::getOrCompute(
    'rlm_failure_stats',
    [WidgetStatsCacheManager::class, 'computeRlmFailureStats'],
);
```

---

### E.3 RLM Knowledge Stats Cache

**Manager:** `Aicl\Swoole\Cache\KnowledgeStatsCacheManager`

Caches `KnowledgeService::stats()` aggregation (10+ COUNT queries across 6 tables). Single global cache key.

**Cache key:** `global`

**Invalidation:** Any create/update/delete on 7 RLM models (`RlmPattern`, `RlmFailure`, `RlmLesson`, `GenerationTrace`, `PreventionRule`, `GoldenAnnotation`, `RlmScore`).

Runtime service status fields (`storage`, `search_engine`, `embeddings`) are excluded from the cache and merged at read time by `KnowledgeService::stats()`.

```php
// KnowledgeService::stats() uses this internally:
$cached = KnowledgeStatsCacheManager::getCachedStats();
if ($cached !== null) {
    return array_merge($runtimeStatus, $cached);
}
$stats = KnowledgeStatsCacheManager::computeStats();
KnowledgeStatsCacheManager::storeStats($stats);
```

---

### E.4 Notification Badge Cache

**Manager:** `Aicl\Swoole\Cache\NotificationBadgeCacheManager`

Caches per-user unread notification counts for Filament's navigation badge. Populated lazily on first access per user.

**Cache key pattern:** `user:{id}`

**Invalidation:** `DatabaseNotification` created/updated/deleted → invalidate `user:{notifiable_id}`

```php
// In NotificationCenter::getNavigationBadge():
return NotificationBadgeCacheManager::getBadge(auth()->id());
// Returns "3" (string count) or null (zero unread)
```

---

### E.5 Elasticsearch Availability Cache

**Manager:** `Aicl\Swoole\Cache\ServiceHealthCacheManager`

Caches external service health check results. TTL-only invalidation (no events — health is determined by external services).

**Cache key pattern:** Service name (e.g., `elasticsearch`)

**Three-tier check in `KnowledgeService::isElasticsearchAvailable()`:**
1. L0: `$this->esAvailable` (per-instance property)
2. L1: `ServiceHealthCacheManager::getCachedAvailability('elasticsearch')`
3. L2: `Http::timeout(2)->get($esBaseUrl)`

```php
// Transparent — KnowledgeService handles the hierarchy:
$service->isElasticsearchAvailable(); // Checks L0 → L1 → L2
```

---

### Testing Cache Managers

All cache manager tests follow a consistent pattern:

1. `SwooleCache::reset()` in setUp
2. `SwooleCache::useClock()` with Carbon for TTL testing
3. `SwooleCache::useResolver()` with in-memory mock table
4. **Register ALL 5 cache tables** (not just the one under test)

The last point is critical: service provider event listeners survive `SwooleCache::reset()`. If a test creates Eloquent models, listeners from other managers fire and try to access their tables. Register all sibling tables to prevent `table not registered` errors.

```php
protected function setUp(): void
{
    parent::setUp();
    SwooleCache::reset();
    SwooleCache::useClock(fn (): int => Carbon::now()->timestamp);
    SwooleCache::useResolver(fn (string $table) => $this->createMockTable($table));

    // Register ALL cache tables — event listeners from other managers survive reset
    SwooleCache::register('widget_stats', rows: 100, ttl: 60, valueSize: 2000);
    SwooleCache::register('notification_badges', rows: 1000, ttl: 60, valueSize: 100);
    SwooleCache::register('permissions', rows: 2000, ttl: 300, valueSize: 5000);
    SwooleCache::register('rlm_stats', rows: 10, ttl: 300, valueSize: 5000);
    SwooleCache::register('service_health', rows: 10, ttl: 30, valueSize: 200);

    // Then register the manager under test
    MyManager::register();
}
```

---

## Files

| File | Purpose |
|------|---------|
| `packages/aicl/src/Swoole/SwooleCache.php` | Main static class |
| `packages/aicl/src/Swoole/Listeners/WarmSwooleCaches.php` | WorkerStarting listener |
| `packages/aicl/src/Swoole/Cache/PermissionCacheManager.php` | Permission/role cache wiring |
| `packages/aicl/src/Swoole/Cache/WidgetStatsCacheManager.php` | Dashboard widget stats cache wiring |
| `packages/aicl/src/Swoole/Cache/KnowledgeStatsCacheManager.php` | RLM knowledge stats cache wiring |
| `packages/aicl/src/Swoole/Cache/NotificationBadgeCacheManager.php` | Notification badge cache wiring |
| `packages/aicl/src/Swoole/Cache/ServiceHealthCacheManager.php` | Service health check cache wiring |
| `packages/aicl/tests/Unit/Swoole/SwooleCacheTest.php` | SwooleCache unit tests (36 tests) |
| `packages/aicl/tests/Feature/Swoole/SwooleCacheFeatureTest.php` | SwooleCache integration tests (6 tests) |
| `packages/aicl/tests/Unit/Swoole/PermissionCacheManagerTest.php` | Permission cache unit tests (19 tests) |
| `packages/aicl/tests/Feature/Swoole/PermissionCacheFeatureTest.php` | Permission cache feature tests (6 tests) |
| `packages/aicl/tests/Unit/Swoole/WidgetStatsCacheManagerTest.php` | Widget stats unit tests (27 tests) |
| `packages/aicl/tests/Feature/Swoole/WidgetStatsCacheFeatureTest.php` | Widget stats feature tests (6 tests) |
| `packages/aicl/tests/Unit/Swoole/KnowledgeStatsCacheManagerTest.php` | Knowledge stats unit tests (21 tests) |
| `packages/aicl/tests/Feature/Swoole/KnowledgeStatsCacheFeatureTest.php` | Knowledge stats feature tests (3 tests) |
| `packages/aicl/tests/Unit/Swoole/NotificationBadgeCacheManagerTest.php` | Notification badge unit tests (20 tests) |
| `packages/aicl/tests/Feature/Swoole/NotificationBadgeCacheFeatureTest.php` | Notification badge feature tests (5 tests) |
| `packages/aicl/tests/Unit/Swoole/ServiceHealthCacheManagerTest.php` | Service health unit tests (14 tests) |
| `packages/aicl/tests/Feature/Swoole/ServiceHealthCacheFeatureTest.php` | Service health feature tests (3 tests) |
