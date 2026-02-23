# Notifications

**Version:** 1.1
**Last Updated:** 2026-02-06
**Owner:** `/architect`

---

## Overview

AICL provides a comprehensive notification system built on Laravel's notification framework, extended with a custom dispatcher for per-channel tracking and a centralized log for admin visibility.

Every notification in AICL flows through the `NotificationDispatcher` service, which creates a `NotificationLog` record, dispatches to each channel independently, and tracks per-channel delivery status.

> **MANDATORY RULE:** All notifications — both AICL-native and third-party — MUST be logged to the `notification_logs` table. AICL notifications achieve this by dispatching through the `NotificationDispatcher`. Non-AICL notifications are caught automatically by the `NotificationSentLogger` listener on Laravel's `NotificationSent` event. Never bypass this logging. If you need to send a notification, use the `NotificationDispatcher`. If a third-party package sends notifications directly, the `NotificationSentLogger` ensures they are still logged.

---

## Architecture

```
┌──────────────────────────────────────────────────────────────────────┐
│                      NOTIFICATION FLOW                                │
│                                                                       │
│  Trigger (Observer, Listener, Command)                               │
│       │                                                               │
│       ▼                                                               │
│  NotificationDispatcher::send($notifiable, $notification, $sender)   │
│       │                                                               │
│       ├──→ Create NotificationLog (UUID PK)                          │
│       │       channels: ['database', 'mail', 'broadcast']            │
│       │       channel_status: {database: 'pending', mail: 'pending'} │
│       │                                                               │
│       ├──→ Clone notification + onlyVia('database') → dispatch       │
│       │       └──→ Success → channel_status.database = 'sent'        │
│       │       └──→ Failure → channel_status.database = 'failed'      │
│       │                                                               │
│       ├──→ Clone notification + onlyVia('mail') → dispatch           │
│       │       └──→ Success → channel_status.mail = 'sent'            │
│       │       └──→ Failure → channel_status.mail = 'failed'          │
│       │                                                               │
│       └──→ Clone notification + onlyVia('broadcast') → dispatch      │
│               └──→ Success → channel_status.broadcast = 'sent'       │
│               └──→ Failure → channel_status.broadcast = 'failed'     │
│                                                                       │
│  Result: NotificationLog with per-channel delivery status            │
└──────────────────────────────────────────────────────────────────────┘
```

---

## Key Components

### NotificationDispatcher

**Location:** `packages/aicl/src/Services/NotificationDispatcher.php`

The single entry point for all notifications. Registered as a singleton in `AiclServiceProvider`.

```php
use Aicl\Services\NotificationDispatcher;

// Usage in observers, listeners, commands
app(NotificationDispatcher::class)->send(
    notifiable: $user,
    notification: new ProjectAssignedNotification($project),
    sender: auth()->user()
);
```

**Why a dispatcher?** Direct `$user->notify()` doesn't create log records or track per-channel status. The dispatcher wraps Laravel's notification system to add these capabilities.

### NotificationLog Model

**Location:** `packages/aicl/src/Models/NotificationLog.php`

Parallel to Laravel's `notifications` table but with richer tracking:

| Column | Type | Purpose |
|--------|------|---------|
| `id` | UUID | Primary key |
| `type` | string | Notification class name |
| `notifiable_type` | string | Morph type (User) |
| `notifiable_id` | int | Morph ID |
| `sender_type` | string | Morph type (who triggered) |
| `sender_id` | int | Morph ID |
| `channels` | JSON | `['database', 'mail', 'broadcast']` |
| `channel_status` | JSON | `{database: 'sent', mail: 'failed', broadcast: 'sent'}` |
| `data` | JSON | Notification payload |
| `read_at` | timestamp | When recipient read it |

### BaseNotification

**Location:** `packages/aicl/src/Notifications/BaseNotification.php`

Base class for all AICL notifications:

```php
class BaseNotification extends Notification
{
    protected ?string $onlyChannel = null;

    public function via(object $notifiable): array
    {
        if ($this->onlyChannel) {
            return [$this->onlyChannel];
        }
        return ['database', 'mail', 'broadcast'];
    }

    public function onlyVia(string $channel): static
    {
        $clone = clone $this;
        $clone->onlyChannel = $channel;
        return $clone;
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
```

---

## Notification Channels

### Database Channel

Stores notifications in Laravel's `notifications` table. Powers the Filament bell icon and Notification Center page.

### Mail Channel

Sends email via configured mail driver. Uses standard Laravel `MailMessage` builder.

### Broadcast Channel

Pushes notification data to the browser via Laravel Reverb (WebSockets). The Filament bell icon listens for broadcast events and updates the unread count instantly.

**Bell polling:** Reduced to 300 seconds (5 minutes) as a fallback. Primary delivery is via broadcast.

---

## Entity Event Notifications

The `EntityEventNotificationListener` automatically creates notifications when entities are created, updated, or deleted:

```
EntityCreated/Updated/Deleted event
    │
    ▼
EntityEventNotificationListener (queued)
    │
    ├──→ Notify entity owner (if different from actor)
    └──→ Notify all super_admin users
```

**Notifications created:**
- `EntityEventNotification` — includes entity type, action, actor name, timestamp
- Both `DatabaseNotification` and `NotificationLog` records are created

---

## Admin Interfaces

### Notification Center

**Location:** `packages/aicl/src/Filament/Pages/NotificationCenter.php`
**Route:** `/admin/notifications`
**Access:** All authenticated users (sees only own notifications)

Features:
- Table of all notifications with read/unread state
- Mark read/unread (row action)
- Mark all read (header action)
- Bulk delete
- Search and filter

### Notification Log

**Location:** `packages/aicl/src/Filament/Pages/NotificationLogPage.php`
**Route:** `/admin/notification-log`
**Access:** Admin and super_admin only

Features:
- System-wide view of ALL notification logs across all users
- Shows: recipient, notification type, channels, per-channel status, sender, read state
- Filters: type, recipient, channel, status, read

---

## User Model Integration

The `HasNotificationLogging` trait is added to the User model:

```php
use Aicl\Traits\HasNotificationLogging;

class User extends Authenticatable
{
    use HasNotificationLogging;
    // Provides: notificationLogs() morphMany relationship
}
```

---

## Testing Notifications

**Key learning:** `Notification::fake()` prevents the dispatcher from working (it fakes the channel manager). Don't use it for tests that need to verify NotificationLog creation via observers.

```php
// Test that notification was dispatched (without fake)
$this->assertDatabaseHas('notification_logs', [
    'type' => ProjectAssignedNotification::class,
    'notifiable_id' => $user->id,
]);

// Test channel status
$log = NotificationLog::latest()->first();
$this->assertEquals('sent', $log->channel_status['database']);
```

For testing that notifications are queued without actually sending:

```php
// phpunit.xml sets QUEUE_CONNECTION=sync and BROADCAST_CONNECTION=log
// This ensures notifications process but don't hit external services
```

---

## NotificationSentLogger — Catch-All for Non-AICL Notifications

**Location:** `packages/aicl/src/Listeners/NotificationSentLogger.php`

Listens on Laravel's `Illuminate\Notifications\Events\NotificationSent` event. Ensures that notifications dispatched outside of the `NotificationDispatcher` (e.g., by third-party packages, Filament native notifications, or direct `$user->notify()` calls) are still logged to `notification_logs`.

```php
// Registered in AiclServiceProvider
Event::listen(NotificationSent::class, NotificationSentLogger::class);
```

**Behavior:**
- Skips `BaseNotification` instances (already logged by the dispatcher)
- Skips non-Model notifiables
- Creates a `NotificationLog` record with the channel and `sent` status
- Extracts data from `getDatabaseMessage()` or `toArray()` if available

This listener is the safety net that guarantees **every notification** reaches the log table — even those not routed through the dispatcher.

---

## Rules for New Notifications

When implementing new notifications, follow these rules:

1. **Extend `BaseNotification`** — All AICL notifications must extend `Aicl\Notifications\BaseNotification`.
2. **Dispatch via `NotificationDispatcher`** — Never use `$user->notify()` directly. Always use `app(NotificationDispatcher::class)->send()`.
3. **Per-channel tracking is automatic** — The dispatcher clones the notification with `onlyVia()` per channel and records success/failure.
4. **Third-party notifications are auto-logged** — The `NotificationSentLogger` catches anything sent via Laravel's native `Notification` facade or `$notifiable->notify()`.
5. **Do not use `Notification::fake()` in tests that verify logging** — Faking prevents the dispatcher from working. Assert against `notification_logs` table instead.

---

## Related Documents

- [Queue, Jobs & Scheduler](queue-jobs-scheduler.md) — Queued notification delivery
- [Search & Real-time](search-realtime.md) — Broadcast channel for bell updates
- [Entity System](entity-system.md) — Entity events that trigger notifications
