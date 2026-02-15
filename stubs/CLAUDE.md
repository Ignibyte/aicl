# AICL — AI-First Laravel Application Framework

## Package Boundary — READ THIS FIRST

The `aicl/aicl` package is installed in `vendor/aicl/aicl/` and is **READ-ONLY**.

**NEVER** modify files under `vendor/`. Extend, override, or configure — never patch.

All your code goes in `app/`, `database/`, `resources/`, `routes/`, and `tests/`.

## Mission

AICL is an AI-first Laravel application framework. The "developer" is Claude Code — humans define entities and business rules; AI generates the full stack consistently, every time. The package provides guardrails, golden examples, base components, and RLM validation so AI output stays within guidelines.

## Available Agents

- `/generate` — Generate entities via the 8-phase pipeline (primary workflow — fast, single-agent)
- `/pm` — Step-by-step pipeline orchestration with human review at each phase gate
- `/rlm` — Validate entity quality against 42 patterns
- `/scan-all` — Run PHPStan + Semgrep + Snyk scans
- `/scan-phpstan` — PHPStan static analysis
- `/scan-semgrep` — Semgrep SAST security scan
- `/scan-snyk` — Snyk dependency vulnerability scan
- `/test-dusk` — Laravel Dusk browser tests

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
- `config()` over `env()` — env vars only in config files
- Explicit return types and type hints
- Run `vendor/bin/pint --dirty --format agent` before finalizing
