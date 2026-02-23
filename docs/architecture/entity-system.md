# AICL Entity System Architecture

**Version:** 1.1
**Last Updated:** 2026-02-06
**Owner:** `/architect`, `/rlm`

---

## Overview

The AICL entity system provides the foundation for all AI-generated models. It consists of:
- **Contracts (Interfaces):** Define what an entity can do
- **Traits:** Provide reusable implementation
- **Events:** Enable lifecycle hooks and extensions
- **Observers:** Handle cross-cutting concerns
- **Policies:** Manage authorization

This system follows the "extension, not modification" philosophy — client code extends via events and observers, never by modifying package code.

---

## Design Philosophy

### Drupal → Laravel Pattern Mapping

| Drupal Pattern | Laravel Equivalent |
|----------------|-------------------|
| `hook_entity_presave()` | Model Observers (`creating`, `updating`) |
| `hook_entity_insert/update/delete()` | Model Observers (`created`, `updated`, `deleted`) |
| `hook_form_alter()` | Filament Resource subclassing |
| Event subscribers | Laravel Event/Listener system |
| Plugin system | Service Container + Strategy pattern |
| `.info.yml` module definition | `composer.json` + Service Provider |

### Key Principles

1. **Composition over Inheritance** — Models use traits, not base classes
2. **Explicit Contracts** — Interfaces define capabilities; traits implement them
3. **Event-Driven Extension** — Lifecycle events allow hooking without modification
4. **Standard Laravel** — Generated code looks hand-written, works with all tooling

---

## Contracts (Interfaces)

Location: `packages/aicl/src/Contracts/`

### HasEntityLifecycle

```php
namespace Aicl\Contracts;

/**
 * Marker interface indicating the model dispatches entity lifecycle events.
 * Implemented by the HasEntityEvents trait.
 */
interface HasEntityLifecycle
{
    // No methods — marker interface
}
```

**Purpose:** Signals that a model fires `EntityCreating`, `EntityCreated`, etc. events.

**When to Use:** Always — every entity should implement this.

---

### Auditable

```php
namespace Aicl\Contracts;

/**
 * Model records changes to activity log.
 * Implemented by the HasAuditTrail trait.
 */
interface Auditable
{
    // No methods — marker interface
    // Trait provides getActivitylogOptions()
}
```

**Purpose:** Indicates the model logs changes to `spatie/laravel-activitylog`.

**When to Use:** Always — audit trail is a core requirement.

---

### Stateful

```php
namespace Aicl\Contracts;

/**
 * Model has a state machine for lifecycle management.
 * Uses spatie/laravel-model-states.
 */
interface Stateful
{
    // No methods — marker interface
    // Model defines $casts with state class
}
```

**Purpose:** Indicates the model has a status field with state machine transitions.

**When to Use:** When entity has a lifecycle (draft → active → completed → archived).

---

### Searchable

```php
namespace Aicl\Contracts;

/**
 * Model is indexed for full-text search.
 * Implemented by the HasSearchableFields trait.
 */
interface Searchable
{
    public function toSearchableArray(): array;
}
```

**Purpose:** Enables Laravel Scout integration for full-text search.

**When to Use:** When entity needs to be searchable by users.

---

### Taggable

```php
namespace Aicl\Contracts;

/**
 * Model supports tagging.
 * Implemented by the HasTagging trait.
 */
interface Taggable
{
    // Uses spatie/laravel-tags HasTags trait
}
```

**Purpose:** Enables polymorphic tagging with `spatie/laravel-tags`.

**When to Use:** When entity needs categorization, labels, or free-form classification.

---

## Traits

Location: `packages/aicl/src/Traits/`

### HasEntityEvents

Dispatches typed events at model lifecycle points.

```php
namespace Aicl\Traits;

use Aicl\Events\EntityCreating;
use Aicl\Events\EntityCreated;
use Aicl\Events\EntityUpdating;
use Aicl\Events\EntityUpdated;
use Aicl\Events\EntityDeleting;
use Aicl\Events\EntityDeleted;

trait HasEntityEvents
{
    public static function bootHasEntityEvents(): void
    {
        static::creating(fn ($model) => event(new EntityCreating($model)));
        static::created(fn ($model) => event(new EntityCreated($model)));
        static::updating(fn ($model) => event(new EntityUpdating($model)));
        static::updated(fn ($model) => event(new EntityUpdated($model)));
        static::deleting(fn ($model) => event(new EntityDeleting($model)));
        static::deleted(fn ($model) => event(new EntityDeleted($model)));
    }
}
```

**Usage:** Always include. Enables typed event listeners for any model.

**Extension Point:** Register listeners for `EntityCreated`, etc. in `EventServiceProvider`.

---

### HasAuditTrail

Wraps `spatie/laravel-activitylog` with sensible defaults.

```php
namespace Aicl\Traits;

use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

trait HasAuditTrail
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(
                fn (string $eventName) => "{$eventName} " . class_basename($this)
            );
    }
}
```

**Usage:** Always include. Provides automatic audit logging.

**Customization:** Override `getActivitylogOptions()` for custom logging behavior.

---

### HasStandardScopes

Common query scopes every entity needs.

```php
namespace Aicl\Traits;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

trait HasStandardScopes
{
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeInactive(Builder $query): Builder
    {
        return $query->where('is_active', false);
    }

    public function scopeRecent(Builder $query, int $days = 30): Builder
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    public function scopeByUser(Builder $query, int|User $user): Builder
    {
        $userId = $user instanceof User ? $user->id : $user;
        return $query->where('user_id', $userId);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }
        return $query->where(function (Builder $q) use ($term) {
            foreach ($this->getSearchableColumns() as $column) {
                $q->orWhere($column, 'like', "%{$term}%");
            }
        });
    }

    protected function getSearchableColumns(): array
    {
        return ['name', 'title'];
    }
}
```

**Usage:** Always include. Provides consistent filtering across entities.

**Customization:** Override `getSearchableColumns()` for entity-specific search fields.

---

### HasSearchableFields

Wraps `laravel/scout` with a standard pattern.

```php
namespace Aicl\Traits;

use Laravel\Scout\Searchable;

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

**Usage:** Include when entity needs full-text search indexing.

**Customization:** Override `getSearchableAttributes()` for entity-specific fields.

---

### HasTagging

Wraps `spatie/laravel-tags`.

```php
namespace Aicl\Traits;

use Spatie\Tags\HasTags;

trait HasTagging
{
    use HasTags;
}
```

**Usage:** Include when entity needs tagging/categorization.

---

### HasSocialAccounts

For User model — manages social provider links.

```php
namespace Aicl\Traits;

use Aicl\Models\SocialAccount;
use Illuminate\Database\Eloquent\Relations\HasMany;

trait HasSocialAccounts
{
    public function socialAccounts(): HasMany
    {
        return $this->hasMany(SocialAccount::class);
    }

    public function linkSocialAccount(string $provider, string $providerId, array $data = []): SocialAccount
    {
        return $this->socialAccounts()->updateOrCreate(
            ['provider' => $provider, 'provider_id' => $providerId],
            $data
        );
    }

    public function unlinkSocialAccount(string $provider): bool
    {
        return $this->socialAccounts()->where('provider', $provider)->delete() > 0;
    }

    public function hasSocialAccount(string $provider): bool
    {
        return $this->socialAccounts()->where('provider', $provider)->exists();
    }
}
```

**Usage:** Add to User model for social login support.

---

## Events

Location: `packages/aicl/src/Events/`

### Event Structure

All entity events follow the same pattern:

```php
namespace Aicl\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EntityCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Model $entity,
    ) {}
}
```

### Available Events

| Event | When Fired | Notes |
|-------|------------|-------|
| `EntityCreating` | Before model saved (create) | Can modify model |
| `EntityCreated` | After model saved (create) | Broadcastable |
| `EntityUpdating` | Before model saved (update) | Can modify model |
| `EntityUpdated` | After model saved (update) | Broadcastable |
| `EntityDeleting` | Before model deleted | Can prevent deletion |
| `EntityDeleted` | After model deleted | Broadcastable, no SerializesModels |

### Broadcast Support

Post-save events (`EntityCreated`, `EntityUpdated`, `EntityDeleted`) implement `ShouldBroadcast`:

```php
class EntityCreated implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public function __construct(public Model $entity) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('entity.' . class_basename($this->entity) . '.' . $this->entity->getKey()),
        ];
    }
}
```

**Note:** `EntityDeleted` must NOT use `SerializesModels` because the model may already be deleted.

### Listening to Events

```php
// In EventServiceProvider
protected $listen = [
    \Aicl\Events\EntityCreated::class => [
        \App\Listeners\NotifyOwnerOnEntityCreated::class,
    ],
];

// Or in a listener (always use NotificationDispatcher, not direct $user->notify)
class NotifyOwnerOnEntityCreated
{
    public function handle(EntityCreated $event): void
    {
        if ($event->entity instanceof Project) {
            app(NotificationDispatcher::class)->send(
                notifiable: $event->entity->owner,
                notification: new ProjectCreatedNotification($event->entity),
                sender: auth()->user()
            );
        }
    }
}
```

---

## Observers

Location: `packages/aicl/src/Observers/`

### BaseObserver

Abstract base for entity observers with activity logging.

```php
namespace Aicl\Observers;

use Illuminate\Database\Eloquent\Model;

abstract class BaseObserver
{
    protected function logActivity(Model $model, string $event): void
    {
        activity()
            ->performedOn($model)
            ->causedBy(auth()->user())
            ->log("{$event} " . class_basename($model));
    }
}
```

### Observer Pattern

> **IMPORTANT:** All observer notifications MUST use the `NotificationDispatcher` — never call `$user->notify()` directly. This ensures every notification is logged to the `notification_logs` table. See [Notifications](notifications.md) for the mandatory logging rule.

```php
namespace Aicl\Observers;

use App\Models\Project;
use Aicl\Notifications\ProjectAssignedNotification;
use Aicl\Notifications\ProjectStatusChangedNotification;
use Aicl\Services\NotificationDispatcher;

class ProjectObserver extends BaseObserver
{
    public function created(Project $project): void
    {
        $this->logActivity($project, 'created');

        // Notify owner via NotificationDispatcher (never direct $user->notify)
        if ($project->owner_id !== auth()->id()) {
            app(NotificationDispatcher::class)->send(
                notifiable: $project->owner,
                notification: new ProjectAssignedNotification($project),
                sender: auth()->user()
            );
        }
    }

    public function updated(Project $project): void
    {
        // Log state transitions
        if ($project->wasChanged('status')) {
            $this->logActivity($project, 'status changed to ' . $project->status->label());

            app(NotificationDispatcher::class)->send(
                notifiable: $project->owner,
                notification: new ProjectStatusChangedNotification($project),
                sender: auth()->user()
            );
        }
    }

    public function deleted(Project $project): void
    {
        $this->logActivity($project, 'deleted');
    }
}
```

### Registering Observers

In the service provider:

```php
public function boot(): void
{
    Project::observe(ProjectObserver::class);
}
```

---

## Policies

Location: `packages/aicl/src/Policies/`

### BasePolicy

Provides default permission checking via Spatie Permission.

```php
namespace Aicl\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

abstract class BasePolicy
{
    use HandlesAuthorization;

    protected string $permissionPrefix;

    public function viewAny(User $user): bool
    {
        return $user->can("ViewAny:{$this->permissionPrefix}");
    }

    public function create(User $user): bool
    {
        return $user->can("Create:{$this->permissionPrefix}");
    }

    public function restore(User $user, $model): bool
    {
        return $user->can("Restore:{$this->permissionPrefix}");
    }

    public function forceDelete(User $user, $model): bool
    {
        return $user->can("ForceDelete:{$this->permissionPrefix}");
    }
}
```

### Entity Policy Pattern

```php
namespace Aicl\Policies;

use App\Models\Project;
use App\Models\User;

class ProjectPolicy extends BasePolicy
{
    protected string $permissionPrefix = 'Project';

    public function view(User $user, Project $project): bool
    {
        // Owner always has access
        if ($project->owner_id === $user->id) {
            return true;
        }

        return $user->can("View:{$this->permissionPrefix}");
    }

    public function update(User $user, Project $project): bool
    {
        if ($project->owner_id === $user->id) {
            return true;
        }

        return $user->can("Update:{$this->permissionPrefix}");
    }

    public function delete(User $user, Project $project): bool
    {
        if ($project->owner_id === $user->id) {
            return true;
        }

        return $user->can("Delete:{$this->permissionPrefix}");
    }
}
```

### Permission Format

Filament Shield generates permissions in `Action:Resource` format:
- `ViewAny:Project`
- `View:Project`
- `Create:Project`
- `Update:Project`
- `Delete:Project`
- `Restore:Project`
- `ForceDelete:Project`

---

## State Machines

Location: `packages/aicl/src/States/`

Using `spatie/laravel-model-states`:

### State Base Class

```php
namespace Aicl\States;

use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

abstract class ProjectState extends State
{
    abstract public function label(): string;

    public static function config(): StateConfig
    {
        return parent::config()
            ->default(Draft::class)
            ->allowTransition(Draft::class, Active::class)
            ->allowTransition(Active::class, OnHold::class)
            ->allowTransition(Active::class, Completed::class)
            ->allowTransition(OnHold::class, Active::class)
            ->allowTransition(Completed::class, Archived::class);
    }
}
```

### State Classes

```php
namespace Aicl\States;

class Draft extends ProjectState
{
    public function label(): string
    {
        return 'Draft';
    }
}

class Active extends ProjectState
{
    public function label(): string
    {
        return 'Active';
    }
}

// ... OnHold, Completed, Archived
```

### State Transitions

```php
// In model casts
protected function casts(): array
{
    return [
        'status' => ProjectState::class,
    ];
}

// Usage
$project->status->transitionTo(Active::class);

// Check current state
if ($project->status instanceof Draft) {
    // ...
}

// Get label
$label = $project->status->label(); // "Active"
```

---

## Model Template

Complete model example following all patterns:

```php
<?php

namespace Aicl\Models;

use Aicl\Contracts\Auditable;
use Aicl\Contracts\HasEntityLifecycle;
use Aicl\Contracts\Searchable;
use Aicl\Contracts\Stateful;
use Aicl\Enums\ProjectPriority;
use Aicl\States\ProjectState;
use Aicl\Traits\HasAuditTrail;
use Aicl\Traits\HasEntityEvents;
use Aicl\Traits\HasSearchableFields;
use Aicl\Traits\HasStandardScopes;
use App\Models\User;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property ProjectState $status
 * @property ProjectPriority $priority
 * @property \Carbon\Carbon|null $start_date
 * @property \Carbon\Carbon|null $end_date
 * @property float|null $budget
 * @property int $owner_id
 * @property bool $is_active
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 * @property-read User $owner
 */
class Project extends Model implements Auditable, HasEntityLifecycle, Searchable, Stateful
{
    use HasFactory;
    use HasEntityEvents;
    use HasAuditTrail;
    use HasStandardScopes;
    use HasSearchableFields;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'status',
        'priority',
        'start_date',
        'end_date',
        'budget',
        'owner_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'status' => ProjectState::class,
            'priority' => ProjectPriority::class,
            'start_date' => 'date',
            'end_date' => 'date',
            'budget' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    protected static function newFactory(): ProjectFactory
    {
        return ProjectFactory::new();
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    protected function getSearchableColumns(): array
    {
        return ['name', 'description'];
    }

    protected function getSearchableAttributes(): array
    {
        return ['name', 'description'];
    }
}
```

---

## AI Generation Rules

When generating a new entity:

1. **Always include:**
   - `HasFactory`, `HasEntityEvents`, `HasAuditTrail`, `HasStandardScopes`, `SoftDeletes`
   - Implement `Auditable`, `HasEntityLifecycle`

2. **Conditionally include:**
   - `HasTagging` → Entity has categories/labels
   - `HasSearchableFields` → Entity is user-searchable
   - State machine → Entity has lifecycle states

3. **Required patterns:**
   - `$fillable` array (never `$guarded = []`)
   - `casts()` method (not `$casts` property)
   - `newFactory()` method for package models
   - Explicit return types on relationships
   - `owner()` BelongsTo relationship to User
   - PHPDoc `@property` annotations

---

## Related Documents

- [Foundation](foundation.md)
- [AI Generation Pipeline](ai-generation-pipeline.md)
- [Auth & RBAC](auth-rbac.md)
- [Filament UI](filament-ui.md)
- [Notifications](notifications.md) — Mandatory notification logging via NotificationDispatcher
- [Golden Entity Guide](../planning/rlm/golden-entity-guide.md)
