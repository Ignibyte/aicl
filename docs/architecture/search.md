# Global Search

## Purpose

Cross-entity full-text search powered by Elasticsearch. Provides a unified search index, permission-filtered results, search analytics, and admin UI components. Disabled by default (`aicl.search.enabled = false`); when disabled, all search components gracefully degrade to no-ops.

## Dependencies

- **Elasticsearch 8.x** — full-text search engine (via `elastic/elasticsearch-php` client)
- **Redis** — queue backend for async indexing jobs
- **Spatie Permission** — role checks in `PermissionFilterBuilder`
- **Filament v4** — Search page, GlobalSearchWidget, nav search bar
- **Laravel Scout** — `HasSearchableFields` trait wraps Scout's `Searchable` for per-model indexing (separate from the unified global index)

## Architecture

### Component Diagram

```
┌─────────────────────────────────────────────────────────────────┐
│  Admin UI                                                       │
│  ┌──────────────┐  ┌───────────────────┐  ┌──────────────────┐  │
│  │ Search Page   │  │ GlobalSearchWidget│  │ Nav Search Bar   │  │
│  │ (Filament)    │  │ (Dashboard)       │  │ (Blade/Alpine)   │  │
│  └──────┬───────┘  └────────┬──────────┘  └────────┬─────────┘  │
│         │                   │                      │             │
│         └───────────────────┼──────────────────────┘             │
│                             ▼                                    │
│                      SearchService                               │
│                   ┌─────────┴──────────┐                         │
│                   ▼                    ▼                          │
│          PermissionFilterBuilder   ES Client                     │
│          (visibility rules)        (query)                       │
└─────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│  Indexing Pipeline                                              │
│                                                                 │
│  Model Event ──► SearchObserver ──► IndexSearchDocumentJob      │
│                                      (queued on 'search')       │
│                                           │                     │
│                                           ▼                     │
│                                   SearchIndexingService          │
│                                   ┌───────┴────────┐            │
│                                   ▼                ▼            │
│                          SearchDocumentBuilder   ES Client      │
│                          (build document)        (index/delete) │
└─────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│  Analytics                                                      │
│  SearchLog model ◄── Search Page (logs every query)             │
│  PruneSearchLogsCommand (scheduled cleanup)                     │
└─────────────────────────────────────────────────────────────────┘
```

### Core Classes

| Class | Namespace | Responsibility |
|-------|-----------|----------------|
| `SearchService` | `Aicl\Search` | Query orchestration — builds ES query, applies permission filters, parses results, provides policy safety-net |
| `SearchIndexingService` | `Aicl\Search` | Index lifecycle — create/delete/swap indices, index/delete/bulk-index documents |
| `SearchDocumentBuilder` | `Aicl\Search` | Builds ES document body from a model + entity config (title, body, owner_id, team_ids, etc.) |
| `PermissionFilterBuilder` | `Aicl\Search` | Translates visibility rules into ES bool filter clauses |
| `SearchResult` | `Aicl\Search` | Immutable value object for a single search hit |
| `SearchResultCollection` | `Aicl\Search` | Typed collection with facets, pagination, and total count |
| `SearchObserver` | `Aicl\Observers` | Dispatches `IndexSearchDocumentJob` on model create/update/delete/restore |
| `IndexSearchDocumentJob` | `Aicl\Jobs` | Queued job that indexes or deletes a single document (3 retries, 5s backoff) |
| `ReindexPermissionsJob` | `Aicl\Jobs` | Re-indexes all documents owned by a user (for role/permission changes) |
| `SearchLog` | `Aicl\Models` | Analytics model — tracks query, user, filter, result count, timestamp |
| `SearchServiceProvider` | `Aicl\Providers` | Registers ES Client, SearchService, SearchIndexingService as singletons; attaches SearchObserver to configured entities |

## Data Flow

### Search Query Flow

1. User types in the Search page or nav search bar
2. `Search` page (Livewire) calls `SearchService::search()` with query, user, optional entity type filter, page number
3. `SearchService` enforces minimum query length, loads entity configs from `aicl.search.entities`
4. `PermissionFilterBuilder::buildFilters()` generates ES `bool.filter` clauses based on each entity's `visibility` rule and the user's roles
5. `SearchService::buildSearchBody()` constructs the ES query:
   - `multi_match` on `title^3` and `body` fields with `fuzziness: AUTO`
   - Permission filters in `bool.filter`
   - Per-entity boost via `function_score`
   - Aggregation on `entity_type` for facet counts
6. ES response is parsed into `SearchResultCollection` with `SearchResult` value objects
7. `SearchService::applyPolicyFilter()` runs as safety-net for `policy` visibility entities (loads model, checks `$user->can('view', $model)`)
8. Search page logs the query to `SearchLog` if analytics are enabled
9. Results rendered in the Blade template with facet pills, pagination, and entity type icons

### Indexing Flow

1. A model registered in `aicl.search.entities` is created/updated/deleted
2. `SearchObserver` checks `aicl.search.enabled` and whether the model class has a config entry
3. `IndexSearchDocumentJob` is dispatched to the `search` queue with model class, ID, and action (`index` or `delete`)
4. The job resolves `SearchIndexingService` and either:
   - **Index:** Loads the model, builds a document via `SearchDocumentBuilder::build()`, calls `SearchIndexingService::index()`
   - **Delete:** Constructs a minimal model for document ID, calls `SearchIndexingService::delete()`
5. `SearchDocumentBuilder` resolves field values (handling enums, dates, arrays), owner ID, team IDs, and constructs the ES document
6. Document ID format: `{ClassName_with_underscores}_{primaryKey}` (e.g., `App_Models_User_abc-123`)

### Reindex Flow

1. `php artisan search:reindex` (optionally `--entity=User` or `--fresh`)
2. With `--fresh`: creates a new versioned index (`_v2`), swaps the alias, deletes the old index (zero-downtime)
3. Iterates each entity config, chunks records (200 per batch), bulk-indexes via `SearchIndexingService::bulkIndex()`

## Permission Filtering

`PermissionFilterBuilder` translates the `visibility` config value into ES query filters:

| Visibility | Behavior |
|------------|----------|
| `authenticated` | Any logged-in user sees results (default) |
| `policy` | All results returned from ES; `SearchService::applyPolicyFilter()` runs Laravel policy `view` check as safety-net |
| `role:{name}` | Only users with the specified role (or `super_admin`) see results |
| `owner` | Only the record owner sees results (matched via `owner_id` in ES document); `super_admin` sees all |
| `owner+admin` | Owner sees own records; `admin` or `super_admin` sees all |

The builder constructs a top-level `bool.should` with `minimum_should_match: 1`, where each entity type contributes one `should` clause. Entity types the user cannot access are excluded entirely (no clause added).

## Configuration Reference

All config keys live under `aicl.search.*` in `config/aicl.php`:

```php
'search' => [
    // Master switch — disabled by default
    'enabled' => env('AICL_SEARCH_ENABLED', false),

    // ES index alias name
    'index' => env('AICL_SEARCH_INDEX', 'aicl_global_search'),

    // Minimum characters before search executes
    'min_query_length' => 2,

    // Elasticsearch connection
    'elasticsearch' => [
        'host'     => env('ELASTICSEARCH_HOST', 'elasticsearch'),
        'port'     => (int) env('ELASTICSEARCH_PORT', 9200),
        'scheme'   => env('ELASTICSEARCH_SCHEME', 'http'),
        'api_key'  => env('ELASTICSEARCH_API_KEY'),
        'username' => env('ELASTICSEARCH_USERNAME'),
        'password' => env('ELASTICSEARCH_PASSWORD'),
    ],

    // Registered searchable entities (key = model FQCN)
    'entities' => [
        // App\Models\User::class => [
        //     'fields'      => ['name', 'email'],
        //     'label'       => 'name',
        //     'subtitle'    => 'email',
        //     'icon'        => 'heroicon-o-user',
        //     'visibility'  => 'authenticated',
        //     'boost'       => 1.5,
        //     'meta_fields' => [],
        // ],
    ],

    // Search analytics (query logging)
    'analytics' => [
        'enabled'        => env('AICL_SEARCH_ANALYTICS', true),
        'retention_days' => (int) env('AICL_SEARCH_RETENTION_DAYS', 90),
    ],
],
```

### Entity Config Keys

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `fields` | `string[]` | `[]` | Model attributes to index in the `body` field |
| `label` | `string` | First field | Attribute used as `title` in the ES document |
| `subtitle` | `string\|null` | Second field | Attribute used as subtitle in results |
| `icon` | `string` | `heroicon-o-document` | Heroicon for result display |
| `visibility` | `string` | `authenticated` | Permission rule (see Permission Filtering above) |
| `boost` | `float` | `1.0` | Relevance boost multiplier via `function_score` |
| `meta_fields` | `array` | `[]` | Additional metadata stored in ES (not indexed) |

## Search Analytics

`SearchLog` records every search query with:
- `query` — the search string
- `user_id` — who searched
- `entity_type_filter` — active type filter (if any)
- `results_count` — total hits
- `searched_at` — timestamp

**Pruning:** `php artisan search:prune-logs` deletes records older than `aicl.search.analytics.retention_days` (default 90). The model also implements `MassPrunable` for Laravel's built-in `model:prune` command.

## How to Make a Model Searchable

### 1. Implement the Searchable contract and use HasSearchableFields

```php
use Aicl\Contracts\Searchable;
use Aicl\Traits\HasSearchableFields;

class Project extends Model implements Searchable
{
    use HasSearchableFields;

    protected function searchableFields(): array
    {
        return ['name', 'description', 'status'];
    }
}
```

### 2. Register in config

In `config/aicl.php` (or `config/aicl-project.php` for project overlay):

```php
'search' => [
    'entities' => [
        \App\Models\Project::class => [
            'fields'     => ['name', 'description'],
            'label'      => 'name',
            'subtitle'   => 'description',
            'icon'       => 'heroicon-o-folder',
            'visibility' => 'authenticated',
            'boost'      => 1.2,
        ],
    ],
],
```

### 3. Enable search and reindex

```php
// config/local.php
'aicl.search.enabled' => true,
```

```bash
# Build the initial index
ddev exec php artisan search:reindex --fresh
```

### 4. Optional: Custom permission metadata

Override `getSearchPermissionMeta()` or implement `getOwnerIdForSearch()` / `getTeamIdsForSearch()` on the model for custom owner/team resolution.

## Admin UI Components

| Component | Type | Location | Description |
|-----------|------|----------|-------------|
| **Search Page** | Filament Page | `/admin/search` | Full-page search with debounced input, entity type facets, paginated results. Hidden from navigation (`$shouldRegisterNavigation = false`). |
| **GlobalSearchWidget** | Filament Widget | Dashboard | Compact search widget showing top 5 results. Only visible when search is enabled. |
| **Nav Search Bar** | Blade Component | Top navigation | Alpine.js search input with `Cmd+K` / `Ctrl+K` shortcut. Redirects to Search page on submit. Rendered via `PanelsRenderHook::GLOBAL_SEARCH_BEFORE`. |

## Artisan Commands

| Command | Description |
|---------|-------------|
| `search:reindex` | Rebuild the global search index. `--entity=User` for single entity, `--fresh` for zero-downtime recreate. |
| `search:prune-logs` | Delete search logs older than retention period. |

## ES Index Schema

The unified index (`aicl_global_search`) uses a versioned alias pattern (`_v1`, `_v2`) for zero-downtime reindexing.

| Field | ES Type | Notes |
|-------|---------|-------|
| `entity_type` | `keyword` | Model FQCN |
| `entity_id` | `keyword` | Primary key |
| `title` | `text` (+ `.keyword`) | Searchable, boosted 3x |
| `body` | `text` | Searchable, standard analyzer |
| `url` | `keyword` (not indexed) | Admin panel URL |
| `icon` | `keyword` (not indexed) | Heroicon name |
| `meta` | `object` (disabled) | Arbitrary metadata, not searchable |
| `owner_id` | `keyword` | For owner-based filtering |
| `required_permission` | `keyword` | Visibility rule |
| `team_ids` | `keyword` (multi-value) | For team-based filtering |
| `boost` | `float` | Stored boost value |
| `indexed_at` | `date` | Last index timestamp |

## Setup Guide

### 1. Prerequisites

- **Elasticsearch 8.x** must be running and accessible from the application container
- DDEV projects typically have Elasticsearch via the `ddev-elasticsearch` addon

### 2. Enable Search

Add to `config/local.php`:

```php
// config/local.php
'aicl.search.enabled' => true,
```

### 3. Update Project Config

**Important:** The project-level `config/aicl.php` does a shallow override of the package config. The `search` array in your project config **completely replaces** the package default. You must include all search keys:

```php
// config/aicl.php
'search' => [
    'enabled' => env('AICL_SEARCH_ENABLED', false),
    'index' => env('AICL_SEARCH_INDEX', 'aicl_global_search'),
    'min_query_length' => 2,
    'elasticsearch' => [
        'host' => env('ELASTICSEARCH_HOST', 'elasticsearch'),
        'port' => (int) env('ELASTICSEARCH_PORT', 9200),
        'scheme' => env('ELASTICSEARCH_SCHEME', 'http'),
    ],
    'entities' => [
        \App\Models\User::class => [
            'fields'     => ['name', 'email'],
            'label'      => 'name',
            'subtitle'   => 'email',
            'icon'       => 'heroicon-o-user',
            'visibility' => 'authenticated',
        ],
    ],
    'analytics' => [
        'enabled' => env('AICL_SEARCH_ANALYTICS', true),
        'retention_days' => (int) env('AICL_SEARCH_RETENTION_DAYS', 90),
    ],
],
```

### 4. Run the Migration

```bash
ddev exec php artisan migrate
```

This creates the `search_logs` table for analytics.

### 5. Create the Index and Populate

```bash
# Create the ES index and index all registered entities
ddev exec php artisan search:reindex --fresh

# Or index a single entity type
ddev exec php artisan search:reindex --entity=User
```

### 6. Reload Octane

```bash
ddev exec php artisan config:clear
ddev octane-reload
```

### 7. Verify

- The search bar should appear in the top navigation (replaces Filament's default)
- Navigate to `/admin/search` for the full-page search
- Type at least 2 characters to trigger a search

### Topbar Behavior

- **Search disabled** (`aicl.search.enabled = false`): No search bar in the topbar. Filament's built-in global search is also disabled.
- **Search enabled** (`aicl.search.enabled = true`): The custom AICL nav search bar appears with `Ctrl+K` / `Cmd+K` keyboard shortcut. It links to the full-page Search page.

### Gotcha: Project Config Override

Laravel's `mergeConfigFrom()` does a **shallow merge** — if your project `config/aicl.php` defines a `search` key, it completely replaces the package default. If you only have the `elasticsearch` sub-key in your project config, the `enabled`, `entities`, and `analytics` keys will be missing and search will silently fail to enable.

Always copy the full `search` section from the package config when publishing.

## Key Decisions

1. **Unified ES index** — All entity types share one index (`aicl_global_search`) rather than per-model indices. Simplifies cross-entity search and faceting.
2. **Permission filtering at query level** — `PermissionFilterBuilder` adds ES filter clauses so unauthorized results never leave Elasticsearch. Policy-based visibility adds a safety-net check on loaded models.
3. **Observer-based indexing** — `SearchObserver` dispatches queued jobs on model events. No manual indexing calls needed.
4. **Disabled by default** — `aicl.search.enabled` defaults to `false`, ensuring search infrastructure doesn't load unless explicitly enabled. All UI components check this flag. Filament's built-in global search is always disabled in favor of the AICL custom search bar.
5. **Alias-based zero-downtime reindexing** — `search:reindex --fresh` creates a new versioned index, swaps the alias, then deletes the old index.
6. **Graceful degradation** — If ES is unreachable or the index doesn't exist, search queries return empty results (logged as warnings). No 500 errors.

## Related

- `packages/aicl/src/Search/` — all search core classes
- `packages/aicl/src/Traits/HasSearchableFields.php` — Scout integration trait
- `packages/aicl/src/Contracts/Searchable.php` — searchable model contract
- `packages/aicl/config/aicl.php` — search configuration section
