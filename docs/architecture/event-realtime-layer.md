# Architecture: Event & Real-Time Layer (Sprint B)

**Version:** 1.0
**Last Updated:** 2026-02-15
**Owner:** `/solutions`

---

This document captures the design decisions, API surfaces, and integration points for AICL's event and real-time communication layer, delivered in Sprint B.

## Overview

Sprint B adds two capabilities to the AICL package:

| Deliverable | What | Namespace |
|-------------|------|-----------|
| **Domain Event Bus** | Structured, append-only business event persistence with query/replay | `Aicl\Events` |
| **Real-Time UI Base Classes** | Broadcasting, polling, channel auth, presence | `Aicl\Broadcasting`, `Aicl\Filament\Widgets` |

These sit on top of Sprint A's Swoole foundations (Concurrent, SwooleCache, SwooleTimer, ApprovalWorkflow).

> **Note:** SSE Streaming was originally part of Sprint B but was removed in Sprint H. SSE connections hold entire Swoole workers for their duration, exhausting the worker pool (4 workers = 4 SSE connections = zero HTTP capacity). All server-push use cases now use WebSocket (Reverb) or Livewire polling.

---

## Transport Selection Guide

| Transport | Direction | Use When | AICL Class |
|-----------|-----------|----------|------------|
| **WebSocket (Reverb)** | Bidirectional | Real-time push, chat, collaboration, presence, streaming | `BaseBroadcastEvent` |
| **Livewire Polling** | Server → client | Low-frequency updates (> 10s), simple dashboards | `PollingWidget` |
| **HTTP POST/PATCH** | Client → server | One-time client actions | Standard controllers |

**Rule of thumb:** WebSocket for real-time push, bidirectional, or presence. Livewire polling for simplicity at low frequencies.

---

## 1. Domain Event Bus

### Purpose

Record business-level actions as structured, append-only events — separate from Spatie Activity Log's automatic CRUD attribute tracking.

### Schema

```
Table: domain_events (append-only)
├── id              UUID PK
├── event_type      VARCHAR(255)    dot-notation (e.g., 'order.fulfilled')
├── actor_type      VARCHAR(50)     'user' | 'system' | 'agent' | 'automation'
├── actor_id        BIGINT          nullable
├── entity_type     VARCHAR(255)    nullable (morph class)
├── entity_id       VARCHAR(255)    nullable (UUID or int)
├── payload         JSONB           business data
├── metadata        JSONB           cross-cutting (IP, user agent, request ID)
├── occurred_at     TIMESTAMPTZ     when the event happened
└── created_at      TIMESTAMPTZ     when the row was inserted
```

5 indexes: `(entity_type, entity_id)`, `event_type`, `occurred_at`, `(actor_type, actor_id)`, `(event_type, occurred_at)`.

### Key Design Decisions

1. **Append-only semantics.** No UPDATE or DELETE operations exposed. Compliance-safe by design.
2. **Spatie coexistence.** Spatie = automatic attribute diffs. Domain events = explicit business actions. No overlap.
3. **Actor resolution.** Auto-detected from auth context (`User` if authenticated, `System` if console). Explicitly overridable for `Agent` and `Automation` contexts.
4. **Broadcasting is opt-in.** Add `ShouldBroadcast` + `BroadcastsDomainEvent` trait to broadcast. Without them, events are persisted only.
5. **Wildcard subscriber.** `DomainEventSubscriber` uses `$events->listen('*', ...)` with `instanceof DomainEvent` check because Laravel dispatches by concrete class name, not parent class.
6. **Replay skips persistence.** Replayed events are marked with a replay flag; the subscriber detects it and skips re-persistence.

### Query Interface

Six Eloquent scopes on `DomainEventRecord`: `forEntity()`, `ofType()` (with `*` wildcard), `since()`, `between()`, `byActor()`, `timeline()`. Plus `prune()` for storage management and `replay()` for event reconstruction.

### Files

| File | Purpose |
|------|---------|
| `Events/DomainEvent.php` | Abstract base class |
| `Events/DomainEventSubscriber.php` | Wildcard listener, auto-persists |
| `Events/DomainEventRegistry.php` | Event type → class mapping for replay |
| `Events/Enums/ActorType.php` | User, System, Agent, Automation |
| `Events/Traits/BroadcastsDomainEvent.php` | Opt-in broadcasting |
| `Events/Exceptions/UnresolvableEventException.php` | Replay resolution failure |
| `Models/DomainEventRecord.php` | Eloquent model with query scopes |

---

## 2. Real-Time UI Base Classes

### Purpose

Formalize broadcasting, polling, channel authorization, and presence patterns into reusable base classes.

### BaseBroadcastEvent

Standard broadcast event with guaranteed envelope: `eventId` (UUID), `eventType` (string), `occurredAt` (ISO8601). Subclasses define `eventType()`, `toPayload()`, and optionally `getEntity()`.

**Relationship to existing events:** EntityCreated/Updated/Deleted are NOT modified. New broadcast events SHOULD extend `BaseBroadcastEvent`. Domain events use `BroadcastsDomainEvent` trait (different hierarchy).

### PollingWidget

Filament widget with Alpine.js-based polling that pauses via the Page Visibility API when the browser tab is hidden. Uses `setInterval` + `$wire.poll()` instead of Livewire's `wire:poll` for explicit control over pause/resume.

**Behavior:** Polls every N seconds. Pauses when tab hidden. Immediate catch-up + restart when tab visible.

### ChannelAuth

Static helpers for `routes/channels.php`:

| Method | Checks |
|--------|--------|
| `entityChannel($user, $class, $id)` | Entity exists + `ViewAny:{Entity}` permission |
| `userChannel($user, $id)` | User key matches ID (string-safe) |
| `presenceChannel($user, $class, $id)` | `entityChannel()` + returns `{id, name}` |

### HasPresenceChannel

Model trait providing `presenceChannelName()` (`presence.{type}s.{id}`) and `presencePermission()` (`ViewAny:{Entity}`).

### PresenceIndicator

Filament widget using Echo's `join()` for presence channels. Shows connected viewer names as badges. Gracefully inert when Echo is not configured.

### Files

| File | Purpose |
|------|---------|
| `Broadcasting/BaseBroadcastEvent.php` | Broadcast event base class |
| `Broadcasting/ChannelAuth.php` | Channel authorization helpers |
| `Broadcasting/Traits/HasPresenceChannel.php` | Model presence trait |
| `Filament/Widgets/PollingWidget.php` | Visibility-aware polling widget |
| `Filament/Widgets/PresenceIndicator.php` | Presence viewer widget |
| `resources/views/widgets/polling-widget.blade.php` | Polling Alpine.js view |
| `resources/views/widgets/presence-indicator.blade.php` | Presence Alpine.js view |

---

## Integration Points

### With Sprint A (Swoole Foundations)

| Sprint A | Sprint B Usage |
|----------|---------------|
| `Concurrent` | Broadcast event handlers can use `Concurrent::run()` for parallel data fetching |
| `SwooleCache` | Polling widget data queries can leverage SwooleCache for fast reads |
| `SwooleTimer` | Could be used for scheduled event emission (not wired by default) |
| `ApprovalWorkflow` | Approval events can extend `DomainEvent` for audit trail |

### With Sprint C/D (Future)

Sprint B deliverables are build-and-document items. Pipeline integration and consumer features come in future sprints:
- **Sprint C:** Notification observability may consume domain events
- **Sprint D:** Scaffolding may generate polling widgets for entities

---

## Test Coverage

| Item | Tests | Assertions | Files |
|------|-------|------------|-------|
| Domain Event Bus | 57 | 111 | 2 (unit + feature) |
| Real-Time UI | 31 | 52 | 5 (unit) |
| **Total** | **88** | **163** | **7** |

---

## CSP Compatibility

Both deliverables work within the existing CSP configuration:
- `connect-src: 'self'` permits same-origin WebSocket (`Echo`) connections
- No CSP changes required
