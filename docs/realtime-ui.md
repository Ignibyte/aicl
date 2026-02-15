# Real-Time UI Base Classes

Base classes and traits for broadcasting, polling, channel authorization, and presence features in Filament/Livewire applications.

**Namespace:** `Aicl\Broadcasting`, `Aicl\Filament\Widgets`

## Overview

| Class | Purpose |
|-------|---------|
| `BaseBroadcastEvent` | Standard broadcast event with consistent payload shape |
| `PollingWidget` | Filament widget with auto-pause when tab is hidden |
| `ChannelAuth` | Entity-level and user-scoped channel authorization |
| `HasPresenceChannel` | Model trait for "who's viewing" presence channels |
| `PresenceIndicator` | Filament widget showing current viewers |

---

## BaseBroadcastEvent

A standard base class for broadcast events with a consistent envelope: `eventId`, `eventType`, `occurredAt`. Replaces duplicated broadcast logic across event classes.

### Creating a Broadcast Event

```php
use Aicl\Broadcasting\BaseBroadcastEvent;
use Illuminate\Database\Eloquent\Model;

class ProjectStatusChanged extends BaseBroadcastEvent
{
    public function __construct(
        private Project $project,
        private string $oldStatus,
        private string $newStatus,
    ) {
        parent::__construct();
    }

    public static function eventType(): string
    {
        return 'project.status_changed';
    }

    public function toPayload(): array
    {
        return [
            'project_id' => $this->project->id,
            'old_status' => $this->oldStatus,
            'new_status' => $this->newStatus,
        ];
    }

    public function getEntity(): ?Model
    {
        return $this->project;
    }
}
```

### What You Get Automatically

Every `BaseBroadcastEvent` includes:

| Property | Type | Description |
|----------|------|-------------|
| `eventId` | string | UUID, unique per event instance |
| `eventType` | string | From `static::eventType()` |
| `occurredAt` | string | ISO 8601 timestamp |

### Broadcasting Behavior

| Method | Behavior |
|--------|----------|
| `broadcastOn()` | `private-dashboard` + `private-{entity}s.{id}` (if entity set) |
| `broadcastAs()` | Returns `eventType` (e.g., `'project.status_changed'`) |
| `broadcastWith()` | Merges `toPayload()` with `eventId`, `eventType`, `occurredAt` |

### Dispatching

```php
// Standard Laravel event dispatch
ProjectStatusChanged::dispatch($project, 'draft', 'active');

// Or via event() helper
event(new ProjectStatusChanged($project, 'draft', 'active'));
```

### Relationship to Existing Events

`BaseBroadcastEvent` is for **new** broadcast events. The existing `EntityCreated`, `EntityUpdated`, `EntityDeleted` events continue to work unchanged. For domain events that need broadcasting, use the `BroadcastsDomainEvent` trait on `DomainEvent` subclasses instead.

---

## PollingWidget

A Filament widget base class with configurable polling and automatic pause when the browser tab is not visible (Page Visibility API).

### Why Not Livewire's `wire:poll`?

Livewire's built-in polling continues even when the browser tab is backgrounded, wasting server resources. `PollingWidget` uses Alpine.js `setInterval` + the Page Visibility API to pause polling when the tab is hidden and resume with an immediate catch-up when visible again.

### Creating a Polling Widget

```php
use Aicl\Filament\Widgets\PollingWidget;

class ActiveJobsWidget extends PollingWidget
{
    protected string $view = 'widgets.active-jobs';

    public array $jobs = [];

    public function pollingInterval(): int
    {
        return 15; // seconds (default: 60)
    }

    public function poll(): void
    {
        $this->jobs = Job::where('status', 'processing')
            ->latest()
            ->take(10)
            ->get()
            ->toArray();
    }
}
```

### Configuration

| Method | Default | Description |
|--------|---------|-------------|
| `pollingInterval()` | `60` | Polling interval in seconds |
| `pauseWhenHidden()` | `true` | Pause when browser tab is hidden |
| `poll()` | dispatches `'poll-tick'` | Called on each poll tick — override to refresh data |

### Behavior

- **Tab visible:** Polls every `pollingInterval()` seconds via `$wire.poll()`
- **Tab hidden:** Timer stopped, no server requests
- **Tab becomes visible:** Immediate `$wire.poll()` (catch up) + timer restarts
- **Shows "Paused" indicator** when tab is hidden (if `pauseWhenHidden` is true)

---

## ChannelAuth

Static helpers for authorizing users on broadcast channels. Integrates with Spatie Permission using Shield's `Action:Resource` format.

### Entity Channel Authorization

Authorize a user for an entity-specific private channel. Checks that the entity exists AND the user has the `ViewAny:{Entity}` permission:

```php
use Aicl\Broadcasting\ChannelAuth;
use App\Models\Project;

// In routes/channels.php
Broadcast::channel('projects.{id}', fn ($user, $id) =>
    ChannelAuth::entityChannel($user, Project::class, $id)
);
```

### User Channel Authorization

Authorize a user for their own private channel:

```php
Broadcast::channel('App.Models.User.{id}', fn ($user, $id) =>
    ChannelAuth::userChannel($user, $id)
);
```

Compares user keys as strings (handles int/string coercion).

### Presence Channel Authorization

Authorize a user for a presence channel and return user data for the presence list:

```php
Broadcast::channel('presence.projects.{id}', fn ($user, $id) =>
    ChannelAuth::presenceChannel($user, Project::class, $id)
);
```

Returns `['id' => ..., 'name' => ...]` on success, `false` on failure.

---

## HasPresenceChannel

A model trait that provides the presence channel name and permission for "who's viewing this record" functionality.

### Adding to a Model

```php
use Aicl\Broadcasting\Traits\HasPresenceChannel;

class Project extends Model
{
    use HasPresenceChannel;
}
```

### Methods

```php
$project = Project::find(1);

$project->presenceChannelName();
// "presence.projects.1"

$project->presencePermission();
// "ViewAny:Project"
```

### Registering the Channel

Use `ChannelAuth::presenceChannel()` in `routes/channels.php`:

```php
Broadcast::channel('presence.projects.{id}', fn ($user, $id) =>
    ChannelAuth::presenceChannel($user, Project::class, $id)
);
```

---

## PresenceIndicator

A Filament widget that shows who is currently viewing a record. Uses Laravel Echo's presence channels.

### Usage on a Resource Page

```php
use Aicl\Filament\Widgets\PresenceIndicator;

class ViewProject extends ViewRecord
{
    protected static string $resource = ProjectResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            PresenceIndicator::make(['channelName' => $this->record->presenceChannelName()]),
        ];
    }
}
```

### How It Works

1. On mount, receives the channel name from the record's `HasPresenceChannel` trait
2. Uses Alpine.js to call `window.Echo.join(channelName)` with presence callbacks
3. Displays viewer names as inline badges
4. Cleans up (leaves channel) when the widget is destroyed

### Requirements

- Laravel Echo must be configured (`window.Echo` available)
- Reverb (or Pusher) must be running for WebSocket transport
- The presence channel must be authorized in `routes/channels.php`

If Echo is not configured, the widget is inert — no errors, just no viewer data.

---

## Testing

### Broadcast Events

```php
use Illuminate\Support\Facades\Event;

Event::fake([ProjectStatusChanged::class]);

// Trigger business logic...

Event::assertDispatched(ProjectStatusChanged::class, function ($event) {
    return $event->eventType === 'project.status_changed';
});
```

### Channel Authorization

```php
use Aicl\Broadcasting\ChannelAuth;
use Spatie\Permission\Models\Permission;

$user = User::factory()->create();
Permission::create(['name' => 'ViewAny:Project', 'guard_name' => 'web']);
$user->givePermissionTo('ViewAny:Project');

$project = Project::factory()->create();

$this->assertTrue(
    ChannelAuth::entityChannel($user, Project::class, $project->id)
);
```

### Polling Widget

```php
// Test the poll() method directly
$widget = new ActiveJobsWidget;
$widget->poll();
$this->assertNotEmpty($widget->jobs);
```

---

## Files

| File | Purpose |
|------|---------|
| `packages/aicl/src/Broadcasting/BaseBroadcastEvent.php` | Broadcast event base class |
| `packages/aicl/src/Broadcasting/ChannelAuth.php` | Channel authorization helpers |
| `packages/aicl/src/Broadcasting/Traits/HasPresenceChannel.php` | Model presence trait |
| `packages/aicl/src/Filament/Widgets/PollingWidget.php` | Polling widget base class |
| `packages/aicl/resources/views/widgets/polling-widget.blade.php` | Alpine.js polling view |
| `packages/aicl/src/Filament/Widgets/PresenceIndicator.php` | Presence indicator widget |
| `packages/aicl/resources/views/widgets/presence-indicator.blade.php` | Echo presence view |
| `packages/aicl/tests/Unit/Broadcasting/BaseBroadcastEventTest.php` | Unit tests (12) |
| `packages/aicl/tests/Unit/Broadcasting/ChannelAuthTest.php` | Unit tests (8) |
| `packages/aicl/tests/Unit/Broadcasting/HasPresenceChannelTest.php` | Unit tests (4) |
| `packages/aicl/tests/Unit/Filament/Widgets/PollingWidgetTest.php` | Unit tests (4) |
| `packages/aicl/tests/Unit/Filament/Widgets/PresenceIndicatorTest.php` | Unit tests (3) |
