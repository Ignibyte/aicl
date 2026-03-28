# Swoole Foundations

**Version:** 3.0
**Last Updated:** 2026-02-13
**Owner:** `/pipeline-implement`
**Sprint:** A + E + F (wiring)

---

## Overview

The Swoole Foundations layer provides low-level primitives that leverage Swoole's coroutine and shared-memory capabilities within Laravel Octane. These are building blocks for higher-level features — they are implemented, tested, and documented, but not yet wired into the entity generation pipeline.

All components follow the same design principles:
- **Static utility classes** for zero-overhead API access
- **Graceful fallback** when not running under Swoole/Octane
- **Test injection points** for unit testing without Swoole runtime
- **Package-only** — lives in `packages/aicl/src/Swoole/`

---

## Components

### 1. Coroutine Concurrency Helpers (`Concurrent`)

**File:** `packages/aicl/src/Swoole/Concurrent.php`

Parallel execution of callables using Swoole coroutines with WaitGroup and Channel-based synchronization.

**API Surface:**

| Method | Purpose |
|--------|---------|
| `run(array $callables, ?float $timeout): array` | Named parallel execution, keyed results |
| `map(array $items, Closure $fn, int $concurrency, ?float $timeout): array` | Fan-out with concurrency limit |
| `race(array $callables, ?float $timeout): mixed` | First successful result wins |
| `isAvailable(): bool` | Check if coroutine context is active |

**Key Decisions:**
- Sequential fallback when `Coroutine::getCid() <= 0` (not in coroutine context)
- `map()` uses Channel semaphore with pop OUTSIDE coroutine to gate creation (memory-efficient)
- `race()` uses Channel(1) — losers complete but results are discarded
- Timeout ignored in sequential fallback (no interruption mechanism)
- `ConcurrentException` aggregates partial results and per-key exceptions
- `ConcurrentTimeoutException` extends ConcurrentException with factory method

**Tests:** 31 tests, 70 assertions

---

### 2. Swoole Table Hot Caches (`SwooleCache`)

**File:** `packages/aicl/src/Swoole/SwooleCache.php`

Cross-worker shared memory cache with TTL, event-driven invalidation, and JSON serialization.

**API Surface:**

| Method | Purpose |
|--------|---------|
| `register(string $name, int $rows, int $ttl, int $valueSize)` | Define table at boot |
| `set(string $table, string $key, mixed $value, ?int $ttl): bool` | Store JSON-serialized value |
| `get(string $table, string $key): mixed` | Retrieve with lazy TTL check |
| `forget(string $table, string $key): bool` | Remove specific row |
| `flush(string $table): bool` | Clear entire table |
| `count(string $table): int` | Current row count |
| `warm(string $table, Closure $loader)` | Bulk populate |
| `registerWarm(string $table, Closure $loader)` | Register boot-time warm callback |
| `invalidateOn(string $table, string $event, Closure $resolver)` | Event-driven invalidation |
| `octaneTableConfig(): array` | Generate config/octane.php table format |
| `isAvailable(): bool` | Check if Swoole tables are accessible |

**Key Decisions:**
- Generic JSON schema (single `value` string column + `expires_at` int column)
- Builds on Octane's existing `config/octane.php` table lifecycle
- Lazy TTL expiration — expired rows deleted on `get()`, no background cleanup
- Event-driven invalidation via `Event::listen()` for security-critical data
- Silent no-op fallback when Swoole unavailable
- `useResolver()` + `useClock()` injection points for testing
- `isAvailable()` checks `WorkerState` tables population (extension loaded alone is insufficient)

**Tests:** 42 tests, 68 assertions

---

### 3. Approval Workflow Engine (`RequiresApproval`)

**File:** `packages/aicl/src/Workflows/Traits/RequiresApproval.php`

Trait-based approval workflow for any Eloquent model with RBAC, audit logging, and notifications.

**API Surface:**

| Method | Purpose |
|--------|---------|
| `requestApproval(?User, ?string): self` | Submit for approval (draft/rejected → pending) |
| `approve(User, ?string): self` | Approve (pending → approved) |
| `reject(User, string): self` | Reject with reason (pending → rejected) |
| `revokeApproval(User, string): self` | Revoke (approved → pending) |
| `isPendingApproval(): bool` | Status check |
| `isApproved(): bool` | Status check |
| `isRejected(): bool` | Status check |
| `approvalLogs(): MorphMany` | Polymorphic audit trail |
| Scopes: `pendingApproval()`, `approved()`, `rejected()` | Query scopes |

**Status Flow:**
```
draft ──→ pending ──→ approved
              │           │
              │           └──→ pending (revoke)
              │
              └──→ rejected ──→ pending (re-submit)
```

**Key Decisions:**
- Dedicated `approval_status` column on the model (not a polymorphic status table)
- `ApprovalStatus` enum (Draft, Pending, Approved, Rejected) with color/icon/label
- RBAC via `Approve:{ModelType}` permission (Spatie Permission)
- Polymorphic `approval_logs` table for audit trail
- `ApprovalRequestedNotification` sent to approvers, `ApprovalDecisionNotification` sent to requester
- Four events: `ApprovalRequested`, `ApprovalGranted`, `ApprovalRejected`, `ApprovalRevoked`
- Single-approver model (no multi-approver, no escalation)
- Configurable: column name, permission name, approver resolution

**Files:**
- Trait: `packages/aicl/src/Workflows/Traits/RequiresApproval.php`
- Enum: `packages/aicl/src/Workflows/Enums/ApprovalStatus.php`
- Model: `packages/aicl/src/Workflows/Models/ApprovalLog.php`
- Events: `packages/aicl/src/Workflows/Events/Approval*.php` (4 classes)
- Notifications: `packages/aicl/src/Workflows/Notifications/Approval*.php` (2 classes)
- Exception: `packages/aicl/src/Workflows/Exceptions/ApprovalException.php`
- Contract: `packages/aicl/src/Contracts/Approvable.php`
- Migration: `packages/aicl/database/migrations/2026_02_12_100000_create_approval_logs_table.php`

**Tests:** 22 tests, 51 assertions

---

### 4. Swoole Timers for Business Workflows (`SwooleTimer`)

**File:** `packages/aicl/src/Swoole/SwooleTimer.php`

Managed Swoole timers with Redis persistence, named keys, and job dispatch.

**API Surface:**

| Method | Purpose |
|--------|---------|
| `every(string $key, int $seconds, string\|object $job, array $data): bool` | Recurring timer |
| `after(string $key, int $seconds, string\|object $job, array $data): bool` | One-shot timer |
| `cancel(string $key): bool` | Cancel named timer |
| `list(): array` | List all timer registrations |
| `exists(string $key): bool` | Check timer existence |
| `isAvailable(): bool` | Check Swoole availability |
| `restore(): void` | Restore from Redis on worker boot |

**Key Decisions:**
- Every timer requires a named key (unnamed timers can't be cancelled or persisted)
- Timer definitions persisted in Redis at `aicl:timers:{key}` (survives restarts)
- Timers always dispatch jobs — never execute business logic inline
- Worker 0 coordination: only worker 0 restores timers (prevents N duplicate recurring dispatches)
- API uses seconds, converted to milliseconds for Swoole internally
- No scheduled task fallback (minute granularity doesn't match timer precision)
- `useRedis()`, `setAvailable()`, `useDispatcher()` injection points for testing

**Tests:** 23 tests, 57 assertions

---

## Swoole Listeners

Two listeners fire on `WorkerStarting` event, registered in `AiclServiceProvider`:

| Listener | Purpose |
|----------|---------|
| `WarmSwooleCaches` | Calls `SwooleCache::warm()` for all registered warm callbacks |
| `RestoreSwooleTimers` | Calls `SwooleTimer::restore()` (worker 0 only) |

---

## Fallback Behavior Summary

All Swoole components degrade gracefully when not running under Octane:

| Component | Fallback |
|-----------|----------|
| `Concurrent` | Sequential execution (same exception behavior) |
| `SwooleCache` | Silent no-op (returns null/false/0) |
| `SwooleTimer` | Persists to Redis, returns false (timers don't fire) |

Detection methods:
- `Concurrent::isAvailable()` — checks `Coroutine::getCid() > 0`
- `SwooleCache::isAvailable()` — checks `WorkerState` tables populated
- `SwooleTimer::isAvailable()` — checks `Coroutine::getCid() >= 0` + Octane class exists

---

## Testing Patterns

All components provide injection points for testing without Swoole runtime:

| Component | Injection Points |
|-----------|-----------------|
| `Concurrent` | Falls back automatically (no injection needed) |
| `SwooleCache` | `useResolver(?Closure)`, `useClock(?Closure)` |
| `SwooleTimer` | `useRedis(?Closure)`, `setAvailable(?bool)`, `useDispatcher(?Closure)` |

Feature tests that need real Swoole wrap their body in `\Swoole\Coroutine\run()` and skip when extension is unavailable.

---

## Usage Guide

### Concurrent — Parallel API Calls

```php
use Aicl\Swoole\Concurrent;

// Fetch from multiple APIs in parallel
$results = Concurrent::run([
    'users' => fn () => Http::get('https://api.example.com/users')->json(),
    'orders' => fn () => Http::get('https://api.example.com/orders')->json(),
    'stats' => fn () => Http::get('https://api.example.com/stats')->json(),
], timeout: 5.0);

// $results['users'], $results['orders'], $results['stats']
```

```php
// Fan-out: process 100 items, 5 at a time
$results = Concurrent::map($records, function ($record, $key) {
    return ExternalService::sync($record);
}, concurrency: 5);
```

```php
// Race: try multiple providers, take the fastest
$translation = Concurrent::race([
    'google' => fn () => GoogleTranslate::translate($text),
    'deepl' => fn () => DeepL::translate($text),
]);
```

```php
// Handle partial failures
try {
    $results = Concurrent::run($callables);
} catch (ConcurrentException $e) {
    $successes = $e->getResults();    // ['users' => [...]]
    $failures = $e->getExceptions();  // ['orders' => TimeoutException]

    if ($e->hasResult('users')) {
        // Use partial data
    }
}
```

### SwooleCache — Permission Caching (Security-Critical)

```php
// In AiclServiceProvider::boot()
use Aicl\Swoole\SwooleCache;

// 1. Register the cache table at boot
SwooleCache::register('permissions', rows: 5000, ttl: 60, valueSize: 4096);

// 2. Warm on worker start
SwooleCache::registerWarm('permissions', function () {
    return User::with('permissions')->get()
        ->mapWithKeys(fn ($u) => ["user:{$u->id}" => $u->getAllPermissions()->pluck('name')->all()])
        ->all();
});

// 3. Invalidate when permissions change
SwooleCache::invalidateOn('permissions', PermissionUpdated::class, function ($event) {
    return "user:{$event->userId}";
});
```

```php
// In middleware or policy — fast L1 cache lookup
$permissions = SwooleCache::get('permissions', "user:{$user->id}");

if ($permissions === null) {
    // Cache miss (expired or Swoole unavailable) — fall through to DB
    $permissions = $user->getAllPermissions()->pluck('name')->all();
    SwooleCache::set('permissions', "user:{$user->id}", $permissions);
}
```

### SwooleTimer — Business Workflow Timers

```php
use Aicl\Swoole\SwooleTimer;

// Recurring: clean up expired sessions every 5 minutes
SwooleTimer::every('session-cleanup', 300, App\Jobs\CleanExpiredSessions::class);

// One-shot: send a reminder 30 minutes after meeting creation
SwooleTimer::after(
    "meeting-reminder-{$meeting->id}",
    1800,
    App\Jobs\SendMeetingReminder::class,
    [$meeting->id]
);

// Cancel if the meeting is deleted
SwooleTimer::cancel("meeting-reminder-{$meeting->id}");

// Admin dashboard: list all active timers
$timers = SwooleTimer::list();
// ['session-cleanup' => ['type' => 'recurring', 'seconds' => 300, 'job' => '...']]
```

### RequiresApproval — Adding Approval to Any Model

```php
// 1. Migration
$table->string('approval_status')->default('draft')->index();

// 2. Model
use Aicl\Contracts\Approvable;
use Aicl\Workflows\Traits\RequiresApproval;

class Expense extends Model implements Approvable
{
    use RequiresApproval;
}

// 3. Permission seeder
Permission::findOrCreate('Approve:Expense', 'web');
$managerRole->givePermissionTo('Approve:Expense');
```

```php
// Submit for approval
$expense->requestApproval($user, 'Q4 budget request');
// → status: pending, notifies all users with Approve:Expense permission

// Manager approves
$expense->approve($manager, 'Approved for Q4');
// → status: approved, fires ApprovalGranted event, notifies requester

// Query by status
Expense::pendingApproval()->get();
Expense::approved()->get();

// Audit trail
$expense->approvalLogs->each(function ($log) {
    echo "{$log->actor->name} {$log->action} at {$log->created_at}";
});
```

```php
// React to approvals via events
Event::listen(ApprovalGranted::class, function (ApprovalGranted $event) {
    $event->approvable->update(['published_at' => now()]);
});
```

### Combining Components

```php
// Example: Approval triggers a timed follow-up and parallel notifications
Event::listen(ApprovalGranted::class, function (ApprovalGranted $event) {
    $model = $event->approvable;

    // Schedule a follow-up review in 7 days
    SwooleTimer::after(
        "review-{$model->id}",
        604800,
        App\Jobs\ScheduleReview::class,
        [$model->id]
    );

    // Notify stakeholders in parallel
    Concurrent::run([
        'slack' => fn () => SlackNotification::send($model),
        'email' => fn () => EmailService::notifyStakeholders($model),
        'audit' => fn () => AuditService::record('approved', $model),
    ]);
});
```

---

## Cache Wiring Layer (Sprint E)

Sprint E wires SwooleCache into real data paths. Sprint A built the mechanism — Sprint E activates it as L1 in front of Redis L2 and PostgreSQL L3.

### Registered Cache Tables

| Table | Manager | Rows | TTL | Warm | Invalidation |
|-------|---------|------|-----|------|-------------|
| `permissions` | `PermissionCacheManager` | 2,000 | 300s | No | Spatie events + Eloquent |
| `widget_stats` | `WidgetStatsCacheManager` | 100 | 60s | Yes | Eloquent model events |
| `rlm_stats` | `KnowledgeStatsCacheManager` | 10 | 300s | Yes | 7 RLM model events |
| `notification_badges` | `NotificationBadgeCacheManager` | 1,000 | 60s | No | DatabaseNotification |
| `service_health` | `ServiceHealthCacheManager` | 10 | 30s | No | TTL-only |

**Total memory:** ~10.4 MB shared across all workers.

### Three-Tier Cache Hierarchy

```
L0: Per-instance property (request scope)
 ↓ miss
L1: SwooleCache / Swoole Tables (cross-worker shared memory, sub-μs)
 ↓ miss
L2: Redis / PostgreSQL / HTTP (network I/O)
```

### Data Flow Changes

| Path | Before Sprint E | After Sprint E |
|------|----------------|----------------|
| Permission checks | 10-20 Redis hits/page | 0 (SwooleCache hit) |
| Dashboard widgets | 30+ PG queries/load | 0 (SwooleCache hit) |
| RLM stats | 10+ PG queries/call | 0 (SwooleCache hit) |
| Notification badges | 1 PG query/page | 0 (SwooleCache hit) |
| ES health checks | 1 HTTP call/search | 0 (SwooleCache hit) |

Redis remains essential for: queue jobs, sessions, rate limiting, timer persistence, embedding cache, non-Octane processes.

### Manager Pattern

All managers are static utility classes in `Aicl\Swoole\Cache\`, wired in `AiclServiceProvider::boot()`:

```php
Swoole\Cache\PermissionCacheManager::register();
Swoole\Cache\WidgetStatsCacheManager::register();
Swoole\Cache\NotificationBadgeCacheManager::register();
Swoole\Cache\KnowledgeStatsCacheManager::register();
Swoole\Cache\ServiceHealthCacheManager::register();
```

Full details: `packages/aicl/docs/swoole-cache.md`

---

## Production Wiring (Sprint F)

Sprint F connects Sprint A–E base classes into production features. No new architecture — just plumbing.

### Concurrent → EntityRegistry

`EntityRegistry::search()`, `atLocation()`, `countsByStatus()` use `Concurrent::map()` for parallel cross-entity queries. Falls back to sequential on non-Swoole environments.

### HasAiContext → Scaffolder

`aicl:make-entity --ai-context` generates models with the `HasAiContext` trait and `aiContextFields()` override. Included in `--all`.

### AI Assistant (Pending WebSocket Migration)

The AI assistant endpoint (`POST /ai/ask`) currently returns 503 pending migration to WebSocket-based streaming via Reverb. The SSE-based `AiStream` was removed in Sprint H due to Swoole worker exhaustion. See Sprint I for the WebSocket replacement plan.

`AiProviderFactory` resolves OpenAI/Anthropic/Ollama from config. Config-driven via `aicl.ai.*` keys.

### Approval Events → DomainEvent

4 approval events now extend `DomainEvent` for automatic persistence to `domain_events` table with actor tracking.

### PausesWhenHidden Trait

Livewire `$this->js()` boot hook injects Page Visibility API auto-pause into any polling widget. Applied to all 17 widgets.

### Default SwooleTimers

Two default timers registered in `AiclServiceProvider::boot()`:
- `health-refresh` (300s) → `RefreshHealthChecksJob`
- `delivery-cleanup` (3600s) → `CleanStaleDeliveriesJob`

---

## Test Summary

| Component | Tests | Assertions |
|-----------|-------|------------|
| Concurrent | 31 | 70 |
| SwooleCache (core) | 42 | 68 |
| Approval Workflow | 22 | 51 |
| SwooleTimer | 23 | 57 |
| PermissionCacheManager | 25 | 70 |
| WidgetStatsCacheManager | 33 | 120 |
| KnowledgeStatsCacheManager | 24 | 58 |
| NotificationBadgeCacheManager | 25 | 51 |
| ServiceHealthCacheManager | 17 | 32 |
| **Total** | **242** | **577** |

---

## Documentation

- `packages/aicl/docs/swoole-concurrent.md` — Concurrent usage guide
- `packages/aicl/docs/swoole-cache.md` — SwooleCache usage guide + cache wiring layer
- `packages/aicl/docs/approval-workflow.md` — Approval workflow guide
- `packages/aicl/docs/swoole-timer.md` — SwooleTimer usage guide
