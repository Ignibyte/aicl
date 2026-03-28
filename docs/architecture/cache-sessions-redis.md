# Cache, Sessions & Redis

**Version:** 1.0
**Last Updated:** 2026-02-06
**Owner:** `/pipeline-implement`

---

## Overview

Redis is the backbone of AICL's caching, session management, and queue processing. Each concern gets its own Redis database to prevent interference between systems.

---

## Redis Database Mapping

| Database | Purpose | Config Key | Why Separate |
|----------|---------|------------|-------------|
| 0 | **Cache** | `REDIS_CACHE_DB` | `cache:clear` only wipes DB 0, not sessions or queues |
| 1 | **Sessions** | `REDIS_SESSION_DB` | Session data persists independently of cache lifecycle |
| 2 | **Queues** | `REDIS_QUEUE_DB` | Queue data is never accidentally flushed |

```php
// config/database.php
'redis' => [
    'client' => env('REDIS_CLIENT', 'phpredis'),

    'default' => [
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'password' => env('REDIS_PASSWORD'),
        'port' => env('REDIS_PORT', '6379'),
        'database' => env('REDIS_DB', '0'),
    ],

    'cache' => [
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'password' => env('REDIS_PASSWORD'),
        'port' => env('REDIS_PORT', '6379'),
        'database' => env('REDIS_CACHE_DB', '0'),
    ],

    'sessions' => [
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'password' => env('REDIS_PASSWORD'),
        'port' => env('REDIS_PORT', '6379'),
        'database' => env('REDIS_SESSION_DB', '1'),
    ],

    'queue' => [
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'password' => env('REDIS_PASSWORD'),
        'port' => env('REDIS_PORT', '6379'),
        'database' => env('REDIS_QUEUE_DB', '2'),
    ],
],
```

---

## Cache Strategy

**Driver:** Redis (database 0)
**Prefix:** `aicl_cache_`

### What AICL Caches

| Data | TTL | Strategy |
|------|-----|----------|
| Permission lookups | Until permission change | Spatie Permission handles invalidation |
| Config values | Until settings change | Invalidated on ManageSettings save |
| Filament assets | Long-lived | Published assets, cache-busted by version |

### Cache Usage in Code

```php
// Always use config(), never env() outside config files
$ttl = config('cache.ttl', 3600);

// Cache with tags for selective invalidation
Cache::tags(['projects'])->remember('project_count', $ttl, function () {
    return Project::count();
});

// Invalidate
Cache::tags(['projects'])->flush();
```

### Cache Commands

```bash
# Clear all cache (Redis DB 0 only)
php artisan cache:clear

# Clear config cache
php artisan config:clear

# Clear route cache
php artisan route:clear

# Clear all caches
php artisan optimize:clear
```

---

## Session Management

**Driver:** Redis (database 1)
**Lifetime:** 120 minutes (configurable via `SESSION_LIFETIME`)

### Why Redis for Sessions

- **Octane compatibility** — File sessions don't work reliably with Swoole's long-running workers
- **Performance** — Sub-millisecond reads for session data
- **Scalability** — Supports multiple Octane workers without file locking

### Configuration

```php
// config/session.php
'driver' => env('SESSION_DRIVER', 'redis'),
'connection' => 'sessions',  // Uses Redis DB 1
'lifetime' => env('SESSION_LIFETIME', 120),
'expire_on_close' => false,
```

### Octane Session Considerations

With Laravel Octane, the session is resolved per-request but the Redis connection persists. This is handled automatically by Octane's request lifecycle, but be aware:

- Don't store large objects in sessions — Redis serializes everything
- Session flash data works normally
- `Auth::login()` and `Auth::logout()` work as expected

---

## Spatie Settings (Key-Value Store)

AICL uses `spatie/laravel-settings` for application settings that persist to the database (not Redis). Settings are managed through the Filament admin panel.

### Settings Classes

| Class | Purpose | Fields |
|-------|---------|--------|
| `GeneralSettings` | Site name, timezone, date format, items per page | `site_name`, `timezone`, `date_format`, `items_per_page` |
| `MailSettings` | Mail driver configuration | `mail_driver`, `mail_host`, `mail_port`, `mail_from_address`, `mail_from_name` |
| `FeatureSettings` | Feature flags | `require_mfa`, `enable_social_login`, `enable_saml`, `enable_api`, `enable_websockets` |

### Settings in Code

```php
// Read a setting
$siteName = app(GeneralSettings::class)->site_name;

// In Blade
{{ app(\Aicl\Settings\GeneralSettings::class)->site_name }}
```

### Testing with Settings

Settings require the `settings` table migration and seeder. `RefreshDatabase` wipes the settings table, so tests must seed settings in `setUp()`:

```php
protected function setUp(): void
{
    parent::setUp();
    $this->seed(SettingsSeeder::class);
}
```

---

## Environment Variables

```env
# Redis
REDIS_HOST=redis
REDIS_PASSWORD=null
REDIS_PORT=6379

# Cache
CACHE_STORE=redis
REDIS_CACHE_DB=0

# Sessions
SESSION_DRIVER=redis
SESSION_LIFETIME=120
REDIS_SESSION_DB=1

# Queues
QUEUE_CONNECTION=redis
REDIS_QUEUE_DB=2
```

---

## Related Documents

- [Queue, Jobs & Scheduler](queue-jobs-scheduler.md) — Queue processing details
- [Auth & RBAC](auth-rbac.md) — Session auth, permission caching
- [Notifications](notifications.md) — Broadcast via Redis
