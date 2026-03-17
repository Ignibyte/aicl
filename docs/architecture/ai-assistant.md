# AI Assistant Architecture

This document provides a complete reference for the in-app AI assistant. It covers the full request lifecycle, every service dependency, configuration, the tool system, troubleshooting procedures, and production hardening. If the assistant is broken, this document tells you exactly what to check and how to fix it.

---

## What the AI Assistant Is

The AI assistant is a floating conversational panel embedded in the Filament admin interface. It supports multi-turn conversations with persistent history stored in PostgreSQL, real-time token streaming via Reverb WebSockets, tool calling with structured result rendering, and automatic conversation compaction (summarization) when the message count exceeds a configurable threshold.

**Keyboard shortcut:** Cmd+J (configurable via `config('aicl.ai.assistant.keyboard_shortcut')`)

**Providers supported:** OpenAI, Anthropic, Ollama (via NeuronAI abstraction), plus a Custom driver hook.

**Access control:** Visible only to users with roles in `config('aicl.ai.assistant.allowed_roles')` (defaults to `['super_admin', 'admin']`). Per-agent visibility is further scoped via `visible_to_roles` on the `AiAgent` model.

---

## Complete Architecture Flow

```
User opens panel (Cmd+J)
  |  Alpine.js aiAssistantPanel component (aicl-widgets.js)
  v
User types message and presses Enter
  |  Alpine.js calls this.$wire.sendMessage(userPrompt)
  v
Livewire AiAssistantPanel.sendMessage()
  |  Creates conversation if none active (AiConversation::create)
  |  Auto-titles conversation from first message
  |  Calls AiChatService->sendMessage()
  v
AiChatService.sendMessage()
  |  1. Validates agent is active + configured (is_active && is_configured)
  |  2. Validates role-based access (agent->isAccessibleByUser)
  |  3. Creates AiMessage (role: User) in PostgreSQL
  |  4. Generates stream UUID
  |  5. Stores stream auth: Cache::put("ai-stream:{streamId}:user", userId, 300)
  |  6. Enforces concurrent stream limit (Redis atomic counter)
  |  7. Dispatches AiConversationStreamJob to Redis queue
  |  Returns: {stream_id, channel, message_id}
  v
Alpine.js receives result, calls _connectToStream(stream_id, channel)
  |  Opens raw WebSocket to Reverb: ws://{host}:{port}/app/{key}?protocol=7
  |  On connection_established: fetches auth from /broadcasting/auth (POST with CSRF)
  |  Sends pusher:subscribe with auth token for private-ai.stream.{streamId}
  v
Horizon picks up AiConversationStreamJob from 'default' queue
  |  1. Loads AiConversation + AiAgent
  |  2. broadcast(AiStreamStarted) -> Reverb
  |  3. AiProviderFactory::makeFromAgent(agent) -> NeuronAI provider
  |  4. Builds NeuronAI Agent with provider + system prompt + tools
  |  5. AiChatService->buildMessageHistory() loads recent N messages + summary
  |  6. Calls neuronAgent->stream(messages)
  |  7. For each chunk:
  |     - ToolCallMessage -> broadcast(AiToolCallEvent) -> Reverb
  |     - Text token     -> broadcast(AiTokenEvent)     -> Reverb
  |  8. Persists AiMessage (role: Assistant) with cleaned content + metadata
  |  9. broadcast(AiStreamCompleted) -> Reverb
  |  10. If conversation.is_compactable -> dispatches CompactConversationJob
  |  Finally: decrements concurrent stream counter
  v
Reverb delivers broadcast events to subscribed WebSocket client
  v
Alpine.js processes events:
  - ai.started:   no-op (stream in progress)
  - ai.tool_call: appends tool chips to current message
  - ai.token:     appends token to _currentResponse, strips tool JSON, renders markdown
  - ai.completed: final cleanup, closes WebSocket
  - ai.failed:    shows error, closes WebSocket
```

---

## Service Dependencies

Five services must be running simultaneously for the AI assistant to function. If any one fails, the assistant breaks in a specific, diagnosable way.

| Service | What It Does for the Assistant | If It Is Down |
|---------|-------------------------------|---------------|
| **Swoole/Octane** | Serves the HTTP request (Livewire `sendMessage`, `/broadcasting/auth`) | Entire site is unreachable |
| **Redis** | Queue backend for jobs, stream auth cache (`ai-stream:{streamId}:user`), concurrent stream counter | Jobs cannot be dispatched; channel auth fails (403); rate limiting fails |
| **Horizon** | Processes `AiConversationStreamJob` from the `default` queue | Jobs sit in queue forever; user sees infinite loading spinner; no tokens streamed |
| **Reverb** | Delivers `AiTokenEvent`, `AiToolCallEvent`, `AiStreamCompleted`, `AiStreamFailed` via WebSocket | Tokens are generated server-side but never reach the browser; user sees infinite loading spinner |
| **PostgreSQL** | Stores `ai_agents`, `ai_conversations`, `ai_messages` | Cannot load agents, create conversations, or persist messages |

### DDEV Daemon Configuration

All services run as DDEV web extra daemons via supervisord (defined in `.ddev/config.yaml`):

```yaml
web_extra_daemons:
    - name: octane
      command: php /var/www/html/artisan octane:start --server=swoole --host=0.0.0.0 --port=8000 --workers=4 --task-workers=2 --max-requests=500
    - name: reverb
      command: php /var/www/html/artisan reverb:start --host=0.0.0.0 --port=8080
    - name: horizon
      command: php /var/www/html/artisan aicl:horizon
    - name: schedule
      command: php /var/www/html/artisan schedule:work
```

Port mapping:
- Octane: container 8000, HTTPS 8443 (proxied by nginx via `try_files @octane`)
- Reverb: container 8080, HTTPS 8080

---

## Configuration Reference

All AI assistant configuration lives in `packages/aicl/config/aicl.php` under the `'ai'` key. Override values in your project's `config/aicl.php` or `config/local.php` using dot notation.

```php
'ai' => [
    // Global provider default (used by AiProviderFactory::make when no agent is specified)
    'provider' => 'openai',                     // 'openai', 'anthropic', 'ollama'

    // Global tool toggle (must be true for any agent to use tools)
    'tools_enabled' => true,

    // Additional custom tool classes registered at boot
    'tools' => [
        // App\AI\Tools\MyCustomTool::class,
    ],

    // Provider credentials — ALL null by default, MUST be set in config/local.php
    'openai' => [
        'api_key' => null,                      // REQUIRED if provider = openai
        'model' => 'gpt-4o-mini',
    ],
    'anthropic' => [
        'api_key' => null,                      // REQUIRED if provider = anthropic
        'model' => 'claude-haiku-4-5-20251001',
    ],
    'ollama' => [
        'host' => 'http://localhost:11434',     // Ollama needs no API key
        'model' => 'llama3.2',
    ],

    // Default system prompt (used when agent has no custom system_prompt)
    'system_prompt' => 'You are a helpful assistant for this application. Answer questions clearly and concisely.',
    'max_prompt_length' => 2000,

    // API rate limiting
    'rate_limit' => [
        'max_attempts' => 10,
        'decay_minutes' => 1,
    ],

    // Streaming job configuration
    'streaming' => [
        'queue' => 'default',                   // Queue name for AiConversationStreamJob
        'timeout' => 120,                       // Job timeout in seconds
        'max_concurrent_per_user' => 2,         // Max simultaneous streams per user
        'reverb' => [
            'host' => 'localhost',              // Must match Reverb server config
            'port' => 8080,                     // Must match DDEV exposed port
            'scheme' => 'http',                 // 'http' or 'https'
        ],
    ],

    // Assistant panel behavior
    'assistant' => [
        'enabled' => false,                     // MUST be set to true to show the widget
        'keyboard_shortcut' => 'cmd+j',
        'default_agent' => null,                // UUID of default agent
        'allowed_roles' => ['super_admin', 'admin'],
        'compaction_threshold' => 50,           // Messages before auto-compaction
        'compaction_delete_old_messages' => false,
        'token_budget_daily' => null,           // null = unlimited
        'context_injection' => true,
    ],
],
```

### Required config/local.php Settings

```php
return [
    // CRITICAL: Enable the assistant widget
    'aicl.ai.assistant.enabled' => true,

    // REQUIRED: At least one provider API key
    'aicl.ai.openai.api_key' => 'sk-...',           // If using OpenAI
    'aicl.ai.anthropic.api_key' => 'sk-ant-...',     // If using Anthropic

    // Broadcasting (must match Reverb config for channel auth to work)
    'broadcasting.connections.reverb.key' => 'your-reverb-key',
    'broadcasting.connections.reverb.secret' => 'your-reverb-secret',
];
```

### Feature Gate

The assistant widget is gated by THREE conditions (all must be true):

1. `config('aicl.ai.assistant.enabled')` is `true` (registered in `AiclPlugin::boot()` as a render hook)
2. The current user is authenticated
3. The current user has one of the roles in `config('aicl.ai.assistant.allowed_roles')`

The render hook is registered at `PanelsRenderHook::BODY_END` in `AiclPlugin::boot()`. If `enabled` is `false`, the render hook is never registered and the Livewire component is never rendered.

---

## Data Models

### AiAgent

Provider, model, system prompt, capabilities, and access control configuration.

| Field | Type | Purpose |
|-------|------|---------|
| `provider` | `AiProvider` enum | `openai`, `anthropic`, `ollama`, `custom` |
| `model` | string | Model identifier (e.g., `gpt-4o-mini`, `claude-haiku-4-5-20251001`) |
| `system_prompt` | text, nullable | Per-agent system prompt override |
| `max_tokens` | integer | Max output tokens (0 = provider default) |
| `temperature` | decimal(4,2) | Sampling temperature |
| `context_window` | integer | Provider's context window size |
| `context_messages` | integer | Number of recent messages to include in history |
| `is_active` | boolean | Toggle to disable agent |
| `capabilities` | JSON, nullable | `{tools_enabled: bool, allowed_tools: [FQCNs]}` |
| `visible_to_roles` | JSON, nullable | Role whitelist; null/empty = visible to all |
| `state` | `AiAgentState` | `Draft -> Active -> Archived` (can reactivate) |
| `is_configured` (accessor) | boolean | True if the agent's provider has a valid API key |

**State machine:** `Draft -> Active -> Archived -> Active` (reactivation allowed)

### AiConversation

Multi-turn conversation owned by a user, tied to an agent.

| Field | Type | Purpose |
|-------|------|---------|
| `user_id` | integer (FK) | Owning user |
| `ai_agent_id` | UUID (FK) | Agent used for this conversation |
| `title` | string, nullable | Auto-set from first message |
| `message_count` | integer | Cached message count |
| `token_count` | integer | Accumulated token usage |
| `summary` | text, nullable | Compaction summary of older messages |
| `is_pinned` | boolean | User-pinned conversation |
| `context_page` | string, nullable | Page URL where conversation started |
| `state` | `AiConversationState` | `Active -> Summarized -> Archived` |
| `is_compactable` (accessor) | boolean | `message_count > threshold && summary === null` |

**State machine:** `Active -> Summarized -> Archived`, `Active -> Archived`, `Archived -> Active`

### AiMessage

Individual message within a conversation.

| Field | Type | Purpose |
|-------|------|---------|
| `ai_conversation_id` | UUID (FK) | Parent conversation |
| `role` | `AiMessageRole` enum | `user`, `assistant`, `system` |
| `content` | text | Message text (tool call JSON stripped before persist) |
| `token_count` | integer, nullable | Token usage for this message |
| `metadata` | JSON, nullable | `{model, provider, usage, stream_id, tool_results}` |

---

## Tool System

### Registration

Tools are registered as a singleton `AiToolRegistry` in `AiclServiceProvider::register()`:

```php
$this->app->singleton(AiToolRegistry::class, function ($app) {
    $registry = new AiToolRegistry($app);
    $registry->registerMany([
        WhosOnlineTool::class,
        CurrentUserTool::class,
        QueryEntityTool::class,
        EntityCountTool::class,
        HealthStatusTool::class,
    ]);
    // Also registers tools from config('aicl.ai.tools')
    return $registry;
});
```

### Built-in Tools

| Tool | Name | Category | Auth Required | Render Type | Purpose |
|------|------|----------|---------------|-------------|---------|
| `CurrentUserTool` | `current_user` | system | Yes | key-value | Returns name, email, roles, member-since |
| `EntityCountTool` | `entity_count` | queries | No | key-value | Counts records by entity type, optionally grouped by status |
| `QueryEntityTool` | `query_entity` | queries | Yes | table | Queries entities with filters, respects Gate policies, blocks sensitive columns |
| `WhosOnlineTool` | `whos_online` | system | Yes | table | Lists online users from PresenceRegistry |
| `HealthStatusTool` | `health_status` | system | Yes | status | Returns service health from HealthCheckRegistry |

### Custom Tool Registration

In `config/aicl.php`:

```php
'ai' => [
    'tools' => [
        App\AI\Tools\MyCustomTool::class,
    ],
],
```

Or programmatically:

```php
app(AiToolRegistry::class)->register(MyTool::class);
```

### Tool Scoping Per Agent

Tools are filtered per agent via `AiToolRegistry::resolveForAgent()`:

1. If `config('aicl.ai.tools_enabled')` is `false` globally: no tools for any agent.
2. If `agent.capabilities.tools_enabled` is `false`: no tools for that agent.
3. If `agent.capabilities.allowed_tools` is an array of FQCNs: only those tools.
4. If `agent.capabilities.allowed_tools` is `null` or missing: all registered tools.

### Tool Render Types

```php
enum ToolRenderType: string
{
    case Text = 'text';       // Plain text (fallback)
    case Table = 'table';     // Columns + rows grid
    case KeyValue = 'key-value'; // Label-value pairs
    case Status = 'status';   // Colored status badges
}
```

The frontend renders structured tool results as cards (tables, key-value lists, status badges) alongside text responses.

### AiTool Contract

Custom tools must extend `BaseTool` (which extends NeuronAI's `Tool` and implements `AiTool`):

```php
interface AiTool extends ToolInterface
{
    public function category(): string;
    public function requiresAuth(): bool;
    public function renderAs(): ToolRenderType;
    public function formatResultForDisplay(mixed $result): array;
}
```

If `requiresAuth()` returns `true`, the tool receives the authenticated user's ID via `setAuthenticatedUser($userId)` before execution.

---

## Rate Limiting and Concurrency

### Concurrent Stream Limit

- Default: 2 concurrent streams per user (`config('aicl.ai.streaming.max_concurrent_per_user')`)
- Implementation: Redis atomic counter at key `ai-stream:user:{userId}:count` with 5-minute TTL
- Enforced in `AiChatService::sendMessage()` via `Cache::add()` + `Cache::increment()`
- Decremented in `AiConversationStreamJob::handle()` finally block
- Error message: "Too many concurrent AI streams. Please wait for a current stream to finish."

### API Rate Limit

- Defined as Laravel rate limiter `ai_assistant` in `AiclServiceProvider::boot()`
- Default: 10 requests per 1 minute, keyed by user ID (or IP for unauthenticated)
- Config: `config('aicl.ai.rate_limit.max_attempts')` and `config('aicl.ai.rate_limit.decay_minutes')`

---

## Channel Authorization

The WebSocket channel `private-ai.stream.{streamId}` is authorized in `routes/channels.php`:

```php
Broadcast::channel('ai.stream.{streamId}', function ($user, $streamId) {
    return (int) Cache::get("ai-stream:{$streamId}:user") === (int) $user->id;
});
```

**CRITICAL:** Redis returns cached integers as strings. Both sides MUST be cast to `(int)` for `===` comparison. Without the cast, `"1" === 1` is `false` and channel auth fails silently.

**Flow:**
1. `AiChatService::sendMessage()` stores `Cache::put("ai-stream:{streamId}:user", userId, 300)` (5-minute TTL)
2. Alpine.js receives `{stream_id, channel}` from Livewire
3. Alpine.js opens raw WebSocket to Reverb, gets `socket_id`
4. Alpine.js POSTs to `/broadcasting/auth` with `{socket_id, channel_name}` and CSRF token
5. Laravel's broadcasting auth checks the channel callback above
6. If auth succeeds, Alpine.js subscribes to the private channel

---

## Conversation Compaction

When a conversation's `message_count` exceeds `compaction_threshold` (default: 50) and has no existing `summary`, `AiConversationStreamJob` dispatches `CompactConversationJob`.

`CompactionService::compact()`:
1. Loads messages outside the `context_messages` window
2. Builds conversation text from old messages
3. Sends text to the agent's AI provider with a summarization prompt
4. Stores summary on the conversation
5. Transitions state to `Summarized`
6. Optionally deletes old messages if `compaction_delete_old_messages` is `true`

Future messages include the summary as a `System` message in the context window.

---

## Broadcast Events

All events implement `ShouldBroadcastNow` (no queue, immediate broadcast via Reverb).

| Event | Channel | broadcastAs | Payload |
|-------|---------|-------------|---------|
| `AiStreamStarted` | `private-ai.stream.{streamId}` | `ai.started` | `{stream_id}` |
| `AiTokenEvent` | `private-ai.stream.{streamId}` | `ai.token` | `{stream_id, token, index}` |
| `AiToolCallEvent` | `private-ai.stream.{streamId}` | `ai.tool_call` | `{stream_id, tools: [{name, inputs, render?}]}` |
| `AiStreamCompleted` | `private-ai.stream.{streamId}` | `ai.completed` | `{stream_id, total_tokens, usage}` |
| `AiStreamFailed` | `private-ai.stream.{streamId}` | `ai.failed` | `{stream_id, error}` |

---

## Frontend Architecture

### Blade View

`packages/aicl/resources/views/livewire/ai-assistant-panel.blade.php`

Injected at `PanelsRenderHook::BODY_END` by `AiclPlugin::boot()` when enabled. Passes Reverb config, broadcasting key, CSRF token, and keyboard shortcut to the Alpine.js component.

```php
x-data="aiAssistantPanel({
    csrfToken: '{{ csrf_token() }}',
    reverbHost: '{{ config('aicl.ai.streaming.reverb.host') }}',
    reverbPort: {{ (int) config('aicl.ai.streaming.reverb.port') }},
    reverbScheme: '{{ config('aicl.ai.streaming.reverb.scheme') }}',
    reverbKey: '{{ config('broadcasting.connections.reverb.key', '') }}',
    authUrl: '/broadcasting/auth',
    keyboardShortcut: '{{ config('aicl.ai.assistant.keyboard_shortcut', 'cmd+j') }}',
})"
```

### Alpine.js Component

`packages/aicl/resources/js/aicl-widgets.js` (function `aiAssistantPanel`)

- Opens a raw WebSocket directly to Reverb (not via Laravel Echo)
- Handles Pusher protocol handshake (connection_established, subscription_succeeded)
- Authenticates private channels by POSTing to `/broadcasting/auth`
- Strips tool call JSON from streamed text via `_stripToolCallJson()`
- Renders markdown via `marked` + `DOMPurify` (CDN-loaded, with fallback to basic HTML escaping)
- Renders structured tool results as cards (tables, key-value pairs, status badges)

### Two Streaming Jobs

| Job | Purpose | Tool Support |
|-----|---------|-------------|
| `AiConversationStreamJob` | Primary. Conversation-based, persists messages, supports tools. | Yes |
| `AiStreamJob` | Legacy endpoint. Stateless, no persistence, no tools. | No (intentionally disabled) |

The assistant panel exclusively uses `AiConversationStreamJob`.

---

## Troubleshooting Decision Tree

### Panel does not appear at all

1. Is `config('aicl.ai.assistant.enabled')` set to `true`?
   - Check `config/aicl.php` or `config/local.php` for `'aicl.ai.assistant.enabled' => true`
   - Default is `false` -- must be explicitly enabled
2. Does the user have a required role?
   - Check `config('aicl.ai.assistant.allowed_roles')` -- default `['super_admin', 'admin']`
   - Verify user roles: `$user->getRoleNames()`
3. Is the Livewire component registered?
   - `AiclServiceProvider::boot()` registers `aicl::ai-assistant-panel`
   - Check browser source for the Livewire component markup at the end of `<body>`

### Panel opens but messages never get a response

This is the most common failure. Walk through the chain:

**Step 1: Is Horizon running?**
```bash
ddev exec supervisorctl status webextradaemons:horizon
```
- If not running: `ddev exec supervisorctl restart webextradaemons:horizon`
- If `BACKOFF` or `FATAL`: check Horizon logs: `ddev exec tail -50 /tmp/logpipe`

**Step 2: Is the queue connection Redis?**
```bash
ddev exec php artisan tinker --execute="echo config('queue.default')"
```
- If `sync`: jobs run inline; errors appear in the HTTP response, not the queue
- Should be `redis` in production/DDEV

**Step 3: Are jobs stuck in the queue?**
```bash
ddev exec redis-cli LLEN queues:default
```
- If count > 0 and not decreasing: Horizon worker is not processing
- Check `ddev exec php artisan horizon:status`

**Step 4: Is Reverb running?**
```bash
ddev exec supervisorctl status webextradaemons:reverb
```
- If not running: `ddev exec supervisorctl restart webextradaemons:reverb`
- If `BACKOFF`: check if port 8080 is already in use inside the container

**Step 5: Check browser console for WebSocket errors**
- `WebSocket connection to 'ws://...' failed`: Reverb is down or port mismatch
- `401` or `403` on `/broadcasting/auth`: Channel authorization failing (see below)
- No WebSocket traffic at all: Broadcasting key may be empty

**Step 6: Is the AI provider API key configured?**
```bash
ddev exec php artisan tinker --execute="echo config('aicl.ai.openai.api_key') ? 'SET' : 'MISSING'"
```
- If `MISSING`: add the key to `config/local.php`
- Also check the agent's specific provider: an agent with `provider=anthropic` needs the Anthropic key, not OpenAI

**Step 7: Is there an active agent with state=Active?**
```bash
ddev exec php artisan tinker --execute="echo Aicl\Models\AiAgent::where('is_active', true)->whereState('state', Aicl\States\AiAgent\Active::class)->count()"
```
- If 0: no agents are available. Create one or activate an existing one.

### "Too many concurrent AI streams" error

The Redis counter is stuck (likely from a previously crashed job that never decremented).

```bash
# Find the user's ID
ddev exec php artisan tinker --execute="echo App\Models\User::where('email', 'admin@aicl.test')->value('id')"

# Clear the counter
ddev exec redis-cli DEL "ai-stream:user:{userId}:count"
```

### Channel auth failing (403 on /broadcasting/auth)

1. Is the user authenticated? Check browser network tab -- the auth POST should include session cookies.
2. Does the cache key exist?
   ```bash
   ddev exec redis-cli GET "ai-stream:{streamId}:user"
   ```
   - If `(nil)`: the key expired (5-minute TTL) or was never set. This means `AiChatService::sendMessage()` never ran, or more than 5 minutes elapsed.
3. Is the `(int)` cast present in `routes/channels.php`? Redis returns strings. Without casting, `"1" === 1` is `false`.
4. Check that `broadcasting.connections.reverb.key` and `broadcasting.connections.reverb.secret` are set in `config/local.php`. Empty values cause auth to fail.

### Tool calls appear but no text response

NeuronAI may stream the tool call/result JSON before the natural-language text. The frontend's `_stripToolCallJson()` hides the JSON and shows only the text portion.

- If only tool chips appear with no text: the AI provider returned only tool results and no text (valid behavior for some queries)
- If raw JSON appears in the message: `_stripToolCallJson()` is not matching the JSON format. Check the browser console for parsing errors.

### Connection timeout on WebSocket

1. Check Reverb host/port config alignment:
   ```bash
   ddev exec php artisan tinker --execute="echo json_encode(config('aicl.ai.streaming.reverb'))"
   ```
   Must match what Reverb is actually listening on (container port 8080).
2. Check DDEV port mapping: `ddev describe` -- Reverb should be accessible on HTTPS port 8080.
3. If behind a load balancer or CDN: WebSocket upgrade headers must be forwarded.

### Entire site down after ddev restart

Octane may `BACKOFF` due to a stale PID file:
```bash
ddev exec php artisan octane:stop
ddev exec supervisorctl restart webextradaemons:octane
```

### Response seems cut off or incomplete

- Check job timeout: `config('aicl.ai.streaming.timeout')` defaults to 120 seconds. Long responses from slow models may exceed this.
- Check the agent's `max_tokens` setting. 0 means provider default.
- Check Horizon failed jobs: `ddev exec php artisan horizon:failed`

---

## Key Files

| File | Purpose |
|------|---------|
| `packages/aicl/config/aicl.php` (lines 358-412) | All AI assistant configuration |
| `packages/aicl/src/AI/AiChatService.php` | Core conversation logic: send message, build history, auth |
| `packages/aicl/src/AI/Jobs/AiConversationStreamJob.php` | Primary streaming job: provider, tools, broadcasting, persistence |
| `packages/aicl/src/AI/Jobs/AiStreamJob.php` | Legacy stateless streaming job (no tools, no persistence) |
| `packages/aicl/src/AI/Jobs/CompactConversationJob.php` | Conversation compaction (summarization) |
| `packages/aicl/src/AI/CompactionService.php` | Summarization logic using the agent's provider |
| `packages/aicl/src/AI/AiProviderFactory.php` | Creates NeuronAI provider instances (OpenAI, Anthropic, Ollama) |
| `packages/aicl/src/AI/AiToolRegistry.php` | Singleton tool registry, agent-scoped resolution |
| `packages/aicl/src/AI/Contracts/AiTool.php` | Tool interface (category, auth, render type) |
| `packages/aicl/src/AI/Tools/BaseTool.php` | Abstract base for custom tools |
| `packages/aicl/src/AI/Tools/CurrentUserTool.php` | Built-in: current user info |
| `packages/aicl/src/AI/Tools/EntityCountTool.php` | Built-in: entity record counts |
| `packages/aicl/src/AI/Tools/QueryEntityTool.php` | Built-in: entity queries with filters |
| `packages/aicl/src/AI/Tools/WhosOnlineTool.php` | Built-in: online user list |
| `packages/aicl/src/AI/Tools/HealthStatusTool.php` | Built-in: service health status |
| `packages/aicl/src/AI/Enums/ToolRenderType.php` | Render type enum (text, table, key-value, status) |
| `packages/aicl/src/AI/Events/AiStreamStarted.php` | Broadcast event: stream started |
| `packages/aicl/src/AI/Events/AiTokenEvent.php` | Broadcast event: individual token |
| `packages/aicl/src/AI/Events/AiToolCallEvent.php` | Broadcast event: tool call with render data |
| `packages/aicl/src/AI/Events/AiStreamCompleted.php` | Broadcast event: stream finished |
| `packages/aicl/src/AI/Events/AiStreamFailed.php` | Broadcast event: stream error |
| `packages/aicl/src/Livewire/AiAssistantPanel.php` | Livewire component: panel state, send/switch/delete/rename |
| `packages/aicl/resources/views/livewire/ai-assistant-panel.blade.php` | Blade template: panel UI, config injection |
| `packages/aicl/resources/js/aicl-widgets.js` | Alpine.js: WebSocket handling, markdown rendering, tool cards |
| `packages/aicl/src/AiclPlugin.php` (lines 233-246) | Render hook registration (gated by enabled + role) |
| `packages/aicl/src/AiclServiceProvider.php` (lines 206-223) | Tool registry singleton + boot-time registration |
| `packages/aicl/src/Models/AiAgent.php` | Agent model: provider, capabilities, visibility, state machine |
| `packages/aicl/src/Models/AiConversation.php` | Conversation model: user-owned, state machine, compaction |
| `packages/aicl/src/Models/AiMessage.php` | Message model: role, content, metadata |
| `packages/aicl/src/Enums/AiProvider.php` | Provider enum: OpenAi, Anthropic, Ollama, Custom |
| `packages/aicl/src/Enums/AiMessageRole.php` | Message role enum: User, Assistant, System |
| `packages/aicl/src/States/AiAgent/AiAgentState.php` | Agent state machine: Draft -> Active -> Archived |
| `packages/aicl/src/States/AiConversation/AiConversationState.php` | Conversation state machine: Active -> Summarized -> Archived |
| `routes/channels.php` | WebSocket channel authorization (cache-based, int cast) |
| `.ddev/config.yaml` | DDEV daemon config: Octane, Reverb, Horizon, Schedule |

---

## Quick Health Check Script

Run this to verify all components of the AI assistant stack:

```bash
# 1. Check all daemons are running
ddev exec supervisorctl status

# 2. Check Redis is reachable
ddev exec redis-cli ping

# 3. Check Horizon is processing
ddev exec php artisan horizon:status

# 4. Check queue is not backed up
ddev exec redis-cli LLEN queues:default

# 5. Check AI provider is configured
ddev exec php artisan tinker --execute="echo Aicl\AI\AiProviderFactory::isConfigured() ? 'OK' : 'MISSING API KEY'"

# 6. Check assistant is enabled
ddev exec php artisan tinker --execute="echo config('aicl.ai.assistant.enabled') ? 'ENABLED' : 'DISABLED'"

# 7. Check active agents exist
ddev exec php artisan tinker --execute="echo 'Agents: ' . Aicl\Models\AiAgent::where('is_active', true)->count()"

# 8. Check Reverb port is listening
ddev exec curl -s -o /dev/null -w '%{http_code}' http://localhost:8080
```

---

## Production Checklist

- [ ] Set AI provider API key in `config/local.php`
- [ ] Set `'aicl.ai.assistant.enabled' => true` in config
- [ ] Set unique `broadcasting.connections.reverb.key` and `secret` (change from dev defaults)
- [ ] Verify Horizon is running and processing jobs on the `default` queue
- [ ] Verify Reverb is running and accepting WebSocket connections
- [ ] Ensure at least one `AiAgent` is `is_active=true` with `state=Active`
- [ ] Set appropriate rate limits (`aicl.ai.rate_limit.*`)
- [ ] Set appropriate concurrent stream limit (`aicl.ai.streaming.max_concurrent_per_user`)
- [ ] Monitor queue sizes (especially `default` queue) for stuck jobs
- [ ] Set up health checks for Redis, Horizon, Reverb
- [ ] Test WebSocket connectivity through any load balancer, CDN, or reverse proxy
- [ ] Verify `/broadcasting/auth` endpoint is reachable and returns 200 for authenticated users
- [ ] Set `aicl.ai.streaming.reverb.scheme` to `https` if using TLS termination
