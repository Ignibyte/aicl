# Redis Architecture

## Purpose

Redis is the central in-memory data store backing six subsystems in AICL: caching, sessions, queues, broadcasting, presence tracking, and AI stream coordination. Every web request touches Redis at least once (session read), and most touch it several times (cache lookups, queue dispatches, presence updates). Understanding how Redis is wired prevents the most common class of "works in tests, breaks in app" failures.

## Dependencies

- **Infrastructure:** DDEV `ddev-redis` addon (Redis 7, persistent volume)
- **PHP extension:** `phpredis` (compiled into the DDEV web container, faster than Predis)
- **Laravel subsystems:** `Illuminate\Cache`, `Illuminate\Session`, `Illuminate\Queue`, `Illuminate\Broadcasting`, `Illuminate\Support\Facades\RateLimiter`
- **AICL subsystems:** Horizon (queue dashboard), Reverb (WebSocket server), PresenceRegistry, AI streaming, ChannelRateLimiter

## DDEV Configuration

### Docker Compose

**File:** `.ddev/docker-compose.redis.yaml`

| Setting | Value |
|---|---|
| Image | `redis:7` |
| Container | `ddev-{DDEV_SITENAME}-redis` |
| Hostname | `redis` (reachable from the web container by name) |
| Port | `6379` (internal only, not exposed to host) |
| Config file | `.ddev/redis/redis.conf` |
| Volume | `redis:/data` (persistent across restarts) |

### Redis Server Configuration

**File:** `.ddev/redis/redis.conf`

```
maxmemory 2048mb
maxmemory-policy allkeys-lfu
```

The DDEV default enables persistence (RDB snapshots). Redis data survives `ddev restart` but not `ddev delete`. The eviction policy is `allkeys-lfu` (least-frequently-used), which evicts any key when memory is full. This is fine for development where cache and queue share the same instance. See the Production Considerations section for why this must change in production.

## Connection Architecture

**File:** `config/database.php` (section `redis`)

| Connection | Redis DB | Purpose | Prefix |
|---|---|---|---|
| `default` | 0 | Sessions, queues, locks, Horizon metadata, presence data, AI stream counters, rate limiting | `aicl_database_` |
| `cache` | 1 | Dedicated cache store | `aicl_database_` (connection-level) + `aicl_cache_` (cache-level) |
| `horizon` | 0 | Auto-configured by `Aicl\Horizon\Horizon::use()` at boot; clones the `default` connection and applies the Horizon prefix | `aicl_horizon:` |

### Client

All connections use the `phpredis` client (the PHP C extension). This is significantly faster than Predis (pure PHP) and is the default in DDEV environments. The client is set via:

```php
// config/database.php
'client' => env('REDIS_CLIENT', 'phpredis'),
```

### Connection Details

Both `default` and `cache` connections point to the same Redis instance but use different database numbers for isolation:

```php
// default — DB 0
'default' => [
    'host' => env('REDIS_HOST', 'redis'),
    'port' => env('REDIS_PORT', '6379'),
    'database' => env('REDIS_DB', '0'),
],

// cache — DB 1
'cache' => [
    'host' => env('REDIS_HOST', 'redis'),
    'port' => env('REDIS_PORT', '6379'),
    'database' => env('REDIS_CACHE_DB', '1'),
],
```

The global prefix for all Redis connections is:

```php
'prefix' => Str::slug(env('APP_NAME', 'aicl'), '_') . '_database_'
// Resolves to: aicl_database_
```

## What Is Stored Where

### DB 0 (default connection)

| Data | Key Pattern | TTL | Owner |
|---|---|---|---|
| Sessions | `aicl_database_<session_id>` | 120 min (config) | `SessionManager` |
| Queue jobs | `aicl_database_queues:default` (list) | Until consumed | `RedisQueue` |
| Presence entries | `aicl_cache_presence:sessions:<id>` | session lifetime + 5 min | `PresenceRegistry` |
| Presence index | `aicl_cache_presence:session_index` | session lifetime + 5 min | `PresenceRegistry` |
| AI stream user auth | `aicl_cache_ai-stream:<streamId>:user` | 5 min | `AiAssistantController` |
| AI stream counters | `aicl_cache_ai-stream:user:<userId>:count` | 5 min | `AiAssistantController` / `AiChatService` |
| Rate limiter state | `aicl_database_notification_channel:<id>` | Varies by config | `ChannelRateLimiter` |
| Horizon supervisors | `aicl_horizon:supervisors` | Persistent | Horizon |
| Horizon job metrics | `aicl_horizon:job:*`, `aicl_horizon:queue:*` | Trimmed per config | Horizon |
| Horizon locks | `aicl_horizon:monitor:*`, `aicl_horizon:notification:*` | 60-300 sec | `Aicl\Horizon\Lock` |

**Note:** Presence data and AI stream keys are written through the Cache facade (which uses the `cache` connection on DB 1), but the cache lock connection falls back to `default` (DB 0). The actual presence/AI-stream cache entries live on DB 1.

### DB 1 (cache connection)

| Data | Key Pattern | TTL | Owner |
|---|---|---|---|
| Application cache | `aicl_database_aicl_cache_<key>` | Varies | `Cache` facade |
| Presence entries | `aicl_database_aicl_cache_presence:sessions:<id>` | session lifetime + 5 min | `PresenceRegistry` |
| AI stream data | `aicl_database_aicl_cache_ai-stream:*` | 5 min | AI streaming |
| Cache locks | `aicl_database_aicl_cache_lock:*` | Varies | `Cache::lock()` |

### Prefix Stacking

Redis keys have layered prefixes that can look confusing when inspecting raw keys:

1. **Connection prefix** (`aicl_database_`) -- applied by phpredis to ALL commands on that connection
2. **Cache prefix** (`aicl_cache_`) -- applied by Laravel's cache store on top of the connection prefix
3. **Horizon prefix** (`aicl_horizon:`) -- applied by Horizon instead of the connection prefix (Horizon creates its own connection)

So a cache key `foo` becomes `aicl_database_aicl_cache_foo` in Redis. A Horizon key `supervisors` becomes `aicl_horizon:supervisors` in Redis.

## Cache Configuration

**File:** `config/cache.php`

| Setting | Value |
|---|---|
| Default store | `redis` |
| Redis connection | `cache` (DB 1) |
| Lock connection | `default` (DB 0) |
| Cache prefix | `aicl_cache_` |

### Available Stores

| Store | Driver | Use Case |
|---|---|---|
| `redis` | `redis` | Default. Persistent across requests, shared across workers. |
| `octane` | `octane` | Swoole in-memory table. Ultra-fast, per-worker, lost on restart. Used by `SwooleCache` for hot data (permissions, notification badges, service health). |
| `array` | `array` | In-process only. Used in tests. |
| `database` | `database` | PostgreSQL-backed. Rarely used. |
| `file` | `file` | Filesystem. Rarely used. |

### Cache vs. Octane Store

Redis cache is the system of record. The Octane store (`SwooleCache`) is a per-worker L1 cache sitting in front of Redis. Data flows: Swoole Table -> Redis -> Database. The `SwooleCache` classes (`PermissionCacheManager`, `NotificationBadgeCacheManager`, `ServiceHealthCacheManager`) warm from Redis on worker boot and refresh periodically.

## Session Configuration

**File:** `config/session.php`

| Setting | Value |
|---|---|
| Driver | `redis` |
| Connection | `null` (uses the `default` Redis connection, DB 0) |
| Lifetime | 120 minutes |
| Cookie name | `aicl_session` |
| HTTP only | `true` |
| SameSite | `lax` |
| Secure | `null` (auto-detects HTTPS) |
| Encryption | `false` |

Sessions are stored directly on the `default` Redis connection (DB 0), not through the cache layer. This means session keys are prefixed with only `aicl_database_`, not the double-prefix that cache keys get.

**Why DB 0 for sessions?** The `session.connection` config defaults to `null`, which means Laravel uses the `default` Redis connection. This is intentional -- sessions should not be evicted by cache pressure, and DB 0 has no cache eviction policy applied at the application level.

## Queue Configuration

**File:** `config/queue.php`

| Setting | Value |
|---|---|
| Default connection | `redis` |
| Redis connection | `default` (DB 0) |
| Queue name | `default` |
| Retry after | 90 seconds |
| Block for | `null` (long polling disabled) |
| Failed job driver | `database-uuids` |
| Failed job table | `failed_jobs` (PostgreSQL) |
| Job batching | `job_batches` table (PostgreSQL) |

### Horizon

**File:** `packages/aicl/config/aicl-horizon.php`

Horizon manages queue workers. It runs as a DDEV daemon (`ddev exec supervisorctl status webextradaemons:horizon`).

| Setting | Value |
|---|---|
| Redis connection | `default` (cloned to `horizon` at boot) |
| Prefix | `aicl_horizon:` |
| Memory limit | 64 MB (master), 128 MB (workers) |
| Max processes (local) | 3 |
| Max processes (production) | 10 |
| Balance strategy | `auto` (time-based) |
| Wait time threshold | 60 seconds on `redis:default` |
| Trim recent/pending/completed | 60 minutes |
| Trim failed | 10,080 minutes (7 days) |
| Feature flag | `config('aicl.features.horizon')` (default: `true`) |

Horizon auto-configures a dedicated `horizon` Redis connection by cloning `default` and setting its own prefix (`aicl_horizon:`). This happens in `Aicl\Horizon\Horizon::use()`.

## Broadcasting (Reverb)

**File:** `config/broadcasting.php`, `config/reverb.php`

The default broadcaster is `reverb` (Laravel Reverb). Reverb itself is a WebSocket server that runs as a DDEV daemon on port 8080. When scaling is enabled, Reverb uses Redis pub/sub for cross-server message distribution:

```php
// config/reverb.php -> servers.reverb.scaling.server
'host' => env('REDIS_HOST', 'redis'),
'port' => env('REDIS_PORT', '6379'),
'database' => env('REDIS_DB', '0'),
```

Scaling is disabled by default in development (`REVERB_SCALING_ENABLED=false`). In production with multiple Reverb instances, enable scaling to use Redis as the pub/sub backbone.

## Presence Tracking

**File:** `packages/aicl/src/Services/PresenceRegistry.php`

The `PresenceRegistry` stores per-session metadata in the cache (DB 1) with keys like `presence:sessions:<session_id>`. It maintains a cache-based index (`presence:session_index`) to enable enumeration of all active sessions without a database query.

The `TrackPresenceMiddleware` calls `PresenceRegistry::touch()` on every authenticated request, updating the user's last-seen timestamp, IP, and user agent.

**TTL:** Session lifetime + 5 minutes (300 seconds buffer). Default: `120 * 60 + 300 = 7500 seconds`.

## AI Stream Authorization

**Files:** `packages/aicl/src/AI/AiAssistantController.php`, `packages/aicl/src/AI/AiChatService.php`

AI streaming uses cache-based authorization and concurrency limiting:

1. **Stream authorization:** When a user starts a stream, a cache key `ai-stream:<streamId>:user` is set to the user ID with a 5-minute TTL. The WebSocket channel auth callback reads this key to verify the user owns the stream.

2. **Concurrent stream limiting:** A counter `ai-stream:user:<userId>:count` tracks active streams per user. The controller uses atomic `Cache::add()` + `Cache::increment()` to prevent TOCTOU races under Swoole. Default max concurrent streams: 2 (configurable via `aicl.ai.streaming.max_concurrent_per_user`).

**Critical gotcha:** Redis returns integers as strings via phpredis. Always cast with `(int)` when doing `===` comparisons:

```php
// WRONG -- will fail because Cache::get() returns "1" (string)
if (Cache::get($key) === 1) { ... }

// CORRECT
if ((int) Cache::get($key) === 1) { ... }
```

## Rate Limiting

**File:** `packages/aicl/src/Notifications/ChannelRateLimiter.php`

Laravel's `RateLimiter` facade uses the `default` Redis connection (DB 0) for atomic rate limiting. AICL uses this for notification channel delivery throttling:

```php
RateLimiter::attempt(
    "notification_channel:{$channel->id}",
    $maxAttempts,
    fn () => true,
    $decaySeconds
);
```

Rate limit state is stored as Redis keys with TTL matching the decay period. Supports period formats: `30s`, `1m`, `5m`, `1h`.

## Locks

Locks are used in two contexts:

### 1. Cache Locks (Laravel)
The `redis` cache store uses the `default` connection (DB 0) for locks via `lock_connection`:

```php
// config/cache.php
'redis' => [
    'driver' => 'redis',
    'connection' => 'cache',           // DB 1 for data
    'lock_connection' => 'default',    // DB 0 for locks
],
```

### 2. Horizon Locks
`Aicl\Horizon\Lock` uses the dedicated `horizon` Redis connection with `SETNX`-based locking:
- **Notification dedup:** `notification:<signature>` (300 sec TTL) -- prevents duplicate Horizon notifications
- **Wait time monitor:** `monitor:time-to-clear` (60 sec TTL) -- ensures only one supervisor checks queue wait times

## Key Decisions

1. **Two Redis databases, not two Redis instances** -- In development, DB 0 and DB 1 on the same Redis instance provide logical isolation with zero overhead. Cache eviction on DB 1 cannot affect session data on DB 0.

2. **phpredis over Predis** -- The C extension is 3-5x faster for typical operations. DDEV includes it by default.

3. **allkeys-lfu eviction in dev** -- Development uses a single eviction policy across all DBs. This is acceptable because dev data is disposable. Production must split cache and queue into separate instances (see Production Considerations).

4. **Cache-based presence over database** -- `PresenceRegistry` uses Redis cache instead of a database table. This avoids write amplification (every request updates last-seen) and naturally expires stale entries via TTL.

5. **Atomic stream counting** -- AI stream concurrency uses `Cache::add()` + `Cache::increment()` instead of `Cache::get()` + `Cache::put()` to prevent race conditions under Swoole's concurrent request handling.

## Common Issues and Troubleshooting

### Redis returns integers as strings

**Symptom:** `===` comparisons with integers always fail.

**Cause:** phpredis returns all values as strings. `Cache::get('counter')` returns `"1"` not `1`.

**Fix:** Always cast: `(int) Cache::get($key)`.

**Where this bites:** AI stream channel authorization, any cache counter logic.

### Connection refused

**Symptom:** `ConnectionException: Connection refused [tcp://redis:6379]`

**Diagnosis:**
```bash
ddev describe               # Check Redis container status
ddev logs -s redis          # Check Redis logs
ddev restart                # Restart all containers
```

**Fix:** If Redis container shows as stopped, `ddev restart` usually resolves it. If persistent, check `.ddev/docker-compose.redis.yaml` for syntax errors.

### Stale cache after code changes

**Symptom:** Config or view changes not reflected, old data appearing.

**Fix:**
```bash
ddev exec php artisan cache:clear       # Clear Redis cache (DB 1)
ddev exec php artisan optimize:clear    # Clear all caches (config, routes, views, events)
ddev octane-reload                      # Reload Swoole workers (picks up new code)
```

**Note:** Under Octane, the Swoole in-memory cache (`SwooleCache`) also needs clearing. `octane-reload` handles this by restarting workers.

### Queue jobs not processing

**Symptom:** Jobs dispatched but never executed. `jobs` table (if using database driver) or Redis queue growing.

**Diagnosis:**
```bash
ddev exec supervisorctl status webextradaemons:horizon    # Check Horizon daemon
ddev exec php artisan aicl:horizon:status                  # Horizon status (if available)
ddev exec php artisan queue:failed                         # List failed jobs
```

**Fix:**
```bash
# Restart Horizon
ddev exec supervisorctl restart webextradaemons:horizon

# If Horizon won't start, check logs
ddev exec supervisorctl tail -f webextradaemons:horizon
```

### Session issues (logged out unexpectedly, sessions not persisting)

**Symptom:** Users logged out on every request, or sessions lost after `ddev restart`.

**Diagnosis:**
1. Check Redis is running: `ddev describe`
2. Check session config: `config('session.driver')` should be `redis`
3. Check cookie domain: must match the request domain
4. Check Redis has session data: `ddev exec redis-cli -n 0 KEYS '*session*'`

**Fix:** Usually a Redis container restart issue. `ddev restart` resolves most cases.

### Key collision between projects

**Symptom:** Data from another DDEV project appearing, or cache clears affecting another project.

**Cause:** Multiple DDEV projects sharing the same Redis instance with identical prefixes.

**Fix:** Each DDEV project gets its own Redis container (`ddev-{SITENAME}-redis`). Key collisions only happen if you manually point multiple projects at the same Redis. The `APP_NAME`-based prefix (`aicl_database_`, `aicl_cache_`) provides additional namespace isolation.

### Horizon stuck in "paused" state

**Symptom:** Jobs queue but don't process. Horizon dashboard shows supervisors as paused.

**Fix:**
```bash
ddev exec php artisan aicl:horizon:continue    # Unpause
# Or restart entirely:
ddev exec supervisorctl restart webextradaemons:horizon
```

## Commands

### Redis CLI

```bash
ddev exec redis-cli                        # Interactive Redis shell
ddev exec redis-cli -n 0 KEYS '*'          # List all keys in DB 0 (dev only!)
ddev exec redis-cli -n 1 KEYS '*'          # List all keys in DB 1 (cache)
ddev exec redis-cli -n 0 TTL <key>         # Check TTL of a key
ddev exec redis-cli -n 0 TYPE <key>        # Check type of a key
ddev exec redis-cli -n 0 GET <key>         # Get string value
ddev exec redis-cli -n 0 FLUSHDB           # Clear DB 0 (sessions, queues!)
ddev exec redis-cli -n 1 FLUSHDB           # Clear DB 1 (cache)
ddev exec redis-cli FLUSHALL               # Clear ALL databases (nuclear option)
ddev exec redis-cli INFO memory            # Memory usage stats
ddev exec redis-cli DBSIZE                 # Key count in current DB
```

### Laravel Artisan

```bash
ddev exec php artisan cache:clear          # Clear the default cache store (Redis DB 1)
ddev exec php artisan optimize:clear       # Clear config, route, view, and event caches
ddev exec php artisan queue:restart        # Signal queue workers to restart after next job
ddev exec php artisan queue:failed         # List failed jobs
ddev exec php artisan queue:retry all      # Retry all failed jobs
ddev exec php artisan queue:retry <uuid>   # Retry a specific failed job
ddev exec php artisan queue:flush          # Delete all failed jobs
ddev exec php artisan queue:clear          # Clear all jobs from a queue
```

### DDEV / Supervisor

```bash
ddev describe                              # Show all container statuses including Redis
ddev logs -s redis                         # Redis container logs
ddev exec supervisorctl status             # Status of all daemons (Octane, Horizon, Reverb)
ddev exec supervisorctl restart webextradaemons:horizon   # Restart Horizon
ddev exec supervisorctl restart webextradaemons:reverb    # Restart Reverb
ddev exec supervisorctl tail -f webextradaemons:horizon   # Follow Horizon logs
```

## Production Considerations

### Separate Redis instances

In production, use separate Redis instances (or at minimum separate clusters) for cache and queue:

| Instance | Purpose | Eviction Policy | Persistence |
|---|---|---|---|
| Cache Redis | Cache store, rate limiting | `allkeys-lru` or `allkeys-lfu` | Optional (RDB snapshots for warm restarts) |
| Queue Redis | Queues, sessions, Horizon, locks | `noeviction` | Required (RDB + AOF) |
| Broadcast Redis | Reverb pub/sub scaling | `noeviction` | Not needed |

**Why:** Cache uses eviction to stay within memory bounds. Queue jobs and sessions must NEVER be evicted -- a `noeviction` policy will return errors instead of silently dropping data. Mixing them on one instance means either cache fills up and blocks queues, or eviction drops queue jobs.

### Memory limits

Set `maxmemory` on each instance:
- Cache: Size based on your working set (start with 512MB, monitor with `INFO memory`)
- Queue: Size based on peak queue depth (start with 256MB, Horizon metrics tell you peak)

### Persistence

- **Queue Redis:** Enable both RDB snapshots (`save 60 10000`) and AOF (`appendonly yes`, `appendfsync everysec`). Jobs must survive Redis restarts.
- **Cache Redis:** RDB snapshots are sufficient for warm restarts. AOF is optional.
- **Session Redis:** Same as Queue -- sessions should survive restarts.

### High availability

- **Redis Sentinel:** Provides automatic failover with master/replica topology. Configure Laravel's `redis.client` to `phpredis` and use Sentinel-aware connection config.
- **Redis Cluster:** For horizontal scaling. Requires `REDIS_CLUSTER=redis` in config. Note: Lua scripts (used by Horizon) must use `{hash_tags}` to ensure related keys land on the same shard.

### TLS

Enable TLS for Redis connections in production:

```php
// config/local.php (production)
return [
    'database.redis.default.scheme' => 'tls',
    'database.redis.cache.scheme' => 'tls',
];
```

### Connection pooling under Swoole

Octane with Swoole reuses Redis connections across requests. The phpredis extension handles connection pooling natively. Ensure `timeout` is set high enough to prevent connections from being closed during idle periods. The Reverb config defaults to 60 seconds (`REDIS_TIMEOUT=60`).

## API Surface

### Config Keys

| Key | Default | Description |
|---|---|---|
| `database.redis.client` | `phpredis` | Redis client library |
| `database.redis.options.prefix` | `aicl_database_` | Global Redis key prefix |
| `database.redis.default.database` | `0` | Default connection DB number |
| `database.redis.cache.database` | `1` | Cache connection DB number |
| `cache.default` | `redis` | Default cache store |
| `cache.prefix` | `aicl_cache_` | Cache key prefix |
| `cache.stores.redis.connection` | `cache` | Redis connection for cache store |
| `cache.stores.redis.lock_connection` | `default` | Redis connection for cache locks |
| `session.driver` | `redis` | Session storage driver |
| `session.lifetime` | `120` | Session lifetime in minutes |
| `session.cookie` | `aicl_session` | Session cookie name |
| `queue.default` | `redis` | Default queue connection |
| `queue.connections.redis.connection` | `default` | Redis connection for queues |
| `aicl-horizon.use` | `default` | Base Redis connection for Horizon |
| `aicl-horizon.prefix` | `aicl_horizon:` | Horizon key prefix |
| `aicl.features.horizon` | `true` | Horizon feature flag |
| `aicl.features.websockets` | `true` | Reverb/WebSocket feature flag |
| `aicl.ai.streaming.max_concurrent_per_user` | `2` | Max concurrent AI streams per user |

### Services

| Service | Class | Redis Usage |
|---|---|---|
| `PresenceRegistry` | `Aicl\Services\PresenceRegistry` | Cache (DB 1) for session data, index |
| `ChannelRateLimiter` | `Aicl\Notifications\ChannelRateLimiter` | RateLimiter (DB 0) for delivery throttling |
| `Horizon Lock` | `Aicl\Horizon\Lock` | Direct Redis (horizon connection) for SETNX locks |
| `SwooleCache` | `Aicl\Swoole\SwooleCache` | Octane store (in-memory), backed by Redis cache |

## Related

- `config/database.php` -- Redis connection definitions
- `config/cache.php` -- Cache store configuration
- `config/session.php` -- Session driver and cookie settings
- `config/queue.php` -- Queue connection and failed job settings
- `config/broadcasting.php` -- Reverb broadcaster config
- `config/reverb.php` -- Reverb server and scaling config
- `packages/aicl/config/aicl-horizon.php` -- Horizon worker, prefix, and trim settings
- `packages/aicl/src/Services/PresenceRegistry.php` -- Cache-based presence tracking
- `packages/aicl/src/Notifications/ChannelRateLimiter.php` -- Rate-limited notification delivery
- `packages/aicl/src/AI/AiAssistantController.php` -- AI stream authorization via cache
- `packages/aicl/src/Horizon/Lock.php` -- Redis SETNX lock for Horizon
- `packages/aicl/src/Horizon/Horizon.php` -- Horizon connection bootstrapping
- `.ddev/docker-compose.redis.yaml` -- DDEV Redis container definition
- `.ddev/redis/redis.conf` -- Redis server settings (memory, eviction)
