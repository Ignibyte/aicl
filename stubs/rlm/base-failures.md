# AICL Base Failures — Universal Institutional Memory

**Owner:** AICL Framework Team
**Last Updated:** 2026-02-09
**Version:** 1.0.0

> **DO NOT MODIFY THIS FILE IN CLIENT PROJECTS.**
> This file ships with AICL and contains universal failures that apply to all projects.
> Log project-specific failures in `failures.md` instead.
> If you discover a failure that should be universal, tag it `[CANDIDATE: base-failure]` in `failures.md`.

---

## Purpose

This file contains curated, universal failures discovered during AICL framework development and entity generation. Every entry applies to ALL AICL projects — not just one specific entity or domain. Agents read this file FIRST before any pipeline work.

## Reading Order

1. **Read this file first** — universal failures that apply to all AICL projects
2. **Then read `failures.md`** — project-specific failures discovered in this project

## ID Scheme

- **BF-NNN** — Base Failures (this file). Universal, shipped with AICL.
- **F-NNN** — Project Failures (`failures.md`). Local to each project, starts at F-001.

## Curation Criteria

Every entry must pass ALL of these:

| # | Criterion |
|---|-----------|
| 1 | **Universal** — affects any AICL project, not specific to an entity or domain |
| 2 | **Reproducible** — will happen again if not prevented |
| 3 | **Actionable** — has a clear preventive rule |
| 4 | **Non-obvious** — AI agents wouldn't naturally avoid it |

---

## Base Failure Log

### Scaffolding Gaps

Failures where `aicl:make-entity` or generated code needs specific handling.

| # | Category | Failure | Preventive Rule | Scaffolding Fixed? |
|---|----------|---------|-----------------|-------------------|
| BF-001 | Scaffolding | `HasStandardScopes::searchableColumns()` defaults to `['name', 'title']`. Models without a `title` column get `QueryException: Unknown column` on search scope. | When using `HasStandardScopes`, ALWAYS override `searchableColumns()` to list only columns that exist on the entity's table. | Yes — `MakeEntityCommand` now generates `searchableColumns()` override. Could regress. |
| BF-003 | Scaffolding | List page missing `CreateAction` in header; Table missing `ExportAction` in headerActions, `ViewAction`/`EditAction` in recordActions, and `ExportBulkAction`/`DeleteBulkAction` in toolbarActions. Entity passed RLM validation and all tests but failed user acceptance testing. | Scaffolding MUST include: (1) `CreateAction` on List page, (2) `ExportAction` in table headerActions, (3) `ViewAction` + `EditAction` in recordActions, (4) `ExportBulkAction` + `DeleteBulkAction` in toolbarActions BulkActionGroup. | Yes — `MakeEntityCommand` now generates all action sections. Could regress. |

### Pipeline / Process Gaps

Failures in the agent workflow — steps skipped, wrong order, missing enforcement.

| # | Category | Failure | Preventive Rule | Scaffolding Fixed? |
|---|----------|---------|-----------------|-------------------|
| BF-002 | Process | Agent wired up entity (policy binding, observer, API routes, Filament resource discovery) WITHOUT running validation or tests first. Bypassed the validate→register pipeline order. | Entity registration (policy binding, observer binding, API routes, Filament resource discovery) MUST happen in a separate phase AFTER validation passes. The pipeline enforces this via phase gate checks. | N/A — process gap. Fixed by `/generate` Orchestrator with Guardrail 4 (pipeline order enforcement). |
| BF-004 | Process | After context continuation (token limit truncation), agent abandoned structured pipeline — skipped phase gates, did not update pipeline documents, did not follow phase-gate discipline. Broad human directive compounded the issue. | After ANY context continuation, agents MUST re-read all active pipeline documents before resuming work. Pipeline document is the source of truth, not conversational memory. Multi-entity projects process ONE entity at a time with human checkpoints. Broad directives are NOT permission to skip phase gates. | N/A — process gap. Fixed by Context Continuity Protocol in all agent commands. |

### Filament v4 / Laravel Gotchas

Platform-specific pitfalls that affect all AICL projects.

| # | Category | Failure | Preventive Rule | Scaffolding Fixed? |
|---|----------|---------|-----------------|-------------------|
| BF-005 | Filament v4 | `HasStandardScopes::searchableColumns()` returns `['name', 'title']` by default. Models without `title` column get 500 errors on search scope. | Always override `searchableColumns()` to list only columns that exist on the entity's table. | Yes — scaffolding generates override. Duplicate of BF-001 root cause, listed separately for Filament context. |
| BF-006 | Filament v4 | Filament v4 form field HTML `id` uses `form.` prefix (e.g., `id="form.email"`), NOT `data.`. `wire:model` uses `data.email` but the HTML id is `form.email`. | In Dusk tests, use `form.{field}` for `#id` selectors and `data.{field}` for wire:model selectors. | N/A — Filament behavior, not scaffolding. |
| BF-007 | Auth/RBAC | Spatie Permission + API guard: `actingAs($user, 'api')` changes the default guard to `api`. Permissions seeded only on `web` guard won't be found. | Seed permissions AND roles on BOTH `web` and `api` guards. Assign roles on both guards for API tests. | N/A — Spatie behavior. Tests must handle explicitly. |
| BF-008 | Events | Entity events with `ShouldBroadcast`: `EntityDeleted` must NOT use `SerializesModels` trait because the model may already be deleted when the event is processed. | Never use `SerializesModels` in delete event classes. | N/A — Laravel behavior. Entity events generated without `SerializesModels` on delete. |
| BF-009 | Testing | Livewire lifecycle hooks (`updatedQuery`, `updatedEntityType`) cannot be called directly via `->call()` in Livewire tests. | Set the property via `->set()` which triggers the hook automatically. | N/A — Livewire behavior. |
| BF-010 | Testing | Routes registered in test `setUp()` are in the route collection but NOT in the named route lookup. | Call `app('router')->getRoutes()->refreshNameLookups()` after registering routes in tests. | N/A — Laravel behavior. |
| BF-011 | Filament v4 | `DoughnutChartWidget` class is deprecated in Filament v4. | Use `ChartWidget` with `getType()` returning `'doughnut'`. | Yes — scaffolding uses `ChartWidget`. |
| BF-012 | Filament v4 | `Section` is in `Filament\Schemas\Components\Section`, NOT `Filament\Forms\Components\Section`. Same for `Grid`. | Form *components* (TextInput, Select, Toggle) are in `Filament\Forms\Components`. Layout *components* (Section, Grid) are in `Filament\Schemas\Components`. | N/A — Filament v4 namespace convention. |
| BF-013 | Tailwind v4 | Tailwind v4 does not support dynamic class interpolation (`bg-{{ $color }}-500`). Classes must be complete strings at compile time. | Use explicit `match` expressions that return complete class strings. Never interpolate Tailwind class fragments. | N/A — Tailwind v4 behavior. |
| BF-014 | Testing | `phpunit.xml` needs `BROADCAST_CONNECTION=log` to prevent broadcast driver failures during tests. Without it, tests that trigger broadcastable events will fail. | Always set `BROADCAST_CONNECTION=log` in test environment config (`phpunit.xml` or `.env.testing`). | Yes — AICL `phpunit.xml` ships with this set. |
| BF-015 | Migrations | Framework development consolidated multiple migrations into single files (e.g., merging `add_event_column_to_activity_log_table` into the base `create_activity_log_table`). This works for fresh installs but would break existing databases that already ran the original split migrations. | NEVER modify a published migration after it has been tagged in a release. Always create a new migration for schema changes. Consolidation is only safe before first release or for fresh skeleton builds. | N/A — Architecture rule. Codified in `patterns/migration.md`. |

---

## Statistics

| Metric | Value |
|--------|-------|
| Total base failures | 15 |
| Scaffolding gaps (fixed) | 4 (BF-001, BF-003, BF-011, BF-014) |
| Process gaps (fixed) | 2 (BF-002, BF-004) |
| Platform gotchas (permanent) | 8 (BF-005–BF-010, BF-012, BF-013) |
| Architecture rules | 1 (BF-015) |

---

## Cross-References

- `failures.md` — Project-specific failures (this project writes here)
- `world-model.md` — Pattern definitions (the rules that were violated)
- `golden-entity-guide.md` — Human-readable patterns (what agents should follow)
- `scores.md` — Entity quality scores
