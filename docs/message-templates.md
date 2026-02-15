# Message Template Engine

Safe, regex-based template rendering for notification messages with per-channel format adapters.

**Namespace:** `Aicl\Notifications\Templates`

## Overview

The template engine renders `{{ variable | filter }}` expressions into channel-native payloads (Slack blocks, email HTML, Teams cards, PagerDuty events, plain text, webhook JSON). No PHP execution — pure string interpolation.

Templates are stored in the `notification_channels.message_templates` JSONB column and are fully optional. Channels without templates use the notification's hardcoded `toDatabase()` output.

---

## Template Syntax

```
{{ model.title }}                      -- variable interpolation
{{ user.name | upper }}                -- single filter
{{ model.created_at | relative }}      -- date formatting
{{ model.description | truncate:50 }}  -- filter with argument
{{ model.name | lower | truncate:20 }} -- chained filters
```

### Variable Prefixes

| Prefix | Source | Example |
|--------|--------|---------|
| `model` | The entity from the notification | `{{ model.title }}` |
| `user` | The sender/actor | `{{ user.name }}` |
| `recipient` | The person receiving the notification | `{{ recipient.email }}` |
| `app` | Application config (allowlisted) | `{{ app.name }}` |
| `channel` | The notification channel (`config` denylisted) | `{{ channel.name }}` |

Dot-notation traversal works for relationships: `{{ model.assignee.name }}` resolves `$model->assignee->name` with null safety.

### Built-in Filters

| Filter | Argument | Behavior |
|--------|----------|----------|
| `upper` | — | `mb_strtoupper()` |
| `lower` | — | `mb_strtolower()` |
| `title` | — | Title case |
| `truncate` | `length` | `Str::limit($value, $arg)` |
| `relative` | — | `Carbon::parse()->diffForHumans()` |
| `date` | `format` | `Carbon::parse()->format($arg)` |
| `default` | `fallback` | Return fallback if value is empty |
| `raw` | — | Skip HTML escaping |
| `nl2br` | — | Newlines to `<br>` |
| `strip_tags` | — | Remove HTML tags |
| `number` | `decimals` | `number_format()` |

---

## Template Storage

Templates are stored per-channel in the `message_templates` JSONB column, keyed by notification class:

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
1. Exact match on notification class name
2. `_default` key
3. Notification's hardcoded `toDatabase()` output (no template rendering)

---

## Per-Channel Format Adapters

Same template data, different output per channel type:

| Adapter | Channel | Output |
|---------|---------|--------|
| `PlainTextAdapter` | SMS | Plain text (HTML stripped) |
| `SlackBlockAdapter` | Slack | Block Kit JSON |
| `EmailHtmlAdapter` | Email | HTML with inline CSS |
| `TeamsCardAdapter` | Teams | Adaptive Card JSON |
| `PagerDutyAdapter` | PagerDuty | Events API v2 payload |
| `WebhookJsonAdapter` | Webhook | Full payload as JSON |

---

## Using the Renderer

```php
use Aicl\Notifications\Templates\MessageTemplateRenderer;

$renderer = app(MessageTemplateRenderer::class);

// Render a template string
$output = $renderer->render(
    '{{ model.title | upper }} assigned to {{ user.name }}',
    ['model' => $failure, 'user' => $admin]
);

// Render for a specific channel type
$payload = $renderer->renderForChannel(
    ['title' => 'Alert: {{ model.title }}', 'body' => '{{ model.description }}'],
    ['model' => $failure, 'user' => $admin],
    ChannelType::Slack
);
```

---

## Custom Resolvers

Register a custom variable prefix:

```php
use Aicl\Notifications\Templates\Contracts\VariableResolver;

class OrganizationResolver implements VariableResolver
{
    public function resolve(string $field, array $context): ?string
    {
        $org = $context['organization'] ?? null;
        return $org?->getAttribute($field);
    }
}

// In your service provider
app(MessageTemplateRenderer::class)->registerResolver('org', new OrganizationResolver());
```

Now use `{{ org.name }}` in templates.

## Custom Filters

```php
use Aicl\Notifications\Templates\Contracts\TemplateFilter;

class CurrencyFilter implements TemplateFilter
{
    public function apply(string $value, ?string $argument, array $context): string
    {
        return '$' . number_format((float) $value, 2);
    }
}

// In your service provider
app(FilterRegistry::class)->register('currency', new CurrencyFilter());
```

Now use `{{ model.total | currency }}` in templates.

---

## Safety

- No PHP execution — pure regex-based string interpolation
- HTML entities escaped by default (configurable via `aicl.notifications.templates.escape_html`)
- `app` resolver uses explicit allowlist (`name`, `url`, `env`, `timezone`)
- `channel` resolver denylists the `config` property (contains encrypted secrets)
- Unknown filters pass through unchanged
