# Reverb WebSocket Architecture

## Purpose

Laravel Reverb is the first-party WebSocket server used by AICL for all real-time communication. It replaces third-party services like Pusher, Ably, and Socket.io. Reverb implements the Pusher protocol, making it compatible with the Pusher JavaScript client and Laravel Echo.

AICL uses Reverb for:
- AI assistant streaming (token-by-token response delivery via queued jobs)
- Real-time entity event broadcasts (created, updated, deleted)
- Presence tracking (who is online, who is on this page)
- Dashboard live updates
- Livewire real-time polling augmentation

Reverb runs as a separate process from Swoole/Octane. This is a critical architectural decision -- SSE (Server-Sent Events) was removed in Sprint H because each SSE connection holds an entire Swoole worker. With 4 workers, 4 SSE connections would exhaust all workers and make the app unresponsive. WebSockets via Reverb use a dedicated process, solving this completely.

## Dependencies

- `laravel/reverb` (Composer package)
- `laravel-echo` + `pusher-js` (npm packages for Echo-based subscriptions)
- Redis (for session/cache-based channel authorization and optional scaling)
- Horizon (for processing queued broadcast jobs)
- Supervisord (DDEV process management)

## Key Files

| File | Purpose |
|------|---------|
| `config/reverb.php` | Reverb server and app configuration |
| `config/broadcasting.php` | Laravel broadcast driver + Reverb connection settings |
| `packages/aicl/config/aicl.php` | Feature flag (`features.websockets`) and AI streaming Reverb config |
| `.ddev/config.yaml` | DDEV daemon definition and port exposure |
| `.ddev/.webimageBuild/reverb.conf` | Supervisord process config for Reverb |
| `.ddev/nginx_full/nginx-site.conf` | nginx proxy (WebSocket upgrade headers) |
| `routes/channels.php` | Channel authorization callbacks |
| `resources/js/echo.js` | Laravel Echo initialization (reads `window.__reverb`) |
| `resources/js/bootstrap.js` | Imports echo.js |
| `packages/aicl/resources/js/aicl-widgets.js` | Alpine.js WebSocket client for AI streaming |
| `packages/aicl/resources/views/livewire/ai-assistant-panel.blade.php` | AI panel Blade template |
| `packages/aicl/src/AiclPlugin.php` | Injects `window.__reverb` config and Echo JS into Filament panel |
| `packages/aicl/src/Broadcasting/BaseBroadcastEvent.php` | Base class for domain broadcast events |
| `packages/aicl/src/Broadcasting/ChannelAuth.php` | Static authorization helpers for channels |
| `packages/aicl/src/AI/Events/` | AI stream broadcast events (5 events) |
| `packages/aicl/src/AI/Jobs/AiStreamJob.php` | Legacy AI streaming job (no tools) |
| `packages/aicl/src/AI/Jobs/AiConversationStreamJob.php` | Conversation-based AI streaming job (with tools) |
| `packages/aicl/src/AI/AiAssistantController.php` | HTTP endpoint that dispatches stream jobs |
| `packages/aicl/src/Events/Traits/BroadcastsDomainEvent.php` | Opt-in trait for broadcasting domain events |
| `packages/aicl/src/Health/Checks/ReverbCheck.php` | Health check for Reverb connectivity |

## DDEV Configuration

### Daemon Definition (`.ddev/config.yaml`)

Reverb runs as a web extra daemon managed by DDEV's supervisord:

```yaml
web_extra_daemons:
  - name: reverb
    command: php /var/www/html/artisan reverb:start --host=0.0.0.0 --port=8080
    directory: /var/www/html
```

### Port Exposure (`.ddev/config.yaml`)

```yaml
web_extra_exposed_ports:
  - name: reverb
    container_port: 8080
    http_port: 8079
    https_port: 8080
```

The DDEV router maps:
- Container port 8080 (where Reverb listens)
- HTTPS port 8080 (browser connects to `wss://aicl.ddev.site:8080`)
- HTTP port 8079 (plain WebSocket fallback)

### Supervisord Config (`.ddev/.webimageBuild/reverb.conf`)

```ini
[program:reverb]
group=webextradaemons
command=bash -c "php /var/www/html/artisan reverb:start --host=0.0.0.0 --port=8080; ..."
directory=/var/www/html
autostart=false
autorestart=true
startsecs=3
startretries=15
```

Key settings:
- `autostart=false` -- Reverb does not start until DDEV's daemon manager launches it
- `autorestart=true` -- Automatically restarts on crash
- `startsecs=3` -- Must stay up 3 seconds to be considered successfully started
- `startretries=15` -- Will retry up to 15 times on failure
- Grouped under `webextradaemons` for collective management

## Reverb Server Configuration (`config/reverb.php`)

```php
'servers' => [
    'reverb' => [
        'host' => env('REVERB_SERVER_HOST', '0.0.0.0'),    // Listen on all interfaces
        'port' => env('REVERB_SERVER_PORT', 8080),           // Internal port
        'hostname' => env('REVERB_HOST', 'localhost'),       // Public hostname
        'max_request_size' => env('REVERB_MAX_REQUEST_SIZE', 10_000),  // 10KB max message
        'scaling' => [
            'enabled' => env('REVERB_SCALING_ENABLED', false),  // Redis pub/sub off by default
            'channel' => 'reverb',
            'server' => [/* Redis connection details */],
        ],
    ],
],
```

### Application Credentials

```php
'apps' => [
    [
        'key' => env('REVERB_APP_KEY', 'riaqpvlloutkcr8ozmjx'),         // Dev default
        'secret' => env('REVERB_APP_SECRET', 'mzljn9t5gdlwyfba53wc'),   // Dev default
        'app_id' => env('REVERB_APP_ID', '780012'),                      // Dev default
        'options' => [
            'host' => env('REVERB_HOST', 'localhost'),
            'port' => env('REVERB_PORT', 8080),
            'scheme' => env('REVERB_SCHEME', 'http'),
        ],
        'allowed_origins' => ['*'],       // Open for dev -- restrict in production
        'ping_interval' => 60,            // Seconds between keep-alive pings
        'activity_timeout' => 30,         // Seconds of inactivity before disconnect
        'max_message_size' => 10_000,     // 10KB max per message
    ],
],
```

## Broadcasting Configuration (`config/broadcasting.php`)

```php
'default' => env('BROADCAST_CONNECTION', 'reverb'),

'connections' => [
    'reverb' => [
        'driver' => 'reverb',
        'key' => env('REVERB_APP_KEY', 'riaqpvlloutkcr8ozmjx'),
        'secret' => env('REVERB_APP_SECRET', 'mzljn9t5gdlwyfba53wc'),
        'app_id' => env('REVERB_APP_ID', '780012'),
        'options' => [
            'host' => env('REVERB_HOST', 'localhost'),
            'port' => env('REVERB_PORT', 8080),
            'scheme' => env('REVERB_SCHEME', 'http'),
        ],
    ],
],
```

The broadcasting config is used by the **server** (PHP) when it sends events to Reverb. The key/secret pair must match what Reverb expects. The host/port/scheme here connect from the PHP process to the Reverb process (server-to-server, both inside the same DDEV container).

## Feature Flag

```php
// packages/aicl/config/aicl.php
'features' => [
    'websockets' => true,  // Default: enabled
],
```

Check: `config('aicl.features.websockets')`

When `false`:
- Echo JS bundle is not injected into the Filament panel
- `window.__reverb` config script is not rendered
- Toolbar presence widget is not loaded
- ReverbCheck health check reports "Disabled" (healthy status, no connection attempted)

The feature flag is checked in `AiclPlugin::boot()` (2 locations) and `ReverbCheck::check()`.

## AI Streaming Reverb Config

```php
// packages/aicl/config/aicl.php -> ai.streaming.reverb
'streaming' => [
    'queue' => 'default',
    'timeout' => 120,
    'max_concurrent_per_user' => 2,
    'reverb' => [
        'host' => 'localhost',
        'port' => 8080,
        'scheme' => 'http',
    ],
],
```

These values are passed to the **browser** (client side) via the Blade template and `window.__reverb`. They tell the JavaScript where to open the WebSocket connection. In DDEV, the browser connects to `wss://aicl.ddev.site:8080` (TLS terminated by DDEV router), while internally the scheme is `http`.

## Channel Types and Authorization

All channel authorization is defined in `routes/channels.php`.

### Public Channels
Not currently used. All AICL channels are private or presence.

### Private Channels

| Channel | Authorization | Purpose |
|---------|--------------|---------|
| `App.Models.User.{id}` | User's own ID must match | Personal notifications |
| `dashboard` | Any authenticated user | Entity CRUD real-time updates |
| `ai.stream.{streamId}` | Cache-based user match | AI token streaming |

### Presence Channels

| Channel | Authorization | Data Returned |
|---------|--------------|---------------|
| `presence-admin-panel` | `super_admin` or `admin` role | `{id, name, current_url, joined_at}` |
| `presence-page.{hash}` | `super_admin` or `admin` role | `{id, name}` |

### AI Stream Channel Authorization (Critical)

The `ai.stream.{streamId}` channel uses cache-based authorization instead of database lookups:

```php
// routes/channels.php
Broadcast::channel('ai.stream.{streamId}', function ($user, $streamId) {
    return (int) Cache::get("ai-stream:{$streamId}:user") === (int) $user->id;
});
```

When a user sends an AI request, `AiAssistantController::ask()` stores the user ID in cache:

```php
// AiAssistantController.php
Cache::put("ai-stream:{$streamId}:user", $userId, 300);  // 5-minute TTL
```

**CRITICAL BUG PATTERN**: Redis returns cached integers as strings. Both sides of the comparison MUST cast to `(int)` for the `===` operator to work. Without the casts, `"42" === 42` evaluates to `false` and channel authorization silently fails.

### Entity Channel Authorization (via ChannelAuth)

`Aicl\Broadcasting\ChannelAuth` provides reusable static methods:

- `ChannelAuth::userChannel($user, $id)` -- string comparison of user key vs channel ID
- `ChannelAuth::entityChannel($user, $entityClass, $id)` -- checks `ViewAny:{Entity}` Spatie permission
- `ChannelAuth::presenceChannel($user, $entityClass, $id)` -- entity auth + returns user data array

## Two Client-Side Connection Patterns

AICL uses two distinct patterns for WebSocket communication:

### Pattern 1: Laravel Echo (Entity Events, Presence)

Used by: Dashboard updates, presence tracking, entity CRUD notifications.

Configured in `resources/js/echo.js`:

```javascript
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: window.__reverb?.key ?? '',
    wsHost: window.__reverb?.host ?? 'localhost',
    wsPort: window.__reverb?.port ?? 80,
    wssPort: window.__reverb?.port ?? 443,
    forceTLS: (window.__reverb?.scheme ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
});
```

The `window.__reverb` object is injected by `AiclPlugin::boot()` into the `<head>`:

```php
$reverbConfig = json_encode([
    'key' => config('broadcasting.connections.reverb.key', ''),
    'host' => config('aicl.ai.streaming.reverb.host', 'localhost'),
    'port' => (int) config('aicl.ai.streaming.reverb.port', 8080),
    'scheme' => config('aicl.ai.streaming.reverb.scheme', 'http'),
]);
// Rendered as: <script>window.__reverb={"key":"...","host":"...","port":8080,"scheme":"http"};</script>
```

Echo subscribes to the `dashboard` private channel and listens for `.entity.created`, `.entity.updated`, `.entity.deleted` events, dispatching Livewire events to refresh widgets.

### Pattern 2: Native WebSocket (AI Streaming)

Used by: AI assistant panel (token streaming).

The AI chat uses a **raw WebSocket connection** implementing the Pusher protocol directly -- no Echo or Pusher.js dependency. This is defined in `packages/aicl/resources/js/aicl-widgets.js` as the `aiAssistantPanel` function.

Connection flow:

```
1. User types message in AI panel
2. Alpine.js calls $wire.sendMessage(prompt) via Livewire
3. Livewire backend returns {stream_id, channel}
4. Alpine.js opens WebSocket:
   const wsScheme = config.reverbScheme === 'https' ? 'wss' : 'ws';
   const wsUrl = `${wsScheme}://${config.reverbHost}:${config.reverbPort}/app/${config.reverbKey}?protocol=7`;
   this._ws = new WebSocket(wsUrl);

5. Reverb sends pusher:connection_established with socket_id
6. Client POSTs to /broadcasting/auth with {socket_id, channel_name}
7. Server validates via routes/channels.php, returns {auth: "key:signature"}
8. Client sends pusher:subscribe with channel and auth token
9. Reverb confirms with pusher_internal:subscription_succeeded
10. Events arrive: ai.started, ai.token, ai.tool_call, ai.completed, ai.failed
```

The Blade template passes Reverb config directly to the Alpine component:

```php
// ai-assistant-panel.blade.php
x-data="aiAssistantPanel({
    csrfToken: '{{ csrf_token() }}',
    reverbHost: '{{ config('aicl.ai.streaming.reverb.host') }}',
    reverbPort: {{ (int) config('aicl.ai.streaming.reverb.port') }},
    reverbScheme: '{{ config('aicl.ai.streaming.reverb.scheme') }}',
    reverbKey: '{{ config('broadcasting.connections.reverb.key', '') }}',
    authUrl: '/broadcasting/auth',
})"
```

## Broadcasting Events

### AI Stream Events (`packages/aicl/src/AI/Events/`)

All five AI events implement `ShouldBroadcastNow` (not `ShouldBroadcast`), meaning they bypass the queue and broadcast immediately. They all broadcast on `PrivateChannel("ai.stream.{streamId}")`.

| Event Class | `broadcastAs()` | Payload | When |
|-------------|-----------------|---------|------|
| `AiStreamStarted` | `ai.started` | `{stream_id}` | Stream begins |
| `AiTokenEvent` | `ai.token` | `{stream_id, token, index}` | Each text chunk |
| `AiToolCallEvent` | `ai.tool_call` | `{stream_id, tools: [{name, inputs, render?}]}` | Tool invocation |
| `AiStreamCompleted` | `ai.completed` | `{stream_id, total_tokens, usage}` | Stream finished |
| `AiStreamFailed` | `ai.failed` | `{stream_id, error}` | Error occurred |

### Entity Domain Events

Entity events extend `DomainEvent` and use `BroadcastsDomainEvent` trait + `ShouldBroadcast` interface (queued, not immediate). They broadcast on two channels:

1. `PrivateChannel('dashboard')` -- always
2. `PrivateChannel("{entityType}s.{entityId}")` -- if entity exists

| Event Class | `broadcastAs()` |
|-------------|-----------------|
| `EntityCreated` | `entity.created` |
| `EntityUpdated` | `entity.updated` |
| `EntityDeleted` | `entity.deleted` |

### BaseBroadcastEvent

`Aicl\Broadcasting\BaseBroadcastEvent` is an abstract base for custom broadcast events. It provides:
- Auto-generated UUID `eventId`
- Dot-notation `eventType` (from `static::eventType()`)
- ISO 8601 `occurredAt` timestamp
- Default broadcast on `PrivateChannel('dashboard')` + optional entity-specific channel
- `broadcastWith()` merges `toPayload()` with event metadata

## How AI Streaming Works (End-to-End)

1. **User sends message**: Alpine.js calls `$wire.sendMessage(prompt)` on the Livewire `AiAssistantPanel` component.

2. **Backend dispatches job**: The Livewire component (or `AiAssistantController`) generates a UUID `streamId`, stores `Cache::put("ai-stream:{streamId}:user", $userId, 300)` for channel auth, enforces concurrent stream limits via atomic cache increment, and dispatches `AiConversationStreamJob` (or `AiStreamJob` for legacy).

3. **Returns stream info**: `{stream_id, channel: "private-ai.stream.{streamId}"}` sent back to the browser.

4. **Client connects WebSocket**: Alpine.js opens a native WebSocket to Reverb, completes the Pusher handshake, authenticates the private channel via `/broadcasting/auth`, and subscribes.

5. **Job runs on queue worker**: Horizon picks up the job. The job creates a NeuronAI agent, calls `$agent->stream($messages)`, and iterates over the generator.

6. **Tokens broadcast in real-time**: Each chunk from the generator is wrapped in an `AiTokenEvent` and broadcast via `broadcast(new AiTokenEvent(...))`. Since these implement `ShouldBroadcastNow`, they go directly to Reverb without queuing.

7. **Client renders incrementally**: The Alpine.js `_ws.onmessage` handler receives each `ai.token` event, appends the token to `_currentResponse`, and updates the message content. Markdown rendering happens on each update.

8. **Stream completes**: `AiStreamCompleted` fires with usage stats. The client calls `_cleanup()` to close the WebSocket.

9. **Concurrent limit decremented**: The `finally` block in the job decrements `ai-stream:user:{userId}:count`.

## Health Check

`ReverbCheck` (`packages/aicl/src/Health/Checks/ReverbCheck.php`) verifies Reverb connectivity:

- If feature flag is off, returns healthy with "Disabled" status
- Makes an HTTP GET to `{scheme}://{host}:{port}` with a 2-second timeout
- Accepts any status code in 200-499 range as "running" (Reverb may return 401/426 for non-WebSocket HTTP requests)
- Checks for the Reverb process via `ps aux | grep reverb`
- Returns `down` on connection refused or timeout

## Commands

| Command | Purpose |
|---------|---------|
| `ddev exec supervisorctl status webextradaemons:reverb` | Check if Reverb is running |
| `ddev exec supervisorctl restart webextradaemons:reverb` | Restart Reverb |
| `ddev exec supervisorctl stop webextradaemons:reverb` | Stop Reverb |
| `ddev exec php artisan reverb:start --host=0.0.0.0 --port=8080` | Manual start (foreground) |
| `ddev exec php artisan reverb:restart` | Graceful restart (signal existing process) |

## Common Issues and Troubleshooting

### WebSocket connection refused in browser

**Symptoms**: Browser console shows `WebSocket connection to 'wss://...' failed`.

**Diagnosis**:
1. Check Reverb is running: `ddev exec supervisorctl status webextradaemons:reverb`
2. Verify port 8080 is exposed: `ddev describe` should list port 8080 under "Extra exposed ports"
3. Check Reverb logs: `ddev exec supervisorctl tail -f webextradaemons:reverb`
4. Verify the Reverb app key in `config/broadcasting.php` matches what the client sends (check `window.__reverb` in browser console)

**Fix**: If Reverb is not running, restart it: `ddev exec supervisorctl restart webextradaemons:reverb`. If ports are wrong, check `.ddev/config.yaml` `web_extra_exposed_ports` and run `ddev restart`.

### AI assistant sends messages but no streaming response appears

**Symptoms**: User types a message, loading indicator shows, but no tokens appear. No error displayed.

**Diagnosis** (check in order):
1. **Reverb running?** `ddev exec supervisorctl status webextradaemons:reverb` -- must show `RUNNING`
2. **Horizon running?** `ddev exec supervisorctl status webextradaemons:horizon` -- must show `RUNNING` (jobs need a worker)
3. **Job dispatched?** Check `ddev exec php artisan horizon:status` and the Horizon dashboard at `/admin/horizon`
4. **Broadcasting config match?** The `key` and `secret` in `config/broadcasting.php` must exactly match those in `config/reverb.php` -> `apps`
5. **Channel auth working?** Open browser Network tab, look for a POST to `/broadcasting/auth` -- it should return 200 with `{"auth": "..."}`. A 403 means authorization failed.
6. **Cache key exists?** The stream authorization cache key `ai-stream:{streamId}:user` must exist. If Redis was flushed between the request and the auth check, it will fail.
7. **Provider configured?** Check that an AI API key is set in `config/local.php` (e.g., `aicl.ai.openai.api_key` or `aicl.ai.anthropic.api_key`)

### Channel authorization failing (403 on /broadcasting/auth)

**Symptoms**: POST to `/broadcasting/auth` returns 403. Browser console may show "Channel authorization failed".

**Diagnosis**:
1. **User authenticated?** The `/broadcasting/auth` endpoint requires an authenticated session. Check cookies are being sent (look for `credentials: 'include'` in the fetch call).
2. **Cache key present?** For AI channels: `Cache::get("ai-stream:{streamId}:user")` must return the user ID. TTL is 5 minutes -- if the user waits too long, it expires.
3. **Redis type casting bug**: Redis returns integers as strings. The channel callback casts both sides to `(int)`: `(int) Cache::get(...) === (int) $user->id`. If this cast is missing, `===` comparison of string vs int always returns false.
4. **Middleware blocking?** Ensure `/broadcasting/auth` is not blocked by custom middleware. The route uses Laravel's built-in broadcasting auth middleware.

**Fix for Redis casting**: Verify the channel definition in `routes/channels.php` uses `(int)` casts on both sides of the `===` comparison.

### Mixed content / TLS errors

**Symptoms**: `Mixed Content: The page at 'https://...' was loaded over HTTPS, but attempted to connect to the insecure WebSocket endpoint 'ws://...'`.

**Explanation**: In DDEV, TLS is terminated by the DDEV router. Reverb runs plain HTTP/WS internally on port 8080. The DDEV router wraps this in TLS and exposes it as HTTPS/WSS on the same port externally.

**Diagnosis**:
1. Check `config('aicl.ai.streaming.reverb.scheme')` -- should be `'http'` for DDEV
2. The client-side code derives `wsScheme` from this: `config.reverbScheme === 'https' ? 'wss' : 'ws'`
3. Since DDEV proxies port 8080 with TLS, the browser actually connects via `wss://` even though the internal scheme is `http`

**Fix**: In DDEV, leave `aicl.ai.streaming.reverb.scheme` as `'http'`. The DDEV router handles TLS. For production behind a TLS-terminating load balancer, set `scheme` to `'https'` and configure Reverb with TLS certificates, or keep `'http'` if the load balancer terminates TLS.

**Important**: The `forceTLS` setting in Echo is derived from the scheme: `forceTLS: (window.__reverb?.scheme ?? 'https') === 'https'`. In DDEV with scheme `'http'`, this is `false`, but the DDEV router still provides TLS. Echo uses `wssPort` when `forceTLS` is true, `wsPort` when false.

### Reverb not receiving broadcasts from PHP

**Symptoms**: Reverb is running, client is connected, but no events arrive.

**Diagnosis**:
1. **Default broadcaster**: `config('broadcasting.default')` must be `'reverb'` (not `'log'` or `'null'`)
2. **Event implements ShouldBroadcast**: Check the event class implements `ShouldBroadcast` or `ShouldBroadcastNow`
3. **broadcastOn() returns correct channel**: Verify the channel name matches what the client subscribed to
4. **Test manually**: `ddev exec php artisan tinker` then `broadcast(new \Aicl\AI\Events\AiStreamStarted('test-123', 1));`
5. **Server-to-server connectivity**: The PHP process connects to Reverb at `localhost:8080` (configured in `broadcasting.php`). Both run in the same container, so this should work. If Reverb is down, broadcasts silently fail.

### Events arrive but client does not update

**Symptoms**: Network tab shows WebSocket frames arriving, but the UI does not update.

**Diagnosis**:
1. **Event name mismatch**: Reverb sends the `broadcastAs()` value as the event name. Check that the client's `switch` statement handles the exact event name (e.g., `'ai.token'`, not `'.ai.token'`).
2. **Data parsing**: The client parses `msg.data` -- check if it's already an object or needs `JSON.parse()`. The code handles both: `typeof msg.data === 'string' ? JSON.parse(msg.data) : msg.data`.
3. **Message index tracking**: The AI panel tracks `msgIndex` to update the correct message in the array. If the index is wrong (e.g., another message was pushed between stream start and first token), updates go to the wrong message.

### Reverb crashes or won't start after ddev restart

**Symptoms**: `supervisorctl status` shows `BACKOFF` or `FATAL` for Reverb.

**Diagnosis**:
1. Check logs: `ddev exec supervisorctl tail -f webextradaemons:reverb`
2. Port conflict: Another process may be using port 8080
3. Stale PID: After `ddev restart`, a stale PID file may prevent startup

**Fix**: `ddev exec supervisorctl restart webextradaemons:reverb`. If that fails, check for port conflicts with `ddev exec ss -tlnp | grep 8080`.

### Presence not working (toolbar shows no users)

**Symptoms**: The toolbar presence indicator shows no other users, even when multiple admins are online.

**Diagnosis**:
1. **Echo loaded?** Check browser console: `window.Echo` should be defined. If not, the `aicl.features.websockets` flag may be `false`.
2. **Reverb connected?** Check browser console for Pusher connection errors.
3. **Channel authorized?** The `presence-admin-panel` channel requires `super_admin` or `admin` role.
4. **Echo subscribed?** The `presenceIndicator` Alpine component in `aicl-widgets.js` calls `window.Echo.join(channelName)`.

## Configuration Reference

### Config Keys Used by Reverb/Broadcasting

| Key | Default | Used By | Purpose |
|-----|---------|---------|---------|
| `aicl.features.websockets` | `true` | AiclPlugin, ReverbCheck | Master on/off for WebSocket features |
| `aicl.ai.streaming.reverb.host` | `'localhost'` | Client JS, AiclPlugin | Browser-accessible Reverb host |
| `aicl.ai.streaming.reverb.port` | `8080` | Client JS, AiclPlugin | Browser-accessible Reverb port |
| `aicl.ai.streaming.reverb.scheme` | `'http'` | Client JS, AiclPlugin | Protocol for WebSocket URL construction |
| `aicl.ai.streaming.queue` | `'default'` | AiStreamJob, AiConversationStreamJob | Queue name for stream jobs |
| `aicl.ai.streaming.timeout` | `120` | AiStreamJob, AiConversationStreamJob | Max seconds per stream |
| `aicl.ai.streaming.max_concurrent_per_user` | `2` | AiAssistantController | Max simultaneous AI streams per user |
| `broadcasting.default` | `'reverb'` | Laravel | Which broadcaster to use |
| `broadcasting.connections.reverb.key` | `'riaqpvlloutkcr8ozmjx'` | Server + Client | Pusher protocol app key |
| `broadcasting.connections.reverb.secret` | `'mzljn9t5gdlwyfba53wc'` | Server | HMAC signing secret |
| `broadcasting.connections.reverb.app_id` | `'780012'` | Server | Pusher protocol app ID |
| `broadcasting.connections.reverb.options.host` | `'localhost'` | Server | PHP->Reverb connection host |
| `broadcasting.connections.reverb.options.port` | `8080` | Server | PHP->Reverb connection port |
| `reverb.servers.reverb.host` | `'0.0.0.0'` | Reverb server | Listen address |
| `reverb.servers.reverb.port` | `8080` | Reverb server | Listen port |
| `reverb.servers.reverb.max_request_size` | `10000` | Reverb server | Max WebSocket frame bytes |
| `reverb.servers.reverb.scaling.enabled` | `false` | Reverb server | Redis pub/sub for multi-instance |
| `reverb.apps.apps.0.allowed_origins` | `['*']` | Reverb server | CORS origins for WebSocket |
| `reverb.apps.apps.0.ping_interval` | `60` | Reverb server | Keep-alive ping seconds |
| `reverb.apps.apps.0.activity_timeout` | `30` | Reverb server | Disconnect after inactivity seconds |

## Production Considerations

1. **Change credentials**: Replace dev-default `key`, `secret`, and `app_id` with strong random values in `config/local.php`
2. **Restrict allowed_origins**: Set to your domain(s) instead of `['*']`
3. **Enable scaling**: Set `reverb.servers.reverb.scaling.enabled` to `true` for multi-instance deployments (uses Redis pub/sub to sync channels across Reverb processes)
4. **TLS termination**: Use a load balancer or nginx to terminate TLS in front of Reverb. Reverb runs plain HTTP internally.
5. **Max request size**: Adjust `max_request_size` and `max_message_size` if your events carry large payloads
6. **File descriptors**: Each WebSocket connection consumes a file descriptor. Monitor `ulimit -n` and increase if needed for high-concurrency deployments
7. **Supervisor/systemd**: Replace DDEV's supervisord with production-grade process management (systemd, Supervisor, Docker health checks)
8. **Connection limits**: Set `max_connections` in the Reverb app config to prevent resource exhaustion

## Key Decisions

1. **Native WebSocket over Echo for AI streaming**: The AI panel uses raw WebSocket + Pusher protocol instead of Laravel Echo. This avoids the Pusher.js dependency in the streaming path and gives fine-grained control over connection lifecycle (open per-stream, close on completion).

2. **ShouldBroadcastNow for AI events**: AI stream events bypass the queue (`ShouldBroadcastNow`) for zero-latency token delivery. Entity events use `ShouldBroadcast` (queued) since slight delay is acceptable.

3. **Cache-based channel authorization**: AI stream channels use Redis cache keys with 5-minute TTL instead of database lookups. This is faster and avoids N+1 queries during high-frequency streaming.

4. **WebSocket over SSE**: SSE was removed in Sprint H. Each SSE connection holds a Swoole worker for the duration of the stream. With Swoole's limited worker pool (default 4), a few concurrent streams would exhaust all workers. Reverb runs as a separate process with its own event loop, handling thousands of connections without touching Swoole workers.

5. **window.__reverb injection**: Reverb config is injected as a JSON object in a `<script>` tag in `<head>` rather than using Vite environment variables. This works correctly under Swoole/Octane (config resolved once at boot) and avoids `.env` file dependency.

## Related

- `packages/aicl/config/aicl.php` -- Feature flags and AI streaming config
- `config/broadcasting.php` -- Laravel broadcasting driver config
- `config/reverb.php` -- Reverb server config
- `.ddev/config.yaml` -- DDEV daemon and port config
- [Laravel Reverb documentation](https://laravel.com/docs/reverb)
- [Pusher Protocol specification](https://pusher.com/docs/channels/library_auth_reference/pusher-websockets-protocol/)
