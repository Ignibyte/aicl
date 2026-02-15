# Domain Event Bus

Structured, append-only business event persistence with optional broadcasting and full query/replay capabilities.

**Namespace:** `Aicl\Events`
**Model:** `Aicl\Models\DomainEventRecord`
**Table:** `domain_events` (append-only)

## When to Use Domain Events vs Spatie Activity Log

| Question | System |
|----------|--------|
| What **changed** on this model? | Spatie Activity Log (`HasAuditTrail`) |
| What **business action** happened? | Domain Event Bus |

Spatie automatically logs attribute-level diffs on every model save. Domain events are **explicitly dispatched** by your code to record business-level actions with context that goes beyond attribute changes.

```php
// These two complement each other — no duplication
$order->update(['status' => 'fulfilled']);
// Spatie: status changed from 'processing' to 'fulfilled' (automatic)

OrderFulfilled::dispatch($order, $warehouse, $trackingNumber);
// Domain event: 'order.fulfilled' with payload { warehouse_id, tracking_number } (explicit)
```

---

## Creating a Domain Event

Extend `DomainEvent` and declare a static `$eventType` string using dot notation:

```php
use Aicl\Events\DomainEvent;
use Aicl\Events\Enums\ActorType;

class OrderFulfilled extends DomainEvent
{
    public static string $eventType = 'order.fulfilled';

    public function __construct(
        public Order $order,
        public Warehouse $warehouse,
        public string $trackingNumber,
    ) {
        parent::__construct();
    }

    public function toPayload(): array
    {
        return [
            'order_id' => $this->order->id,
            'warehouse_id' => $this->warehouse->id,
            'tracking_number' => $this->trackingNumber,
            'items_count' => $this->order->items()->count(),
        ];
    }
}
```

**Every domain event gets automatically:**
- A UUID `eventId`
- A `occurredAt` Carbon timestamp
- Actor resolution (who triggered this — see Actor Types below)
- Automatic persistence to the `domain_events` table

---

## Dispatching Events

Domain events are standard Laravel events. Dispatch them with `event()` or the static `dispatch()` method:

```php
// Basic dispatch
OrderFulfilled::dispatch($order, $warehouse, $trackingNumber);

// Or via event() helper
event(new OrderFulfilled($order, $warehouse, $trackingNumber));
```

### Associating an Entity

Link the event to a specific model for later querying:

```php
$event = new OrderFulfilled($order, $warehouse, $trackingNumber);
$event->forEntity($order);
event($event);
```

The entity's morph type and primary key are stored in `entity_type` and `entity_id`. This enables the `forEntity()` and `timeline()` query scopes. Entity association is optional — some domain events are not entity-specific (e.g., `system.maintenance_started`).

---

## Actor Types

Every domain event records **who** triggered it via `ActorType`:

| Actor | Value | Description |
|-------|-------|-------------|
| `ActorType::User` | `'user'` | Human user authenticated via session/token |
| `ActorType::System` | `'system'` | Framework internals (migrations, seeds, schedulers) |
| `ActorType::Agent` | `'agent'` | AI agent (Claude, GPT, etc.) |
| `ActorType::Automation` | `'automation'` | Business rules, workflows, approval engine |

### Auto-Resolution

If you don't specify an actor type, `DomainEvent` resolves it automatically:

- Authenticated user? `ActorType::User` with `auth()->id()`
- Running in console? `ActorType::System` with `null` actor ID
- Neither? `ActorType::System` with `null` actor ID

### Explicit Actor

Override auto-resolution for agent or automation contexts:

```php
// AI agent action
$event = new DocumentAnalyzed($document);
event(new DocumentAnalyzed($document, ActorType::Agent, agentUserId: 42));

// Automation rule triggered
event(new IncidentEscalated($incident, ActorType::Automation));
```

---

## Custom Payloads

Override `toPayload()` to persist event-specific business data as JSONB:

```php
class IncidentEscalated extends DomainEvent
{
    public static string $eventType = 'incident.escalated';

    public function __construct(
        public Incident $incident,
        public int $fromTier,
        public int $toTier,
        public string $reason,
    ) {
        parent::__construct();
    }

    public function toPayload(): array
    {
        return [
            'incident_id' => $this->incident->id,
            'from_tier' => $this->fromTier,
            'to_tier' => $this->toTier,
            'reason' => $this->reason,
        ];
    }
}
```

The base class also captures cross-cutting **metadata** automatically (IP address, user agent, X-Request-ID header) when running in an HTTP context. Metadata is stored separately from payload so business data stays clean.

---

## Broadcasting (Opt-In)

To broadcast a domain event via Reverb/WebSocket, implement `ShouldBroadcast` and use the `BroadcastsDomainEvent` trait:

```php
use Aicl\Events\DomainEvent;
use Aicl\Events\Traits\BroadcastsDomainEvent;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class IncidentEscalated extends DomainEvent implements ShouldBroadcast
{
    use BroadcastsDomainEvent;

    public static string $eventType = 'incident.escalated';

    // ... constructor and toPayload() as above
}
```

The trait provides:

| Method | Behavior |
|--------|----------|
| `broadcastOn()` | `private-dashboard` channel + `private-{entity}s.{id}` if entity is set |
| `broadcastAs()` | Returns `$eventType` (e.g., `'incident.escalated'`) |
| `broadcastWith()` | Merges `toPayload()` with `eventId`, `eventType`, `occurredAt` |

Events without this trait are persisted but NOT broadcast. Broadcasting is handled asynchronously by Laravel's broadcast queue — persistence is synchronous.

---

## Querying Events

Use `DomainEventRecord` with Eloquent scopes to query persisted events:

### `forEntity(Model $entity)`

All events for a specific entity:

```php
use Aicl\Models\DomainEventRecord;

$events = DomainEventRecord::forEntity($order)->get();
```

### `ofType(string $type)`

Filter by event type with optional wildcard support:

```php
// Exact match
DomainEventRecord::ofType('order.fulfilled')->get();

// Wildcard prefix — all order events
DomainEventRecord::ofType('order.*')->get();

// Wildcard suffix — all escalation events
DomainEventRecord::ofType('*.escalated')->get();
```

### `since(Carbon $date)`

Events on or after a date:

```php
DomainEventRecord::ofType('order.*')->since(now()->subDay())->get();
```

### `between(Carbon $start, Carbon $end)`

Events within a date range:

```php
DomainEventRecord::between(
    Carbon::parse('2026-01-01'),
    Carbon::parse('2026-01-31'),
)->paginate();
```

### `byActor(ActorType $type, ?int $id = null)`

Events by actor type, optionally filtered to a specific actor:

```php
use Aicl\Events\Enums\ActorType;

// All events triggered by AI agents
DomainEventRecord::byActor(ActorType::Agent)->get();

// Events by a specific user
DomainEventRecord::byActor(ActorType::User, $userId)->get();
```

### `timeline(Model $entity)`

Shorthand for `forEntity($entity)->latest('occurred_at')` — returns all events for an entity ordered newest first:

```php
$timeline = DomainEventRecord::timeline($order)->get();
// Most recent event first
```

### Chaining Scopes

All scopes can be chained:

```php
DomainEventRecord::forEntity($order)
    ->ofType('order.*')
    ->since(now()->subWeek())
    ->byActor(ActorType::User)
    ->paginate();
```

---

## Replay

Replay re-dispatches persisted events through Laravel's event dispatcher for debugging or reprocessing. Replayed events are **not re-persisted** (the subscriber detects the replay flag and skips persistence).

### Setup

Register your event classes in a service provider so the registry can reconstruct them:

```php
// In AppServiceProvider::boot()
OrderFulfilled::register();
IncidentEscalated::register();
```

### Replaying Events

```php
// Replay all events for an entity
DomainEventRecord::forEntity($order)->each(fn ($record) => $record->replay());

// Replay specific event types in a time range
DomainEventRecord::ofType('order.*')
    ->between($start, $end)
    ->each(fn ($record) => $record->replay());

// Replay a single event
$record = DomainEventRecord::find($id);
$replayedEvent = $record->replay();
```

The replayed event preserves the original actor type and ID from the persisted record. All listeners except the persistence subscriber will receive the event normally.

If an event type has no registered class, `replay()` throws `UnresolvableEventException`.

---

## Pruning Old Events

The table is append-only by design, but you can remove old historical data for storage management:

```php
use Aicl\Models\DomainEventRecord;

// Delete events older than 1 year
$deleted = DomainEventRecord::prune(now()->subYear());
// Returns the number of deleted records
```

---

## Database Schema

```
Table: domain_events (append-only, no UPDATE/DELETE in normal operation)

id              UUID PK         gen_random_uuid()
event_type      VARCHAR(255)    NOT NULL — dot-notation (e.g., 'order.fulfilled')
actor_type      VARCHAR(50)     NOT NULL — 'user', 'system', 'agent', 'automation'
actor_id        BIGINT          NULL     — nullable for system/automation actors
entity_type     VARCHAR(255)    NULL     — morph class name
entity_id       VARCHAR(255)    NULL     — supports UUID and integer PKs
payload         JSONB           NOT NULL — event-specific business data
metadata        JSONB           NOT NULL — cross-cutting (IP, user agent, request ID)
occurred_at     TIMESTAMPTZ     NOT NULL — when the event happened (business time)
created_at      TIMESTAMPTZ     NOT NULL — when the row was inserted (system time)

Indexes:
  (entity_type, entity_id)  — forEntity() scope
  event_type                — ofType() scope
  occurred_at               — since()/between() scopes
  (actor_type, actor_id)    — byActor() scope
  (event_type, occurred_at) — combined type + time queries
```

---

## Testing

Domain events work naturally in PHPUnit tests since the subscriber runs synchronously:

```php
use Aicl\Models\DomainEventRecord;

public function test_fulfilling_order_creates_domain_event(): void
{
    $order = Order::factory()->create();
    $warehouse = Warehouse::factory()->create();

    event((new OrderFulfilled($order, $warehouse, 'TRACK123'))->forEntity($order));

    $this->assertDatabaseCount('domain_events', 1);

    $record = DomainEventRecord::first();
    $this->assertSame('order.fulfilled', $record->event_type);
    $this->assertSame('TRACK123', $record->payload['tracking_number']);
}
```

To test without side effects, fake events as usual:

```php
use Illuminate\Support\Facades\Event;

Event::fake([OrderFulfilled::class]);

// ... trigger business logic ...

Event::assertDispatched(OrderFulfilled::class);
```

---

## Files

| File | Purpose |
|------|---------|
| `packages/aicl/src/Events/DomainEvent.php` | Abstract base class |
| `packages/aicl/src/Events/DomainEventSubscriber.php` | Auto-persistence subscriber |
| `packages/aicl/src/Events/DomainEventRegistry.php` | Event type to class mapping for replay |
| `packages/aicl/src/Events/Enums/ActorType.php` | Actor type enum |
| `packages/aicl/src/Events/Traits/BroadcastsDomainEvent.php` | Opt-in broadcasting trait |
| `packages/aicl/src/Events/Exceptions/UnresolvableEventException.php` | Replay resolution exception |
| `packages/aicl/src/Models/DomainEventRecord.php` | Eloquent model with query scopes |
| `packages/aicl/database/migrations/2026_02_12_200000_create_domain_events_table.php` | Migration |
| `packages/aicl/tests/Unit/Events/DomainEventTest.php` | Unit tests (19 tests) |
| `packages/aicl/tests/Feature/Events/DomainEventBusTest.php` | Feature tests (38 tests) |
