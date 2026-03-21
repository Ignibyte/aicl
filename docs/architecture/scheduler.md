# Task Scheduler

## Purpose

The Laravel task scheduler runs recurring maintenance commands on a fixed schedule without requiring a traditional system cron job. In AICL, it runs as a supervisor-managed daemon inside the DDEV container via `php artisan schedule:work`, which wakes every 60 seconds and checks for due tasks. Every execution is recorded in the `schedule_history` table by the `ScheduleEventSubscriber`, giving the Operations Manager page and the health check system visibility into what ran, when it ran, and whether it succeeded.

AICL also has a separate mechanism for in-process recurring work: Swoole timer jobs. These run inside the Octane worker process on fixed intervals and are documented in the final section to clarify the boundary.

## Dependencies

- **supervisord** -- Manages the `schedule` daemon process within the DDEV container
- **`Aicl\Listeners\ScheduleEventSubscriber`** -- Records task start/finish/failure to `schedule_history`
- **`Aicl\Models\ScheduleHistory`** -- Eloquent model for the execution log
- **`Aicl\Health\Checks\SchedulerCheck`** -- Health check that monitors recency of scheduler runs
- **`Aicl\Console\Commands\PruneScheduleHistoryCommand`** -- Prunes old history records
- **`Aicl\Livewire\ScheduleHistoryTable`** -- Filament table widget for the Operations Manager
- **`Aicl\Filament\Pages\OperationsManager`** -- Admin UI that displays scheduler stats, registered tasks, and execution history
- **Redis** -- Queue backend for dispatched jobs (Horizon manages the workers)
- **PostgreSQL** -- Stores the `schedule_history` table

## Key Decisions

### 1. schedule:work daemon, not system cron

DDEV containers do not have a persistent crontab. Instead, `schedule:work` runs as a foreground process managed by supervisor. It sleeps between runs, wakes every 60 seconds, and invokes `schedule:run`. This is Laravel's recommended approach for containerized environments.

### 2. Automatic execution tracking via event subscriber

Rather than requiring each scheduled command to log its own execution, the `ScheduleEventSubscriber` listens to Laravel's built-in `ScheduledTaskStarting`, `ScheduledTaskFinished`, and `ScheduledTaskFailed` events. Every scheduled task is automatically recorded in `schedule_history` with its command name, cron expression, exit code, output (capped at `output_max_bytes`), and duration.

### 3. Health check integration

The `SchedulerCheck` queries the most recent `ScheduleHistory` record. If no task has run in 5+ minutes, the health status degrades. If no task has run in 15+ minutes, the health status goes down. This catches silent scheduler failures (crashed process, stale PID, supervisor not started) without requiring external monitoring.

### 4. Feature flag for framework scheduled tasks

All framework-provided scheduled tasks (backups, Horizon snapshots, history pruning) are wrapped in a `config('aicl.features.scheduler')` guard in `routes/console.php`. When the flag is `false`, no framework tasks are registered with the scheduler. The flag defaults to `true` (opt-out). Project-level scheduled tasks should be registered outside the feature flag guard block so they remain unaffected. The Horizon-specific tasks have an additional nested guard (`config('aicl.features.horizon')`) for finer-grained control.

### 5. Scheduler vs. Swoole timers

The scheduler and Swoole timers serve different purposes:

| Aspect | Scheduler (`schedule:work`) | Swoole Timers (`SwooleTimer`) |
|--------|----------------------------|-------------------------------|
| Runs in | Dedicated scheduler process | Octane worker process (worker 0) |
| Granularity | 1-minute minimum | Sub-second intervals |
| Use case | Maintenance commands, backups, pruning | Health refresh, stale data cleanup |
| Persistence | Defined in `routes/console.php` | Redis-persisted, restored on worker boot |
| Monitoring | `schedule_history` table, health check | `SwooleTimer::list()`, Redis keys |

## Scheduled Tasks

All tasks are registered in `routes/console.php`.

### Backup tasks (spatie/laravel-backup)

| Command | Schedule | Purpose |
|---------|----------|---------|
| `backup:run` | Daily at 02:00 | Create a full application backup |
| `backup:clean` | Daily at 03:00 | Remove old backups per retention policy |
| `backup:monitor` | Daily at 08:00 | Check backup health and alert on failures |

### Horizon metrics

| Command | Schedule | Condition | Purpose |
|---------|----------|-----------|---------|
| `aicl:horizon:snapshot` | Every 5 minutes | `config('aicl.features.horizon')` is true | Store queue metrics snapshot for the Horizon dashboard |

The Horizon snapshot uses a distributed lock (300s minus 30s buffer) to prevent duplicate snapshots when multiple scheduler instances are running.

### Maintenance

| Command | Schedule | Purpose |
|---------|----------|---------|
| `schedule:prune-history` | Daily at 04:00 | Delete `schedule_history` records older than the configured retention period (default: 30 days) |

The prune command accepts a `--days` option to override the configured retention: `artisan schedule:prune-history --days=7`.

## DDEV Configuration

### Supervisor Config

**File:** `.ddev/.webimageBuild/schedule.conf`

```ini
[program:schedule]
group=webextradaemons
command=bash -c "php /var/www/html/artisan schedule:work; exit_code=$?; if [ $exit_code -ne 0 ]; then sleep 2; fi; exit $exit_code"
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
- **autostart=false** -- Managed by the `webextradaemons` supervisor group (along with octane, reverb, and horizon), not individually
- **autorestart=true** -- Supervisor restarts the process if it exits
- **startsecs=3** -- Must stay up 3 seconds to be considered started (matches the `sleep 2` on failure in the command wrapper)
- **startretries=15** -- Retries on startup failures (e.g., database not ready on container boot)
- **stopasgroup=true** -- Kills child processes when stopping
- **sleep 2 on failure** -- Prevents tight restart loops by delaying the exit when the command fails

### Daemon Group

**File:** `.ddev/.webimageBuild/webextradaemons.conf`

```ini
[group:webextradaemons]
programs=octane,reverb,horizon,schedule
```

All four daemons are managed as a single supervisor group. Starting the group starts all four; stopping it stops all four.

## Scheduler Monitoring

### ScheduleHistory Model

**Class:** `Aicl\Models\ScheduleHistory`
**Table:** `schedule_history`
**Migration:** `packages/aicl/database/migrations/2026_03_12_100000_create_schedule_history_table.php`

Schema:

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint (PK) | Auto-increment ID |
| `command` | string | Artisan command name (stripped of PHP binary and `artisan` prefix) |
| `description` | string (nullable) | Task description if set |
| `expression` | string(100) | Cron expression (e.g., `0 2 * * *`) |
| `status` | string(20) | `running`, `success`, `failed`, `skipped` |
| `exit_code` | integer (nullable) | Process exit code (0 = success) |
| `output` | text (nullable) | Captured stdout, truncated to `output_max_bytes` |
| `duration_ms` | integer (nullable) | Execution time in milliseconds |
| `started_at` | timestamp | When execution began |
| `finished_at` | timestamp (nullable) | When execution completed (null if still running) |
| `created_at` | timestamp | Record creation time |

Indexes: `(command, started_at)`, `(status)`, `(started_at)`.

Scopes: `successful()`, `failed()`, `forCommand(string)`, `recent(int $hours = 24)`.

### ScheduleEventSubscriber

**Class:** `Aicl\Listeners\ScheduleEventSubscriber`
**Registered in:** `AiclServiceProvider::boot()` via `Event::subscribe(ScheduleEventSubscriber::class)`

Subscribes to three Laravel events:
- `ScheduledTaskStarting` -- Creates a `ScheduleHistory` record with status `running`
- `ScheduledTaskFinished` -- Updates the record to `success` with exit code, output, and duration
- `ScheduledTaskFailed` -- Updates the record to `failed` with exit code, exception message, output, and duration

Output capture reads from the task's output file path (if not `/dev/null`), capped at `output_max_bytes` (default 10,240 bytes). Larger output is truncated with a `[TRUNCATED]` marker.

### Health Check

**Class:** `Aicl\Health\Checks\SchedulerCheck`

Queries `ScheduleHistory` for the most recent `started_at` and compares the age against configured thresholds:

| Minutes since last run | Health status | Meaning |
|------------------------|---------------|---------|
| < 5 | Healthy | Scheduler is running normally |
| 5 -- 14 | Degraded | Scheduler may have issues, investigate soon |
| >= 15 | Down | Scheduler is not running, action required |

The check also reports the count of failed tasks in the last 24 hours.

### Admin UI

The **Operations Manager** page (`/admin/operations-manager`) includes a Scheduler section with three tabs:
- **Registered Tasks** -- Parses `artisan schedule:list` output and cross-references with `ScheduleHistory` to show last execution status
- **Execution History** -- `ScheduleHistoryTable` Livewire widget with status badges, duration formatting, command filtering, and detail modals
- **Failures** -- Filtered view showing only failed executions

## API Surface

### Config Keys

| Key | Type | Default | Purpose |
|-----|------|---------|---------|
| `aicl.features.scheduler` | bool | `true` | Master toggle for all framework scheduled tasks |
| `aicl.scheduler.history_retention_days` | int | `30` | Days of history to keep before pruning |
| `aicl.scheduler.output_max_bytes` | int | `10240` | Maximum bytes of task output to capture |
| `aicl.scheduler.health_degraded_minutes` | int | `5` | Minutes without a run before health degrades |
| `aicl.scheduler.health_down_minutes` | int | `15` | Minutes without a run before health goes down |

### Adding New Scheduled Tasks

Register tasks in `routes/console.php` using the `Schedule` facade. Add project-level tasks **outside** the `config('aicl.features.scheduler')` guard block so they run regardless of the framework scheduler flag:

```php
use Illuminate\Support\Facades\Schedule;

// Framework tasks are inside the if-block above...

// Project-level tasks — always run regardless of scheduler feature flag
Schedule::command('your:command')->dailyAt('05:00');
```

No additional wiring is needed. The `ScheduleEventSubscriber` automatically records execution history for all scheduled tasks.

### Artisan Commands

| Command | Purpose |
|---------|---------|
| `schedule:prune-history` | Delete history records older than retention period. `--days=N` overrides config. |
| `aicl:horizon:snapshot` | Store queue metrics snapshot (normally run by scheduler, can be run manually). |

## Swoole Timer Jobs (Not Scheduler)

AICL registers two recurring jobs via `SwooleTimer` in `AiclServiceProvider::boot()`. These run inside the Octane worker process on worker 0, not via the task scheduler:

| Timer Key | Interval | Job Class | Purpose |
|-----------|----------|-----------|---------|
| `health-refresh` | 300s (5 min) | `Aicl\Jobs\RefreshHealthChecksJob` | Refresh cached health check results |
| `delivery-cleanup` | 3600s (1 hr) | `Aicl\Jobs\CleanStaleDeliveriesJob` | Clean stale notification delivery records |

These timers persist in Redis at `aicl:timers:{key}` and are restored on worker boot by `RestoreSwooleTimers`. They are independent of the scheduler -- they run even if the scheduler process is down, as long as Octane is running.

For full details on SwooleTimer, see the [Swoole / Octane architecture doc](swoole-octane.md).

## Troubleshooting

### Scheduler not running

**Check supervisor status:**
```bash
ddev exec supervisorctl status webextradaemons:schedule
```

Expected output when healthy: `webextradaemons:schedule   RUNNING   pid 1234, uptime 0:45:23`

If `STOPPED` or `FATAL`, start it:
```bash
ddev exec supervisorctl start webextradaemons:schedule
```

If `BACKOFF`, check logs for the underlying error:
```bash
ddev exec supervisorctl tail webextradaemons:schedule
```

### Tasks not executing

**List registered tasks:**
```bash
ddev exec artisan schedule:list
```

This shows all tasks, their cron expressions, and next due times. If the list is empty, `routes/console.php` is not loading correctly.

**Run tasks manually (once):**
```bash
ddev exec artisan schedule:run
```

This executes all currently due tasks immediately. Useful for testing without waiting for the next minute tick.

### Health check shows scheduler down

The `SchedulerCheck` reports "down" when no `ScheduleHistory` record has been written in the last 15 minutes. Common causes:

1. **Supervisor process crashed** -- Check `supervisorctl status`
2. **Database migration missing** -- The `schedule_history` table must exist. Run `ddev exec artisan migrate`.
3. **No tasks are due** -- If no task runs for 15+ minutes (unlikely given the 5-minute Horizon snapshot), the health check will trigger. This is by design -- it means the scheduler or its tasks have a problem.

### Pruning history manually

```bash
ddev exec artisan schedule:prune-history --days=7
```

## Related

- **Swoole / Octane architecture:** [Swoole/Octane](swoole-octane.md) -- Process model, SwooleTimer details, supervisor group configuration
- **Scheduler config:** `packages/aicl/config/aicl.php` (key: `scheduler`)
- **Schedule definition:** `routes/console.php`
- **Supervisor config:** `.ddev/.webimageBuild/schedule.conf`
- **Supervisor group:** `.ddev/.webimageBuild/webextradaemons.conf`
- **Event subscriber:** `packages/aicl/src/Listeners/ScheduleEventSubscriber.php`
- **History model:** `packages/aicl/src/Models/ScheduleHistory.php`
- **Health check:** `packages/aicl/src/Health/Checks/SchedulerCheck.php`
- **Prune command:** `packages/aicl/src/Console/Commands/PruneScheduleHistoryCommand.php`
- **Admin UI:** `packages/aicl/src/Filament/Pages/OperationsManager.php` (Scheduler section)
- **History table widget:** `packages/aicl/src/Livewire/ScheduleHistoryTable.php`
- **Horizon snapshot command:** `packages/aicl/src/Horizon/Console/SnapshotCommand.php`
