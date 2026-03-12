# Scaffolding & AI Layer

**Version:** 3.0
**Last Updated:** 2026-02-15
**Owner:** `/architect`
**Sprint:** D, K, O

---

## Overview

The Scaffolding & AI layer extends the entity generation system with base class inheritance, runtime entity discovery, an AI framework powered by NeuronAI, AI tool calling for the AI Assistant, and Markdown-based entity spec files.

Seven components:

1. **`--base=` Flag** — Scaffold child entities that extend shared base models
2. **EntityRegistry** — Auto-discover entities at runtime, cross-entity queries
3. **NeuronAI Integration** — Embedding generation, LLM streaming, structured AI context
4. **AI Tool Calling Framework** — Extensible function-calling tools for the AI Assistant (Sprint K)
5. **Entity Spec Files** — Markdown-based, version-controlled entity definitions (Sprint K)
6. **Expanded Spec Sections** — Structured Widget, Notification, Observer, and Report specs replacing hint stubs (Sprint O)
7. **Standalone Spec Types** — `*.tool.md` and `*.permissions.md` parsers for non-entity specs (Sprint O)

---

## 1. Base Class Flag (`--base=`)

### Problem

Some domains share common fields across entity types (e.g., all network devices have hostname, IP, MAC address). Without base class support, every child entity redeclares these fields, violating DRY and complicating schema changes.

### Solution

The `--base=` flag on `aicl:make-entity` enables model inheritance. The scaffolder inspects the base class via the `DeclaresBaseSchema` contract and generates only the delta.

### Architecture

```
DeclaresBaseSchema (contract)
        │
        ▼
BaseSchemaInspector ──→ validates base class
        │                 extracts schema
        ▼
MakeEntityCommand ──→ merges base + child fields
        │               deduplicates traits/contracts
        ▼
    Stubs generate:
      - Model extends BaseClass
      - Migration (child columns only)
      - Resource ("Inherited Fields" + child sections)
```

### Key Decisions

| Decision | Rationale |
|----------|-----------|
| Contract-based inspection (`baseSchema()` static method) | No runtime reflection magic, explicit and testable |
| Delta-only migration | Base migration provides base columns; child migration adds only new columns |
| Trait/contract deduplication | Base traits inherited, not re-declared on child model |
| "Inherited Fields" form section | Visual separation in Filament resource, clear UX |
| Fail-fast validation | Class existence, Model subclass, and contract checks before any file generation |

### Files

- `packages/aicl/src/Contracts/DeclaresBaseSchema.php`
- `packages/aicl/src/Console/Support/BaseSchemaInspector.php`
- `packages/aicl/src/Console/Support/FieldDefinition.php` (`fromBaseSchema()`)

### Usage Guide

`packages/aicl/docs/base-flag.md`

---

## 2. EntityRegistry

### Problem

Cross-entity features (global search, location dashboards, status overviews) require knowing which entities exist at runtime. Hardcoding entity lists is brittle — entities are generated dynamically.

### Solution

`EntityRegistry` scans `app/Models/` for classes implementing `HasEntityLifecycle`, caches results in Redis, and provides column-aware cross-entity query methods.

### Architecture

```
app/Models/ scan
      │
      ▼
HasEntityLifecycle check ──→ abstract classes skipped
      │
      ▼
Schema::hasColumn() probes ──→ column metadata
      │
      ▼
Redis tagged cache ('aicl', 'entity-registry')
      │
      ▼
Query methods (search, atLocation, countsByStatus)
    └── column-aware: skip entities missing required column
```

### Key Decisions

| Decision | Rationale |
|----------|-----------|
| `HasEntityLifecycle` marker interface | Explicit opt-in, not all models are AICL entities |
| Column probing at discovery time | Avoids runtime column checks on every query |
| Column-aware query methods | No raw UNION — each entity queried individually, results merged |
| Redis tagged cache | Efficient bulk invalidation; falls back to simple key for non-taggable stores |
| `flush()` on scaffold/remove | Automatic cache invalidation when entity landscape changes |
| `HasStandardScopes::search()` preferred | Uses AICL's search scope when available, falls back to `LOWER(name) LIKE` |

### Cache Invalidation

```
aicl:make-entity ──→ EntityRegistry::flush()
aicl:remove-entity ──→ EntityRegistry::flush()
```

### Files

- `packages/aicl/src/Services/EntityRegistry.php`

### Usage Guide

`packages/aicl/docs/entity-registry.md`

---

## 3. NeuronAI Integration

### Philosophy: Add, Don't Replace

NeuronAI provides the AI/agent framework layer. NeuronAI handles LLM abstraction, embeddings, and streaming. The RLM knowledge system was extracted to Forge in Sprint F0.

### Layer Diagram

```
┌─────────────────────────────────────────────────┐
│                  Consumer Code                   │
│  (Controllers, Jobs, Validators, Widgets)        │
├────────────┬──────────────┬─────────────────────┤
│ EmbeddingService │ AiProvider  │  HasAiContext    │
│ (thin wrapper)   │ Factory     │  (trait)         │
├────────────┴──────┴───────┴─────────────────────┤
│              NeuronAI Framework                   │
│  EmbeddingProvider  │  Agent  │  Streaming API    │
├─────────────────────┴─────────┴─────────────────┤
│           LLM Providers (via NeuronAI)            │
│  Anthropic (primary) │ OpenAI (embeddings) │ ...  │
└─────────────────────────────────────────────────┘

                    ╔═══════════════════════╗
                    ║   EXTERNAL (Forge)    ║
                    ║  Knowledge base       ║
                    ║  Validation patterns  ║
                    ║  via Forge MCP tools  ║
                    ╚═══════════════════════╝
```

### 3a. Embedding Driver Migration

**Before:** Custom `OpenAiDriver` and `OllamaDriver` classes making direct HTTP calls.
**After:** `NeuronAiEmbeddingAdapter` bridging NeuronAI's `EmbeddingsProviderInterface` to AICL's `EmbeddingDriver` contract.

```
EmbeddingService (public API unchanged)
      │
      ▼
resolveDriver()
      │
      ├── driver='null' → NullDriver (test/fallback)
      ├── OpenAI key present → NeuronAiEmbeddingAdapter(OpenAI provider, pad=false)
      ├── Ollama reachable → NeuronAiEmbeddingAdapter(Ollama provider, pad=true)
      └── fallback → NullDriver
```

**Key Decisions:**

| Decision | Rationale |
|----------|-----------|
| Adapter pattern (not direct replacement) | AICL's `EmbeddingDriver` contract preserved; consumers unaffected |
| Zero-padding for Ollama | Ollama models produce variable dimensions; pad to 1536 for ES index compat |
| `NullDriver` kept | Tests and environments without LLM access still work |
| No new `.env` vars | `configureNeuronAi()` maps existing AICL config keys |
| Legacy drivers deleted | `OpenAiDriver.php`, `OllamaDriver.php` removed — single code path |

### 3b. AI Token Streaming (WebSocket via Reverb)

AI streaming uses a queued job + Reverb WebSocket architecture (Sprint I). The `AiStreamJob` dispatches to the queue, builds a NeuronAI agent, streams the response, and broadcasts each token via Reverb. This frees Swoole workers immediately (~5ms hold time vs 10-60s with SSE).

**WebSocket Event Types:**

| Event | Payload | When |
|-------|---------|------|
| `ai.stream_started` | `{stream_id, user_id}` | Stream begins |
| `ai.token` | `{stream_id, token, index}` | Each token from LLM |
| `ai.tool_call` | `{stream_id, tools: [{name, inputs}]}` | LLM invokes a tool (Sprint K) |
| `ai.stream_completed` | `{stream_id, total_tokens, usage}` | Stream finished |
| `ai.stream_failed` | `{stream_id, error}` | Exception occurred |

### 3c. HasAiContext Trait

Serializes any Eloquent model into a structured format for LLM consumption.

**Output Shape:**

```php
[
    'type' => 'Cable Modem',           // Str::headline(class_basename)
    'id' => 'uuid',                    // Primary key
    'label' => 'CM-001',              // name/title/id fallback chain
    'attributes' => [...],             // From aiContextFields() (default: fillable)
    'relationships' => [...],          // Loaded relations only (no N+1)
    'meta' => [...],                   // created_at, updated_at, status, is_active
]
```

**Key Decisions:**

| Decision | Rationale |
|----------|-----------|
| Fillable fields as default | Sensible default; override `aiContextFields()` to customize |
| Only loaded relationships | Prevents N+1; caller controls what's loaded via eager loading |
| Enum/DateTime/object coercion | LLM needs strings, not PHP objects |
| Relationship summaries (`{id, type, label}`) | Compact; full nested context would bloat prompt tokens |

### Files

- `packages/aicl/src/Traits/HasAiContext.php`
- `packages/aicl/src/AI/AiProviderFactory.php`
- `packages/aicl/src/AI/AiAssistantController.php`

---

## 4. AI Tool Calling Framework (Sprint K)

### Problem

The AI Assistant (Sprint I) streams text only — the LLM cannot query application data, check system status, or interact with entities. Users asking "who's online?" or "how many overdue invoices?" get hallucinated answers.

### Solution

An extensible tool-calling framework built on NeuronAI's native `Tool` system. Tools are PHP classes with name, description, and typed parameters that NeuronAI serializes into the LLM provider's function-calling format. The LLM autonomously decides when to invoke tools based on the descriptions.

### Architecture

```
AiToolRegistry (singleton)
      │ register() / registerMany()
      ▼
AiStreamJob::buildAgent()
      │ $registry->resolve($userId) → tool instances with auth injected
      │ $agent->addTool($tools)
      ▼
NeuronAI Agent
      │ ToolPayloadMapper serializes tools to provider format
      ▼
LLM API Request
      │ tools: [{name, description, input_schema}]
      ▼
LLM Response
      │ tool_use: {name: "whos_online", inputs: {}}
      ▼
NeuronAI executes tool.__invoke()
      │ feeds result back to LLM
      ▼
LLM generates final text response
      │
      ▼
AiToolCallEvent broadcasts on WebSocket
      │ Frontend shows "Used: Who's Online" chip
```

### Built-in Tools (5)

| Tool | Category | Auth | Description |
|------|----------|------|-------------|
| `WhosOnlineTool` | system | No | Queries PresenceRegistry for online users |
| `CurrentUserTool` | system | Yes | Returns authenticated user info (id, name, email, roles) |
| `QueryEntityTool` | queries | Yes | Queries entities via EntityRegistry with filters and policy checks |
| `EntityCountTool` | queries | Yes | Counts records with optional status/date grouping |
| `HealthStatusTool` | system | No | Returns health check results from HealthCheckRegistry |

### Key Decisions

| Decision | Rationale |
|----------|-----------|
| Extend NeuronAI `Tool` (not custom) | Leverage native function-calling format, no reinvention |
| `BaseTool` abstract with `AiTool` contract | AICL-specific features (category, auth) without replacing NeuronAI |
| Registry pattern (singleton) | Centralized tool registration, client extension via config |
| Auth injection via `setAuthenticatedUser()` | Tools that query data can enforce policies per-user |
| `AiToolCallEvent` broadcast only (no result event) | NeuronAI doesn't yield tool results to the consumer; LLM text incorporates them |
| Tool descriptions are the "API" | LLM reads descriptions to decide relevance — good names matter |

### Client Extension

```php
// config/aicl.php
'ai' => [
    'tools_enabled' => true,
    'tools' => [
        \App\AI\Tools\MyCustomTool::class,
    ],
],
```

### Files

- `packages/aicl/src/AI/Contracts/AiTool.php` — Tool interface
- `packages/aicl/src/AI/Tools/BaseTool.php` — Abstract base class
- `packages/aicl/src/AI/AiToolRegistry.php` — Service container singleton
- `packages/aicl/src/AI/Tools/WhosOnlineTool.php`
- `packages/aicl/src/AI/Tools/CurrentUserTool.php`
- `packages/aicl/src/AI/Tools/QueryEntityTool.php`
- `packages/aicl/src/AI/Tools/EntityCountTool.php`
- `packages/aicl/src/AI/Tools/HealthStatusTool.php`
- `packages/aicl/src/AI/Events/AiToolCallEvent.php` — Broadcast event

---

## 5. Entity Spec Files (Sprint K)

### Problem

Entity generation requires manually translating pipeline specs (Phase 1/2) into CLI flags (`--fields`, `--states`, `--relationships`). This is error-prone, not version-controlled, and the translation step is wasted effort since the spec already has all the data.

### Solution

Markdown-based `.entity.md` spec files that serve as the compilable source of truth. The spec file IS the entity definition — it gets parsed directly into an `EntitySpec` value object and fed to the scaffolder. No manual flag translation needed.

### Architecture

```
specs/{Name}.entity.md
      │
      ▼
SpecFileParser::parse()
      │ splits Markdown into sections
      │ parses tables, fenced blocks, bullet lists
      ▼
EntitySpec (value object)
      │ fields, states, transitions, relationships,
      │ enums, traits, options, business rules, hints
      ▼
MakeEntityCommand --from-spec
      │ transfers EntitySpec to internal state
      │ runs full generation pipeline
      ▼
22-31 generated files
```

### Spec Format (Sections)

| Section | Format | Required |
|---------|--------|----------|
| `# Name` | H1 heading (PascalCase) | Yes |
| Description | Plain text after H1 | No (recommended) |
| `## Fields` | Markdown table | Yes |
| `## Enums` | H3 subsections with case/label/color/icon tables | No |
| `## States` | Fenced code block with → arrows | No |
| `## Relationships` | Markdown table | No |
| `## Traits` | Bullet list | No |
| `## Options` | Bullet list (key: value) | No |
| `## Business Rules` | Bullet list | No |
| `## Widget Hints` | Bullet list (legacy) | No |
| `## Notification Hints` | Bullet list (legacy) | No |
| `## Widgets` | Structured subsections (Sprint O upgrade) | No |
| `## Notifications` | Structured key-value tables (Sprint O upgrade) | No |
| `## Observer Rules` | Structured event/action tables (Sprint O upgrade) | No |
| `## Report Layout` | Structured section/column tables (Sprint O upgrade) | No |

### Key Decisions

| Decision | Rationale |
|----------|-----------|
| Markdown format (not YAML/JSON) | Human-readable, version-control friendly, diffable |
| Pipe-separated modifiers (`nullable|unique`) | Avoids confusion with colon-separated CLI format |
| Rich enum data (case/label/color/icon) | Spec generates complete enum classes — no placeholder customization needed |
| Reserved column validation (ERROR) vs auto-columns (WARN) | `id`/timestamps always auto-generated; `is_active`/`owner_id` from traits but overridable |
| `--from-spec` rejects conflicting flags | Prevents ambiguity — spec is the single source |
| Business rules are passthrough | Not parsed into code — used by agents for Phase 2/3 context |
| Legacy hints coexist with structured sections | `## Widget Hints` / `## Notification Hints` still work; structured `## Widgets` / `## Notifications` take precedence when present (Sprint O) |

### Commands

```bash
# Validate spec before generation
ddev artisan aicl:validate-spec Invoice

# Generate from spec
ddev artisan aicl:make-entity Invoice --from-spec --no-interaction

# Explicit spec path
ddev artisan aicl:make-entity Invoice --spec-path=path/to/spec.md --no-interaction
```

### Spec Validation Patterns (P-043 through P-046)

| Pattern | Target | Severity | Description |
|---------|--------|----------|-------------|
| `spec.file_exists` | spec | warning | Spec file exists in `specs/` directory |
| `spec.matches_code` | spec | warning | Generated code matches spec definitions |
| `spec.has_business_rules` | spec | warning | Spec has business rules section |
| `spec.has_description` | spec | warning | Spec has description paragraph |

All additive — don't affect existing 40/40 base pattern scoring.

### Files

- `packages/aicl/src/Console/Support/EntitySpec.php` — Value object
- `packages/aicl/src/Console/Support/SpecFileParser.php` — Markdown parser
- `packages/aicl/src/Console/Commands/ValidateSpecCommand.php` — `aicl:validate-spec`
- `specs/Invoice.entity.md` — Golden example spec

---

## 6. Expanded Spec Sections (Sprint O)

### Problem

The entity spec system (Sprint K) proved "Markdown → parse → validate → generate" works, but `## Widget Hints`, `## Notification Hints`, and `## Business Rules` are unstructured natural language. The scaffolder still generates ~20% placeholder code (TODO stubs) because these sections can't be deterministically parsed into code.

### Solution

Replace hint sections with structured Markdown tables that generate complete, working code. Four new structured sections plus shared infrastructure:

| Section | Replaces | Generates |
|---------|----------|-----------|
| `## Widgets` | `## Widget Hints` | Complete `getStats()`, `getData()`, `table()` methods |
| `## Notifications` | `## Notification Hints` | Complete notification classes + observer dispatch logic |
| `## Observer Rules` | Inline observer TODOs | Complete observer methods with `isDirty()` checks |
| `## Report Layout` | Field-based PDF stubs | Complete Blade templates with typed formatting |

All four are **additive** — legacy hint sections continue to work unchanged. Structured sections take precedence when present.

### Architecture

```
specs/{Name}.entity.md
      │
      ├── ## Widgets ──→ WidgetSpec[] ──→ generateStructuredWidgets()
      │     ├── ### StatsOverview ──→ MetricDefinition[]
      │     ├── ### Chart ──→ chartType, groupBy, colors
      │     └── ### Table ──→ ColumnDefinition[], query DSL
      │
      ├── ## Notifications ──→ NotificationSpec[]
      │     └── trigger, title, body (template vars), icon, color, recipient, channels
      │
      ├── ## Observer Rules ──→ ObserverRuleSpec[]
      │     ├── ### On Create ──→ log/notify actions
      │     ├── ### On Update ──→ field-watch + conditional actions
      │     └── ### On Delete ──→ cleanup actions
      │
      └── ## Report Layout ──→ ReportLayoutSpec
            ├── ### Single Report ──→ ReportSectionSpec[] (title, badges, info-grid, card, timeline)
            └── ### List Report ──→ ReportColumnSpec[] (text, date, currency, badge, percent)
```

### Widget Query DSL

The `WidgetQueryParser` converts a mini-DSL into Eloquent code strings:

```
count(*)                           → Model::query()->count()
count(*) where status = active     → Model::query()->where('status', ...)->count()
sum(amount) where status != paid   → Model::query()->where('status', '!=', 'paid')->sum('amount')
where status = active, order by due_date, limit 5
                                   → Model::query()->where(...)->orderBy('due_date')->limit(5)
```

State fields use class references (`Active::getMorphClass()`); enum/string fields use string literals.

### Observer Generation Priority Chain

Three-level fallback for observer generation:

```
1. Observer Rules (O.3)       → Full observer from ## Observer Rules (field-watch + isDirty)
2. Notification Specs (O.2)   → Observer from ## Notifications (trigger-based dispatch)
3. Legacy Stubs               → TODO-stub observer (existing behavior)
```

### Notification Template Variables

`NotificationTemplateResolver` resolves variables in notification body templates:

| Variable Pattern | Resolution |
|-----------------|------------|
| `{model.field}` | `$record->field` |
| `{actor.field}` | `auth()->user()->field` |
| `{old.field}` | `$record->getOriginal('field')` |
| `{new.field}` | `$record->field` (current value) |

### Report Section Types

| Type | Renders |
|------|---------|
| `title` | `<h1>` with resolved field value |
| `badges` | Inline colored spans |
| `info-grid` | Two-column key-value table |
| `card` | Text content block with `nl2br(e())` |
| `timeline` | Activity log loop with configurable limit |

### Key Decisions

| Decision | Rationale |
|----------|-----------|
| Null-check branching (not replacement) | Legacy specs work unchanged; `has*()` returns false when section absent |
| Deterministic DSL (no AI/fuzzy matching) | Unparseable queries output TODO comment with original DSL string |
| `MarkdownTableParser` shared utility | Extracted in O.5 — used by all four section parsers |
| `SpecValidation` shared trait | 9 validation helpers reused across `ValidateSpecCommand` checks |
| Three-priority observer chain | Most-specific wins; earlier tracks don't break later ones |
| Template variable resolution | Compile-time string replacement — no runtime overhead |

### Infrastructure (Track O.5)

| File | Purpose |
|------|---------|
| `packages/aicl/src/Console/Support/MarkdownTableParser.php` | Static: `parseMarkdownTable()`, `splitSections()`, `parseBulletList()` |
| `packages/aicl/src/Console/Support/SpecValidation.php` | Trait: `isPascalCase()`, `isSnakeCase()`, `isCamelCase()`, `isValidColumnType()`, etc. |

### Files

**Value Objects:**
- `packages/aicl/src/Console/Support/WidgetSpec.php`
- `packages/aicl/src/Console/Support/MetricDefinition.php`
- `packages/aicl/src/Console/Support/ColumnDefinition.php`
- `packages/aicl/src/Console/Support/NotificationSpec.php`
- `packages/aicl/src/Console/Support/NotificationTemplateResolver.php`
- `packages/aicl/src/Console/Support/ObserverRuleSpec.php`
- `packages/aicl/src/Console/Support/ReportLayoutSpec.php`
- `packages/aicl/src/Console/Support/ReportSectionSpec.php`
- `packages/aicl/src/Console/Support/ReportFieldSpec.php`
- `packages/aicl/src/Console/Support/ReportColumnSpec.php`

**Parsers:**
- `packages/aicl/src/Console/Support/WidgetQueryParser.php` — Query DSL → Eloquent code
- `packages/aicl/src/Console/Support/SpecFileParser.php` — Added `parseWidgets()`, `parseNotifications()`, `parseObserverRules()`, `parseReportLayout()`

**Generation:**
- `packages/aicl/src/Console/Commands/MakeEntityCommand.php` — Structured generation branches for all four sections
- `packages/aicl/src/Console/Commands/ValidateSpecCommand.php` — Validation for all four sections

---

## 7. Standalone Spec Types (Sprint O)

### Problem

The entity spec format covers entities well, but AI Tools and RBAC Permissions are cross-cutting concerns that don't fit the entity lifecycle. Without structured specs, these are defined ad-hoc in Slack/docs and manually translated to code.

### Solution

Two standalone spec file formats with dedicated parsers:

- **`*.tool.md`** — Defines NeuronAI-compatible AI tools with typed parameters and return schemas
- **`*.permissions.md`** — Defines roles, entity permission matrices, and custom permissions

Parsers only — generation commands (`aicl:make-tool --from-spec`, `aicl:make-permissions --from-spec`) are deferred to a future sprint.

### Tool Spec Format

```markdown
# MyCustomTool

Description paragraph.

## Tool
| Key | Value |
|-----|-------|
| Name | my_custom_tool |
| Category | queries |
| Auth Required | Yes |
| Description | Does something useful |

## Parameters
| Name | Type | Required | Description |
|------|------|----------|-------------|
| query | string | Yes | Search query |
| limit | integer | No | Max results |

## Returns
| Field | Type | Description |
|-------|------|-------------|
| results | array | Matched items |
| total | integer | Total count |
```

### Permission Spec Format

```markdown
# Project Permissions

## Roles
| Role | Description | Guard |
|------|-------------|-------|
| Admin | Full access | web, api |
| Editor | Content management | web |

## Permissions
| Entity | Admin | Editor |
|--------|-------|--------|
| Post | * | ViewAny, View, Create, Update |
| User | * | ViewAny, View |

## Custom Permissions
| Permission | Roles | Description |
|------------|-------|-------------|
| manage-settings | Admin | Access system settings |
```

### Key Decisions

| Decision | Rationale |
|----------|-----------|
| Standalone files (not entity spec sections) | Tools and permissions are cross-cutting, not entity-specific |
| Parsers first, generators deferred | Validate the format before investing in generation code |
| NeuronAI type mapping (`neuronAiType()`) | Tool parameters map directly to NeuronAI `PropertyType` constants |
| `*` wildcard expansion | `*` → 7 standard CRUD actions (ViewAny, View, Create, Update, Delete, Restore, ForceDelete) |
| Multi-guard support | Roles can specify `web, api` — generates role on both guards |

### Files

**Tool Spec:**
- `packages/aicl/src/Console/Support/ToolSpec.php` — Container value object
- `packages/aicl/src/Console/Support/ToolParameterSpec.php` — Parameter with `neuronAiType()` and `phpType()`
- `packages/aicl/src/Console/Support/ToolReturnFieldSpec.php` — Return field value object
- `packages/aicl/src/Console/Support/ToolSpecParser.php` — Standalone parser

**Permission Spec:**
- `packages/aicl/src/Console/Support/PermissionSpec.php` — Container with `expandWildcard()`
- `packages/aicl/src/Console/Support/RoleSpec.php` — Role value object
- `packages/aicl/src/Console/Support/CustomPermissionSpec.php` — Custom permission value object
- `packages/aicl/src/Console/Support/PermissionSpecParser.php` — Standalone parser

---

## Cross-References

| Topic | Document |
|-------|----------|
| Knowledge & Validation | Managed via Forge MCP (extracted from AICL in Sprint F0) |
| Real-Time Layer (Broadcasting, Polling) | `.claude/architecture/event-realtime-layer.md` |
| Swoole Foundations (Concurrent, SwooleCache) | `.claude/architecture/swoole-foundations.md` |
| Entity System (traits, contracts, generation) | `.claude/architecture/entity-system.md` |

---

## Test Summary

| Component | Tests | Source |
|-----------|-------|--------|
| BaseSchemaInspector / `--base=` flag | 10 | `tests/Framework/BaseSchemaFlagTest.php` |
| EntityRegistry | 22 | `packages/aicl/tests/Unit/Services/EntityRegistryTest.php` |
| HasAiContext | 21 | `packages/aicl/tests/Unit/Traits/HasAiContextTest.php` |
| AiAssistantController | 7 | `packages/aicl/tests/Feature/AI/AiAssistantControllerTest.php` |
| AI Tool Registry | 9 | `packages/aicl/tests/Unit/AI/AiToolRegistryTest.php` |
| Built-in AI Tools (5) | 37 | `packages/aicl/tests/Unit/AI/Tools/` |
| AI Tool Call Streaming | 9 | `packages/aicl/tests/Feature/AI/AiToolCallStreamTest.php` |
| SpecFileParser | 52 | `packages/aicl/tests/Unit/Console/Support/SpecFileParserTest.php` |
| ValidateSpecCommand | 14 | `packages/aicl/tests/Feature/Console/ValidateSpecCommandTest.php` |
| MakeEntity --from-spec | 10 | `packages/aicl/tests/Feature/Console/MakeEntityFromSpecTest.php` |
| MarkdownTableParser (O.5) | 22 | `packages/aicl/tests/Unit/Console/Support/MarkdownTableParserTest.php` |
| SpecValidation trait (O.5) | 15 | `packages/aicl/tests/Unit/Console/Support/SpecValidationTest.php` |
| MakeEntity --cleanup (O.5) | 4 | `packages/aicl/tests/Unit/Console/Support/MakeEntityCleanupTest.php` |
| WidgetQueryParser (O.1) | 30 | `packages/aicl/tests/Unit/Console/Support/WidgetQueryParserTest.php` |
| WidgetSpec value objects (O.1) | 8 | `packages/aicl/tests/Unit/Console/Support/WidgetSpecTest.php` |
| Widget parser integration (O.1) | 11 | `packages/aicl/tests/Unit/Console/Support/WidgetParserTest.php` |
| NotificationSpec value objects (O.2) | 12 | `packages/aicl/tests/Unit/Console/Support/NotificationSpecTest.php` |
| NotificationTemplateResolver (O.2) | 15 | `packages/aicl/tests/Unit/Console/Support/NotificationTemplateResolverTest.php` |
| Notification parser integration (O.2) | 12 | `packages/aicl/tests/Unit/Console/Support/NotificationParserTest.php` |
| ObserverRuleSpec (O.3) | 10 | `packages/aicl/tests/Unit/Console/Support/ObserverRuleSpecTest.php` |
| Observer rule parser (O.3) | 10 | `packages/aicl/tests/Unit/Console/Support/ObserverRuleParserTest.php` |
| ReportLayoutSpec value objects (O.4) | 12 | `packages/aicl/tests/Unit/Console/Support/ReportLayoutSpecTest.php` |
| Report layout parser (O.4) | 9 | `packages/aicl/tests/Unit/Console/Support/ReportLayoutParserTest.php` |
| ToolSpecParser (O.6) | 11 | `packages/aicl/tests/Unit/Console/Support/ToolSpecParserTest.php` |
| PermissionSpecParser (O.6) | 11 | `packages/aicl/tests/Unit/Console/Support/PermissionSpecParserTest.php` |
| **Total** | **403** | |
