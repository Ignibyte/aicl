# Live Ops Panel

A Filament page showing service health and application status at a glance with pluggable health checks.

**Namespace:** `Aicl\Health`, `Aicl\Filament\Pages`

## Overview

The Ops Panel is a static health dashboard at `/admin/ops-panel` showing whether services are up and basic counts. It refreshes via Livewire polling (30s) with Redis-cached results (30s TTL). RBAC-gated to admin/super_admin roles.

---

## Built-in Health Checks

| Check | Order | What It Checks | Status Logic |
|-------|-------|---------------|--------------|
| `SwooleCheck` | 10 | Extension, Octane, coroutines, memory | Healthy if Swoole + Octane; degraded if extension only; down if missing |
| `PostgresCheck` | 20 | Connection, version, active connections, DB size | Healthy if queries succeed; down on failure |
| `RedisCheck` | 30 | Version, memory, clients, keys, uptime | Healthy; degraded if memory > 90%; down on failure |
| `ElasticsearchCheck` | 40 | Cluster health, nodes, indices, doc count | Healthy if green; degraded if yellow; down if red/unreachable |
| `QueueCheck` | 50 | Pending jobs per queue, failed job count | Healthy; degraded if failed jobs above threshold |
| `ApplicationCheck` | 60 | PHP/Laravel versions, drivers, environment | Healthy; degraded if debug mode in production |

---

## Adding Custom Health Checks

### 1. Implement the Contract

```php
use Aicl\Health\Contracts\ServiceHealthCheck;
use Aicl\Health\ServiceCheckResult;

class ExternalApiCheck implements ServiceHealthCheck
{
    public function check(): ServiceCheckResult
    {
        try {
            $response = Http::timeout(5)->get('https://api.example.com/health');

            if ($response->successful()) {
                return ServiceCheckResult::healthy('External API', 'heroicon-o-globe-alt', [
                    'Status' => 'Connected',
                    'Response Time' => $response->transferStats->getTransferTime() . 's',
                ]);
            }

            return ServiceCheckResult::degraded('External API', 'heroicon-o-globe-alt', [
                'Status' => 'Degraded',
            ], 'Non-200 response: ' . $response->status());
        } catch (Throwable $e) {
            return ServiceCheckResult::down('External API', 'heroicon-o-globe-alt', $e->getMessage());
        }
    }

    public function order(): int
    {
        return 70; // After built-in checks (10-60)
    }
}
```

### 2. Register in Your Service Provider

```php
use Aicl\Health\HealthCheckRegistry;

public function boot(): void
{
    app(HealthCheckRegistry::class)->register(ExternalApiCheck::class);

    // Or register multiple:
    app(HealthCheckRegistry::class)->registerMany([
        ExternalApiCheck::class,
        PaymentGatewayCheck::class,
    ]);
}
```

---

## Cached Health Checks (Sprint G)

Health check results are cached in Redis (30s TTL) to avoid live probes on every page load:

```php
use Aicl\Health\HealthCheckRegistry;

$registry = app(HealthCheckRegistry::class);

// Returns cached results (or runs live if cache empty)
$results = $registry->runAllCached();

// Force fresh probes (bypasses cache)
$results = $registry->forceRefresh();
```

The OpsPanel includes a "Force Refresh" button that calls `forceRefresh()`.

**Graceful fallback:** When Redis is unavailable, falls through to live probes.

---

## Active Sessions (Sprint H)

The Ops Panel includes a "Connected Sessions" section showing all tracked admin sessions in real-time. Powered by the `PresenceRegistry` Redis-backed service and `TrackPresenceMiddleware`.

### Features

- **Session table** — User name, masked session ID (`a1b2...z9`), current page URL, last-seen time
- **Auto-refresh** — Livewire `wire:poll.15s` for near-real-time updates
- **Kill Session** — `super_admin` only, with Filament confirmation modal. Cannot terminate your own session.
- **Multiple sessions per user** — Supports multiple tabs/browsers

### PresenceRegistry

```php
use Aicl\Services\PresenceRegistry;

$registry = app(PresenceRegistry::class);

$registry->touch($sessionId, $userId, $meta);    // Update/create presence entry
$registry->allSessions();                          // Get all active sessions
$registry->sessionsForUser($userId);               // Query sessions by user
$registry->terminateSession($sessionId);           // Force logout + remove entry
$registry->maskSessionId($sessionId);              // Display as a1b2...z9
```

### TrackPresenceMiddleware

Registered on all Filament admin panel requests via `AiclPlugin::register()` authMiddleware:

- Throttled to one Redis write per 30 seconds per session
- Captures: user_id, name, email, current_url, IP address, last_seen_at
- TTL-based auto-cleanup (session lifetime + 5-minute buffer)
- Dispatches `SessionTerminated` DomainEvent on force-logout for audit trail

---

## Toolbar Page Presence (Sprint H)

The `ToolbarPresence` widget shows compact badges of other users viewing the same page in the Filament topbar. Only visible when others are on the page.

- Per-page WebSocket presence channels: `presence-page.{md5(path)}`
- Handles Livewire `navigate` events (Filament SPA): leaves old channel, joins new
- Feature-gated: `config('aicl.features.websockets')` must be `true`
- Max 3 visible badges + "+N more" overflow

---

## Presence Indicator (Sprint G)

The OpsPanel includes a `PresenceIndicator` footer widget showing which admins are currently viewing the ops dashboard. Requires `BROADCAST_CONNECTION=reverb` and the `presence-admin-panel` channel authorized in `routes/channels.php`.

---

## ServiceCheckResult

```php
use Aicl\Health\ServiceCheckResult;
use Aicl\Health\ServiceStatus;

// Static factories
ServiceCheckResult::healthy('Name', 'icon', ['Key' => 'Value']);
ServiceCheckResult::degraded('Name', 'icon', ['Key' => 'Value'], 'Warning message');
ServiceCheckResult::down('Name', 'icon', 'Error message');

// Properties
$result->name;    // string
$result->status;  // ServiceStatus enum (Healthy, Degraded, Down)
$result->icon;    // string (Heroicon reference)
$result->details; // array (key-value display pairs)
$result->error;   // ?string
```

---

## Configuration

```php
// config/aicl.php
'health' => [
    'queues' => ['default', 'notifications'],  // Queues to monitor
    'failed_jobs_threshold' => 10,              // Degraded if failed jobs >= this
],
```

---

## Files

| File | Purpose |
|------|---------|
| `src/Health/ServiceStatus.php` | Healthy/Degraded/Down enum |
| `src/Health/ServiceCheckResult.php` | Immutable result value object |
| `src/Health/Contracts/ServiceHealthCheck.php` | Health check contract |
| `src/Health/HealthCheckRegistry.php` | Singleton registry with `runAllCached()` |
| `src/Health/Checks/SwooleCheck.php` | Swoole/Octane health |
| `src/Health/Checks/PostgresCheck.php` | PostgreSQL health |
| `src/Health/Checks/RedisCheck.php` | Redis health |
| `src/Health/Checks/ElasticsearchCheck.php` | Elasticsearch cluster health |
| `src/Health/Checks/QueueCheck.php` | Queue depth and failed jobs |
| `src/Health/Checks/ApplicationCheck.php` | PHP/Laravel/driver info |
| `src/Filament/Pages/OpsPanel.php` | Filament page (health checks + sessions) |
| `src/Services/PresenceRegistry.php` | Redis-backed session tracking service |
| `src/Http/Middleware/TrackPresenceMiddleware.php` | Admin request presence tracking |
| `src/Filament/Widgets/ToolbarPresence.php` | Topbar page presence widget |
| `resources/views/widgets/toolbar-presence.blade.php` | Toolbar presence Blade template |
