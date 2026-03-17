# Horizon & Queue Architecture

**Version:** 1.0
**Last Updated:** 2026-03-16
**Owner:** `/architect`

---

## Purpose

AICL ships a custom Horizon implementation (ported from `laravel/horizon` MIT) under the `Aicl\Horizon\` namespace. Horizon is not just a queue worker -- it is a **process manager** (master supervisor) that spawns, monitors, scales, and terminates queue worker child processes. It also provides metrics collection, job tracking, wait-time alerting, and a Filament-integrated dashboard for observing all queue activity.

Without Horizon running, **no queued jobs process**. This includes AI streaming, notification delivery, search indexing, and any application-dispatched jobs.

---

## Dependencies

- **Redis** -- Communication backbone between the master supervisor, supervisors, and workers. All Horizon metadata (job state, metrics, supervisor info, command queues) is stored in Redis.
- **PCNTL extension** -- Process control signals (`SIGTERM`, `SIGUSR1`, `SIGUSR2`, `SIGCONT`, `SIGINT`) for managing child processes.
- **POSIX extension** -- Process signaling via `posix_kill()`.
- **supervisord** -- DDEV uses supervisord to keep the `aicl:horizon` process alive.
- **`config('aicl.features.horizon')` feature flag** -- Must be `true` (default) for `HorizonServiceProvider` to register.

---

## Architecture

### Process Hierarchy

```
supervisord (DDEV container)
  └── aicl:horizon (MasterSupervisor)
        └── aicl:horizon:supervisor (Supervisor, per config entry)
              ├── aicl:horizon:work (Worker process 1)
              ├── aicl:horizon:work (Worker process 2)
              └── ...up to maxProcesses
```

### How It Works

1. **supervisord** starts `php artisan aicl:horizon` inside the DDEV container.
2. `HorizonCommand` creates a `MasterSupervisor` instance and deploys a `ProvisioningPlan`.
3. The `ProvisioningPlan` reads `config('aicl-horizon.environments')` merged with `config('aicl-horizon.defaults')` and selects the plan matching the current `APP_ENV`.
4. For each supervisor definition in the plan, a `SupervisorProcess` is spawned (via `AddSupervisor` command on the Redis command queue).
5. Each `Supervisor` creates a `ProcessPool` that manages individual `WorkerProcess` instances (which run `aicl:horizon:work`).
6. The `MasterSupervisor` enters an infinite `monitor()` loop (1-second sleep), processing pending signals and commands, monitoring supervisors, trimming old jobs, and checking memory.
7. The `AutoScaler` (when `balance: auto`) adjusts worker count per queue based on time-to-clear or queue size metrics.

### Signal Handling

The `ListensForSignals` trait on both `MasterSupervisor` and `Supervisor` maps OS signals to actions:

| Signal   | Action      | Description                              |
|----------|-------------|------------------------------------------|
| `SIGTERM`| `terminate` | Gracefully shut down all workers         |
| `SIGUSR1`| `restart`   | Terminate and re-provision all workers   |
| `SIGUSR2`| `pause`     | Pause all job processing                 |
| `SIGCONT`| `continue`  | Resume paused processing                 |
| `SIGINT` | `terminate` | Graceful shutdown (caught in HorizonCommand) |

### Redis Communication

Horizon uses a dedicated Redis connection named `horizon` (auto-configured from the `default` connection with the Horizon prefix applied). All supervisor commands, job states, metrics snapshots, and process information flow through Redis keys prefixed with `aicl_horizon:`.

The `RedisHorizonCommandQueue` provides the command bus between the master supervisor and its child supervisors. Commands like `Scale`, `Terminate`, `Pause`, `Balance`, and `AddSupervisor` are pushed to per-supervisor Redis queues and consumed in each supervisor's loop.

---

## Key Decisions

1. **Custom port, not `laravel/horizon` package.** AICL ports the Horizon source under `Aicl\Horizon\` to avoid dependency conflicts and to integrate directly with Filament (no separate Horizon dashboard route). All artisan commands use `aicl:horizon:*` prefix.

2. **Filament dashboard instead of Horizon UI.** The standard Horizon Vue.js dashboard is replaced by 7 Livewire components embedded in the Filament admin panel's Queue Manager page (10-tab layout with Horizon, 3-tab without).

3. **Feature-flagged.** `config('aicl.features.horizon')` gates the entire Horizon subsystem. When `false`, `HorizonServiceProvider` is never registered, no Horizon bindings exist, and the Queue Manager page falls back to basic Redis queue inspection.

4. **Single supervisor by default.** The `supervisor-1` default configuration handles all queues. Production environments can add additional supervisors in `config('aicl-horizon.environments.production')` for dedicated queue workers.

---

## Configuration

### File: `packages/aicl/config/aicl-horizon.php`

Config key: `aicl-horizon.*`

| Key | Default | Description |
|-----|---------|-------------|
| `use` | `'default'` | Redis connection name for Horizon metadata |
| `prefix` | `'{app_name}_horizon:'` | Redis key prefix (auto-slugged from `APP_NAME`) |
| `waits.redis:default` | `60` | Seconds before `LongWaitDetected` event fires |
| `trim.recent` | `60` | Minutes to keep recent jobs |
| `trim.pending` | `60` | Minutes to keep pending job records |
| `trim.completed` | `60` | Minutes to keep completed job records |
| `trim.recent_failed` | `10080` | Minutes to keep recent failed jobs (7 days) |
| `trim.failed` | `10080` | Minutes to keep failed jobs (7 days) |
| `trim.monitored` | `10080` | Minutes to keep monitored tag records (7 days) |
| `silenced` | `[]` | Job classes to hide from completed jobs list |
| `silenced_tags` | `[]` | Tags to silence |
| `metrics.trim_snapshots.job` | `24` | Metric snapshots to retain per job |
| `metrics.trim_snapshots.queue` | `24` | Metric snapshots to retain per queue |
| `fast_termination` | `false` | Allow new Horizon to start before old one fully stops |
| `memory_limit` | `64` | Master supervisor memory limit (MB) before restart |

### Default Supervisor (`supervisor-1`)

| Option | Default | Production Override | Local Override |
|--------|---------|-------------------|----------------|
| `connection` | `redis` | -- | -- |
| `queue` | `['default']` | -- | -- |
| `balance` | `auto` | -- | -- |
| `autoScalingStrategy` | `time` | -- | -- |
| `maxProcesses` | `1` | `10` | `3` |
| `minProcesses` | `1` (implicit) | -- | -- |
| `maxTime` | `0` (unlimited) | -- | -- |
| `maxJobs` | `0` (unlimited) | -- | -- |
| `memory` | `128` MB | -- | -- |
| `tries` | `3` | -- | -- |
| `timeout` | `60` seconds | -- | -- |
| `nice` | `0` | -- | -- |
| `balanceMaxShift` | `1` (implicit) | `1` | -- |
| `balanceCooldown` | `3` (implicit) | `3` | -- |

The `auto` balance strategy uses `time` scaling (time-to-clear) by default. This means Horizon estimates how long it will take to clear each queue and distributes workers proportionally.

### Feature Flag

```
config('aicl.features.horizon') -- default: true
```

When `false`:
- `HorizonServiceProvider` is NOT registered (checked in `AiclServiceProvider::registerServices()`).
- No Horizon bindings (`JobRepository`, `SupervisorRepository`, etc.) exist in the container.
- Queue Manager page falls back to direct Redis inspection (3 tabs instead of 10).
- **All queued jobs remain unprocessed** unless you run `queue:work` directly or set `QUEUE_CONNECTION=sync`.

---

## Queue Names

AICL uses the following named queues:

| Queue | Used By | Notes |
|-------|---------|-------|
| `default` | `AiStreamJob`, `AiConversationStreamJob`, `CompactConversationJob`, general jobs | Primary queue, processed by default supervisor |
| `notifications` | `RetryNotificationDelivery` | Notification channel delivery |
| `search` | `IndexSearchDocumentJob`, `ReindexPermissionsJob` | Elasticsearch indexing |
| `high` | Priority jobs (application-defined) | Available for high-priority dispatches |
| `low` | Background/deferrable jobs (application-defined) | Available for low-priority dispatches |

The health check monitors: `config('aicl.health.queues', ['default', 'notifications', 'high', 'low'])`.

**Important:** The default `supervisor-1` config only processes the `default` queue. To process other queues, either:
1. Add them to the `queue` array in the supervisor config: `'queue' => ['default', 'notifications', 'high', 'low', 'search']`
2. Add additional supervisors dedicated to specific queues.

---

## DDEV Configuration

### File: `.ddev/.webimageBuild/horizon.conf`

```ini
[program:horizon]
group=webextradaemons
command=bash -c "php /var/www/html/artisan aicl:horizon; exit_code=$?; if [ $exit_code -ne 0 ]; then sleep 2; fi; exit $exit_code"
directory=/var/www/html
autostart=false
autorestart=true
startsecs=3
startretries=15
stdout_logfile=/var/tmp/logpipe
stdout_logfile_maxbytes=0
redirect_stderr=true
stopasgroup=true
```

Key settings:
- **`autostart=false`** -- Horizon does not start automatically with the container. It must be started via supervisord group or manually.
- **`autorestart=true`** -- supervisord restarts Horizon if it crashes.
- **`startsecs=3`** -- Process must stay alive 3 seconds to be considered "started" (the command sleeps 2 seconds on failure, so a crash-loop is detected).
- **`startretries=15`** -- Maximum retry attempts before giving up.
- **`stopasgroup=true`** -- Sends signals to the entire process group (master + all child supervisors/workers).

---

## Job Flow

```
Code dispatches job (e.g., AiStreamJob::dispatch(...))
    │
    ▼
Redis queue (e.g., queues:default)
    │
    ▼
Horizon MasterSupervisor detects work via Supervisor loop
    │
    ▼
Worker process (aicl:horizon:work) picks up the job
    │
    ├── Success ──→ Job deleted from queue
    │                MarkJobAsComplete listener fires
    │                UpdateJobMetrics listener fires
    │                Trimmed after 60 min (config: trim.completed)
    │
    ├── Exception ──→ Job released back to queue (up to 3 tries)
    │                  ForgetJobTimer listener fires
    │                  Backoff applied between retries
    │
    └── Final failure ──→ MarkJobAsFailed listener fires
                          StoreTagsForFailedJob listener fires
                          Job stored in failed_jobs table
                          Kept for 7 days (config: trim.failed = 10080 min)
```

---

## Horizon Dashboard

The Horizon monitoring UI is integrated into the Filament admin panel as part of the Queue Manager page in the Ops Panel section.

**URL:** `https://{project}.ddev.site/admin` > Ops Panel > Queue Manager

### Livewire Components

| Component | Wire Name | Description |
|-----------|-----------|-------------|
| `RecentJobsTable` | `aicl::horizon-recent-jobs-table` | Recently dispatched jobs with status |
| `PendingJobsTable` | `aicl::horizon-pending-jobs-table` | Jobs waiting to be processed |
| `CompletedJobsTable` | `aicl::horizon-completed-jobs-table` | Successfully completed jobs |
| `FailedJobsTable` | `aicl::horizon-failed-jobs-table` | Failed jobs with retry/delete actions |
| `MonitoredTagsTable` | `aicl::horizon-monitored-tags-table` | Monitored tag tracking |
| `MetricsCharts` | `aicl::horizon-metrics-charts` | Throughput and wait time charts |
| `BatchesTable` | `aicl::horizon-batches-table` | Job batch status (always available, not Horizon-dependent) |

When Horizon is enabled, the Queue Manager shows 10 tabs: Overview, Recent Jobs, Pending, Completed, Failed Jobs, Batches, Metrics, Workload, Supervisors, Monitoring. When disabled, it shows 3 tabs: Overview, Failed Jobs, Batches.

---

## API Surface

### Artisan Commands

#### Primary Commands

| Command | Description |
|---------|-------------|
| `aicl:horizon` | Start the master supervisor (foreground). Options: `--environment=` |
| `aicl:horizon:status` | Get current Horizon status (running/paused/inactive). Exit codes: 0=running, 1=paused, 2=inactive |
| `aicl:horizon:terminate` | Gracefully terminate all master supervisors. Options: `--wait` |
| `aicl:horizon:pause` | Pause all supervisors (sends SIGUSR2) |
| `aicl:horizon:continue` | Resume all paused supervisors (sends SIGCONT) |

#### Supervisor-Level Commands

| Command | Description |
|---------|-------------|
| `aicl:horizon:supervisors` | List all supervisors with PID, status, workers, balancing |
| `aicl:horizon:supervisor-status {name}` | Get status of a specific supervisor |
| `aicl:horizon:pause-supervisor {name}` | Pause a specific supervisor |
| `aicl:horizon:continue-supervisor {name}` | Resume a specific supervisor |

#### Job Management

| Command | Description |
|---------|-------------|
| `aicl:horizon:clear {connection?}` | Delete all jobs from a queue. Options: `--queue=`, `--force` |
| `aicl:horizon:forget {id?}` | Delete a failed job by ID. Options: `--all` |
| `aicl:horizon:list` | List all deployed machines (master supervisors) |

#### Metrics & Maintenance

| Command | Description |
|---------|-------------|
| `aicl:horizon:snapshot` | Store a metrics snapshot (typically scheduled) |
| `aicl:horizon:clear-metrics` | Delete all job and queue metrics |
| `aicl:horizon:purge` | Terminate orphaned/rogue Horizon processes. Options: `--signal=SIGTERM` |
| `aicl:horizon:timeout {environment=production}` | Show maximum timeout for an environment |

#### Internal (Hidden) Commands

| Command | Description |
|---------|-------------|
| `aicl:horizon:supervisor` | Start a supervisor process (invoked by master, not directly) |
| `aicl:horizon:work` | Worker process (invoked by supervisor, not directly) |

### Events

| Event | Fired When |
|-------|-----------|
| `MasterSupervisorDeployed` | Provisioning plan deployed |
| `MasterSupervisorLooped` | Each master supervisor loop iteration (triggers trim/expire/memory checks) |
| `SupervisorLooped` | Each supervisor loop iteration (triggers prune/memory/wait-time checks) |
| `LongWaitDetected` | Queue wait time exceeds threshold |
| `JobPending` | Job pushed and pending |
| `JobPushed` | Job pushed to Redis |
| `JobReserved` | Worker picks up a job |
| `JobReleased` | Job released back to queue (retry) |
| `JobDeleted` | Job completed and removed |
| `JobFailed` | Job permanently failed |
| `JobsMigrated` | Jobs migrated between queues |
| `WorkerProcessRestarting` | Worker process restarting |
| `SupervisorProcessRestarting` | Supervisor process restarting |
| `MasterSupervisorReviving` | Master supervisor recovering |
| `MasterSupervisorOutOfMemory` | Master supervisor exceeded memory limit |
| `SupervisorOutOfMemory` | Supervisor exceeded memory limit |
| `UnableToLaunchProcess` | Failed to start a worker process |

### Service Bindings

All Redis-backed, registered in `HorizonServiceProvider`:

| Contract | Implementation |
|----------|---------------|
| `Contracts\JobRepository` | `Repositories\RedisJobRepository` |
| `Contracts\MasterSupervisorRepository` | `Repositories\RedisMasterSupervisorRepository` |
| `Contracts\MetricsRepository` | `Repositories\RedisMetricsRepository` |
| `Contracts\ProcessRepository` | `Repositories\RedisProcessRepository` |
| `Contracts\SupervisorRepository` | `Repositories\RedisSupervisorRepository` |
| `Contracts\TagRepository` | `Repositories\RedisTagRepository` |
| `Contracts\WorkloadRepository` | `Repositories\RedisWorkloadRepository` |
| `Contracts\HorizonCommandQueue` | `RedisHorizonCommandQueue` |
| `Contracts\LongWaitDetectedNotification` | `Notifications\LongWaitDetected` |

---

## Relationship to AI Assistant

AI streaming is deeply coupled to the queue system:

1. **`AiStreamJob`** and **`AiConversationStreamJob`** both dispatch to `config('aicl.ai.streaming.queue', 'default')`.
2. Both have `$timeout = 120` seconds (from `config('aicl.ai.streaming.timeout', 120)`), which is double the default worker timeout of 60 seconds. The job sets its own `$timeout` property, which Laravel's worker respects per-job.
3. Both have `$tries = 1` -- AI streams are not retried because the user experience depends on a single continuous stream.
4. **Concurrent stream limit:** 2 per user (from `config('aicl.ai.streaming.max_concurrent_per_user', 2)`), enforced by an atomic Redis counter (`ai-stream:user:{id}:count`). This is NOT enforced by Horizon -- it is a Redis-level gate in `AiAssistantController` and `AiChatService`.
5. If Horizon is down, AI messages dispatch to Redis but no worker picks them up. The user sees their message sent but never receives streamed tokens back. The stream silently times out.

---

## Troubleshooting

### Jobs Not Processing

**Symptom:** Jobs dispatched but nothing happens. AI assistant unresponsive. Notifications not sending.

1. **Check Horizon status:**
   ```bash
   ddev exec supervisorctl status webextradaemons:horizon
   ```
   Expected: `RUNNING`. If `STOPPED`, `FATAL`, or `BACKOFF`, Horizon is not running.

2. **Check feature flag:**
   ```bash
   ddev exec php artisan tinker --execute="echo config('aicl.features.horizon') ? 'enabled' : 'disabled';"
   ```
   Must output `enabled`.

3. **Check Redis connectivity:**
   ```bash
   ddev exec redis-cli ping
   ```
   Expected: `PONG`.

4. **Check queue connection:**
   ```bash
   ddev exec php artisan tinker --execute="echo config('queue.default');"
   ```
   Should be `redis`. If `sync`, jobs execute inline (no worker needed, but blocking).

5. **Check Horizon status via artisan:**
   ```bash
   ddev exec php artisan aicl:horizon:status
   ```
   Exit code 0 = running, 1 = paused, 2 = inactive.

6. **List supervisors and their workers:**
   ```bash
   ddev exec php artisan aicl:horizon:supervisors
   ```

### AI Assistant Not Responding

The AI streaming pipeline: user sends message -> controller dispatches `AiConversationStreamJob` to `default` queue -> Horizon worker picks it up -> job streams tokens via Reverb WebSocket.

If the assistant appears to accept messages but never responds:
- Horizon may be down (jobs never process).
- The `default` queue may be backed up (jobs waiting behind other jobs).
- The worker timeout (60s) may be killing the stream job before it completes. Check that the AI stream job's `$timeout = 120` is being respected (it should be -- Laravel reads per-job `$timeout`).

### Jobs Timing Out

Default worker timeout is 60 seconds. Jobs that need more time must set their own `$timeout` property:

```php
class LongRunningJob implements ShouldQueue
{
    public int $timeout = 300; // 5 minutes
}
```

The AI stream jobs already set `$timeout = 120`. If you see timeout failures for these, check `config('aicl.ai.streaming.timeout')` -- it may have been lowered.

### Memory Issues

- **Worker memory limit:** 128 MB (per `aicl-horizon.defaults.supervisor-1.memory`). Workers restart when they exceed this.
- **Master supervisor memory limit:** 64 MB (per `aicl-horizon.memory_limit`). The master restarts if it exceeds this.
- If jobs consistently cause OOM, check for memory leaks (un-freed references, growing arrays, large model hydrations).

### Supervisor Not Starting

```bash
ddev ssh
supervisorctl status
```

Look for the `webextradaemons:horizon` entry. Check logs at `/var/tmp/logpipe`.

Common causes:
- **Stale PID:** A previous Horizon process left state in Redis. Fix: `ddev exec php artisan aicl:horizon:terminate` then restart.
- **Redis not ready:** supervisord starts Horizon before Redis is available. It retries up to 15 times.
- **Duplicate master:** Horizon refuses to start if it detects another master supervisor with the same name. Fix: `ddev exec php artisan aicl:horizon:purge` to clean up orphans.

### Stale Supervisor

If Horizon appears stuck or out of sync:

```bash
ddev exec php artisan aicl:horizon:terminate
# Wait a few seconds for supervisord to auto-restart it
ddev exec supervisorctl status webextradaemons:horizon
```

Or force a hard restart:

```bash
ddev exec supervisorctl restart webextradaemons:horizon
```

### Queue Backed Up

In local/development, only 1 worker processes jobs (maxProcesses: 1). This means jobs are processed sequentially. For heavier workloads:

1. Increase `maxProcesses` in the `local` environment config (currently set to 3).
2. Check what is backed up: `ddev exec php artisan aicl:horizon:supervisors` to see worker counts.
3. Check pending jobs via the Queue Manager dashboard or: `ddev exec redis-cli llen queues:default`.

### Orphaned Processes

After crashes or forced restarts, orphaned worker processes may linger:

```bash
ddev exec php artisan aicl:horizon:purge
```

This finds processes that Redis knows about but that are no longer running (or vice versa) and terminates them.

---

## Operational Commands Quick Reference

```bash
# Check if Horizon is running
ddev exec supervisorctl status webextradaemons:horizon

# Hard restart Horizon
ddev exec supervisorctl restart webextradaemons:horizon

# Graceful terminate (supervisord will auto-restart)
ddev exec php artisan aicl:horizon:terminate

# Check Horizon status
ddev exec php artisan aicl:horizon:status

# List all supervisors
ddev exec php artisan aicl:horizon:supervisors

# List all master supervisors (machines)
ddev exec php artisan aicl:horizon:list

# Pause all processing
ddev exec php artisan aicl:horizon:pause

# Resume processing
ddev exec php artisan aicl:horizon:continue

# View failed jobs (Laravel's built-in)
ddev exec php artisan queue:failed

# Retry all failed jobs (Laravel's built-in)
ddev exec php artisan queue:retry all

# Delete all failed jobs (Laravel's built-in)
ddev exec php artisan queue:flush

# Delete failed jobs from Horizon's tracking
ddev exec php artisan aicl:horizon:forget --all

# Clean up orphaned processes
ddev exec php artisan aicl:horizon:purge

# Take a metrics snapshot
ddev exec php artisan aicl:horizon:snapshot

# Clear all metrics
ddev exec php artisan aicl:horizon:clear-metrics

# Clear a specific queue
ddev exec php artisan aicl:horizon:clear --queue=default

# Monitor queue sizes (Laravel's built-in)
ddev exec php artisan queue:monitor default,notifications,high,low,search
```

---

## Production Considerations

1. **Increase `maxProcesses`.** Default production is 10. Scale based on expected job throughput.
2. **Separate supervisors per queue.** For heavy workloads, define dedicated supervisors in the `production` environment config:
   ```php
   'environments' => [
       'production' => [
           'supervisor-default' => [
               'queue' => ['default'],
               'maxProcesses' => 5,
           ],
           'supervisor-ai' => [
               'queue' => ['default'],
               'maxProcesses' => 3,
               'timeout' => 180,  // Longer timeout for AI streaming
           ],
           'supervisor-notifications' => [
               'queue' => ['notifications'],
               'maxProcesses' => 2,
           ],
       ],
   ],
   ```
3. **Monitor wait times.** The `LongWaitDetected` event fires when any `redis:*` queue exceeds its threshold (default 60s). Wire this to an alerting system.
4. **Schedule snapshots.** Add `$schedule->command('aicl:horizon:snapshot')->everyFiveMinutes();` to your scheduler for metrics history.
5. **Schedule purge.** Add `$schedule->command('aicl:horizon:purge')->everyFiveMinutes();` to clean up orphaned processes.
6. **Fast termination.** Set `fast_termination: true` for zero-downtime deployments -- a new Horizon instance starts while the old one drains.
7. **Memory limits.** If running many workers, monitor the master supervisor's 64 MB limit. Increase via `aicl-horizon.memory_limit` if needed.

---

## Source Files

All Horizon source lives in the read-only package:

| Path | Description |
|------|-------------|
| `packages/aicl/src/Horizon/` | All Horizon source code (~124 PHP files) |
| `packages/aicl/src/Horizon/Console/` | 16 artisan commands |
| `packages/aicl/src/Horizon/Contracts/` | Repository and service interfaces |
| `packages/aicl/src/Horizon/Repositories/` | Redis-backed repository implementations |
| `packages/aicl/src/Horizon/Livewire/` | 7 Filament Livewire components |
| `packages/aicl/src/Horizon/Events/` | 15 event classes |
| `packages/aicl/src/Horizon/Listeners/` | Job lifecycle and monitoring listeners |
| `packages/aicl/src/Horizon/SupervisorCommands/` | Scale, Pause, Terminate, Balance, Restart, ContinueWorking |
| `packages/aicl/src/Horizon/MasterSupervisorCommands/` | AddSupervisor |
| `packages/aicl/config/aicl-horizon.php` | Horizon configuration |
| `.ddev/.webimageBuild/horizon.conf` | supervisord config for DDEV |
| `packages/aicl/src/Health/Checks/QueueCheck.php` | Health check (uses Horizon repos when available) |

---

## Related

- [Reverb & WebSockets](reverb-websockets.md) -- Reverb WebSocket integration (AI streaming destination)
- [AI Assistant](ai-assistant.md) -- AI streaming job details and troubleshooting
- [Service Orchestration](service-orchestration.md) -- Master reference for all services
- [Notification & Observability](notification-observability.md) -- Notification delivery via queues
- `packages/aicl/src/AI/Jobs/AiStreamJob.php` -- AI streaming job implementation
- `packages/aicl/src/AI/Jobs/AiConversationStreamJob.php` -- Conversation streaming job implementation
