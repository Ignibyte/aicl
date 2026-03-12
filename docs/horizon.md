# Horizon Integration

## Overview

AICL integrates Laravel Horizon's queue monitoring backend directly into the core package (`packages/aicl/src/Horizon/`). Horizon's Vue.js frontend is fully replaced by native Filament pages and Livewire components.

## Architecture

```
Aicl\Horizon\
├── Connectors/         RedisConnector — overrides Laravel's default to fire lifecycle events
├── Console/            18 artisan commands (aicl:horizon:*)
├── Contracts/          13 interfaces (JobRepository, MetricsRepository, etc.)
├── Events/             18 custom events (JobPending, JobDeleted, LongWaitDetected, etc.)
├── Jobs/               MonitorTag, RetryFailedJob, StopMonitoringTag
├── Listeners/          21 event listeners (store, track, measure, trim)
├── Livewire/           5 table components (Recent, Pending, Completed, Failed, Monitoring)
├── MasterSupervisorCommands/   AddSupervisor
├── Notifications/      LongWaitDetected (mail-only)
├── Repositories/       7 Redis-backed implementations
├── SupervisorCommands/ Balance, Pause, Restart, Scale, Terminate, ContinueWorking
├── AutoScaler.php      Worker auto-scaling (time or size strategy)
├── EventMap.php        Trait — maps 14 events to 22 listener slots
├── Horizon.php         Main class (stripped of Vue assets)
├── HorizonServiceProvider.php  Conditional provider (events, commands, bindings, connector)
├── JobPayload.php      Job payload parsing and tag extraction
├── MasterSupervisor.php  Top-level process manager
├── ServiceBindings.php Trait — contract-to-implementation bindings
├── Supervisor.php      Worker process supervisor
└── Tags.php            Eloquent model auto-tagging
```

## Feature Flag

Horizon is controlled by `config('aicl.features.horizon')` (default: `true`, env: `AICL_HORIZON`).

When disabled:
- `HorizonServiceProvider` is not registered
- No event listeners, no Redis connector override, no commands
- QueueManager page shows Overview + Failed Jobs tabs only
- `QueueStatsWidget` uses `Queue::size()` fallback

## Configuration

Config file: `config/aicl-horizon.php`

Key settings:
- `use` — Redis connection to use (default: `default`)
- `prefix` — Redis key prefix (default: `{app_slug}_horizon:`)
- `waits` — Long-wait thresholds per queue (seconds)
- `trim.recent` / `trim.failed` — Job retention (default: 60 min / 10080 min)
- `environments` — Per-environment supervisor config (local vs production)
- `defaults.supervisor-1` — Default supervisor settings (connection, queue, workers, balancing)

## Queue Manager Dashboard

The `QueueManager` Filament page (`/admin/queue-manager`) adapts to the active queue configuration:

**With Horizon enabled (8 tabs):**
Overview, Recent Jobs, Pending, Completed, Failed Jobs, Workload, Supervisors, Monitoring

**Without Horizon (2 tabs):**
Overview, Failed Jobs

The Overview tab shows:
- Queue driver badge (Redis/Database/Sync)
- Horizon status indicator (Active/Inactive)
- Pending jobs count, failed jobs count, jobs/min throughput, process count
- Last failure info

## DDEV Integration

Horizon runs as a daemon in `.ddev/config.yaml`, replacing the standard `queue-worker`:
```yaml
web_extra_daemons:
  horizon:
    command: php artisan aicl:horizon
```

Metrics snapshots are scheduled every 5 minutes via `routes/console.php`:
```php
Schedule::command('aicl:horizon:snapshot')->everyFiveMinutes();
```

## Artisan Commands

All commands use the `aicl:horizon:` prefix:

| Command | Purpose |
|---------|---------|
| `aicl:horizon` | Start the master supervisor |
| `aicl:horizon:snapshot` | Take metrics snapshot |
| `aicl:horizon:status` | Show Horizon status |
| `aicl:horizon:pause` | Pause all processing |
| `aicl:horizon:continue` | Resume processing |
| `aicl:horizon:terminate` | Gracefully terminate |
| `aicl:horizon:clear` | Clear queue |
| `aicl:horizon:purge` | Purge terminated processes |

## Testing

108 tests (266 assertions) across 13 test files:
- 7 unit tests: Horizon class, ServiceBindings, EventMap, Contracts, Config, Notification, Provider
- 6 feature tests: FeatureFlag, QueueManagerPage, QueueCheck, LivewireComponents, QueueStatsWidget
