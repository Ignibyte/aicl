# Entity Registry

Auto-discover AICL entity models and run cross-entity queries.

**Namespace:** `Aicl\Services\EntityRegistry`
**Location:** `packages/aicl/src/Services/EntityRegistry.php`

## Overview

EntityRegistry scans `app/Models/` at runtime for classes that implement `HasEntityLifecycle`, caches the results, and provides cross-entity query methods. It enables features like global search, location-based filtering, and status dashboards that span all generated entities.

---

## Discovery

### How It Works

1. Recursively scans `app/Models/` for PHP files
2. Checks each class for `HasEntityLifecycle` contract (skips abstract classes)
3. Inspects database columns to determine available query methods
4. Caches results using Redis tagged cache (`aicl`, `entity-registry` tags)
5. Cache is automatically flushed on `aicl:make-entity` and `aicl:remove-entity`

### Discovery Result Shape

Each discovered entity includes:

```php
[
    'class' => 'App\Models\CableModem',      // FQCN
    'table' => 'cable_modems',                // Database table
    'label' => 'Cable Modem',                 // Human-readable (Str::headline)
    'base_class' => 'App\Models\Base\BaseNetworkDevice', // or null
    'columns' => [
        'has_name' => true,          // Can be searched
        'has_status' => true,        // Can be grouped by status
        'has_location_id' => false,  // Can be filtered by location
        'has_owner_id' => true,      // Has owner relationship
        'has_is_active' => true,     // Has active/inactive flag
    ],
]
```

---

## API Reference

### `allTypes(): Collection`

Returns all discovered entity types with metadata. Results are cached.

```php
use Aicl\Services\EntityRegistry;

$registry = app(EntityRegistry::class);
$types = $registry->allTypes();

foreach ($types as $entity) {
    echo "{$entity['label']} — {$entity['table']}";
}
```

---

### `search(string $term, int $limit = 10): Collection`

Search across all entities that have a `name` column. Uses `HasStandardScopes::search()` when available, falls back to case-insensitive `LIKE`.

Returns results grouped by entity label:

```php
$results = $registry->search('server');

// Collection keyed by entity label:
// 'Cable Modem' => Collection<CableModem>
// 'Router' => Collection<Router>

foreach ($results as $label => $models) {
    echo "{$label}: {$models->count()} results\n";
    foreach ($models as $model) {
        echo "  - {$model->name}\n";
    }
}
```

Entities without a `name` column are silently skipped.

---

### `atLocation(int $locationId): Collection`

Find all entities at a given location. Only queries entities with a `location_id` column.

```php
$entitiesAtHQ = $registry->atLocation($headquarters->id);

// 'Cable Modem' => Collection<CableModem>
// 'Switch' => Collection<Switch>
```

---

### `countsByStatus(): array`

Aggregate status counts across all entity types that have a `status` column.

```php
$counts = $registry->countsByStatus();

// [
//     'Cable Modem' => ['active' => 42, 'inactive' => 3, 'maintenance' => 1],
//     'Router' => ['active' => 15, 'decommissioned' => 2],
// ]
```

---

### `resolveType(string $morphClass): ?string`

Resolve a morph class string to its AICL entity FQCN. Useful for polymorphic relationship resolution.

```php
$class = $registry->resolveType('cable_modem');
// 'App\Models\CableModem' or null
```

---

### `isEntity(string $class): bool`

Check if a class is a registered AICL entity.

```php
if ($registry->isEntity(App\Models\CableModem::class)) {
    // It's an AICL-managed entity
}
```

---

### `flush(): void`

Clear the discovery cache. Called automatically by `aicl:make-entity` and `aicl:remove-entity`. Call manually if you add/remove entity models outside the scaffolder.

```php
EntityRegistry::flush();
```

---

## Column-Aware Query Safety

Cross-entity methods (`search`, `atLocation`, `countsByStatus`) check the `columns` metadata before querying. Entities missing the required column are silently skipped — no raw UNION queries, no runtime errors from missing columns.

| Method | Required Column |
|--------|----------------|
| `search()` | `name` |
| `atLocation()` | `location_id` |
| `countsByStatus()` | `status` |

---

## Cache Behavior

- **Store:** Redis tagged cache (`aicl`, `entity-registry` tags)
- **TTL:** Forever (no expiration — invalidated explicitly)
- **Invalidation:** `EntityRegistry::flush()` called by `MakeEntityCommand` and `RemoveEntityCommand`
- **Fallback:** If the cache store doesn't support tagging (e.g., `file` driver), uses a simple `Cache::rememberForever()` with a fixed key

---

## Files

| File | Purpose |
|------|---------|
| `packages/aicl/src/Services/EntityRegistry.php` | Main service class |
| `packages/aicl/src/Contracts/HasEntityLifecycle.php` | Marker interface for discovery |
| `packages/aicl/src/Console/Commands/MakeEntityCommand.php` | Calls `flush()` after scaffolding |
| `packages/aicl/src/Console/Commands/RemoveEntityCommand.php` | Calls `flush()` after removal |
| `packages/aicl/tests/Unit/Services/EntityRegistryTest.php` | Unit tests (22 tests) |
