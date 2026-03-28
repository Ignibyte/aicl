# Queue, Jobs & Scheduler

**Version:** 1.0
**Last Updated:** 2026-02-06
**Owner:** `/pipeline-implement`

---

## Overview

AICL uses Laravel's queue system backed by Redis for all asynchronous processing. The queue handles notification delivery, broadcast events, and any client-defined jobs. Monitoring is built into the admin panel via custom Filament pages.

---

## Architecture

```
┌─────────────────────────────────────────────────────────┐
│                    JOB PRODUCERS                          │
│                                                           │
│  Observers ──┐                                           │
│  Events ─────┼──→ dispatch(new SomeJob(...))             │
│  Commands ───┤      └──→ Redis (database 2)              │
│  Scheduler ──┘              │                            │
│                             ▼                            │
│  ┌──────────────────────────────────────────────────┐   │
│  │              QUEUE WORKER                          │   │
│  │                                                    │   │
│  │  php artisan queue:work redis                     │   │
│  │    --queue=default,notifications,broadcasts       │   │
│  │    --tries=3                                      │   │
│  │    --timeout=60                                   │   │
│  │                                                    │   │
│  │  Processes jobs from Redis queues sequentially     │   │
│  │  Failed jobs → failed_jobs table (MySQL/MariaDB)  │   │
│  └──────────────────────────────────────────────────┘   │
│                             │                            │
│                             ▼                            │
│  ┌──────────────────────────────────────────────────┐   │
│  │         MONITORING (Filament Admin)                │   │
│  │                                                    │   │
│  │  Queue Dashboard ── pending / processed / failed  │   │
│  │  Failed Jobs ────── table with retry / delete     │   │
│  │  Queue Stats Widget ── widget for dashboard       │   │
│  └──────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────┘
```

---

## Redis Queue Configuration

**Queue connection:** Redis, database 2 (dedicated — cache is 0, sessions is 1).

```php
// config/queue.php
'default' => env('QUEUE_CONNECTION', 'redis'),

'connections' => [
    'redis' => [
        'driver' => 'redis',
        'connection' => 'queue',
        'queue' => env('REDIS_QUEUE', 'default'),
        'retry_after' => 90,
        'block_for' => null,
    ],
],

// config/database.php → redis connections
'queue' => [
    'url' => env('REDIS_URL'),
    'host' => env('REDIS_HOST', '127.0.0.1'),
    'password' => env('REDIS_PASSWORD'),
    'port' => env('REDIS_PORT', '6379'),
    'database' => env('REDIS_QUEUE_DB', '2'),
],
```

**Why separate Redis databases?** Prevents cache flushes from wiping queue data, and session expiry from interfering with job processing.

---

## Queue Names

| Queue | Purpose | Priority |
|-------|---------|----------|
| `default` | General-purpose jobs | Normal |
| `notifications` | Notification delivery (mail, database, broadcast) | Normal |
| `broadcasts` | WebSocket broadcast events | Normal |

All queues are processed by a single worker with priority ordering:

```bash
php artisan queue:work redis --queue=default,notifications,broadcasts
```

---

## Job Pattern

Standard Laravel queued jobs with the `ShouldQueue` interface:

```php
namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessEntityReport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 60;

    public function __construct(
        public Project $project,
    ) {}

    public function handle(): void
    {
        // Job logic
    }

    public function failed(\Throwable $exception): void
    {
        // Failure handling
    }
}
```

**AICL convention:** Always define `$tries` and `$timeout` on jobs. Always implement `failed()` for error visibility.

---

## Failed Job Management

Failed jobs are stored in the `failed_jobs` database table (not Redis). AICL provides two Filament interfaces for managing them:

### FailedJobResource

**Location:** `packages/aicl/src/Filament/Resources/FailedJobs/FailedJobResource.php`

Full CRUD resource for failed jobs with:
- Table columns: queue, payload (truncated), exception (truncated), failed_at
- Row actions: Retry (pushes job back to queue), Delete
- Bulk actions: Retry selected, Delete selected

### Queue Dashboard Page

**Location:** `packages/aicl/src/Filament/Pages/QueueDashboard.php`

Overview page with:
- `QueueStatsWidget` — pending, processed today, failed counts
- `RecentFailedJobsWidget` — last 5 failures with quick retry

**Access:** Admin and super_admin roles only.

---

## Scheduler

Laravel's task scheduler runs via cron (or Octane's built-in tick):

```php
// routes/console.php or app/Console/Kernel.php (Laravel 11 style)
Schedule::command('queue:prune-failed --hours=168')->daily();
Schedule::command('activitylog:clean --days=90')->daily();
```

**AICL scheduled tasks:**
- `queue:prune-failed` — Remove failed jobs older than 7 days
- `activitylog:clean` — Prune old audit log entries

In DDEV, the scheduler runs as a web_extra_daemon or via `php artisan schedule:work`.

---

## Octane Worker Context

With Laravel Octane, queue workers run in the same Swoole environment. Key considerations:

- **Singleton services** persist across jobs — be careful with stateful services
- **Database connections** are managed by Octane's connection pool
- Queue workers are separate from the Octane HTTP server — they run as standard `queue:work` processes, not inside Swoole workers
- Use `ddev octane-reload` to restart Octane HTTP workers; queue workers restart independently

---

## Testing Queues

```php
// In tests, queues are synchronous by default (QUEUE_CONNECTION=sync in phpunit.xml)
// To test async behavior:

Queue::fake();

// Dispatch job
ProcessEntityReport::dispatch($project);

// Assert job was dispatched
Queue::assertPushed(ProcessEntityReport::class);

// Assert job was dispatched with specific data
Queue::assertPushed(ProcessEntityReport::class, function ($job) use ($project) {
    return $job->project->is($project);
});
```

---

## Related Documents

- [Cache, Sessions & Redis](cache-sessions-redis.md) — Redis database mapping
- [Notifications](notifications.md) — Queued notification delivery
- [Search & Real-time](search-realtime.md) — Broadcast queue
