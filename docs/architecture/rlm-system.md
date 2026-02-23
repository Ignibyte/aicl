# AICL RLM System — Reinforcement Learning Model

**Version:** 2.6
**Last Updated:** 2026-02-19
**Owner:** `/rlm`, `/architect`

---

## Overview

The RLM is a **self-improving quality assurance system for AI-generated code**. When Claude generates an entity (model, migration, Filament resource, etc.), the RLM validates the output against established patterns and known failure modes — catching bugs before they ship.

The system uses a **two-layer architecture** — PostgreSQL as the source of truth, Elasticsearch for hybrid search:

```
┌────────────────────────────────────────────────────────────┐
│                  PostgreSQL (Source of Truth)                │
│  11 Eloquent Models · Filament Admin · REST API             │
│  Failures, lessons, scores, traces, waivers, annotations    │
├────────────────────────────────────────────────────────────┤
│            Optimization Layer (Sprint X)                     │
│  Effectiveness-weighted ranking · Surfaced count tracking   │
│  Outcome-driven optimize · EntitySignature contracts        │
├────────────────────────────────────────────────────────────┤
│            Hardening Layer (Sprint W)                        │
│  Structured reflection · Observation/instruction split      │
│  Proof-backed promotion · Pattern versioning · Waivers      │
│  GC scheduler · Mutation suite · RuleNormalizer             │
├────────────────────────────────────────────────────────────┤
│              Feedback Loop + KPIs (Sprint M)                │
│  KpiCalculator · RedistillJob · RlmFailureDistillObserver   │
│  Prevention/ignore/surfaced tracking · Confidence decay     │
├────────────────────────────────────────────────────────────┤
│                Distillation Layer (Sprint L)                 │
│  DistillationService · DistilledLesson model                │
│  rule_hash clustering · Impact scoring · Agent cheat sheets │
├────────────────────────────────────────────────────────────┤
│                  Elasticsearch (Hybrid Search)               │
│  6 Scout-indexed models · kNN + BM25 · aicl_ prefix         │
│  dense_vector (1536 dims, cosine) · embeddings via OpenAI   │
├────────────────────────────────────────────────────────────┤
│                  Validation Engine (Always On)               │
│  L1: 42+ structural regex patterns (versioned, targetable)  │
│  L2: 8 semantic checks via sub-LLM (optional)              │
│  L3: Pattern discovery from generation traces               │
│  Inline assertions: --target + --quick mid-generation       │
└────────────────────────────────────────────────────────────┘
```

> **Consolidation complete:** The previous SQLite L0 layer was eliminated. `KnowledgeBase.php` and `MarkdownParser.php` have been deleted. `KnowledgeService` is the central query interface. `EmbeddingService` handles vector generation via configurable drivers (OpenAI/NeuronAI/Null).

---

## Layer Architecture

### Knowledge Layer (PostgreSQL + Elasticsearch)

All RLM data lives in PostgreSQL via Eloquent models. Elasticsearch provides hybrid search (kNN vector similarity + BM25 keyword matching) for the `KnowledgeService`.

**Central service:** `packages/aicl/src/Rlm/KnowledgeService.php`

The `KnowledgeService` provides:
- `search(query)` — Hybrid kNN + BM25 search across all indexed models via Elasticsearch
- `recall(--agent, --phase)` — Context-filtered knowledge for specific agent/phase combinations (risk briefings)
- `learn(summary, topic, tags)` — Record new lessons to PostgreSQL
- `failures()` — List known failure modes
- `scores()` — Show validation score history
- `stats()` — Aggregate counts across all RLM models

**Embedding layer:** `packages/aicl/src/Rlm/EmbeddingService.php`

Configurable drivers for vector generation:
- **NeuronAI adapter** — Uses NeuronAI's embedding API (wraps OpenAI)
- **Null driver** — No-op for testing and environments without API keys
- **GenerateEmbeddingJob** — Queued job for batch embedding generation

**Artisan commands:**
```bash
aicl:rlm search "query"              # Hybrid ES search across lessons + failures + patterns
aicl:rlm recall --agent=architect    # Context-filtered knowledge for specific agent
aicl:rlm learn "lesson" --topic=X    # Record new lesson (free-text, structured flags, or --json)
aicl:rlm failures                    # List known failures
aicl:rlm scores                      # Show validation score history
aicl:rlm stats                       # Aggregate counts (includes surfaced_count + prevention_rate)
aicl:rlm export                      # Dump to markdown for review
aicl:rlm trace-save                  # Save generation trace
aicl:rlm sync --push                 # Push to hub
aicl:rlm sync --pull                 # Pull from hub
aicl:rlm sync-knowledge              # Re-run all RLM seeders with diff reporting
aicl:rlm embed                       # Generate embeddings for unembedded records
aicl:rlm index                       # Reindex Elasticsearch
aicl:rlm aar                         # After-action review (closed-loop learning)
aicl:rlm cleanup                     # Remove faker/test data from knowledge tables
aicl:rlm distill                     # Cluster failures into agent-targeted distilled lessons
aicl:rlm feedback                    # Submit lesson effectiveness feedback after validation
aicl:rlm health                      # Display 3 KPIs with trend indicators
aicl:rlm health --verdict            # Add system verdict (EARNING_ITS_KEEP / MARGINAL / OVERHEAD)
aicl:rlm waiver add/list/remove      # Manage entity pattern waivers (Sprint W)
aicl:rlm optimize                    # Adjust lesson rankings from trace outcomes (Sprint X)
aicl:rlm optimize --apply            # Persist optimization adjustments
aicl:rlm optimize --reset            # Restore base impact scores
```

**Validate commands:**
```bash
aicl:validate {Name}                    # Structural validation (full)
aicl:validate {Name} --semantic         # Structural + L2 semantic
aicl:validate {Name} --target=model --quick  # Inline assertion (Sprint X)
aicl:validate {Name} --diff             # Show delta from last run
```

**ES Indices:** 6 models indexed with `aicl_` prefix:
- `RlmPattern` — name, description, target, category
- `RlmFailure` — title, description, failure_code, root_cause, fix
- `RlmLesson` — topic, summary, detail, tags
- `GoldenAnnotation` — file, annotation content, entity context
- `PreventionRule` — description, entity type, phase targeting
- `DistilledLesson` — title, guidance, lesson_code, target_agent, trigger_context

All indices include `dense_vector` fields (1536 dimensions, cosine similarity) for kNN search.

**Scout driver:** `matchish/laravel-scout-elasticsearch` v7.12 — auto-configured via `AiclServiceProvider::configureElasticsearch()`.

**Seeded data:** 281+ records (40 patterns, 20 failures, 209 golden annotations, 15 curated lessons, 15 curated prevention rules, distilled lessons generated from base failures) — all with embeddings.

---

### Distillation Layer (Sprint L)

The distillation layer transforms raw failure data into agent-targeted intelligence. Instead of every agent seeing every failure, each agent gets a focused cheat sheet with the top lessons relevant to their role and phase.

**Key file:** `packages/aicl/src/Rlm/DistillationService.php`

**How it works:**

1. **Failure Clustering** — 4-pass algorithm: (1) `rule_hash` deterministic clustering (primary — Sprint W), (2) `pattern_id` grouping for legacy records without `rule_hash`, (3) category + root cause fuzzy text similarity, (4) unclustered singles. Each cluster represents a single conceptual issue.

2. **Impact Scoring** — Each cluster is scored: `sum(report_count × severity_weight × scaffolding_fixed_factor)`. Severity weights: Critical=10, High=5, Medium=2, Low=1, Informational=0. Fixed factor: 0.3 if already fixed in scaffolder, 1.0 otherwise.

3. **Effectiveness-Weighted Ranking** (Sprint X) — Lessons ranked by `impact_score * GREATEST(confidence, 0.1)` instead of raw `impact_score`. Confidence floor (0.1) prevents new lessons from vanishing; decaying lessons naturally fall off cheat sheets.

4. **Agent-Perspective Lesson Generation** — Each cluster produces one `DistilledLesson` per relevant agent/phase combination. Six agent perspectives: architect (phases 3, 5), tester (phases 4, 6, 7), rlm (phases 4, 6), designer (phase 3.5), solutions (phase 2), pm (phases 1, 7, 8).

5. **When-Then Rules** — Structured WHEN/THEN rules derived from `feedback`/`fix` fields (Sprint W); falls back to RULE-only format for legacy lessons. Lessons with `trigger_context` (JSONB) generate actionable rules: "WHEN scaffolding with HasStandardScopes THEN override searchableColumns()".

6. **Cheat Sheet Delivery** — `aicl:rlm recall` defaults to a ~30-line cheat sheet (top 5 lessons by effectiveness, When-Then rules, recent outcomes). Falls back to full recall if no distilled lessons exist yet. `surfaced_count` bulk-incremented on each recall (Sprint X).

7. **Observation/Instruction Split** (Sprint W) — Lessons classified as `observation` (default), `instruction`, or `prevention_rule` via `LessonType` enum. Only `instruction` and `prevention_rule` types with `is_verified = true` appear in recall. Observations promoted to instructions via `promoteObservation()` which requires `KnowledgeLink` proof hooks.

8. **Auto-Deactivation** (Sprint W) — Lessons with confidence < 0.2 automatically deactivated. Lessons surfaced 50+ times with zero interactions flagged `needs_review`. Stale lessons (no positive signal in N generations) also flagged.

**Recall formats:**
- `cheatsheet` (default) — ~30-line focused output
- `full` — Legacy 229-line verbose output (backward compatible)
- `json` — Structured JSON for programmatic consumption

**Model:** `DistilledLesson` — UUID PK, lesson_code (e.g., `DL-001-A3`), title, guidance, target_agent, target_phase, trigger_context (JSONB), source_failure_codes (JSONB), impact_score, base_impact_score (Sprint X — original score for reset), confidence, surfaced_count (Sprint X), applied/prevented/ignored counts, is_active, soft deletes. Scout-indexed in Elasticsearch.

**Lesson codes:** Deterministic format `DL-{BF_NUM}-{AGENT_INITIAL}{PHASE}` (e.g., `DL-001-A3` = lesson from BF-001 for architect phase 3).

---

### Feedback Loop (Sprint M)

The feedback loop makes the distillation system self-improving. After each pipeline validation, the feedback command compares surfaced lessons against actual failures to track what worked and what didn't.

**Key files:**
| File | Purpose |
|------|---------|
| `packages/aicl/src/Rlm/KpiCalculator.php` | KPI computation service (3 KPIs + verdict + `lessonRelevanceRates()`) |
| `packages/aicl/src/Jobs/RedistillJob.php` | Queued re-distillation for affected clusters |
| `packages/aicl/src/Observers/RlmFailureDistillObserver.php` | Triggers RedistillJob on new failure creation |
| `packages/aicl/src/Jobs/ProofLinkIntegrityJob.php` | Validates proof links, demotes/flags broken (Sprint W) |

**How it works:**

1. **Feedback Collection** — After Phase 4/6 validation, agents run `aicl:rlm feedback --entity=X --phase=4 --surfaced="DL-001,DL-003" --failures="BF-012"`. The command compares surfaced lesson source failures against actual failures.

2. **Prevention/Ignore Counting** — If a surfaced lesson's source failure did NOT recur → `prevented_count++`. If it DID recur → `ignored_count++`. Uncovered failures (not addressed by any lesson) are flagged.

3. **Confidence Growth/Decay** — `confidence = clamp(confidence + (prevented × 0.02) - (ignored × 0.05), 0.0, 1.0)`. Effective lessons rise to the top of cheat sheets; ineffective ones decay and eventually auto-retire.

4. **Auto-Redistillation** — `RlmFailureDistillObserver` watches for new `RlmFailure` records. When a new failure clusters with existing failures covered by distilled lessons, it dispatches a `RedistillJob` to update the affected lessons.

5. **GenerationTrace KPI Fields** — Feedback writes `known_failure_count`, `novel_failure_count`, `surfaced_lesson_codes`, `failure_codes_hit` to the entity's `GenerationTrace` for KPI measurement.

**Artisan commands:**
```bash
aicl:rlm feedback --entity=X --phase=4 --surfaced="DL-001" --failures=""   # All prevented
aicl:rlm feedback --entity=X --phase=4 --surfaced="DL-003" --failures="BF-012"  # DL-003 ignored
aicl:rlm health                    # Display 3 KPIs with trend indicators
aicl:rlm health --verdict          # Add system verdict (EARNING_ITS_KEEP / MARGINAL / OVERHEAD)
```

### Pipeline KPIs (Sprint M)

Four objective KPIs measure whether the distillation system improves pipeline outcomes:

| KPI | Metric | Signal |
|-----|--------|--------|
| Fix Iteration Trend | Rolling avg of `fix_iterations` (last 5 vs last 20) | IMPROVING (↓>20%), STABLE, DECLINING (↑>20%) |
| Known vs Novel Failure Ratio | `known_failure_count / total` per run | HEALTHY (<30%), MODERATE (30-50%), HIGH_RECURRENCE (>50%) |
| Lesson Effectiveness Rate | `prevented / (prevented + ignored)` per lesson | Top/bottom performers listed |
| Lesson Relevance Rate (Sprint X) | `prevented_count / max(surfaced_count, 1)` | Measures real-world prevention vs. surface frequency |

**Auto-retirement:** Lessons with effectiveness < 30% after 5+ applications are automatically deactivated (`is_active = false`). Lessons with confidence < 0.2 also auto-deactivated (Sprint W). Lessons surfaced 50+ times with zero interactions flagged `needs_review` (Sprint X).

**Outcome optimization** (Sprint X): `aicl:rlm optimize` analyzes `GenerationTrace` outcomes (GOOD/FAIR/POOR), adjusts lesson `impact_score` with +/- 20% clamp per run. Minimum 5 traces required. Original scores preserved in `base_impact_score` for reset.

**System verdict** (`aicl:rlm health --verdict`):
- `EARNING_ITS_KEEP` — All 3 metrics pass thresholds
- `MARGINAL` — Borderline (within 10% of threshold)
- `OVERHEAD` — 2+ metrics fail after 20+ pipeline runs
- `INSUFFICIENT_DATA` — Fewer than 20 pipeline runs

The verdict is computed from KPI thresholds, not subjective assessment. If after 20 runs the verdict is `OVERHEAD`, the system recommends simplifying itself.

---

### L1 — Structural Validation (42+ Versioned Regex Patterns)

The pattern registry checks generated files structurally using regex. Each pattern has a target file type, a regex check, a severity, a weight, and version bounds (`introducedIn`/`removedIn`).

**Key file:** `packages/aicl/src/Rlm/PatternRegistry.php`

**Pattern categories (40 base + up to 18 frontend/view):**
| Category | Count | What It Checks |
|----------|-------|----------------|
| Model | 12 | Namespace, traits, $fillable, casts(), newFactory(), relationships, owner, PHPDoc |
| Migration | 5 | Anonymous class, standard columns, foreign keys, column types, down() |
| Factory | 5 | $model property, definition(), state methods, inactive(), relationship factories |
| Policy | 5 | Extends BasePolicy, permissionPrefix, owner checks on view/update/delete |
| Observer | 3 | Extends BaseObserver, created() logging, status change logging |
| Filament | 6 | Directory structure, delegated form/table, 4 pages, v4 namespaces, name styling, status badges |
| Test | 4 | PHPUnit class, setUp(), test categories, minimum coverage |
| Media | 2 | Media collection registration, thumbnail conversions |
| Component | 10 | C01-C10: component existence, data matching, nesting, props, responsive, dark mode (Sprint O) |
| View | 8 | V01-V08: Blade structure, Alpine lifecycle, composition, tokens, a11y, echo, controller, responsive (Sprint O) |

**Pattern versioning** (Sprint W): `PatternRegistry::VERSION` constant tracks current version. Each `EntityPattern` has `introducedIn`/`removedIn` properties. `getPatternSet($version)` filters patterns for backward-compatible validation. Unpinned entities get latest patterns with a warning banner.

**Inline assertions** (Sprint X): `--target` and `--quick` flags enable mid-generation validation:
```bash
aicl:validate {Name} --target=model --target=factory --quick   # Single-line pass/fail
aicl:validate {Name} --target=filament --quick                  # Check Filament patterns only
```

**Waiver system** (Sprint W): `EntityWaiver` model allows pattern exceptions with budget math. Waiver cost = pattern weight. Budget configurable via `aicl.rlm.waiver_budget`. Expired waivers auto-revert.

**Run validation:**
```bash
aicl:validate {EntityName}                              # Structural only (full)
aicl:validate {EntityName} --semantic                   # Structural + L2 semantic
aicl:validate {EntityName} --diff                       # Show delta from last run
aicl:validate {EntityName} --target=model --quick       # Inline assertion (Sprint X)
aicl:validate {EntityName} --semantic --diff            # All options
```

**Scoring:** Each pattern is weighted. The validator calculates a percentage score. Target: **100%**. Scores are recorded to PostgreSQL via `RlmScore` model for trend tracking. Enriched score card (Sprint W) shows pattern set version, L1 badge with waiver info, file count, and learning summary.

---

### L2 — Semantic Validation (8 Sub-LLM Checks)

Structural regex catches syntax-level issues. Semantic validation uses a small Claude model (Haiku) to catch cross-file consistency problems that regex cannot — like a migration that defines a column the model doesn't include in `$fillable`.

**Key files:**
| File | Purpose |
|------|---------|
| `SemanticCheck.php` | Check definition (name, prompt template, file requirements) |
| `SemanticResult.php` | Structured pass/fail/explanation result |
| `SemanticCheckRegistry.php` | 8 check definitions with applicability filtering |
| `SemanticValidator.php` | Orchestrator: prompt building, `Http::pool()`, response parsing |
| `SemanticCache.php` | Content-hash caching in PostgreSQL via `RlmSemanticCache` model |

**The 8 checks:**
| # | Check | What It Catches |
|---|-------|-----------------|
| 1 | `migration_model_alignment` | Migration columns vs. model $fillable/$casts mismatch |
| 2 | `factory_types` | Factory fake data types don't match migration column types |
| 3 | `authorization_coverage` | Controller actions missing policy authorization |
| 4 | `validation_nullability` | Form request rules vs. migration nullable columns |
| 5 | `resource_exposure` | API resource exposes fields not in $fillable |
| 6 | `test_coverage` | Test file missing coverage for key model features |
| 7 | `widget_queries` | Widget queries reference non-existent columns/relationships |
| 8 | `state_transitions` | State machine transitions vs. observer handling |

**Execution model:**
- Checks run in parallel via `Http::pool()` (one API call per check)
- Results cached by SHA256 hash of input file contents — skip LLM if files unchanged
- Default model: `claude-haiku-4-5-20251001` (configurable via `AICL_SEMANTIC_MODEL`)
- Mode: Advisory (warnings only). Can promote to gate after confidence builds.
- Fallback: API unreachable → semantic validation skipped with warning, structural score stands alone
- Cost: ~$0.01/entity with Haiku

**Configuration:**
```php
// Environment variables
AICL_SEMANTIC_ENABLED=false       // Enable/disable semantic validation
AICL_SEMANTIC_API_KEY=            // Anthropic API key for sub-LLM calls
AICL_SEMANTIC_MODEL=claude-haiku-4-5-20251001  // Model for semantic checks
AICL_SEMANTIC_MODE=advisory       // advisory | gate
```

---

### L3 — Pattern Discovery

Automatically analyzes generation traces to discover new validation patterns and detect stale existing ones.

**Key files:**
| File | Purpose |
|------|---------|
| `PatternCandidate.php` | Candidate definition with `toEntityPattern()` conversion |
| `PatternDiscovery.php` | Trace analysis, stale detection, candidate export (Eloquent-backed) |
| `DiscoverPatternsCommand.php` | `aicl:discover-patterns` artisan command |

**How it works:**
1. After each entity generation (Phase 8), a trace is saved via `aicl:rlm trace-save`
2. `aicl:discover-patterns` analyzes accumulated traces:
   - Identifies common fix patterns across multiple entities
   - Proposes candidate patterns with confidence scores
   - Exports candidates as markdown for human review
3. `aicl:discover-patterns --stale` detects patterns that always pass (100% across all traces) — these may be noise worth removing

**Promotion workflow:** Candidates are never auto-promoted. Human reviews candidates → adds to `PatternRegistry.php` manually.

---

## Hub Architecture

The hub is PostgreSQL-backed with Elasticsearch for search. All data is Eloquent models — no SQLite layer.

### Data Flow

```
Project A                        PostgreSQL + Elasticsearch              Project B
┌──────────────┐                ┌─────────────────────────┐            ┌──────────────┐
│ Eloquent     │───push───────►│ 9 Eloquent Models       │◄───push────│ Eloquent     │
│              │◄──pull────────│ REST API                 │────pull───►│              │
│ Failures     │               │ Elasticsearch (hybrid)   │            │ Failures     │
│ Lessons      │  anonymized   │ Filament Dashboard       │  anonymized│ Lessons      │
│ Traces       │  via SHA256   │ Promotion Pipeline       │  via SHA256│ Traces       │
└──────────────┘               │ Regression Detection     │            └──────────────┘
                               └─────────────────────────┘
```

### 11 Hub Entities

All live in `packages/aicl/src/` with `Aicl\` namespace:

| Entity | Purpose | Key Features |
|--------|---------|-------------|
| `RlmPattern` | Validation rule definitions | Mirrors PatternRegistry in DB, source tracking (registry/discovered/manual), `DeclaresBaseSchema` |
| `RlmFailure` | Known failure modes | 6-state machine, report counting, structured reflection fields (attempt/feedback/fix/preventive_rule), `rule_hash` for deterministic clustering (Sprint W) |
| `FailureReport` | Per-project failure occurrences | Links to RlmFailure, anonymized project hash, resolution method, 4-state machine |
| `RlmLesson` | Solutions and workarounds | Topic categorization, tags, entity context, `lesson_type` (observation/instruction/prevention_rule), `is_verified`, `needs_review` (Sprint W) |
| `GenerationTrace` | Generation audit trails | Structural/semantic scores, duration, phase data, file manifest, `pattern_set_version`, `scaffolder_version`, `signature_hash` (Sprint W/X) |
| `PreventionRule` | Contextual prevention advice | Links to RlmFailure, entity type + phase targeting, severity |
| `RlmScore` | Validation score history | Per-entity structural + semantic scores, trend tracking |
| `GoldenAnnotation` | Golden example annotations | Links golden example files to the knowledge graph, entity context |
| `DistilledLesson` | Agent-targeted synthesized guidance | Clustered from failures, effectiveness-weighted ranking, `surfaced_count`, `base_impact_score`, per agent/phase, When-Then rules |
| `KnowledgeLink` | Cross-entity knowledge connections | Links patterns, failures, lessons, annotations for graph traversal; `link_type` (golden_entity_file/test_case/commit_sha/doc_anchor) with proof strength ranking (Sprint W) |
| `EntityWaiver` | Pattern validation exceptions (Sprint W) | Entity/pattern binding, reason, scope_justification, ticket_url, budget math (cost = pattern weight), auto-expiry |

### Hub Entity Package Layout

Hub entities live directly in `packages/aicl/` with `Aicl\` namespace — they are framework infrastructure, always present, no install command needed.

**Namespace mapping:**

| Layer | Namespace | Notes |
|-------|-----------|-------|
| Models | `Aicl\Models\{Entity}` | Use `App\Models\User` explicitly (not same-namespace resolution) |
| Factories | `Aicl\Database\Factories\{Entity}Factory` | Models require `newFactory()` override |
| Seeders | `Aicl\Database\Seeders\{Entity}Seeder` | |
| Policies | `Aicl\Policies\{Entity}Policy` | Extend `Aicl\Policies\BasePolicy` |
| Observers | `Aicl\Observers\{Entity}Observer` | Extend `Aicl\Observers\BaseObserver` |
| Filament Resources | `Aicl\Filament\Resources\{Plural}\{Entity}Resource` | |
| Controllers | `Aicl\Http\Controllers\Api\{Entity}Controller` | |
| Form Requests | `Aicl\Http\Requests\Store{Entity}Request` | |
| API Resources | `Aicl\Http\Resources\{Entity}Resource` | |
| Enums | `Aicl\Enums\{Name}` | `FailureCategory`, `FailureSeverity`, `ResolutionMethod` |
| States | `Aicl\States\{Entity}\{State}` | RlmFailure: 6 states |
| Tests | `Aicl\Tests\Hub\{Entity}Test` | Part of Package test suite |

**Registration:**

- **AiclPlugin** — 8 Filament resources (6 hub + User + FailedJob), 20+ widgets, 18+ pages (including RlmDashboard). Hub resources grouped under "RLM Hub" navigation group, sorted 10-60.
- **AiclServiceProvider** — 9 policies via `Gate::policy()` (6 hub + User + Role + BasePolicy), 7 observers via `Model::observe()` (6 hub + BaseObserver), 5 route files via `loadRoutesFrom()` (web, api, hub-api, socialite, saml — last two conditional), `HubClient` + `ProjectIdentity` singletons. RLM GC schedule via `registerRlmSchedule()` (Sprint W).
- **InstallCommand** — Seeds: PatternRegistrySeeder, BaseFailureSeeder, RlmLessonSeeder, PreventionRuleSeeder, DistilledLessonSeeder, GoldenAnnotationSeeder, NotificationChannelSeeder, RoleSeeder, SettingsSeeder. `sync-knowledge` re-runs all with diff reporting.

**Key design decisions:**

- **UUID primary keys** — All hub entities use `HasUuids` for cross-project sync. The `owner_id` FK remains integer (references `users.id`).
- **App\Models\User import** — Hub models need explicit `use App\Models\User;` because User is a client model, not a package model.
- **Factory resolution** — Models override `newFactory()` since Laravel's default looks in `Database\Factories\` (root namespace), not `Aicl\Database\Factories\`.
- **Separate route file** — Hub API routes in `hub-api.php` (not appended to general package routes). Route pattern: `apiResource('{plural}', Controller)->parameters(['{plural}' => 'record'])`.
- **Always present** — All AICL projects get hub migrations/tables even if hub not used. No performance impact when empty. Feature flags (`hub_admin`, `hub_search`) control admin visibility and ES indexing.

### Sync Commands

**Push** (`aicl:rlm sync --push`):
1. Reads unsynced data from PostgreSQL (failures, lessons, traces)
2. Anonymizes via `ProjectIdentity` — strips file paths, hashes project identity with `sha256(app.name + app.key)`
3. Sends to hub REST API via `HubClient` (HTTP client with `Http::pool()`, retry logic, token auth)

**Pull** (`aicl:rlm sync --pull`):
1. Fetches updated patterns, base failures, prevention rules from hub API
2. Merges into PostgreSQL:
   - Patterns: upsert, preserve local-only entries
   - Base failures: replace base-tier, preserve project-tier
   - Prevention rules: cached locally

### Promotion Pipeline

When a failure meets promotion criteria, it becomes a candidate for "base failure" — a known issue baked into the default set for all projects.

**Criteria:** `report_count >= 3` AND `project_count >= 2` AND `promoted_to_base = false`

**Flow:**
1. `FailureReportObserver.created()` increments parent `RlmFailure` counters (report_count, project_count, resolution_count, resolution_rate)
2. If criteria met → dispatches `CheckPromotionCandidatesJob`
3. Job sends `FailurePromotionCandidateNotification` to admin
4. Admin reviews and promotes (or dismisses)

### Regression Detection

If a failure marked as fixed (`scaffolding_fixed = true`) reappears in a new `FailureReport`, the system sends a `FailureRegressionNotification` to admin — something broke.

### Hub Admin Dashboard

`RlmDashboard` Filament page with cross-entity analytics:
- **FailureTrendChart** — Line chart of failure reports over last 6 months
- **CategoryBreakdownChart** — Doughnut chart of failures by category
- **PromotionQueueWidget** — Table of failures meeting promotion criteria
- **ProjectHealthWidget** — Per-project average scores from GenerationTrace

Controlled by `config('aicl.features.hub_admin')` feature flag.

### Hub Configuration

Hub visibility is controlled by feature flags in `config/aicl.php`:

```php
// config/aicl.php → features
'features' => [
    'hub_search' => (bool) env('AICL_HUB_SEARCH', false),  // Enable Elasticsearch indexing for hub entities
    'hub_admin' => (bool) env('AICL_HUB_ADMIN', false),     // Enable RLM Hub admin dashboard
],
```

---

## Generation Pipeline Integration

The RLM integrates into the 8-phase entity generation pipeline:

```
Phase 1: PLAN         → /pm         → Parse request, classify, produce spec
Phase 2: DESIGN       → /solutions  → Design blueprint
Phase 3: GENERATE     → /architect  → Scaffold + customize code (aicl:make-entity)
Phase 3.5: STYLE      → /designer   → Review + enhance UI layer (conditional)
Phase 4: VALIDATE     → /rlm + /tester ◄── aicl:validate runs here (target: 100%)
         └── aicl:rlm feedback (submit lesson effectiveness data)
Phase 5: REGISTER     → /architect  → Wire up policy, observer, routes
Phase 6: RE-VALIDATE  → /rlm + /tester ◄── Re-run aicl:validate post-registration
         └── aicl:rlm feedback (submit lesson effectiveness data)
Phase 7: VERIFY       → /tester     → Full test suite
Phase 8: COMPLETE     → /docs       → Document, cleanup, reload + rebuild
         └── Score recorded to PostgreSQL (RlmScore)
         └── Trace saved via aicl:rlm trace-save
         └── If hub enabled, sync --push
         └── AAR closed-loop learning (aicl:rlm aar)
```

### Agent Integration

Agents use RLM data during generation:

| Agent | How They Use RLM |
|-------|-----------------|
| `/architect` | `aicl:rlm recall --agent=architect --phase=3` → cheat sheet with top 5 lessons + When-Then rules |
| `/rlm` | `aicl:validate` for structural + semantic validation |
| `/tester` | `aicl:rlm recall --agent=tester --phase=4` → testing-specific failure guidance |
| `/designer` | `aicl:rlm recall --agent=designer --phase=3` → UI/component lessons |
| `/solutions` | `aicl:rlm recall --agent=solutions --phase=2` → design-level failure patterns |
| `/pm` | `aicl:rlm recall --agent=pm --phase=1` → process-level lessons |
| `/docs` | Saves generation trace in Phase 8 |

### Pre-Compaction Flush

Before context window compression or phase transitions, agents flush findings to the knowledge base via `aicl:rlm learn`. This preserves institutional knowledge that would otherwise be lost during compaction.

---

## Key Files Reference

### Core RLM Engine
| File | Purpose |
|------|---------|
| `packages/aicl/src/Rlm/KnowledgeService.php` | Central query interface (search, recall, learn, failures, scores, stats) |
| `packages/aicl/src/Rlm/EmbeddingService.php` | Vector embedding generation (NeuronAI/Null drivers) |
| `packages/aicl/src/Rlm/PatternRegistry.php` | L1 structural pattern definitions (42+ versioned patterns) |
| `packages/aicl/src/Rlm/EntityValidator.php` | L1 validation runner (version-aware, target-filterable, waiver-integrated) |
| `packages/aicl/src/Rlm/EntityPattern.php` | Pattern definition class (with `target`, `introducedIn`, `removedIn`) |
| `packages/aicl/src/Rlm/ValidationResult.php` | Validation result container (with `$waived` property) |
| `packages/aicl/src/Rlm/RuleNormalizer.php` | Canonical text normalization + SHA-1 hashing (Sprint W) |
| `packages/aicl/src/Rlm/EntitySignature.php` | Typed entity contract value object (Sprint X) |
| `packages/aicl/src/Rlm/KnowledgeWriter.php` | Lesson creation with `LessonType` and completeness enforcement |

### Distillation Engine
| File | Purpose |
|------|---------|
| `packages/aicl/src/Rlm/DistillationService.php` | Failure clustering, impact scoring, lesson generation, When-Then rules, confidence recalculation |
| `packages/aicl/src/Rlm/KpiCalculator.php` | KPI computation (fix iteration trend, failure ratio, lesson effectiveness, verdict) |
| `packages/aicl/src/Models/DistilledLesson.php` | Agent-targeted synthesized guidance model |
| `packages/aicl/src/Jobs/RedistillJob.php` | Queued re-distillation for affected failure clusters |
| `packages/aicl/src/Observers/RlmFailureDistillObserver.php` | Triggers RedistillJob on new failure creation |
| `packages/aicl/database/factories/DistilledLessonFactory.php` | Factory with forAgent(), highImpact(), inactive() states |
| `packages/aicl/database/seeders/DistilledLessonSeeder.php` | Runs distillation on seeded base failures |

### Embedding Drivers
| File | Purpose |
|------|---------|
| `packages/aicl/src/Rlm/Embeddings/NeuronAiEmbeddingAdapter.php` | NeuronAI adapter for OpenAI embeddings |
| `packages/aicl/src/Rlm/Embeddings/NullDriver.php` | No-op driver for testing |
| `packages/aicl/src/Rlm/Embeddings/IndexMappings.php` | ES index mapping definitions |

### Semantic Validation (L2)
| File | Purpose |
|------|---------|
| `packages/aicl/src/Rlm/SemanticCheck.php` | Check definition |
| `packages/aicl/src/Rlm/SemanticResult.php` | Result class |
| `packages/aicl/src/Rlm/SemanticCheckRegistry.php` | 8 check definitions |
| `packages/aicl/src/Rlm/SemanticValidator.php` | Orchestrator (Http::pool, caching) |
| `packages/aicl/src/Rlm/SemanticCache.php` | Content-hash cache (PG-backed via RlmSemanticCache model) |

### Pattern Discovery (L3)
| File | Purpose |
|------|---------|
| `packages/aicl/src/Rlm/PatternCandidate.php` | Candidate definition |
| `packages/aicl/src/Rlm/PatternDiscovery.php` | Trace analysis engine (Eloquent-backed) |
| `packages/aicl/src/Console/Commands/DiscoverPatternsCommand.php` | `aicl:discover-patterns` |

### Hub Infrastructure
| File | Purpose |
|------|---------|
| `packages/aicl/src/Rlm/HubClient.php` | HTTP client for hub API |
| `packages/aicl/src/Rlm/ProjectIdentity.php` | Privacy/anonymization service |
| `packages/aicl/routes/hub-api.php` | Hub REST API routes |
| `packages/aicl/src/Filament/Pages/RlmDashboard.php` | Admin analytics page |

### Artisan Commands
| File | Purpose |
|------|---------|
| `packages/aicl/src/Console/Commands/RlmCommand.php` | `aicl:rlm` (20 actions: search, recall, learn, failures, scores, stats, export, trace-save, sync, sync-knowledge, embed, index, aar, cleanup, distill, feedback, health, waiver, optimize) |
| `packages/aicl/src/Console/Commands/ValidateEntityCommand.php` | `aicl:validate` |
| `packages/aicl/src/Console/Commands/PipelineContextCommand.php` | `aicl:pipeline-context` |
| `packages/aicl/src/Console/Commands/DiscoverPatternsCommand.php` | `aicl:discover-patterns` |

### Mutation Suite (Sprint W)
| File | Purpose |
|------|---------|
| `packages/aicl/src/Rlm/Mutators/Mutator.php` | Interface: `name()`, `target()`, `expectedFailures()`, `mutate()` |
| `packages/aicl/src/Rlm/Mutators/MutatorWrongNamespace.php` | Mutates `App\Models` to wrong namespace |
| `packages/aicl/src/Rlm/Mutators/MutatorMissingTrait.php` | Removes `use HasFactory` |
| `packages/aicl/src/Rlm/Mutators/MutatorPolicyGap.php` | Removes policy `view()` method |
| `packages/aicl/src/Rlm/Mutators/MutatorFactoryTypeMismatch.php` | Removes factory `$model` property |
| `packages/aicl/src/Rlm/Mutators/MutatorMissingSearchableColumns.php` | Removes `$fillable` block |
| `packages/aicl/src/Rlm/Mutators/MutatorConflictingFix.php` | Replaces extends with wrong base class |

### Enums (Sprint W)
| File | Purpose |
|------|---------|
| `packages/aicl/src/Enums/LessonType.php` | `observation`/`instruction`/`prevention_rule` |
| `packages/aicl/src/Enums/KnowledgeLinkType.php` | `golden_entity_file`/`test_case`/`commit_sha`/`doc_anchor` |

### Hub Entity Models (11)
| File | Purpose |
|------|---------|
| `packages/aicl/src/Models/RlmPattern.php` | Pattern definitions |
| `packages/aicl/src/Models/RlmFailure.php` | Failure tracking (6-state, structured reflection) |
| `packages/aicl/src/Models/FailureReport.php` | Per-project occurrences |
| `packages/aicl/src/Models/RlmLesson.php` | Lessons learned (typed: observation/instruction/prevention_rule) |
| `packages/aicl/src/Models/GenerationTrace.php` | Generation audit trail (versioned, signature-hashed) |
| `packages/aicl/src/Models/PreventionRule.php` | Prevention rules |
| `packages/aicl/src/Models/RlmScore.php` | Validation score history |
| `packages/aicl/src/Models/GoldenAnnotation.php` | Golden example annotations |
| `packages/aicl/src/Models/DistilledLesson.php` | Agent-targeted distilled guidance (effectiveness-ranked) |
| `packages/aicl/src/Models/KnowledgeLink.php` | Cross-entity knowledge graph links (typed, proof-ranked) |
| `packages/aicl/src/Models/EntityWaiver.php` | Pattern validation exceptions with budget math |

---

## Automated Maintenance (Sprint W)

The RLM system includes automated garbage collection and health monitoring, registered in `AiclServiceProvider::registerRlmSchedule()`:

| Schedule | Command | Safety |
|----------|---------|--------|
| Sunday 02:00 | `discover-patterns --stale` | `onOneServer`, `withoutOverlapping` |
| Sunday 02:30 | `rlm cleanup --remove-faker-records` | `onOneServer`, `withoutOverlapping` |
| Sunday 03:00 | `ProofLinkIntegrityJob` dispatch | `onOneServer`, `withoutOverlapping` |
| Daily 06:00 | `rlm stats` | `onOneServer` |
| Monthly 1st 03:00 | Log rotation (GC >90d, health >365d) | `onOneServer` |

**`RlmMaintenanceComplete` event** — Dispatched after GC cycle with task summaries and duration.

---

## Related Documents

- [AI Generation Pipeline](ai-generation-pipeline.md) — L1 pattern details and scaffolding rules
- [Entity System](entity-system.md) — Traits, contracts, events
- [Testing & Quality](testing-quality.md) — Test strategy and conventions
- [Foundation](foundation.md) — Core AICL principles
