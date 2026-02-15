# AI Assistant — Real-Time Streaming

The AI assistant provides a real-time chat interface in the Filament admin panel, powered by NeuronAI with token-by-token streaming via Reverb WebSocket.

**Admin page:** `/admin/ai-assistant`
**Namespace:** `Aicl\AI`

---

## Architecture

```
Browser ──POST /ai/ask──► Swoole Worker ──dispatch──► Queue Worker ──broadcast──► Reverb ──► Browser
         (~5ms hold)                                  (streams tokens)            (WebSocket)
```

The previous SSE approach held a Swoole worker for the entire LLM response (10-60s). This architecture dispatches a queued job and the worker returns immediately (~5ms), with the job broadcasting tokens over WebSocket as they arrive.

| Concern | SSE (removed) | WebSocket via Job (current) |
|---------|---------------|----------------------------|
| Worker hold time | 10-60s | ~5ms |
| Concurrent streams | Limited to worker count | Unlimited (queue workers) |
| Transport | HTTP chunked | Reverb WebSocket |
| Failure mode | Worker exhaustion | Queue backs up |

---

## Request Flow

1. User types a prompt in the admin chat UI
2. `POST /ai/ask` hits `AiAssistantController::ask()`
3. Controller generates a UUID stream ID, stores `Cache::put("ai-stream:{$streamId}:user", $userId, 300)`
4. Controller dispatches `AiStreamJob` to the queue and returns `{ stream_id, channel }` immediately
5. Browser opens a native WebSocket to Reverb using the Pusher protocol
6. Browser authenticates the private channel via `POST /broadcasting/auth`
7. `AiStreamJob` runs on a queue worker:
   - Broadcasts `AiStreamStarted`
   - Builds NeuronAI agent, calls `$agent->stream()`
   - Broadcasts `AiTokenEvent` for each token
   - Broadcasts `AiStreamCompleted` on finish (or `AiStreamFailed` on error)
8. Browser renders tokens character-by-character as they arrive

---

## Configuration

All config under `aicl.ai.streaming` in `config/aicl.php`:

```php
'ai' => [
    'streaming' => [
        'queue' => env('AICL_AI_QUEUE', 'default'),           // Queue for AI jobs
        'timeout' => (int) env('AICL_AI_TIMEOUT', 120),       // Job timeout (seconds)
        'max_concurrent_per_user' => 2,                         // Max simultaneous streams
        'reverb' => [
            'host' => env('VITE_REVERB_HOST', 'localhost'),    // Browser-accessible host
            'port' => (int) env('VITE_REVERB_PORT', 8080),    // Reverb port
            'scheme' => env('VITE_REVERB_SCHEME', 'http'),     // 'http' or 'https'
        ],
    ],
],
```

### Environment Variables

| Variable | Default | Purpose |
|----------|---------|---------|
| `AICL_AI_QUEUE` | `default` | Queue name for AI streaming jobs |
| `AICL_AI_TIMEOUT` | `120` | Job timeout in seconds |
| `VITE_REVERB_HOST` | `localhost` | Reverb host visible to the browser |
| `VITE_REVERB_PORT` | `8080` | Reverb port visible to the browser |
| `VITE_REVERB_SCHEME` | `http` | Protocol for Reverb (`http` or `https`) |

---

## Prerequisites

1. **Reverb running** — Configured as a DDEV daemon or standalone process
2. **Queue worker** — `ddev artisan queue:work` must be running
3. **AI provider configured** — At least one of `OPENAI_API_KEY`, `ANTHROPIC_API_KEY`, or Ollama host
4. **WebSockets enabled** — `config('aicl.features.websockets')` must be `true` (default)

---

## Components

### AiStreamJob

Queued job that builds a NeuronAI agent, streams the response, and broadcasts tokens.

```php
AiStreamJob::dispatch(
    streamId: $streamId,
    userId: $user->id,
    prompt: 'Explain this model',
    systemPrompt: config('aicl.ai.system_prompt'),
    context: $contextArray,
    driver: null, // uses default provider
);
```

| Property | Value | Purpose |
|----------|-------|---------|
| `$timeout` | `120` | Generous timeout for slow LLMs |
| `$tries` | `1` | Don't retry (idempotency not guaranteed) |

### Broadcast Events

All events implement `ShouldBroadcastNow` and broadcast on `private-ai.stream.{streamId}`.

| Event Class | `broadcastAs()` | Payload |
|-------------|-----------------|---------|
| `AiStreamStarted` | `ai.started` | `{ stream_id }` |
| `AiTokenEvent` | `ai.token` | `{ stream_id, token, index }` |
| `AiStreamCompleted` | `ai.completed` | `{ stream_id, total_tokens, usage }` |
| `AiStreamFailed` | `ai.failed` | `{ stream_id, error }` |

Events use `ShouldBroadcastNow` (not `ShouldBroadcast`) because the job is already queued — broadcasting should be immediate from within the job.

### Channel Authorization

Channel auth is cache-based. The controller stores the user ID before dispatching:

```php
Cache::put("ai-stream:{$streamId}:user", $userId, 300);
```

The channel callback in `routes/channels.php` verifies:

```php
Broadcast::channel('ai.stream.{streamId}', function ($user, $streamId) {
    return (int) Cache::get("ai-stream:{$streamId}:user") === (int) $user->id;
});
```

Both sides cast to `(int)` because Redis stores integers as strings.

### Controller

`AiAssistantController::ask()` handles:

1. **Rate limiting** — 10 requests/minute per user
2. **Concurrent limit** — Max 2 simultaneous streams per user (returns 429)
3. **Entity context** — Optional `entity_type` + `entity_id` for model-aware prompts
4. **Provider check** — Returns 503 if no AI provider is configured

### Frontend (Alpine.js)

The `aiChat()` Alpine component in `aicl-widgets.js` implements a minimal Pusher-protocol WebSocket client:

- **No external dependencies** — Uses native `WebSocket` API
- Handles the full Pusher handshake: connect, authenticate, subscribe
- Renders tokens reactively via Alpine data binding
- Auto-scrolls chat container as tokens arrive
- Shows loading dots until first token arrives
- Enter-to-send, Shift+Enter for newlines

---

## Files

| File | Purpose |
|------|---------|
| `src/AI/AiAssistantController.php` | Chat endpoint — dispatches job, returns channel |
| `src/AI/AiAssistantRequest.php` | Form request validation |
| `src/AI/Jobs/AiStreamJob.php` | Queued LLM streaming job |
| `src/AI/Events/AiStreamStarted.php` | Stream started broadcast event |
| `src/AI/Events/AiTokenEvent.php` | Token broadcast event |
| `src/AI/Events/AiStreamCompleted.php` | Stream completed broadcast event |
| `src/AI/Events/AiStreamFailed.php` | Stream failed broadcast event |
| `src/Filament/Pages/AiAssistant.php` | Filament admin page |
| `resources/views/filament/pages/ai-assistant.blade.php` | Chat UI Blade template |
| `resources/js/aicl-widgets.js` | Alpine.js `aiChat()` component |

---

## Known Gotchas

1. **Redis type coercion** — `Cache::put(key, int)` stores as string in Redis. Always cast both sides with `(int)` in channel auth comparisons.
2. **Filament asset publishing** — `Js::make()` serves from `public/js/`, not the source file. Must run `artisan filament:assets` after modifying `resources/js/aicl-widgets.js`.
3. **URL generation** — Use relative paths for same-origin AJAX requests (`/ai/ask`, `/broadcasting/auth`). With the nginx→Swoole proxy (Sprint J), port mismatch is no longer an issue for standard URLs, but relative paths remain the safest approach.
