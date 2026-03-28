# Notification & Observability

**Version:** 1.1
**Last Updated:** 2026-02-13
**Owner:** `/pipeline-implement`
**Sprint:** C

---

## Overview

The Notification & Observability layer delivers three production-grade subsystems that extend AICL's existing notification infrastructure and add operational visibility to the Filament admin panel:

1. **Notification Channel Drivers (3.1)** -- Pluggable external delivery channels (Slack, Email, Teams, PagerDuty, Webhook, SMS) with database-backed configuration, encrypted secrets, delivery tracking, automatic retry with exponential backoff, and per-channel rate limiting. Builds on top of the existing `BaseNotification` and `NotificationLog` systems.

2. **Message Template Engine (3.2)** -- A safe, regex-based template rendering engine with `{{ variable | filter }}` syntax. Prefix-based variable resolvers extract data from models, users, app config, and custom sources. Eleven built-in filters transform values. Six per-channel format adapters produce channel-native payloads (Slack blocks, Adaptive Cards, PagerDuty Events API v2, email HTML, plain text, webhook JSON). Templates are stored in JSONB on the `notification_channels` table and are fully optional -- channels without templates use the notification's hardcoded `toDatabase()` output.

3. **Live Ops Panel (3.5)** -- A Filament page showing service health and application status at a glance. Six built-in health checks cover Swoole, PostgreSQL, Redis, Elasticsearch, queues, and application info. Extensible via a registry pattern so client apps can add custom service checks. RBAC-gated to admin/super_admin roles. Refreshes via Livewire polling at 30-second intervals.

All components follow AICL's core principles: extension over modification, standard idiomatic Laravel, singleton registries with boot-time population, and graceful behavior under Swoole/Octane.

---

## 3.1 Notification Channel Drivers

### Architecture

The channel driver system adds three layers on top of the existing notification infrastructure:

```
BaseNotification (existing)
    |
    v
NotificationDispatcher::send() (enhanced)
    |
    +-- Laravel channels (database, mail, broadcast) -- existing behavior, unchanged
    |
    +-- External channels (Slack, Email, Webhook, PagerDuty, Teams, SMS) -- NEW
            |
            v
        dispatchToExternalChannel()
            |
            +-- Rate limiting check (ChannelRateLimiter)
            +-- Create NotificationDeliveryLog
            +-- Dispatch RetryNotificationDelivery job
                    |
                    v
                DriverRegistry::resolve() --> NotificationChannelDriver::send()
                    |
                    v
                DriverResult (success/failure)
```

### NotificationChannelDriver Interface

The driver contract defines three methods: send a payload, validate channel config, and describe required config fields.

```php
namespace Aicl\Notifications\Contracts;

interface NotificationChannelDriver
{
    public function send(NotificationChannel $channel, array $payload): DriverResult;
    public function validateConfig(array $config): array;
    public function configSchema(): array;
}
```

- `send()` receives the channel model (with decrypted credentials) and the notification payload. Returns a `DriverResult`.
- `validateConfig()` returns an empty array if valid, or field-to-error-message pairs if invalid.
- `configSchema()` returns metadata about required config fields (type, label, required flag) for UI generation.

All built-in drivers use Laravel's `Http` facade for HTTP calls -- no external SDK dependencies.

### DriverResult

An immutable value object representing the outcome of a delivery attempt:

```php
namespace Aicl\Notifications;

class DriverResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $messageId = null,
        public readonly ?array $response = null,
        public readonly ?string $error = null,
        public readonly bool $retryable = true,
    ) {}

    public static function success(?string $messageId = null, ?array $response = null): static;
    public static function failure(string $error, bool $retryable = true, ?array $response = null): static;
}
```

The `retryable` flag controls whether failed deliveries are retried. Non-retryable failures (e.g., 4xx client errors, invalid config) are marked as final immediately.

### DriverRegistry

A singleton that maps `ChannelType` enum values to driver class names. Drivers are resolved through the service container on demand.

```php
namespace Aicl\Notifications;

class DriverRegistry
{
    public function register(ChannelType $type, string $driverClass): void;
    public function resolve(ChannelType $type): NotificationChannelDriver;
    public function registered(): array;
    public function has(ChannelType $type): bool;
}
```

Built-in drivers are registered in `AiclServiceProvider`. Client apps call `$registry->register()` to override a built-in driver or add new channel types.

### Built-in Drivers

Six drivers ship with AICL, all in `packages/aicl/src/Notifications/Drivers/`:

| Driver | Channel Type | Config Keys | Transport |
|--------|-------------|-------------|-----------|
| `SlackDriver` | Slack | `webhook_url`, `channel?`, `username?`, `icon_emoji?` | POST to Slack webhook URL |
| `EmailDriver` | Email | `to`, `from?`, `subject_prefix?` | Laravel Mail |
| `WebhookDriver` | Webhook | `url`, `method?`, `headers?`, `auth?` | Configurable HTTP request |
| `PagerDutyDriver` | PagerDuty | `routing_key`, `severity_map?` | POST to Events API v2 |
| `TeamsDriver` | Teams | `webhook_url` | POST Adaptive Card to webhook |
| `SmsDriver` | SMS | `provider`, `api_key`, `from` | Twilio Messages API |

### NotificationChannel Model

Database-backed channel configuration with encrypted secrets:

```php
namespace Aicl\Notifications\Models;

class NotificationChannel extends Model
{
    use HasUuids;

    protected $table = 'notification_channels';

    // Key columns: name, slug, type (ChannelType enum), config (encrypted:array),
    // message_templates (array, JSONB), rate_limit (array), is_active (boolean)

    // Scopes: active(), ofType(ChannelType)
    // Relationships: deliveryLogs() -> HasMany NotificationDeliveryLog
    // Methods: getTemplate(string $notificationClass): ?array
}
```

The `config` column uses Laravel's `encrypted:array` cast -- API keys, webhook URLs, and auth tokens are encrypted at rest using `APP_KEY`. The `message_templates` column is NOT encrypted (no secrets in display strings).

**Migration schema (`notification_channels`):**

| Column | Type | Notes |
|--------|------|-------|
| `id` | UUID PK | HasUuids trait |
| `name` | varchar(255) | Human-readable name |
| `slug` | varchar(255) UNIQUE | URL-safe identifier |
| `type` | varchar(50) | ChannelType enum value |
| `config` | JSONB | Encrypted at application level |
| `message_templates` | JSONB nullable | Per-notification-type templates (3.2) |
| `rate_limit` | JSONB nullable | `{max: int, period: string}` |
| `is_active` | boolean, default true | Soft enable/disable |
| `created_at`, `updated_at` | timestamps | Standard |

### Delivery Tracking (NotificationDeliveryLog)

A separate model from the existing `NotificationLog`. The existing log tracks notification-level auditing (who received what, which Laravel channels). The delivery log tracks external channel driver delivery attempts -- the HTTP-level lifecycle with retry metadata.

**Why separate?** The existing `NotificationLog` stores one record per notification dispatch. Delivery tracking needs one record per delivery attempt per channel, with retry counts, HTTP responses, and rate-limit timestamps. Two focused models are simpler than one bloated model.

**Migration schema (`notification_delivery_logs`):**

| Column | Type | Notes |
|--------|------|-------|
| `id` | UUID PK | HasUuids trait |
| `notification_log_id` | UUID FK | References `notification_logs.id` ON DELETE CASCADE |
| `channel_id` | UUID FK | References `notification_channels.id` ON DELETE CASCADE |
| `status` | varchar(50) | DeliveryStatus enum: pending, sent, delivered, failed, rate_limited |
| `attempt_count` | integer, default 0 | Number of send attempts |
| `payload` | JSONB nullable | What was sent to the driver |
| `response` | JSONB nullable | What came back from the external service |
| `error_message` | text nullable | Failure reason |
| `sent_at` | timestamp nullable | When first sent |
| `delivered_at` | timestamp nullable | When confirmed delivered |
| `failed_at` | timestamp nullable | When permanently failed |
| `next_retry_at` | timestamp nullable | Scheduled retry time |
| `created_at` | timestamp | Standard |

### Delivery Status Lifecycle

```
pending -----> delivered (success on first or subsequent attempt)
   |
   +--------> failed (non-retryable, or max attempts exceeded)
   |
   +--------> rate_limited -----> pending (after rate limit window resets)
```

**Enum: `DeliveryStatus`**

| Value | Label | Color | Final? |
|-------|-------|-------|--------|
| `pending` | Pending | gray | No |
| `sent` | Sent | info | No |
| `delivered` | Delivered | success | Yes |
| `failed` | Failed | danger | Yes |
| `rate_limited` | Rate Limited | warning | No |

### Retry with Exponential Backoff

Retry is handled by a queued job (`RetryNotificationDelivery`), not inline in the dispatcher. This decouples retry from the initial dispatch and uses Laravel's native queue infrastructure.

**Strategy:**
- Each job execution is a single send attempt
- On failure, the job re-dispatches itself with a calculated delay
- Maximum 5 attempts (configurable via `aicl.notifications.retry.max_attempts`)
- Exponential backoff with jitter: `base * 2^(attempt-1) + random(0, base)`
- Delay progression: ~1-2s, ~2-3s, ~4-5s, ~8-9s (with jitter)
- Non-retryable failures (`DriverResult::$retryable = false`) are marked as permanently failed immediately
- Inactive or missing channels are marked as failed without retry

```php
namespace Aicl\Notifications\Jobs;

class RetryNotificationDelivery implements ShouldQueue
{
    public int $tries = 1;  // Single attempt per job -- self re-dispatches

    public function __construct(public string $deliveryLogId) {}

    public function handle(DriverRegistry $registry): void
    {
        // 1. Load delivery log, check max attempts
        // 2. Verify channel exists and is active
        // 3. Resolve driver, call send()
        // 4. On success: mark delivered
        // 5. On retryable failure: update log, re-dispatch with delay
        // 6. On non-retryable failure: mark permanently failed
    }

    protected function calculateDelay(int $attempt): int
    {
        $base = (int) config('aicl.notifications.retry.base_delay', 1);
        $exponential = $base * (2 ** ($attempt - 1));
        $jitter = random_int(0, $base * 1000) / 1000;
        return (int) ceil($exponential + $jitter);
    }
}
```

### Rate Limiting

Per-channel, config-driven rate limiting using Laravel's `RateLimiter` facade (backed by Redis). Excess notifications are queued for later delivery, never dropped.

```php
namespace Aicl\Notifications;

class ChannelRateLimiter
{
    public function attempt(NotificationChannel $channel): bool;
    public function availableIn(NotificationChannel $channel): int;
    protected function parsePeriod(string $period): int;  // "30s", "1m", "5m", "1h"
}
```

- Rate limit is configured per channel in the `rate_limit` JSONB column: `{"max": 30, "period": "1m"}`
- When rate-limited, the delivery log status is set to `RateLimited` and the retry job is dispatched with a delay matching the rate limit reset window
- Period format supports seconds (`30s`), minutes (`1m`, `5m`), and hours (`1h`)
- Channels without `rate_limit` config are unlimited

### Extension Points

#### NotificationChannelResolver -- Custom Channel Routing

```php
namespace Aicl\Notifications\Contracts;

interface NotificationChannelResolver
{
    public function resolve(BaseNotification $notification, object $notifiable): Collection;
}
```

Implement this contract to control which `NotificationChannel` models receive each notification. Bind via config (`aicl.notifications.channel_resolver`) or service container.

#### NotificationRecipientResolver -- Custom Recipient Routing

```php
namespace Aicl\Notifications\Contracts;

interface NotificationRecipientResolver
{
    public function resolve(Model $entity, string $action): Collection;
}
```

Implement this contract to control who receives notifications for entity events. Bind via config (`aicl.notifications.recipient_resolver`).

#### HasExternalChannels -- Notification Opt-In

```php
namespace Aicl\Notifications\Contracts;

interface HasExternalChannels
{
    public function externalChannels(): Collection;
}
```

Notifications that implement `HasExternalChannels` can declare which external channels they use. This is the default resolution path when no `NotificationChannelResolver` is configured.

#### Pre/Post Dispatch Events

| Event | When | Properties | Usage |
|-------|------|------------|-------|
| `NotificationSending` | Before dispatch | `$notification`, `$notifiable`, `$sender` | Cancellable via `$event->cancel()` |
| `NotificationDispatched` | After dispatch | `$notification`, `$notifiable`, `$log` | Audit, telemetry, side effects |

### Configuration

Added to `config/aicl.php`:

```php
'notifications' => [
    'default_channels' => ['database', 'mail', 'broadcast'],
    'channel_resolver' => null,     // class-string<NotificationChannelResolver>
    'recipient_resolver' => null,   // class-string<NotificationRecipientResolver>
    'retry' => [
        'max_attempts' => 5,
        'base_delay' => 1,          // seconds
    ],
    'queue' => 'notifications',
],
```

---

## 3.2 Message Template Engine

### Template Syntax

Safe Mustache-style syntax with dot-notation variables and pipe-delimited filters. No PHP execution -- pure string interpolation via regex.

```
{{ model.title }}
{{ user.name | upper }}
{{ model.created_at | relative }}
{{ model.description | truncate:50 }}
{{ model.name | lower | truncate:20 }}
```

**Regex pattern:** `/\{\{\s*(.+?)\s*\}\}/`

Each match is split on `|` to extract the variable reference and filter chain.

| Syntax | Meaning |
|--------|---------|
| `{{ prefix.field }}` | Variable interpolation |
| `{{ prefix.field \| filter }}` | Single filter |
| `{{ prefix.field \| filter:arg }}` | Filter with argument |
| `{{ prefix.field \| f1 \| f2 }}` | Chained filters (left to right) |

**Safety:** HTML entities in variable values are escaped by default (prevents XSS in email HTML output). The `raw` filter opts out for trusted content. Escaping is controlled by `config('aicl.notifications.templates.escape_html')`.

### Variable Resolvers

Each variable prefix maps to a `VariableResolver` implementation. Resolvers are registered at boot time and are extensible.

```php
namespace Aicl\Notifications\Templates\Contracts;

interface VariableResolver
{
    public function resolve(string $field, array $context): ?string;
}
```

**Built-in resolvers:**

| Prefix | Resolver Class | Source | Notes |
|--------|---------------|--------|-------|
| `model` | `ModelVariableResolver` | `$context['model']` | Eloquent attributes + dot-notation relationship traversal |
| `user` | `UserVariableResolver` | `$context['user']` | Sender/actor (User model) |
| `recipient` | `RecipientVariableResolver` | `$context['recipient']` | The notifiable receiving the notification |
| `app` | `AppVariableResolver` | `config('app.*')` | Allowlisted fields: `name`, `url`, `env`, `timezone` |
| `channel` | `ChannelVariableResolver` | `$context['channel']` | NotificationChannel model, `config` property denylisted |

**Dot-notation traversal:** `{{ model.assignee.name }}` resolves by calling `$model->assignee` (relationship), then `->name` (attribute). Each step checks for null -- returns empty string if any part is null.

**Safety rules:**
- Resolvers only expose public properties and Eloquent attributes. No method calls.
- The `app` resolver uses an explicit allowlist -- no arbitrary config access.
- The `channel` resolver never exposes the `config` property (contains encrypted secrets).

### Filter Pipeline

Eleven built-in filters ship with AICL. All implement the `TemplateFilter` contract:

```php
namespace Aicl\Notifications\Templates\Contracts;

interface TemplateFilter
{
    public function apply(string $value, ?string $argument, array $context): string;
}
```

| Filter | Arguments | Behavior |
|--------|-----------|----------|
| `upper` | none | `mb_strtoupper()` |
| `lower` | none | `mb_strtolower()` |
| `title` | none | `mb_convert_case(MB_CASE_TITLE)` |
| `truncate` | `length` (int) | `Str::limit($value, $arg)` |
| `relative` | none | `Carbon::parse()->diffForHumans()` |
| `date` | `format` (string) | `Carbon::parse()->format($arg)` |
| `default` | `fallback` (string) | Return fallback if value is empty |
| `raw` | none | Skip HTML escaping for this value |
| `nl2br` | none | `nl2br()` |
| `strip_tags` | none | `strip_tags()` |
| `number` | `decimals` (int, default 0) | `number_format()` |

Filters are registered in a `FilterRegistry` singleton, populated at boot. Unknown filters pass the value through unchanged.

### Per-Channel Format Adapters

Each `ChannelType` has a corresponding format adapter that transforms rendered template output (title + body strings) into the channel's native payload format.

```php
namespace Aicl\Notifications\Templates\Contracts;

interface ChannelFormatAdapter
{
    public function format(array $rendered, array $context): array;
    public function channelType(): ChannelType;
}
```

**Input shape:** `{title: string, body: string, action_url: ?string, action_text: ?string, color: ?string}`

**Built-in adapters:**

| Adapter | Channel Type | Output Format |
|---------|-------------|---------------|
| `PlainTextAdapter` | SMS | Plain text (HTML stripped) |
| `SlackBlockAdapter` | Slack | Slack Block Kit JSON (text, attachments, actions) |
| `EmailHtmlAdapter` | Email | HTML email body with inline CSS |
| `TeamsCardAdapter` | Teams | Adaptive Card JSON |
| `PagerDutyAdapter` | PagerDuty | Events API v2 payload |
| `WebhookJsonAdapter` | Webhook | Full rendered payload as JSON |

Adapters are registered in a `FormatAdapterRegistry` singleton, keyed by `ChannelType`. Client apps can replace or add adapters.

### MessageTemplateRenderer

The core renderer service with three key methods:

```php
namespace Aicl\Notifications\Templates;

class MessageTemplateRenderer
{
    public function __construct(
        protected FilterRegistry $filterRegistry,
        protected FormatAdapterRegistry $formatAdapterRegistry,
    ) {}

    // Register a variable resolver for a prefix
    public function registerResolver(string $prefix, VariableResolver $resolver): void;

    // Render a template string with context variables and filters
    public function render(string $template, array $context): string;

    // Render title + body templates, then format for a channel type
    public function renderForChannel(array $templates, array $context, ChannelType $channelType): array;

    // Resolve a template from a channel's message_templates JSONB
    public function resolveTemplate(NotificationChannel $channel, string $notificationClass): ?array;
}
```

### Template Storage

Templates are stored in `notification_channels.message_templates` (JSONB column), keyed by notification class name:

```json
{
    "Aicl\\Notifications\\RlmFailureAssignedNotification": {
        "title": "Failure {{ model.failure_code }} assigned",
        "body": "{{ model.title }} was assigned to you by {{ user.name }}."
    },
    "_default": {
        "title": "{{ title }}",
        "body": "{{ body }}"
    }
}
```

**Resolution order:**
1. Exact match on notification's fully-qualified class name
2. Fall back to `_default` key
3. Fall back to the notification's hardcoded `toDatabase()` output (no template rendering)

Templates are optional and additive. A channel with no `message_templates` configured works exactly as it did before the template engine existed.

### Integration with NotificationDispatcher

Template rendering occurs in `dispatchToExternalChannel()`, between payload extraction and delivery log creation:

```
dispatchToExternalChannel()
  1. $rawPayload = $notification->toDatabase($notifiable)
  2. $template = $renderer->resolveTemplate($channel, notificationClass)
  3. if ($template):
       $context = buildTemplateContext(notification, notifiable, channel, rawPayload)
       $payload = $renderer->renderForChannel($template, $context, $channel->type)
     else:
       $payload = $rawPayload  // backward compatibility
  4. DeliveryLog::create(['payload' => $payload])
  5. RetryNotificationDelivery::dispatch()
```

**Context building** (`buildTemplateContext`) uses reflection to extract model and sender from the notification's promoted constructor parameters:
- First promoted parameter of type `Model` becomes `$context['model']`
- First promoted parameter implementing `Authenticatable` becomes `$context['user']`
- Falls back to `auth()->user()` for sender if no explicit sender found
- Raw payload keys (`title`, `body`, etc.) are merged for direct variable access

### Backward Compatibility

- Drivers retain their existing `buildPayload()` methods as fallback
- Format adapters only run when templates are configured
- No template = driver receives the raw `toDatabase()` payload, same as before 3.2
- The `TemplateExamples` class provides example templates for built-in notification types (for seeders and documentation, not auto-applied)

---

## 3.5 Live Ops Panel

### ServiceHealthCheck Contract

Every health check implements this contract. Implementations MUST catch their own exceptions and return a down/degraded result -- never throw.

```php
namespace Aicl\Health\Contracts;

interface ServiceHealthCheck
{
    public function check(): ServiceCheckResult;
    public function order(): int;  // Lower = higher in the list
}
```

### ServiceCheckResult

An immutable value object (DTO) carrying check results:

```php
namespace Aicl\Health;

class ServiceCheckResult
{
    public function __construct(
        public readonly string $name,
        public readonly ServiceStatus $status,
        public readonly string $icon,
        public readonly array $details = [],   // key => value display pairs
        public readonly ?string $error = null,
    ) {}

    public static function healthy(string $name, string $icon, array $details = []): static;
    public static function degraded(string $name, string $icon, array $details = [], ?string $error = null): static;
    public static function down(string $name, string $icon, ?string $error = null): static;
}
```

### ServiceStatus Enum

```php
namespace Aicl\Health;

enum ServiceStatus: string
{
    case Healthy = 'healthy';   // color: success, icon: check-circle
    case Degraded = 'degraded'; // color: warning, icon: exclamation-triangle
    case Down = 'down';         // color: danger, icon: x-circle
}
```

### HealthCheckRegistry

A singleton holding class names (not instances). Checks are resolved fresh from the container on each `runAll()` call.

```php
namespace Aicl\Health;

class HealthCheckRegistry
{
    public function register(string $checkClass): void;        // Deduplicated
    public function registerMany(array $checkClasses): void;
    public function runAll(): array;                           // Returns ServiceCheckResult[] sorted by order()
    public function registered(): array;                       // Returns class names
}
```

### Built-in Health Checks

Six checks ship with AICL, all in `packages/aicl/src/Health/Checks/`:

| Check | Order | What It Checks | Status Logic |
|-------|-------|---------------|--------------|
| `SwooleCheck` | 10 | Swoole extension, Octane availability, coroutine count, memory | Healthy if Swoole + Octane detected; degraded if extension loaded but not under Octane; down if extension not loaded |
| `PostgresCheck` | 20 | PDO connection, `SELECT version()`, `pg_stat_activity` count, `pg_database_size()` | Healthy if all queries succeed; down on connection/query failure |
| `RedisCheck` | 30 | Redis `INFO` (version, memory, clients, keys, uptime) | Healthy if info returns data; degraded if memory > 90% of maxmemory; down on connection failure |
| `ElasticsearchCheck` | 40 | `_cluster/health`, `_cat/indices` (nodes, indices, doc count) | Healthy if cluster green; degraded if yellow; down if red or connection fails |
| `QueueCheck` | 50 | Redis `LLEN` per queue, `failed_jobs` count | Healthy if all queues reachable and failed jobs below threshold; degraded if failed jobs >= threshold |
| `ApplicationCheck` | 60 | PHP version, Laravel version, Octane driver, cache/session/queue drivers, environment, debug mode | Healthy always; degraded if debug mode enabled in production |

Every check follows the same try/catch pattern:

```php
public function check(): ServiceCheckResult
{
    try {
        $data = $this->gatherStats();
        return ServiceCheckResult::healthy('Name', 'icon', $data);
    } catch (Throwable $e) {
        return ServiceCheckResult::down('Name', 'icon', $e->getMessage());
    }
}
```

### OpsPanel Filament Page

```php
namespace Aicl\Filament\Pages;

class OpsPanel extends Page
{
    // Navigation: System group, sort 10, slug 'ops-panel'
    // View: 'aicl::filament.pages.ops-panel'

    public function getServiceChecks(): array;          // Resolves from HealthCheckRegistry
    public function getRecentErrors(): Collection;      // Last 10 ERROR/CRITICAL/ALERT/EMERGENCY from log
    public function getLevelColor(string $level): string;
    public static function canAccess(): bool;           // Requires super_admin or admin role

    // Header action: manual Refresh button
}
```

**Blade template layout:**
- `wire:poll.30s` on the outermost container
- 3-column responsive grid (`grid-cols-1 md:grid-cols-2 lg:grid-cols-3`) for service checks
- Each check rendered as a `<x-filament::section>` with status badge, icon, and key-value detail list
- Recent Errors section at the bottom showing last 10 error-level log entries with level badge, truncated message, and timestamp
- No custom CSS -- pure Filament component composition

---

## Key Design Decisions

### Why Not Blade for Templates

Blade compiles to PHP and executes via `eval()`. Admin-editable templates with `eval()` would be a remote code execution vulnerability. Sandboxing Blade to prevent `{{ system('rm -rf /') }}` is non-trivial. The template engine deliberately avoids any PHP execution -- it is a pure string interpolation system with registered resolvers and filters.

### Why Not Twig or Mustache.php

Adding a templating library (Twig at 2.6MB, Mustache at 500KB) for simple string interpolation is disproportionate. AICL templates have no conditionals, no loops, no partials, no includes. The full feature set would go unused while adding dependency surface area.

### Why JSONB Column, Not a Separate MessageTemplate Model

Templates are per-channel configuration, not independent entities. A separate model would add a migration, model class, factory, and relationship methods for what is a configuration detail. JSONB keeps templates co-located with the channel, enables atomic updates, and avoids join queries. The cardinality (5-20 templates per channel) fits comfortably in a JSONB column.

### Why message_templates Is Not Encrypted

Unlike `config` (which holds API keys, webhook URLs, auth tokens), templates contain only display strings with variable placeholders. No sensitive data. Keeping them unencrypted enables direct database queries, debugging, and bulk updates.

### Why a Separate NotificationDeliveryLog Model

The existing `NotificationLog` is clean and focused on notification-level auditing (who received what, on which Laravel channels). Delivery tracking has different concerns: HTTP responses, retry counts, rate limiting timestamps. Two focused models are simpler than one bloated model. The `notification_log_id` FK ties them together.

### Why Not Spatie/Laravel-Health

Spatie's health package adds a dependency, has its own views/routing, and stores check results in the database for historical trends. AICL only needs Phase 1 (static checks, no history). The built-in implementation is approximately 200 lines of simple code with zero dependencies, fully integrated into the Filament panel and extension point patterns. Spatie can be integrated later if historical health data becomes necessary.

### Why Not Laravel Native Notification Channels

Laravel's native `via()` channel system requires defining channel classes per driver type but does not provide database-backed channel configuration, encrypted secrets, retry, or rate limiting. The driver pattern is simpler and more focused for the external delivery use case.

### Why Queue-Based Retry, Not Inline

Inline retry blocks the request (even if async under Swoole). Queue-based retry uses Laravel's existing job infrastructure, supports delayed dispatch, and is observable. Each retry attempt is a separate job execution -- clean, auditable, and non-blocking.

### Why Rate Limiting Queues Excess Rather Than Dropping

Dropping notifications silently is unacceptable for production systems. Rate-limited notifications are deferred with `next_retry_at` set to when the limit resets, and a delayed job handles delivery when the window opens.

---

## Extension Points

### Notification Channel Drivers

| Extension | How |
|-----------|-----|
| Add a custom driver | Implement `NotificationChannelDriver`, call `$registry->register(ChannelType, DriverClass)` in service provider |
| Override a built-in driver | Same as above -- later registration wins |
| Custom channel routing | Implement `NotificationChannelResolver`, set in `aicl.notifications.channel_resolver` config |
| Custom recipient routing | Implement `NotificationRecipientResolver`, set in `aicl.notifications.recipient_resolver` config |
| React to notification lifecycle | Listen to `NotificationSending` (cancellable) and `NotificationDispatched` events |
| Opt a notification into external channels | Implement `HasExternalChannels` on the notification class |

### Message Template Engine

| Extension | How |
|-----------|-----|
| Add a custom variable prefix | Call `$renderer->registerResolver('prefix', new CustomResolver())` in service provider |
| Add a custom filter | Call `$filterRegistry->register('name', new CustomFilter())` in service provider |
| Replace a format adapter | Call `$formatAdapterRegistry->register(ChannelType, new CustomAdapter())` in service provider |
| Configure templates for a channel | Set the `message_templates` JSONB column on the `NotificationChannel` model |

### Live Ops Panel

| Extension | How |
|-----------|-----|
| Add a custom health check | Implement `ServiceHealthCheck`, call `$registry->register(CheckClass::class)` in service provider boot |
| Add multiple checks | Call `$registry->registerMany([...])` |
| Custom check ordering | Return desired `order()` value (built-in checks use 10-60 in increments of 10) |

---

## Swoole/Octane Safety

All Sprint C singletons are safe under Swoole/Octane -- they hold configuration data registered at boot time and do not mutate per-request state.

| Singleton | State | Safety |
|-----------|-------|--------|
| `DriverRegistry` | Class name map (`ChannelType => string`), populated at boot | Immutable after boot. No per-request state. |
| `ChannelRateLimiter` | Stateless. Delegates to Laravel `RateLimiter` (Redis-backed). | No instance state. Safe. |
| `FilterRegistry` | Filter instance map, populated at boot | Immutable after boot. Filter instances are stateless. |
| `FormatAdapterRegistry` | Adapter instance map, populated at boot | Immutable after boot. Adapter instances are stateless. |
| `MessageTemplateRenderer` | Resolver map, populated at boot. Filter/adapter registries injected. | Immutable after boot. Resolvers are stateless (read from context array, not instance state). |
| `HealthCheckRegistry` | Class name array, populated at boot | Holds class names, not instances. Checks resolved fresh from container per `runAll()` call. |
| `NotificationDispatcher` | Injected dependencies (registry, rate limiter, optional resolvers) | All dependencies are singletons or stateless. No per-request state. |

---

## File Inventory

### 3.1 Notification Channel Drivers

| File | Location |
|------|----------|
| `ChannelType` enum | `packages/aicl/src/Notifications/Enums/ChannelType.php` |
| `DeliveryStatus` enum | `packages/aicl/src/Notifications/Enums/DeliveryStatus.php` |
| `NotificationChannelDriver` interface | `packages/aicl/src/Notifications/Contracts/NotificationChannelDriver.php` |
| `HasExternalChannels` interface | `packages/aicl/src/Notifications/Contracts/HasExternalChannels.php` |
| `NotificationChannelResolver` interface | `packages/aicl/src/Notifications/Contracts/NotificationChannelResolver.php` |
| `NotificationRecipientResolver` interface | `packages/aicl/src/Notifications/Contracts/NotificationRecipientResolver.php` |
| `DriverResult` value object | `packages/aicl/src/Notifications/DriverResult.php` |
| `DriverRegistry` | `packages/aicl/src/Notifications/DriverRegistry.php` |
| `ChannelRateLimiter` | `packages/aicl/src/Notifications/ChannelRateLimiter.php` |
| `NotificationChannel` model | `packages/aicl/src/Notifications/Models/NotificationChannel.php` |
| `NotificationDeliveryLog` model | `packages/aicl/src/Notifications/Models/NotificationDeliveryLog.php` |
| `SlackDriver` | `packages/aicl/src/Notifications/Drivers/SlackDriver.php` |
| `EmailDriver` | `packages/aicl/src/Notifications/Drivers/EmailDriver.php` |
| `WebhookDriver` | `packages/aicl/src/Notifications/Drivers/WebhookDriver.php` |
| `PagerDutyDriver` | `packages/aicl/src/Notifications/Drivers/PagerDutyDriver.php` |
| `TeamsDriver` | `packages/aicl/src/Notifications/Drivers/TeamsDriver.php` |
| `SmsDriver` | `packages/aicl/src/Notifications/Drivers/SmsDriver.php` |
| `RetryNotificationDelivery` job | `packages/aicl/src/Notifications/Jobs/RetryNotificationDelivery.php` |
| `NotificationSending` event | `packages/aicl/src/Notifications/Events/NotificationSending.php` |
| `NotificationDispatched` event | `packages/aicl/src/Notifications/Events/NotificationDispatched.php` |

### 3.2 Message Template Engine

| File | Location |
|------|----------|
| `VariableResolver` interface | `packages/aicl/src/Notifications/Templates/Contracts/VariableResolver.php` |
| `TemplateFilter` interface | `packages/aicl/src/Notifications/Templates/Contracts/TemplateFilter.php` |
| `ChannelFormatAdapter` interface | `packages/aicl/src/Notifications/Templates/Contracts/ChannelFormatAdapter.php` |
| `MessageTemplateRenderer` | `packages/aicl/src/Notifications/Templates/MessageTemplateRenderer.php` |
| `FilterRegistry` | `packages/aicl/src/Notifications/Templates/FilterRegistry.php` |
| `FormatAdapterRegistry` | `packages/aicl/src/Notifications/Templates/FormatAdapterRegistry.php` |
| `TemplateExamples` | `packages/aicl/src/Notifications/Templates/TemplateExamples.php` |
| `ModelVariableResolver` | `packages/aicl/src/Notifications/Templates/Resolvers/ModelVariableResolver.php` |
| `UserVariableResolver` | `packages/aicl/src/Notifications/Templates/Resolvers/UserVariableResolver.php` |
| `RecipientVariableResolver` | `packages/aicl/src/Notifications/Templates/Resolvers/RecipientVariableResolver.php` |
| `AppVariableResolver` | `packages/aicl/src/Notifications/Templates/Resolvers/AppVariableResolver.php` |
| `ChannelVariableResolver` | `packages/aicl/src/Notifications/Templates/Resolvers/ChannelVariableResolver.php` |
| `UpperFilter` | `packages/aicl/src/Notifications/Templates/Filters/UpperFilter.php` |
| `LowerFilter` | `packages/aicl/src/Notifications/Templates/Filters/LowerFilter.php` |
| `TitleFilter` | `packages/aicl/src/Notifications/Templates/Filters/TitleFilter.php` |
| `TruncateFilter` | `packages/aicl/src/Notifications/Templates/Filters/TruncateFilter.php` |
| `RelativeFilter` | `packages/aicl/src/Notifications/Templates/Filters/RelativeFilter.php` |
| `DateFilter` | `packages/aicl/src/Notifications/Templates/Filters/DateFilter.php` |
| `DefaultFilter` | `packages/aicl/src/Notifications/Templates/Filters/DefaultFilter.php` |
| `RawFilter` | `packages/aicl/src/Notifications/Templates/Filters/RawFilter.php` |
| `Nl2brFilter` | `packages/aicl/src/Notifications/Templates/Filters/Nl2brFilter.php` |
| `StripTagsFilter` | `packages/aicl/src/Notifications/Templates/Filters/StripTagsFilter.php` |
| `NumberFilter` | `packages/aicl/src/Notifications/Templates/Filters/NumberFilter.php` |
| `PlainTextAdapter` | `packages/aicl/src/Notifications/Templates/Adapters/PlainTextAdapter.php` |
| `SlackBlockAdapter` | `packages/aicl/src/Notifications/Templates/Adapters/SlackBlockAdapter.php` |
| `EmailHtmlAdapter` | `packages/aicl/src/Notifications/Templates/Adapters/EmailHtmlAdapter.php` |
| `TeamsCardAdapter` | `packages/aicl/src/Notifications/Templates/Adapters/TeamsCardAdapter.php` |
| `PagerDutyAdapter` | `packages/aicl/src/Notifications/Templates/Adapters/PagerDutyAdapter.php` |
| `WebhookJsonAdapter` | `packages/aicl/src/Notifications/Templates/Adapters/WebhookJsonAdapter.php` |

### 3.5 Live Ops Panel

| File | Location |
|------|----------|
| `ServiceStatus` enum | `packages/aicl/src/Health/ServiceStatus.php` |
| `ServiceCheckResult` value object | `packages/aicl/src/Health/ServiceCheckResult.php` |
| `ServiceHealthCheck` interface | `packages/aicl/src/Health/Contracts/ServiceHealthCheck.php` |
| `HealthCheckRegistry` | `packages/aicl/src/Health/HealthCheckRegistry.php` |
| `SwooleCheck` | `packages/aicl/src/Health/Checks/SwooleCheck.php` |
| `PostgresCheck` | `packages/aicl/src/Health/Checks/PostgresCheck.php` |
| `RedisCheck` | `packages/aicl/src/Health/Checks/RedisCheck.php` |
| `ElasticsearchCheck` | `packages/aicl/src/Health/Checks/ElasticsearchCheck.php` |
| `QueueCheck` | `packages/aicl/src/Health/Checks/QueueCheck.php` |
| `ApplicationCheck` | `packages/aicl/src/Health/Checks/ApplicationCheck.php` |
| `OpsPanel` page | `packages/aicl/src/Filament/Pages/OpsPanel.php` |
| Ops Panel Blade template | `packages/aicl/resources/views/filament/pages/ops-panel.blade.php` |

### Modified Files (Across All 3 Items)

| File | Changes |
|------|---------|
| `NotificationDispatcher` | Enhanced constructor (DriverRegistry, ChannelRateLimiter, optional resolvers). `send()` fires events, dispatches to external channels. `dispatchToExternalChannel()` with template rendering. `buildTemplateContext()` added. |
| `AiclServiceProvider` | Singleton registrations: DriverRegistry, ChannelRateLimiter, FilterRegistry, FormatAdapterRegistry, MessageTemplateRenderer, HealthCheckRegistry. NotificationDispatcher binding updated. Optional resolver bindings from config. |
| `AiclPlugin` | OpsPanel added to `getPages()`. |
| `config/aicl.php` | `notifications` key with channels, resolvers, retry, queue, and templates sub-keys. |

---

## External Pipeline Activation (Sprint G)

As of Sprint G (v1.9.0), the external notification pipeline is **live**:

- `aicl:install` seeds 3 demo channels via `NotificationChannelSeeder`: Email (active), Slack (inactive), Webhook (inactive). Idempotent via `updateOrCreate`.
- `RlmFailureAssignedNotification` implements `HasExternalChannels` as the **reference implementation**, returning all active channels.
- Health check results on OpsPanel are cached via `HealthCheckRegistry::runAllCached()` (Redis, 30s TTL) with a "Force Refresh" button for live probes.

To activate external channels for your own notifications, implement `HasExternalChannels` on the notification class and return the channels to target. See `packages/aicl/docs/notification-drivers.md` for the full usage guide.

---

## Relationship to Other Sprints

- **Sprint A (Swoole Foundations):** The Approval Workflow (Sprint A) integrates with AICL's notification system. Channel drivers provide additional delivery paths for approval notifications. SwooleCheck in the Ops Panel reports on Swoole health established in Sprint A.
- **Sprint B (Event & Realtime):** The domain event bus may trigger notifications through the channel driver system in future integrations.
- **Sprint D (Scaffolding & AI):** Generated entities may declare external channel configurations. RLM patterns may validate notification template syntax.
- **Sprint E (Swoole Cache Wiring):** Channel and driver configuration could be cached in Swoole tables for zero-latency lookups.
- **Sprint G (Remaining Wiring):** External pipeline activated with seeded channels and reference implementation. OpsPanel health checks now cached via Redis.
