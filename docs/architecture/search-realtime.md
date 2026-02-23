# Search & Real-time

**Version:** 1.0
**Last Updated:** 2026-02-06
**Owner:** `/architect`

---

## Overview

AICL provides two interconnected systems: full-text search via Laravel Scout and real-time updates via Laravel Reverb (WebSockets). Search enables users to find entities; real-time ensures dashboards stay current without manual refresh.

---

## Search Architecture

### Laravel Scout (Database Driver)

AICL uses Scout's database driver by default — no external search service required. This is sufficient for small-to-medium datasets. Elasticsearch can be swapped in later for larger deployments.

```php
// config/scout.php
'driver' => env('SCOUT_DRIVER', 'database'),
```

### HasSearchableFields Trait

**Location:** `packages/aicl/src/Traits/HasSearchableFields.php`

Wraps Scout's `Searchable` trait with AICL conventions:

```php
trait HasSearchableFields
{
    use Searchable;

    public function toSearchableArray(): array
    {
        return collect($this->getSearchableAttributes())
            ->mapWithKeys(fn ($attr) => [$attr => $this->getAttribute($attr)])
            ->toArray();
    }

    protected function getSearchableAttributes(): array
    {
        return ['name', 'title', 'description'];
    }
}
```

**Convention:** Models override `getSearchableAttributes()` to define which fields are indexed.

### Search Interfaces

| Interface | Location | Purpose |
|-----------|----------|---------|
| **Filament Global Search** | Top search bar | Searches across all registered resources |
| **GlobalSearchWidget** | Dashboard widget | Compact search with dropdown results |
| **Search Page** | `/admin/search` | Dedicated full-page search results |

### HasStandardScopes Search

Separate from Scout, the `HasStandardScopes` trait provides a `scopeSearch()` for database-level LIKE queries:

```php
// Quick database search (no index)
Project::search('website')->get();      // Scout search
Project::query()->search('website');     // Scope LIKE search
```

**Important:** Override `getSearchableColumns()` in each model to avoid querying non-existent columns (default is `['name', 'title']`).

---

## Real-time Architecture (Laravel Reverb)

### WebSocket Server

Laravel Reverb runs as a DDEV daemon on port 8080:

```yaml
# .ddev/config.yaml
web_extra_daemons:
  - name: reverb
    command: php /var/www/html/artisan reverb:start --host=0.0.0.0 --port=8080
```

### Broadcast Events

AICL entity events implement `ShouldBroadcast`:

```php
class EntityCreated implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public function __construct(public Model $entity) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('dashboard')];
    }

    public function broadcastAs(): string
    {
        return 'entity.created';
    }
}
```

**Broadcast channel:** `private-dashboard` — all authenticated users subscribe to this channel.

**Events broadcast:**
| Event | Broadcast Name | Payload |
|-------|---------------|---------|
| `EntityCreated` | `entity.created` | Entity type, ID, name |
| `EntityUpdated` | `entity.updated` | Entity type, ID, changed fields |
| `EntityDeleted` | `entity.deleted` | Entity type, ID |

**Note:** `EntityDeleted` must NOT use `SerializesModels` — the model may already be deleted when the event is broadcast.

### Frontend Echo Listeners

**Location:** `resources/js/echo.js`

```javascript
Echo.private('dashboard')
    .listen('.entity.created', (e) => {
        Livewire.dispatch('entity-changed', { type: 'created', entity: e });
    })
    .listen('.entity.updated', (e) => {
        Livewire.dispatch('entity-changed', { type: 'updated', entity: e });
    })
    .listen('.entity.deleted', (e) => {
        Livewire.dispatch('entity-changed', { type: 'deleted', entity: e });
    });
```

### Livewire Event Chain

```
WebSocket broadcast
    → Echo listener in browser
        → Livewire.dispatch('entity-changed')
            → All widgets/components with #[On('entity-changed')] refresh
```

**Components that listen:**
- `ProjectStatsOverview` widget (also polls every 60s)
- `ProjectsByStatusChart` widget (also polls every 60s)
- `UpcomingDeadlines` widget (also polls every 60s)
- `ActivityFeed` component (also polls every 30s)

### Feature Flag

WebSockets are optional and controlled by:

```env
AICL_WEBSOCKETS=true  # Enable Reverb
```

When disabled, components fall back to polling only.

---

## Configuration

```env
# Broadcasting
BROADCAST_CONNECTION=reverb

# Reverb — Server-side (internal container connection, plain HTTP)
REVERB_APP_ID=aicl
REVERB_APP_KEY=aicl-key
REVERB_APP_SECRET=aicl-secret
REVERB_HOST=localhost
REVERB_PORT=8080
REVERB_SCHEME=http

# Reverb — Browser-side (external through DDEV router, HTTPS)
# VITE_REVERB_HOST must be the DDEV hostname, NOT ${REVERB_HOST}
VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST=myproject.ddev.site
VITE_REVERB_PORT=8080
VITE_REVERB_SCHEME=https

# Scout
SCOUT_DRIVER=database
```

### Testing

```xml
<!-- phpunit.xml -->
<env name="BROADCAST_CONNECTION" value="log"/>
<env name="SCOUT_DRIVER" value="database"/>
```

Broadcasting is set to `log` in tests to prevent actual WebSocket connections. Scout uses the database driver, which works with `RefreshDatabase`.

---

## Related Documents

- [Entity System](entity-system.md) — Broadcastable entity events
- [Notifications](notifications.md) — Broadcast channel for notification bell
- [Queue, Jobs & Scheduler](queue-jobs-scheduler.md) — Broadcast queue processing
