# NeuronAI Integration & AI Features

NeuronAI provides the AI/agent framework layer for AICL, handling LLM provider abstraction, embedding generation, and real-time streaming via Reverb WebSockets.

**Namespace:** `Aicl\AI`, `Aicl\Rlm\Embeddings`, `Aicl\Traits`

## Overview

| Component | Purpose |
|-----------|---------|
| `AiProviderFactory` | Config-driven LLM provider resolution (OpenAI, Anthropic, Ollama) |
| `AiAssistantController` | Admin AI chat endpoint — dispatches streaming job via Reverb |
| `NeuronAiEmbeddingAdapter` | Bridges NeuronAI's `EmbeddingsProviderInterface` to AICL's `EmbeddingDriver` |
| `EmbeddingService` | Pluggable embedding generation with auto-driver resolution |
| `HasAiContext` | Model trait providing structured context for LLM prompts |

> **See also:** [AI Assistant Streaming](ai-assistant.md) for the real-time WebSocket streaming architecture (Sprint I).

---

## AiProviderFactory

Resolves NeuronAI LLM providers from AICL config. Supports three providers:

```php
use Aicl\AI\AiProviderFactory;

// Resolve the configured provider (returns null if no API key)
$provider = AiProviderFactory::make();

// Resolve a specific provider
$provider = AiProviderFactory::make('anthropic');

// Check if the provider has valid credentials
if (AiProviderFactory::isConfigured()) {
    // Safe to make LLM calls
}
```

### Supported Providers

| Provider | Config Key | API Key Env Var | Default Model |
|----------|-----------|-----------------|---------------|
| OpenAI | `aicl.ai.provider = 'openai'` | `OPENAI_API_KEY` | `gpt-4o-mini` |
| Anthropic | `aicl.ai.provider = 'anthropic'` | `ANTHROPIC_API_KEY` | `claude-haiku-4-5-20251001` |
| Ollama | `aicl.ai.provider = 'ollama'` | N/A (host-based) | `llama3.2` |

### Configuration

In `config/aicl.php`:

```php
'ai' => [
    'provider' => env('AICL_AI_PROVIDER', 'openai'),
    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'model' => env('AICL_AI_OPENAI_MODEL', 'gpt-4o-mini'),
    ],
    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
        'model' => env('AICL_AI_ANTHROPIC_MODEL', 'claude-haiku-4-5-20251001'),
    ],
    'ollama' => [
        'host' => env('AICL_AI_OLLAMA_HOST', 'http://localhost:11434'),
        'model' => env('AICL_AI_OLLAMA_MODEL', 'llama3.2'),
    ],
],
```

---

## EmbeddingService

Generates embedding vectors for text, used by the RLM knowledge system for semantic search.

```php
use Aicl\Rlm\EmbeddingService;

$service = app(EmbeddingService::class);

// Single embedding
$vector = $service->generate('Some text to embed');

// Batch embeddings
$vectors = $service->generateBatch(['text one', 'text two']);

// Check availability
if ($service->isAvailable()) {
    // Not using NullDriver
}

// Get dimension
$dim = $service->getDimension(); // 1536
```

### Driver Resolution Priority

1. `NullDriver` if explicitly configured (`aicl.rlm.embeddings.driver = 'null'`)
2. NeuronAI OpenAI adapter if API key present
3. NeuronAI Ollama adapter if Ollama is reachable
4. Fallback: `NullDriver` (kNN search disabled, BM25 still active)

### NeuronAI Embedding Adapter

Bridges NeuronAI's `EmbeddingsProviderInterface` to AICL's `EmbeddingDriver` contract:

```php
use Aicl\Rlm\Embeddings\NeuronAiEmbeddingAdapter;

// OpenAI: 1536 dimensions natively, no padding needed
new NeuronAiEmbeddingAdapter($provider, targetDimension: 1536, padToTarget: false);

// Ollama: 768 dimensions, zero-padded to 1536 for ES index compatibility
new NeuronAiEmbeddingAdapter($provider, targetDimension: 1536, padToTarget: true);
```

---

## HasAiContext Trait

Provides structured context for LLM prompts. Add to any Eloquent model to make it AI-consumable.

### Adding to a Model

```php
use Aicl\Traits\HasAiContext;

class Project extends Model
{
    use HasAiContext;

    // Optional: customize which fields the LLM sees
    protected function aiContextFields(): array
    {
        return ['name', 'status', 'priority', 'description'];
    }
}
```

### Output Shape

```php
$project->toAiContext();
// [
//     'type' => 'Project',
//     'id' => 'uuid-here',
//     'label' => 'My Project',
//     'attributes' => ['name' => 'My Project', 'status' => 'active', ...],
//     'relationships' => ['owner' => ['id' => 1, 'type' => 'User', 'label' => 'Admin']],
//     'meta' => ['created_at' => '2026-01-01 00:00:00', 'status' => 'active'],
// ]
```

### Customization Points

| Method | Default | Override To |
|--------|---------|-------------|
| `aiContextType()` | `Str::headline(class_basename())` | Custom type label |
| `aiContextLabel()` | `$model->name ?? $model->title ?? $model->id` | Custom summary |
| `aiContextFields()` | `$model->getFillable()` | Specific field list |
| `aiContextAttributes()` | Auto-built from `aiContextFields()` | Custom attribute extraction |
| `aiContextRelationships()` | Already-loaded relations (avoids N+1) | Custom relationship data |
| `aiContextMeta()` | `created_at`, `updated_at`, `status`, `is_active` | Custom metadata |

### Scaffolder Integration

Use `--ai-context` (or `--all`) with `aicl:make-entity` to generate models with `HasAiContext` pre-configured:

```bash
ddev artisan aicl:make-entity Invoice --fields="number:string,total:float,status:enum" --ai-context
```

The generated model will include:

```php
use HasAiContext;

protected function aiContextFields(): array
{
    return ['number', 'total', 'status'];
}
```

---

## AI Assistant Page

A Filament page at `/admin/ai-assistant` with a real-time streaming chat UI. Dispatches a queued job that broadcasts tokens via Reverb WebSocket — zero Swoole worker occupation.

**Access:** `super_admin` or `admin` role required. Rate limited (10 requests/minute). Max 2 concurrent streams per user.

**Endpoint:** `POST /ai/ask` with `{ prompt, entity_type?, entity_id? }`

Entity context is injected when both `entity_type` and `entity_id` are provided and the model uses `HasAiContext`.

**Full documentation:** See [AI Assistant Streaming](ai-assistant.md) for architecture, configuration, and usage details.

---

## Files

| File | Purpose |
|------|---------|
| `src/AI/AiProviderFactory.php` | LLM provider resolution |
| `src/AI/AiAssistantController.php` | Chat endpoint — dispatches AiStreamJob |
| `src/AI/AiAssistantRequest.php` | Form request validation |
| `src/Rlm/EmbeddingService.php` | Embedding generation service |
| `src/Rlm/Embeddings/NeuronAiEmbeddingAdapter.php` | NeuronAI adapter |
| `src/Rlm/Embeddings/NullDriver.php` | No-op fallback driver |
| `src/Traits/HasAiContext.php` | Model AI context trait |
| `src/Filament/Pages/AiAssistant.php` | Filament chat page |
