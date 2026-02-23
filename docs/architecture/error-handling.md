# Error Handling Convention

## Three-Tier Error Handling Model

All AICL package services MUST follow this three-tier model:

### Tier 1: Expected Failures (missing data, empty results, feature unavailable)

- **Return type:** `null`, empty collection, or sentinel value (e.g., `INSUFFICIENT_DATA`)
- **Log level:** `Log::debug()` -- only if the empty result is noteworthy (e.g., first-time degradation)
- **Exception:** NEVER throw
- **Examples:**
  - No search results found -> return empty `Collection`
  - Embedding service unavailable -> return `null` embedding, proceed with BM25
  - No generation traces for KPI -> return `INSUFFICIENT_DATA` sentinel

### Tier 2: Programming Errors (invalid arguments, broken contracts, violated invariants)

- **Return type:** N/A -- always throw
- **Log level:** No logging needed (exception itself carries context)
- **Exception:** Domain-specific exception class extending `\RuntimeException` or `\InvalidArgumentException`
- **Examples:**
  - `recordFailure()` called without `failure_code` -> throw `RlmInvalidArgumentException`
  - Invalid enum value passed to service -> throw `RlmInvalidArgumentException`
  - Service method called with wrong type -> let PHP TypeError propagate

### Tier 3: External Service Errors (ES down, API failures, network timeouts)

- **Return type:** `null` or fallback value
- **Log level:** `Log::warning()` with structured context array
- **Exception:** NEVER throw to callers -- catch internally and degrade
- **Examples:**
  - ES search fails -> `Log::warning('KnowledgeService: ES search failed', [...])` -> return `null`
  - Embedding API times out -> `Log::warning('EmbeddingService: embed() call failed', [...])` -> return `null`
  - Hub sync connection fails -> `Log::warning(...)` -> enqueue for retry

## Exception Classes

Located in `packages/aicl/src/Rlm/Exceptions/`:

| Class | Extends | Tier | Purpose |
|-------|---------|------|---------|
| `RlmException` | `\RuntimeException` | Base | Base class for all RLM runtime exceptions |
| `RlmInvalidArgumentException` | `\InvalidArgumentException` | 2 | Programming errors (missing required fields, invalid contracts) |
| `EmbeddingException` | `RlmException` | Internal | Wraps driver failures; caught within EmbeddingService |
| `SearchUnavailableException` | `RlmException` | Internal | Wraps ES failures; caught within KnowledgeService |

Located in `packages/aicl/src/Services/Exceptions/`:

| Class | Extends | Tier | Purpose |
|-------|---------|------|---------|
| `SocialAuthException` | `\RuntimeException` | 2 | Non-recoverable auth errors (missing email, auto-create disabled) |

## Logging Format Convention

All service log messages MUST follow this format:

```
"{ServiceName}: {brief description}"
```

With a structured context array as the second argument:

```php
Log::warning('KnowledgeService: ES search failed for index', [
    'index' => $indexName,
    'status' => $response->status(),
    'message' => $e->getMessage(),
]);
```

### Rules

- Service name prefix is mandatory (enables log filtering)
- Context array MUST include at minimum: the operation that failed and the error message
- NEVER log full stack traces at WARNING level (use DEBUG if needed)
- NEVER log sensitive data (API keys, tokens, user passwords)
- For Tier 3 errors: include enough context to diagnose without reproducing (index name, HTTP status, error message)

## Service-Specific Patterns

### KnowledgeService

| Method | Tier | Handling |
|--------|------|----------|
| `search()` | 1 | Silent fallback to deterministic search |
| `executeEsSearch()` | 3 | `Log::warning()` + return `null` |
| `isElasticsearchAvailable()` | 1 | `Log::debug()` on health check failure |
| `recordFailure()` | 2 | Throws `RlmInvalidArgumentException` for missing `failure_code` |

### EmbeddingService

| Method | Tier | Handling |
|--------|------|----------|
| `generate()` | 3 | Try/catch around `$driver->embed()`, `Log::warning()` + return `null` |
| `generateBatch()` | 3 | Try/catch around `$driver->embedBatch()`, `Log::warning()` + return nulls |
| `resolveDriver()` | 3 | `Log::warning()` on driver init failure, fallback to NullDriver |
| `isOllamaReachable()` | 1 | `Log::debug()` on connection failure |

### DistillationService

| Method | Tier | Handling |
|--------|------|----------|
| `distill()` | Wrapped in `DB::transaction()` | `Log::info()` at start/end |
| `distillCluster()` | Wrapped in `DB::transaction()` | `Log::info()` at start/end |
| `getSeverityWeight()` | 1 | `FailureSeverity::tryFrom()` with fallback to `Low` |

### HubClient

| Method | Tier | Handling |
|--------|------|----------|
| `isReachable()` | 3 | `Log::warning()` on `ConnectionException` |
| `pushBatch()` | 3 | `Log::warning()` + enqueue for retry |
| `paginatedGet()` | 3 | `Log::warning()` + return partial results |
| `drainQueue()` | 3 | `Log::warning()` + re-enqueue remaining |

### KpiCalculator

No changes needed. Already follows convention with explicit sentinel values.

### PatternRegistry

No changes needed. Pure data class with no I/O.
