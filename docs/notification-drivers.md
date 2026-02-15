# Notification Channel Drivers

External notification delivery with 6 built-in drivers, delivery tracking, retry with exponential backoff, and per-channel rate limiting.

**Namespace:** `Aicl\Notifications`

## Overview

The channel driver system extends AICL's existing notification infrastructure with database-backed external delivery channels. Notifications can be sent to Slack, Email, Teams, PagerDuty, generic webhooks, and SMS with encrypted config, automatic retry, and delivery tracking.

```
BaseNotification
    |
    v
NotificationDispatcher::send()
    |
    +-- Laravel channels (database, mail, broadcast) -- unchanged
    |
    +-- External channels (new)
            |
            +-- Rate limiting check
            +-- Create delivery log
            +-- Dispatch RetryNotificationDelivery job
                    |
                    v
                DriverRegistry::resolve() --> Driver::send()
```

---

## Quick Start

### 1. Seed Channels

Channels are seeded by `aicl:install`. To manually create:

```php
use Aicl\Notifications\Models\NotificationChannel;
use Aicl\Notifications\Enums\ChannelType;

NotificationChannel::create([
    'name' => 'Ops Slack',
    'slug' => 'ops-slack',
    'type' => ChannelType::Slack,
    'config' => ['webhook_url' => 'https://hooks.slack.com/...'],  // encrypted at rest
    'rate_limit' => ['max' => 30, 'period' => '1m'],
    'is_active' => true,
]);
```

### 2. Opt a Notification Into External Channels

```php
use Aicl\Notifications\Contracts\HasExternalChannels;
use Aicl\Notifications\Models\NotificationChannel;
use Illuminate\Support\Collection;

class IncidentCreatedNotification extends BaseNotification implements HasExternalChannels
{
    public function externalChannels(): Collection
    {
        return NotificationChannel::active()->get();
    }
}
```

### 3. Dispatch Normally

```php
$dispatcher = app(NotificationDispatcher::class);
$dispatcher->send($user, new IncidentCreatedNotification($incident));
```

The dispatcher handles both Laravel channels (database, mail, broadcast) AND external channels automatically.

---

## Built-in Drivers

| Driver | Channel Type | Config Keys | Transport |
|--------|-------------|-------------|-----------|
| `SlackDriver` | Slack | `webhook_url`, `channel?`, `username?`, `icon_emoji?` | POST to webhook |
| `EmailDriver` | Email | `to`, `from?`, `subject_prefix?` | Laravel Mail |
| `WebhookDriver` | Webhook | `url`, `method?`, `headers?`, `auth?` | Configurable HTTP |
| `PagerDutyDriver` | PagerDuty | `routing_key`, `severity_map?` | Events API v2 |
| `TeamsDriver` | Teams | `webhook_url` | Adaptive Card POST |
| `SmsDriver` | SMS | `provider`, `api_key`, `from` | Twilio Messages API |

All drivers use Laravel's `Http` facade — no external SDK dependencies.

---

## Delivery Tracking

Every external delivery attempt is tracked in `notification_delivery_logs`:

| Status | Meaning | Final? |
|--------|---------|--------|
| `pending` | Queued for delivery | No |
| `sent` | Sent to external service | No |
| `delivered` | Confirmed delivered | Yes |
| `failed` | Permanently failed | Yes |
| `rate_limited` | Deferred by rate limiter | No |

### Retry Strategy

- Exponential backoff with jitter: ~1-2s, ~2-3s, ~4-5s, ~8-9s
- Maximum 5 attempts (configurable: `aicl.notifications.retry.max_attempts`)
- Non-retryable failures (4xx errors, invalid config) are marked final immediately
- Each retry is a separate queued job

### Rate Limiting

Per-channel, config-driven. Excess is queued, never dropped.

```php
// Channel config
'rate_limit' => ['max' => 30, 'period' => '1m']

// Supported periods: "30s", "1m", "5m", "1h"
```

---

## Custom Drivers

Implement `NotificationChannelDriver` and register:

```php
use Aicl\Notifications\Contracts\NotificationChannelDriver;
use Aicl\Notifications\Models\NotificationChannel;
use Aicl\Notifications\DriverResult;

class DiscordDriver implements NotificationChannelDriver
{
    public function send(NotificationChannel $channel, array $payload): DriverResult
    {
        $response = Http::post($channel->config['webhook_url'], [
            'content' => $payload['body'] ?? $payload['title'] ?? '',
        ]);

        return $response->successful()
            ? DriverResult::success()
            : DriverResult::failure($response->body(), retryable: $response->serverError());
    }

    public function validateConfig(array $config): array
    {
        return empty($config['webhook_url']) ? ['webhook_url' => 'Required'] : [];
    }

    public function configSchema(): array
    {
        return [
            'webhook_url' => ['type' => 'string', 'label' => 'Webhook URL', 'required' => true],
        ];
    }
}
```

Register in your service provider:

```php
use Aicl\Notifications\DriverRegistry;
use Aicl\Notifications\Enums\ChannelType;

app(DriverRegistry::class)->register(ChannelType::Webhook, DiscordDriver::class);
```

---

## Extension Points

| Extension | How |
|-----------|-----|
| Custom driver | Implement `NotificationChannelDriver`, register via `DriverRegistry` |
| Custom channel routing | Implement `NotificationChannelResolver`, set in `aicl.notifications.channel_resolver` |
| Custom recipient routing | Implement `NotificationRecipientResolver`, set in `aicl.notifications.recipient_resolver` |
| Lifecycle events | Listen to `NotificationSending` (cancellable) and `NotificationDispatched` |

---

## Configuration

```php
// config/aicl.php
'notifications' => [
    'default_channels' => ['database', 'mail', 'broadcast'],
    'channel_resolver' => null,
    'recipient_resolver' => null,
    'retry' => [
        'max_attempts' => 5,
        'base_delay' => 1,
    ],
    'queue' => 'notifications',
],
```
