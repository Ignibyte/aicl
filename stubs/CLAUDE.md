# AICL — AI-First Laravel Application Framework

## Package Boundary — READ THIS FIRST

The `aicl/aicl` package is installed in `vendor/aicl/aicl/` and is **READ-ONLY**.

**NEVER** modify files under `vendor/`. Extend, override, or configure — never patch.

All your code goes in `app/`, `database/`, `resources/`, `routes/`, and `tests/`.

## Mission

AICL is an AI-first Laravel application framework. The "developer" is Claude Code — humans define entities and business rules; AI generates the full stack consistently, every time. The package provides guardrails, base components, and RLM validation so AI output stays within guidelines.

## MCP-First Workflow

Forge connects to its own MCP server for golden examples, architecture decisions, and pattern context. Agent commands use these MCP tools instead of local files:

- **`bootstrap`** — Project context + architecture decisions (world model rules). Call at session start.
- **`search-patterns`** — Golden example code by component type (e.g., `component_type=model`).
- **`pipeline-context`** — Phase-matched golden examples when working on a pipeline ticket.
- **`search-docs`** — Laravel ecosystem docs (from `laravel-boost` server, separate).

Golden examples and world model rules live in the Forge database, not local files.

## Available Agents

### Shipped with Package
- `/forge-connect` — Register project with Forge and configure MCP connection
- `/init-help` — Bootstrap project with Forge (CLAUDE.md, slash commands, MCP config)

### Delivered via Forge MCP
All other agents (entity generation, pipeline sub-agents, quality scans, maintenance) are delivered dynamically by the Forge MCP server after running `/forge-connect`. Run `/init-help` to get started.

## Entity Generation

**Quick (recommended):**
```
/generate {EntityName}
```

**Step-by-step with human review:**
```
/pm new {EntityName}
```

**Scaffold only (no pipeline):**
```bash
ddev exec php artisan aicl:make-entity {EntityName}
```

**With smart scaffolding:**
```bash
ddev exec php artisan aicl:make-entity {EntityName} \
  --fields="name:string,description:text:nullable,status:enum:EntityStatus" \
  --states="draft,active,completed" \
  --all
```

**Validate:**
```bash
ddev exec php artisan aicl:validate {EntityName}
```

## Work Pipeline (Non-Entity)

For features, integrations, infrastructure, and refactors that aren't full entities:

**Step-by-step with human review (6 phases):**
```
/pm work {description}
```

This creates a `WORK-{Title}.md` pipeline document with phases: Plan, Design, Implement, Validate, Verify, Complete. Same agents, same workspace, same conventions as the entity pipeline — but without entity-specific phases (Register, Re-Validate, RLM scoring).

## Development Environment

- **DDEV** with PHP 8.3, PostgreSQL 17, Redis 7, Swoole 6.0.0
- **Site URL:** `https://{project}.ddev.site`
- **Admin Panel:** `https://{project}.ddev.site/admin`
- **Login:** `admin@aicl.test` / `password`
- **Reload workers:** `ddev octane-reload`
- **Rebuild frontend:** `ddev npm run build`

## Core Principles

1. **AI-Readability First** — Every pattern must be explicit, documented, and verifiable
2. **Extension, Not Modification** — Extend via Observers, Events, Resource subclassing. Never modify package code.
3. **Standard Idiomatic Laravel** — Generated code looks like hand-written Laravel
4. **Dashboard for Days** — Rich, dashboard-grade interfaces from known UI components

## Architecture

- **UI:** Filament v4 — declarative PHP admin panel
- **Auth:** Filament auth + Passport (OAuth2) + MFA + Social login
- **RBAC:** `spatie/laravel-permission` + `filament-shield`
- **Entity System:** Base traits + interfaces + event contracts
- **Database:** PostgreSQL (default), MySQL/MariaDB supported
- **Cache/Queue/Sessions:** Redis
- **Testing:** PHPUnit + Laravel Dusk + Pint + PHPStan

## Standards

- PHPUnit tests (not Pest) — `php artisan make:test --phpunit {name}`
- Form Request classes for all validation
- Eloquent over raw queries — `Model::query()`, never `DB::`
- `config()` over `env()` — no `.env` file, all config via `config/local.php`
- Explicit return types and type hints
- Run `vendor/bin/pint --dirty --format agent` before finalizing
