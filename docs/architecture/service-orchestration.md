# Service Orchestration -- Master Reference

This document is the master reference for how all AICL services fit together. When debugging a multi-service issue or understanding how a request flows through the system, start here.

For Swoole/Octane internals (worker lifecycle, state leaks, coroutines), see [Swoole/Octane](swoole-octane.md).

---

## 1. Service Map

All services run inside DDEV Docker containers. The web container uses supervisord to manage four long-lived PHP processes.

```
DDEV Container: ddev-aicl-web
├── nginx (port 80/443)
│   Reverse proxy, TLS termination, static asset serving
│   Config: .ddev/nginx_full/nginx-site.conf
│
├── php-fpm (installed but unused -- Octane handles all PHP requests)
│
└── supervisord manages [group:webextradaemons]:
    ├── octane    -- Swoole PHP server (127.0.0.1:8000)
    │   4 workers + 2 task workers, max 500 requests before restart
    │   Config: .ddev/.webimageBuild/octane.conf
    │   Command: php artisan octane:start --server=swoole --host=0.0.0.0
    │            --port=8000 --workers=4 --task-workers=2 --max-requests=500
    │
    ├── reverb   -- WebSocket server (0.0.0.0:8080)
    │   Laravel Reverb for real-time broadcasting
    │   Config: .ddev/.webimageBuild/reverb.conf
    │   Command: php artisan reverb:start --host=0.0.0.0 --port=8080
    │
    ├── horizon  -- Queue master supervisor
    │   Redis-backed, auto-scaling workers
    │   Config: .ddev/.webimageBuild/horizon.conf
    │   Command: php artisan aicl:horizon
    │
    └── schedule -- Laravel scheduler (cron replacement)
        Runs schedule:work for minute-by-minute task evaluation
        Config: .ddev/.webimageBuild/schedule.conf
        Command: php artisan schedule:work

DDEV Container: ddev-aicl-db
└── PostgreSQL 17 (port 5432)
    Default database: db (user: db, password: db)
    Testing database: aicl_testing (created by post-start hook)

DDEV Container: ddev-aicl-redis
└── Redis 7 (port 6379)
    Databases: 0 (default/queue/session), 1 (cache)

DDEV Container: ddev-aicl-elasticsearch (optional)
└── Elasticsearch 8.17 (port 9200)
    Only needed when config('aicl.features.scout_driver') is 'elasticsearch'
```

All supervisor programs share the same configuration pattern: `autostart=false` (supervisord starts the group, not individual programs), `autorestart=true`, `startsecs=3` (must stay up 3 seconds to be considered running), `startretries=15`, and a 2-second sleep on failure to prevent tight restart loops.

The supervisor group definition at `.ddev/.webimageBuild/webextradaemons.conf`:
```ini
[group:webextradaemons]
programs=octane,reverb,horizon,schedule
```

---

## 2. Request Flow -- Web Request

```
Browser
  │
  ▼
DDEV Router (TLS termination, *.ddev.site → container)
  │
  ▼
nginx (port 80/443 inside container)
  │
  ├── Static asset? (.css, .js, .png, .woff2, etc.)
  │   └── Serve from /var/www/html/public with 30d cache
  │       If file not on disk → fall through to @octane (handles /livewire/livewire.js etc.)
  │
  └── Dynamic request? (everything else)
      └── proxy_pass http://127.0.0.1:8000 (Swoole/Octane)
          │
          ▼
      Laravel application (booted once per worker, persists across requests)
          │
          ├── Middleware stack (session, auth, CSRF, presence tracking)
          ├── Route resolution (Filament admin, API, web)
          ├── Controller / Livewire component
          ├── Database queries (PostgreSQL via pgsql connection)
          ├── Cache reads/writes (Redis, database 1)
          └── Response
```

### Key configuration at each hop

**DDEV Router:** Terminates TLS using auto-generated certificates. Maps `https://aicl.ddev.site` to the web container. Configured in `.ddev/config.yaml`.

**nginx:** The custom config at `.ddev/nginx_full/nginx-site.conf` defines:
- An `upstream octane` block pointing to `127.0.0.1:8000`
- A `map` block that sets `X-Forwarded-Proto` to `https` for `*.ddev.site` hosts (because DDEV router strips TLS before forwarding)
- Static assets matched by extension (`css|js|ico|png|...`) with `try_files $uri @octane` fallback
- All other requests proxied to Octane with WebSocket upgrade headers (`Upgrade`, `Connection`)
- Proxy timeouts: connect 60s, send 60s, read 300s (5 minutes, important for long-running operations)
- Buffering disabled (`proxy_buffering off`) for streaming responses

**Octane/Swoole:** Configured in `config/octane.php`:
- Server: `swoole` (via `OCTANE_SERVER` env, defaults to swoole)
- Force HTTPS: `true` (all generated URLs use https)
- Listeners reset application state between requests via `Octane::prepareApplicationForNextOperation()` and `Octane::prepareApplicationForNextRequest()`
- File watching enabled for `app/`, `config/**/*.php`, `routes/`, `config/local.php`
- Garbage collection threshold: 50 MB
- Max execution time: 30 seconds per request
- Swoole cache table: 1000 rows, 10000 bytes per row

**Legacy direct access:** `https://aicl.ddev.site:8443` still works (configured in `.ddev/config.yaml` `web_extra_exposed_ports`) but bypasses nginx static asset serving and TLS handling. Not recommended.

---

## 3. Real-Time Flow -- WebSocket / Broadcasting

```
Backend code dispatches event
  │
  ▼
Laravel Broadcasting (driver: reverb)
  │  Uses key/secret/app_id to authenticate with Reverb
  │  Connection: localhost:8080, scheme: http (internal, no TLS needed)
  │
  ▼
Reverb server (port 8080 inside container)
  │  Receives event, resolves channel subscriptions
  │  Pushes to all connected WebSocket clients on matching channels
  │
  ▼
DDEV Router (exposes container port 8080 as wss://aicl.ddev.site:8080)
  │
  ▼
Browser WebSocket connection (Laravel Echo / Alpine.js)
  │  Client subscribes to channels on page load
  └── Receives event, updates UI reactively
```

### Broadcasting configuration

**`config/broadcasting.php`:** Default connection is `reverb`. The Reverb connection uses:
- Key: `riaqpvlloutkcr8ozmjx` (default dev value)
- Secret: `mzljn9t5gdlwyfba53wc` (default dev value)
- App ID: `780012`
- Host: `localhost`, Port: `8080`, Scheme: `http`

**`config/reverb.php`:** The Reverb server configuration:
- Server binds to `0.0.0.0:8080` (all interfaces, needed for DDEV routing)
- Allowed origins: `['*']` (development -- restrict in production)
- Ping interval: 60 seconds
- Activity timeout: 30 seconds
- Max request size: 10,000 bytes
- Scaling: disabled in development (enable Redis pub/sub for production multi-server)

### Broadcast channels (defined in `routes/channels.php`)

| Channel Pattern | Type | Authorization | Purpose |
|---|---|---|---|
| `App.Models.User.{id}` | Private | User ID match | Personal notifications |
| `dashboard` | Private | Any authenticated user | Real-time dashboard stats |
| `presence-admin-panel` | Presence | super_admin or admin role | Online admin users |
| `presence-page.{hash}` | Presence | super_admin or admin role | Per-page "who else is here" |
| `ai.stream.{streamId}` | Private | Cache-based user match | AI token streaming |

### Event types that broadcast

**Entity lifecycle events** (implement `ShouldBroadcast`, queued):
- `EntityCreated`, `EntityUpdated`, `EntityDeleted` -- broadcast to `dashboard` channel and entity-specific channels via the `BroadcastsDomainEvent` trait

**AI streaming events** (implement `ShouldBroadcastNow`, synchronous within the job):
- `AiStreamStarted` -- stream is beginning
- `AiTokenEvent` -- individual token from AI provider
- `AiToolCallEvent` -- AI tool call being executed
- `AiStreamCompleted` -- stream finished with usage stats
- `AiStreamFailed` -- stream encountered an error

All AI events broadcast on `private-ai.stream.{streamId}` and fire immediately (`ShouldBroadcastNow`) because they are already running inside a queued job.

---

## 4. Background Job Flow

```
Application code dispatches job
  │  e.g., AiStreamJob::dispatch(...), CleanStaleDeliveriesJob::dispatch()
  │
  ▼
Redis queue (database 0, key: queues:{queue_name})
  │  Default connection: redis
  │  Retry after: 90 seconds
  │  Failed job storage: database-uuids driver (failed_jobs table in PostgreSQL)
  │
  ▼
Horizon master supervisor (aicl:horizon command)
  │  Monitors Redis queues, assigns work to worker processes
  │  Uses 'auto' balancing with 'time' scaling strategy
  │
  ├── Local environment: up to 3 worker processes
  └── Production environment: up to 10 worker processes
      │
      ▼
  Worker picks up job, executes handle() method
      │
      ├── Success → Job removed from queue, logged in Horizon
      └── Failure → Retried up to N times (default 3), then → failed_jobs table
```

### Queue configuration

**`config/queue.php`:**
- Default connection: `redis`
- Redis queue connection: uses `default` Redis connection (database 0)
- Default queue name: `default`
- Retry after: 90 seconds
- Job batching: stored in `job_batches` table (PostgreSQL)
- Failed jobs: stored in `failed_jobs` table via `database-uuids` driver

**Horizon (`packages/aicl/config/aicl-horizon.php`):**
- Redis prefix: `aicl_horizon:` (derived from app name)
- Queue wait time threshold: 60 seconds on `redis:default`
- Trim policy: recent/pending/completed jobs kept 60 minutes, failed jobs kept 7 days (10080 minutes)
- Memory limit: 64 MB for master supervisor, 128 MB per worker
- Default worker: `supervisor-1` on `default` queue, auto-balance by time, 3 max tries, 60s timeout
- Environment overrides: local = 3 max processes, production = 10 max processes with balance shift

### Known queues

| Queue Name | Used By | Notes |
|---|---|---|
| `default` | AI streaming jobs, general jobs, compaction | Primary queue, auto-scaled by Horizon |
| `notifications` | Notification dispatch jobs | Configured in `aicl.notifications.queue` |
| `high` | Health check monitoring | Priority queue |
| `low` | Cleanup, reindexing | Background maintenance |

### Job inventory

| Job Class | Queue | Tries | Timeout | Purpose |
|---|---|---|---|---|
| `AiStreamJob` | configurable (default) | 1 | 120s | Legacy single-prompt AI streaming |
| `AiConversationStreamJob` | configurable (default) | 1 | 120s | Conversation-based AI streaming with tools |
| `CompactConversationJob` | configurable (default) | default | default | Summarize old conversation messages |
| `CleanStaleDeliveriesJob` | default | default | default | Remove stale notification deliveries |
| `RefreshHealthChecksJob` | default | default | default | Refresh cached health check results |
| `ReindexPermissionsJob` | default | default | default | Rebuild permission cache after changes |
| `IndexSearchDocumentJob` | default | default | default | Index entity in search engine |

---

## 5. AI Assistant Flow -- Complete End-to-End

This is the most complex flow in the system, touching all services.

```
1. User types message in Filament admin panel
   │  AiAssistantPanel Livewire component
   │
   ▼
2. Livewire sends message to server
   │  AiAssistantPanel::sendMessage($message)
   │  Browser → nginx → Octane → Livewire
   │
   ▼
3. AiChatService processes the request
   │  a. Verifies user has role-based access to the AI agent
   │  b. Creates AiMessage record in PostgreSQL (role: user)
   │  c. Generates a UUID stream ID
   │  d. Stores stream_id → user_id mapping in Redis cache (5 min TTL)
   │  e. Checks concurrent stream limit (max 2 per user, atomic increment)
   │  f. Dispatches AiConversationStreamJob to Redis queue
   │
   ▼
4. Returns stream info to browser
   │  { stream_id, channel: "private-ai.stream.{uuid}", message_id }
   │  Browser receives response, subscribes to WebSocket channel
   │
   ▼
5. Browser opens WebSocket connection
   │  Alpine.js → wss://aicl.ddev.site:8080 (Reverb)
   │  Subscribes to private-ai.stream.{streamId}
   │  Channel auth: Reverb calls back to Laravel, checks Redis cache
   │
   ▼
6. Horizon picks up AiConversationStreamJob from Redis queue
   │  Worker process executes handle()
   │
   ▼
7. Job builds AI agent and streams response
   │  a. Loads AiConversation with agent config from PostgreSQL
   │  b. Creates AI provider via AiProviderFactory (OpenAI/Anthropic/Ollama)
   │  c. Builds message history (respects context_messages limit)
   │  d. Registers tools if agent has tools enabled (AiToolRegistry)
   │  e. Broadcasts AiStreamStarted event via Reverb
   │  f. Calls agent->stream() -- iterates over token generator
   │
   ▼
8. Token-by-token streaming loop
   │  For each chunk from the AI provider:
   │  ├── ToolCallMessage? → broadcast AiToolCallEvent
   │  └── Text token? → broadcast AiTokenEvent (with index)
   │  Each broadcast: Job → Laravel Broadcasting → Reverb → WebSocket → Browser
   │
   ▼
9. Stream completes
   │  a. Broadcasts AiStreamCompleted (with token count + usage stats)
   │  b. Creates AiMessage record in PostgreSQL (role: assistant, full content)
   │  c. Decrements concurrent stream count in Redis cache
   │
   ▼
10. Browser renders complete response
    Alpine.js accumulates tokens into rendered markdown
```

### Service touchpoints in the AI flow

| Step | Octane | Redis | Horizon | Reverb | PostgreSQL |
|---|---|---|---|---|---|
| 1-2. User sends message | Handles Livewire request | Session auth | -- | -- | -- |
| 3. AiChatService | Runs in Octane worker | Cache stream_id, concurrent count | -- | -- | Create AiMessage |
| 4. Return stream info | Sends response | -- | -- | -- | -- |
| 5. WebSocket connect | -- | -- | -- | Accepts connection, auth callback | -- |
| 6. Job pickup | -- | Dequeue job | Assigns to worker | -- | -- |
| 7-8. AI streaming | -- | -- | Worker executes | Broadcasts each token | Load conversation + history |
| 9. Complete | -- | Decrement count | Job done | Final broadcast | Save assistant message |

---

## 6. Service Dependency Matrix

| Feature | Octane | Redis | Horizon | Reverb | PostgreSQL | Elasticsearch |
|---|---|---|---|---|---|---|
| Web pages (Filament) | Required | Required (session, cache) | -- | -- | Required | -- |
| API endpoints | Required | Required (session, throttle) | -- | -- | Required | -- |
| AI Assistant | Required | Required (stream tracking) | Required (job execution) | Required (token streaming) | Required (conversations, messages) | -- |
| Notifications | Required | Required (queue) | Required (dispatch) | Optional (broadcast channel) | Required (database channel) | -- |
| Background jobs | -- | Required (queue backend) | Required (supervision) | -- | Required (most jobs touch DB) | -- |
| Presence tracking | Required (middleware) | Required (cache storage) | -- | -- | -- | -- |
| Real-time dashboard | Required | Required (cache) | -- | Required (event delivery) | Required | -- |
| Health checks | Required (endpoint) | Required (queue depth check) | -- | -- | Required (DB check) | -- |
| Scheduler | -- | -- | -- | -- | Required (task history) | -- |
| Full-text search | Required | -- | -- | -- | Required (default driver) | Optional (ES driver) |

---

## 7. Port Map

| Service | Container Port | DDEV HTTP Port | DDEV HTTPS Port | Access URL |
|---|---|---|---|---|
| nginx (web) | 80 / 443 | auto | auto | `https://aicl.ddev.site` |
| Octane (Swoole) | 8000 | 8000 | 8443 | `https://aicl.ddev.site:8443` (legacy, not recommended) |
| Reverb (WebSocket) | 8080 | 8079 | 8080 | `wss://aicl.ddev.site:8080` |
| PostgreSQL | 5432 | -- | -- | Internal: `host=db port=5432` |
| Redis | 6379 | -- | -- | Internal: `host=redis port=6379` |
| Elasticsearch | 9200 | 9200 | -- | `http://localhost:9200` (when running) |
| Mailpit | 8025 / 8026 | 8025 | 8026 | `https://aicl.ddev.site:8026` |

Port assignments are defined in `.ddev/config.yaml` under `web_extra_exposed_ports`. The DDEV router maps external HTTPS ports to container ports automatically.

---

## 8. Configuration File Map

| File | Purpose | Key Settings |
|---|---|---|
| `.ddev/config.yaml` | DDEV project definition | PHP 8.5, PostgreSQL 17, nginx-fpm, daemon definitions, exposed ports, post-start hooks |
| `.ddev/nginx_full/nginx-site.conf` | nginx reverse proxy | Upstream to Octane, static asset serving with `try_files`, WebSocket upgrade headers, proxy timeouts |
| `.ddev/.webimageBuild/octane.conf` | Octane supervisor config | Swoole command with worker counts, autorestart, 3s startsecs, 15 retries |
| `.ddev/.webimageBuild/reverb.conf` | Reverb supervisor config | WebSocket server command, same restart policy |
| `.ddev/.webimageBuild/horizon.conf` | Horizon supervisor config | Queue supervisor via `aicl:horizon`, same restart policy |
| `.ddev/.webimageBuild/schedule.conf` | Scheduler supervisor config | `schedule:work` command, same restart policy |
| `.ddev/.webimageBuild/webextradaemons.conf` | Supervisor group | Groups octane, reverb, horizon, schedule into `webextradaemons` |
| `config/octane.php` | Swoole/Octane settings | Server type, HTTPS forcing, listener hooks, warm/flush bindings, file watching, cache table, GC threshold |
| `config/reverb.php` | Reverb WebSocket server | Server host/port, app credentials, allowed origins, scaling (Redis pub/sub), ping/timeout |
| `config/broadcasting.php` | Event broadcasting | Default driver: reverb, connection credentials (must match reverb.php app config) |
| `config/queue.php` | Queue connections | Default: redis, retry_after: 90s, failed jobs: database-uuids, batching: PostgreSQL |
| `config/cache.php` | Cache stores | Default: redis, cache connection uses database 1, prefix: `aicl_cache_` |
| `config/session.php` | Session handling | Driver: redis, lifetime: 120 minutes, cookie: `aicl_session` |
| `config/database.php` | Database + Redis | PostgreSQL (host: db, database: db), Redis (host: redis, db 0 default, db 1 cache), prefix: `aicl_database_` |
| `packages/aicl/config/aicl.php` | AICL feature flags and settings | Feature toggles, AI provider config, streaming config, notification channels, health check queues, security headers |
| `packages/aicl/config/aicl-horizon.php` | Horizon queue workers | Supervisor definitions, auto-scaling, environment-specific max processes, trim/retention policies |
| `config/local.php` | Per-instance secrets | API keys, app key, environment-specific overrides (gitignored) |
| `config/local.testing.php` | Test overrides | Sync queues, array cache, test-specific settings (gitignored) |
| `routes/channels.php` | Broadcast channel auth | User channels, dashboard, presence, AI stream authorization |

---

## 9. Startup Sequence

```
ddev start
  │
  ├── 1. Docker containers start: web, db, redis (+ elasticsearch if configured)
  │
  ├── 2. Post-start hook runs inside web container:
  │      psql checks if aicl_testing database exists, creates it if not
  │
  ├── 3. supervisord starts inside web container (managed by DDEV)
  │
  └── 4. supervisord starts webextradaemons group (all 4 programs):
         │
         ├── octane: Swoole boots Laravel app, binds to 0.0.0.0:8000
         │   Must stay up 3 seconds to be considered running
         │   Up to 15 restart attempts on failure
         │
         ├── reverb: WebSocket server binds to 0.0.0.0:8080
         │   Must stay up 3 seconds
         │
         ├── horizon: Queue master supervisor connects to Redis
         │   Spawns worker processes based on environment config
         │   Must stay up 3 seconds
         │
         └── schedule: Scheduler begins minute-by-minute evaluation
             Runs artisan schedule:work in foreground
             Must stay up 3 seconds
```

All four daemons start in parallel. Each has a 2-second sleep on failure before exiting (to prevent tight restart loops that burn through the 15 retry limit). The `startsecs=3` setting means supervisord only considers a process "running" if it stays up for at least 3 seconds.

The daemons are configured with `autostart=false` -- supervisord does not start them individually. Instead, the group starts them together when the supervisor process begins.

---

## 10. Health Check Commands

Run these first when debugging any issue.

```bash
# All services at a glance
ddev exec supervisorctl status

# Expected output (all RUNNING):
# webextradaemons:horizon    RUNNING   pid 123, uptime 1:23:45
# webextradaemons:octane     RUNNING   pid 124, uptime 1:23:45
# webextradaemons:reverb     RUNNING   pid 125, uptime 1:23:45
# webextradaemons:schedule   RUNNING   pid 126, uptime 1:23:45

# Individual service checks
ddev exec supervisorctl status webextradaemons:octane
ddev exec supervisorctl status webextradaemons:reverb
ddev exec supervisorctl status webextradaemons:horizon
ddev exec supervisorctl status webextradaemons:schedule

# Octane status via artisan
ddev octane-status

# Redis connectivity
ddev exec redis-cli ping
# Expected: PONG

# PostgreSQL connectivity
ddev exec psql -U db -d db -c "SELECT 1"

# Queue depth (should be 0 or near 0 when idle)
ddev exec redis-cli LLEN queues:default
ddev exec redis-cli LLEN queues:notifications

# Web endpoint check
curl -sk https://aicl.ddev.site/up
# Expected: 200 OK

# Legacy direct Octane check (bypasses nginx)
curl -sk https://aicl.ddev.site:8443/up
# Expected: 200 OK

# Reverb WebSocket check
# Open browser dev tools → Network → WS tab
# Look for WebSocket connection to wss://aicl.ddev.site:8080

# Horizon dashboard (requires admin login)
# Visit: https://aicl.ddev.site/admin → Ops Panel → Horizon status
```

---

## 11. Common Multi-Service Failures

### After `ddev restart`

**Octane BACKOFF (stale PID file):**
Swoole may refuse to start because a PID file from the previous run still exists. Supervisor shows `BACKOFF` or `FATAL` state.
```bash
ddev exec php artisan octane:stop
ddev exec supervisorctl restart webextradaemons:octane
```

**Horizon slow to reconnect:**
Horizon may take up to 10 seconds to re-establish its Redis connection after container restart. If it does not recover:
```bash
ddev exec supervisorctl restart webextradaemons:horizon
```

**Reverb:** Generally auto-recovers. If not:
```bash
ddev exec supervisorctl restart webextradaemons:reverb
```

### After code changes

| What changed | Action needed |
|---|---|
| PHP code (app/, routes/, packages/) | `ddev octane-reload` |
| Frontend assets (resources/css, js, views) | `ddev npm run build` |
| Filament JS assets (Js::make()) | `ddev exec php artisan filament:assets` then `ddev octane-reload` |
| Config files | `ddev octane-reload` (Octane watches `config/local.php` if file watching enabled) |
| Horizon config | `ddev exec supervisorctl restart webextradaemons:horizon` |
| Reverb config | `ddev exec supervisorctl restart webextradaemons:reverb` |
| Composer dependencies | `ddev exec supervisorctl restart webextradaemons:*` |
| Database migrations | `ddev exec php artisan migrate` (does NOT require service restart) |

### Redis connection lost

**Impact:** All services degrade simultaneously:
- Sessions lost (users logged out)
- Cache empty (cold start on every request)
- Queue stalls (no new jobs processed, in-flight jobs may fail)
- Presence tracking gone (all users appear offline)
- AI streaming fails (stream tracking lost)
- Horizon loses supervision state

**Fix:**
```bash
ddev restart   # Restarts all containers including Redis
```

### PostgreSQL connection lost

**Impact:**
- All web pages fail (most routes touch the database)
- API endpoints fail
- Running jobs that touch the database fail
- Horizon worker processes throw exceptions but supervisor restarts them

**Fix:**
```bash
ddev restart   # Restarts all containers including PostgreSQL
```

### WebSocket not connecting

**Symptoms:** Real-time features (AI streaming, dashboard updates, presence) not working. Browser console shows WebSocket connection errors.

**Debug steps:**
1. Check Reverb is running: `ddev exec supervisorctl status webextradaemons:reverb`
2. Check browser console for WebSocket errors (wrong host/port/protocol)
3. Verify broadcasting config matches reverb config (key, secret, app_id must match in both `config/broadcasting.php` and `config/reverb.php`)
4. Check channel authorization: failing auth callbacks silently reject subscriptions

### AI streaming not working

**Symptoms:** User sends message, spinner appears, but no tokens arrive.

**Debug steps (follow this order):**
1. Check Horizon is running: `ddev exec supervisorctl status webextradaemons:horizon`
2. Check queue depth: `ddev exec redis-cli LLEN queues:default` (if growing, jobs are not being processed)
3. Check Reverb is running: `ddev exec supervisorctl status webextradaemons:reverb`
4. Check failed jobs: `ddev exec php artisan queue:failed` (look for AiStreamJob or AiConversationStreamJob failures)
5. Check AI provider config: `config('aicl.ai.provider')` and API key in `config/local.php`
6. Check Laravel log: `ddev exec tail -50 storage/logs/laravel.log` for "AI stream failed" entries
7. Check concurrent stream limit: `ddev exec redis-cli GET "aicl_database_ai-stream:user:{userId}:count"` (max 2 per user)

---

## 12. The Nuclear Option -- Full Reset

When all else fails, restart everything from scratch:

```bash
ddev stop
ddev start
ddev exec supervisorctl restart webextradaemons:*
ddev octane-reload
```

If that does not work, rebuild containers:

```bash
ddev delete -O   # Remove containers but keep database
ddev start       # Rebuild everything
ddev exec php artisan migrate
```

---

## 13. Development vs Production Differences

| Setting | Development (DDEV) | Production |
|---|---|---|
| **Octane workers** | 4 workers + 2 task workers | 2x CPU cores + proportional task workers |
| **Octane max requests** | 500 (frequent recycling) | 1000+ (longer-lived workers) |
| **Horizon max processes** | 3 (local env) | 10+ (production env) |
| **Horizon balance shift** | default | 1 (gradual scaling) |
| **Broadcasting key/secret** | Dev defaults (hardcoded in config) | Unique per environment (via config/local.php) |
| **Reverb allowed_origins** | `['*']` | Domain-specific list |
| **Reverb scaling** | Disabled | Redis pub/sub enabled (multi-server) |
| **TLS termination** | DDEV router (self-signed certs) | Load balancer or nginx (real certs) |
| **Debug mode** | `true` | `false` |
| **Queue connection** | redis | redis (potentially separate instance) |
| **Cache store** | redis (database 1) | redis (separate instance recommended) |
| **Session driver** | redis | redis |
| **Elasticsearch** | Optional, single node | Cluster recommended for search-heavy workloads |
| **Supervisor** | DDEV-managed supervisord | System-level supervisor or container orchestrator |
| **Config secrets** | `config/local.php` (gitignored) | `config/local.php` or secrets manager |

### Production deployment notes

- Horizon config (`aicl-horizon.php`) has separate `production` and `local` environment blocks that automatically apply based on `APP_ENV`
- Reverb scaling must be enabled for multi-server deployments (uses Redis pub/sub to synchronize WebSocket state across servers)
- The `config/local.php` file is the single source of truth for all environment-specific configuration -- no `.env` file exists in this architecture
- Octane's `max_requests=500` in development is intentionally low to catch memory leaks early; increase in production

---

## 14. Redis Database Layout

Redis is the backbone for multiple subsystems. Understanding the database split prevents key collisions.

| Database | Purpose | Prefix | Configured In |
|---|---|---|---|
| 0 | Default: queues, sessions, Horizon metadata, Reverb state, presence cache | `aicl_database_` | `config/database.php` → `redis.default` |
| 1 | Cache store | `aicl_cache_` | `config/database.php` → `redis.cache` |

**Key patterns in database 0:**
- `aicl_database_queues:default` -- Default job queue (list)
- `aicl_database_queues:notifications` -- Notification queue (list)
- `aicl_horizon:*` -- Horizon metadata (supervisors, metrics, job records)
- `aicl_database_presence:sessions:*` -- Presence registry entries
- `aicl_database_ai-stream:*` -- AI stream tracking (stream-to-user mapping, concurrent counts)
- `aicl_session_*` -- Session data

**Key patterns in database 1:**
- `aicl_cache_*` -- Application cache entries (health checks, config cache, query cache)

---

## 15. Process Lifecycle Commands

### DDEV custom commands (`.ddev/commands/web/`)

| Command | Purpose |
|---|---|
| `ddev octane-reload` | Reload Octane workers (picks up PHP code changes without restart) |
| `ddev octane-status` | Check if Octane server is running |
| `ddev seed-admin` | Seed the admin user |
| `ddev dusk` | Run Laravel Dusk browser tests |

### Supervisor management

```bash
# Start/stop/restart individual services
ddev exec supervisorctl start webextradaemons:octane
ddev exec supervisorctl stop webextradaemons:octane
ddev exec supervisorctl restart webextradaemons:octane

# Restart all managed services
ddev exec supervisorctl restart webextradaemons:*

# View process logs (all daemons log to /var/tmp/logpipe → stdout)
ddev logs

# View supervisor's own log
ddev exec cat /var/log/supervisor/supervisord.log
```

### Artisan management commands

```bash
# Octane
ddev exec php artisan octane:status      # Is Swoole running?
ddev exec php artisan octane:reload      # Graceful worker reload
ddev exec php artisan octane:stop        # Stop Swoole (supervisor will restart)

# Horizon
ddev exec php artisan horizon:status     # Is Horizon running?
ddev exec php artisan horizon:pause      # Pause job processing
ddev exec php artisan horizon:continue   # Resume job processing
ddev exec php artisan horizon:terminate  # Graceful shutdown

# Queue
ddev exec php artisan queue:failed       # List failed jobs
ddev exec php artisan queue:retry all    # Retry all failed jobs
ddev exec php artisan queue:flush        # Delete all failed jobs

# Scheduler
ddev exec php artisan schedule:list      # List scheduled tasks
```
