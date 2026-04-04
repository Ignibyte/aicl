# Repository Structure & Code Flow

**Version:** 1.0
**Last Updated:** 2026-02-09
**Owner:** Human-maintained

---

## Three-Repo Model

AICL uses three GitHub repositories under the **Ignibyte** organization. All development happens in the dev monorepo; the other two repos are **derived outputs** that are never edited directly.

| Repo | GitHub URL | Type | Purpose |
|------|-----------|------|---------|
| **aicl_dev** | `git@github.com:Ignibyte/aicl_dev.git` | Monorepo | Development — all work happens here |
| **aicl** | `git@github.com:Ignibyte/aicl.git` | Library | Composer package (`aicl/aicl`) — what clients install |
| **project** | `git@github.com:Ignibyte/project.git` | Project | Skeleton — template for `composer create-project` |

### Golden Rule

> **All development happens in `aicl_dev`.** The `aicl` and `project` repos are build artifacts derived from `aicl_dev`. They are never edited directly, only synced during releases.

---

## Code Flow

```
aicl_dev (monorepo)
  │
  ├─── packages/aicl/ ──── rsync ────→ Ignibyte/aicl (Composer package)
  │                                       └── Tagged releases (v1.x.x)
  │                                       └── Clients: composer require aicl/aicl
  │
  └─── app/, config/, .claude/, ─── build-skeleton.sh ────→ Ignibyte/project (Skeleton)
       dist/, tests/, etc.                                    └── Tagged releases (v1.x.x)
                                                              └── Clients: composer create-project
```

**Direction:** `aicl_dev` → `aicl` + `project`. Never backward.

---

## What Lives Where

### Dev Monorepo (`aicl_dev`)

Everything. This is the single source of truth.

```
aicl_dev/
├── packages/aicl/          # Package source (Aicl\ namespace)
│   ├── src/                # Traits, contracts, events, commands, Filament, API
│   ├── resources/          # Views, components, translations
│   ├── database/           # Package migrations, factories, seeders
│   ├── config/             # Published config (aicl.php)
│   ├── routes/             # Package routes (api.php)
│   └── composer.json       # Library type, aicl/aicl
│
├── app/                    # App-layer (skeleton infrastructure + golden entity)
├── config/                 # App config (backup.php, filesystems.php, etc.)
├── database/migrations/    # App-layer migrations
├── routes/                 # App routes (api.php, channels.php, console.php)
├── tests/                  # All tests (Unit, Feature, Browser, Package)
├── resources/              # Frontend (CSS, JS, views)
│
├── .claude/
│   ├── architecture/       # Architecture docs (framework-only, excluded from skeleton)
│   ├── commands/           # Agent prompts (framework + pipeline variants)
│   └── planning/
│       ├── framework/      # Framework dev planning (.internal marker)
│       ├── pipeline/       # Entity generation pipeline (ships with product)
│       └── rlm/            # RLM patterns and validation (ships with product)
│
├── dist/                   # Skeleton build system
│   ├── build-skeleton.sh   # Assembly script
│   ├── composer.json       # Skeleton composer (references Ignibyte/aicl VCS)
│   ├── CLAUDE.md           # Skeleton AI guidelines
│   ├── README.md           # Skeleton README
│   ├── .env.example        # Skeleton env defaults
│   ├── .gitignore          # Skeleton gitignore
│   └── .gitattributes      # Export-ignore rules
│
└── CHANGELOG_FRAMEWORK.md  # Version history
```

### Framework Package Repo (`aicl`)

An exact copy of `packages/aicl/` from the dev monorepo. Synced via rsync during release.

```
aicl/
├── src/                    # Package PHP source
├── resources/              # Package views and components
├── database/               # Package migrations, factories, seeders
├── config/                 # Published config
├── routes/                 # Package routes
└── composer.json           # name: aicl/aicl, type: library
```

Clients install this via:
```json
{
    "repositories": [{"type": "vcs", "url": "git@github.com:Ignibyte/aicl.git"}],
    "require": {"aicl/aicl": "^1.0"}
}
```

### Skeleton Project Repo (`project`)

A clean, distributable copy of the dev monorepo with all framework-only files removed. Built by `dist/build-skeleton.sh`.

Key differences from dev:
- `composer.json` requires `aicl/aicl:^1.0` from VCS (not path repo)
- No `packages/` directory
- No `.claude/architecture/` or `.claude/planning/framework/`
- Pipeline-variant agent prompts (not framework variants)
- No golden entity (Project model, migrations, etc. removed)
- Registration files cleaned of entity references
- DDEV config uses auto-derived project name
- First-run bootstrap hooks for `ddev start`

Clients create new projects via:
```bash
composer create-project aicl/project my-app \
  --repository='{"type":"vcs","url":"git@github.com:Ignibyte/project.git"}' \
  --repository='{"type":"vcs","url":"git@github.com:Ignibyte/aicl.git"}'
```

---

## Versioning

All three repos use the **same version number** for a given release. SemVer (`MAJOR.MINOR.PATCH`):

- **MAJOR** — Breaking changes to package contracts, traits, base classes, or public API
- **MINOR** — New features, commands, components, or non-breaking additions
- **PATCH** — Bug fixes, test improvements, documentation updates

The human decides the version based on the scope of changes.

---

## Release Workflow Summary

### Quick Reference

```
1. Run pre-flight checks            # Tests, Pint, quality gates
2. Analyze changes                  # What changed since last release?
3. Decide version number            # v1.x.x (human decision)
4. Commit dev repo                  # If uncommitted changes exist
5. Build package                    # rsync packages/aicl/ → temp clone
6. Build skeleton                   # dist/build-skeleton.sh → temp clone
7. Tag both                         # git tag v{VERSION}
8. Push all three repos             # Manual push
9. Verify                           # Post-release summary
```

### Detailed Documentation

| Document | Location | Purpose |
|----------|----------|---------|
| Skeleton build script | `dist/build-skeleton.sh` | Automated skeleton assembly |
| Changelog | `CHANGELOG_FRAMEWORK.md` | Version history |

---

## When to Release

| Changed Files | What to Release |
|---------------|----------------|
| `packages/aicl/` only | Package release only (clients get it via `composer update`) |
| App-layer only (config, agents, DDEV, tests) | Skeleton release only (only affects new projects) |
| Both | Release both (common case) |
| Planning/docs only (no code changes) | Dev commit only, no release needed |

---

## Pipeline Commands & Standalone Agents

The dev repo uses Forge-synced pipeline commands (`.claude/commands/pipeline/`) and standalone agents (`.claude/agents/`). See the project root `CLAUDE.md` for the complete list.

**Framework** (dev monorepo) uses packages at `packages/aicl/`.
**Skeleton** (client projects) uses packages at `vendor/aicl/aicl/`.

---

## Dependency Chain

```
Ignibyte/project (skeleton)
  └── requires: aicl/aicl:^1.0 (from Ignibyte/aicl VCS repo)
        └── requires: filament/filament:^4.0, spatie/laravel-permission, etc.
              └── requires: laravel/framework:^11.0|^12.0|^13.0
```

When a client runs `composer update aicl/aicl`, they get the latest tagged release from `Ignibyte/aicl` that satisfies `^1.0`. The skeleton itself is only used at project creation time — after that, the project is independent.
