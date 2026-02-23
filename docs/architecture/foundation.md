# AICL Foundation — Why We Are AICL

**Version:** 1.2
**Last Updated:** 2026-02-15
**Owner:** `/docs`

---

## What Is AICL?

AICL (Absolute Insanity Claude Laravel) is an **AI-first Laravel application framework** delivered as a Composer package (`aicl/aicl`). It is not a CMS, not a SaaS product, and not a starter kit. It is a **framework for building dashboard-driven backend applications** where the primary developer is Claude Code.

Humans define entities and business rules. AI generates the full stack — models, migrations, factories, policies, observers, Filament resources, API controllers, tests, and dashboard widgets — consistently, every time.

---

## The Problem AICL Solves

Traditional Laravel development requires a developer to make hundreds of micro-decisions per entity: which traits to use, how to structure Filament resources, how to wire up policies, how to handle audit trails, how to compose dashboard UIs. These decisions are repetitive, error-prone, and hard to keep consistent across a team.

AICL eliminates this by encoding every decision into:
- **Traits and contracts** that enforce patterns at the code level
- **A golden entity** (Project) that demonstrates every pattern once
- **An RLM validation system** (40 base patterns) that scores generated code for correctness
- **A component library** (21 components) that constrains UI composition to proven patterns

The result: AI generates production-quality code that looks hand-written, passes static analysis, and works with all standard Laravel tooling.

---

## Product Model

```
┌──────────────────────────────────────────────────────────────┐
│                    CLIENT PROJECT                              │
│                                                                │
│   composer require aicl/aicl                                  │
│   php artisan aicl:install                                    │
│                                                                │
│   ┌────────────────────────────────────────────────────────┐  │
│   │              AICL BASE (what you get)                    │  │
│   │                                                          │  │
│   │  Auth + RBAC + MFA + Social Login + OAuth2 API          │  │
│   │  Filament v4 Admin Panel + Custom Theme                 │  │
│   │  Entity System (traits, contracts, events, observers)   │  │
│   │  Component Library (21 dashboard components)            │  │
│   │  System Utilities (queue, logs, settings, notifications)│  │
│   │  Search + WebSockets + Export/PDF                       │  │
│   │  RLM Validation Pipeline                                │  │
│   └────────────────────────────────────────────────────────┘  │
│                          │                                     │
│                          ▼                                     │
│   ┌────────────────────────────────────────────────────────┐  │
│   │          AI-GENERATED DOMAIN LAYER                       │  │
│   │                                                          │  │
│   │  php artisan aicl:make-entity Task                      │  │
│   │  php artisan aicl:make-entity Invoice                   │  │
│   │  php artisan aicl:make-entity Employee                  │  │
│   │                                                          │  │
│   │  → Models, migrations, factories, seeders               │  │
│   │  → Policies, observers                                  │  │
│   │  → Filament resources (forms, tables, pages)            │  │
│   │  → API controllers + resources                          │  │
│   │  → Dashboard widgets                                    │  │
│   │  → Full test suites                                     │  │
│   └────────────────────────────────────────────────────────┘  │
└──────────────────────────────────────────────────────────────┘
```

Each client project installs AICL into a fresh Laravel project. The package provides the foundation. Then AI generates domain-specific entities on top.

---

## Core Principles

### 1. AI-Readability First

Every pattern must be explicit, documented, and verifiable. If the AI can't generate it consistently, the pattern is redesigned. This means:

- No implicit conventions — everything is in traits, contracts, or config
- Golden examples for every pattern (the Project entity)
- Machine-verifiable validation (40 RLM base patterns, target: 100%)
- Explicit decision rules for when to use optional features

### 2. Extension, Not Modification

Inspired by Drupal's "alter, don't replace" philosophy. Client code extends package behavior through standard Laravel mechanisms:

| Extension Point | Drupal Equivalent | How It Works |
|-----------------|-------------------|--------------|
| Model Observers | `hook_entity_presave` | Intercept model lifecycle without changing model code |
| Laravel Events | Event subscribers | Typed events for every entity lifecycle point |
| Resource Subclassing | `hook_form_alter` | Override Filament resource methods in client code |
| Service Container | Plugin swapping | Rebind interfaces to swap implementations |
| Config Publishing | Settings API | Override package defaults via published config |

Package code is never modified. Client code extends.

### 3. Standard Idiomatic Laravel

Generated code looks hand-written. No runtime abstractions, no EAV tables, no magic registries. Every generated file is a concrete Laravel class:

- Models are standard Eloquent models with traits
- Controllers are standard Laravel controllers
- Migrations are standard anonymous class migrations
- Tests are standard PHPUnit test cases

IDE autocomplete works. PHPStan works. Pint works. `php artisan tinker` works. There is no AICL "runtime" — just Laravel with well-chosen traits.

### 4. Dashboard for Days

The target is dashboard-driven backend applications, not marketing sites. The AI composes from a known set of UI components (stats, charts, tables, feeds) to produce rich, data-dense interfaces:

- **StatsRow** + **StatCard** for KPIs at the top of every dashboard
- **CardGrid** for organizing widgets and info cards
- **SplitLayout** for detail views with metadata sidebars
- **ActivityFeed** for real-time audit trails
- **StatusBadge** + **Timeline** for lifecycle visualization

---

## The Technology Stack

AICL is built on a curated, opinionated set of Laravel ecosystem packages. Every package was chosen for a specific role and verified for compatibility.

### Core Framework

| Component | Technology | Role in AICL |
|-----------|-----------|--------------|
| **Application** | Laravel 12 | Foundation — routing, Eloquent, events, queues, etc. |
| **Performance** | Laravel Octane + Swoole 6 | Long-running workers, high-throughput serving |
| **Admin Panel** | Filament v4.7 | Declarative PHP admin UI — resources, pages, widgets |
| **Frontend** | Tailwind CSS v4 + Livewire | Utility-first CSS, reactive server-rendered components |

### Auth & Security

| Component | Technology | Role in AICL |
|-----------|-----------|--------------|
| **Session Auth** | Filament built-in | Login, register, password reset, email verification |
| **API Auth** | Laravel Passport v13 | OAuth2 — client credentials, auth code, personal tokens |
| **MFA** | Filament Breezy v3 | TOTP two-factor authentication |
| **Social Login** | Laravel Socialite | Google, GitHub, Facebook, etc. |
| **RBAC** | Spatie Permission + Shield | Roles, permissions, per-resource access control |

### Entity System

| Component | Technology | Role in AICL |
|-----------|-----------|--------------|
| **Audit Trail** | Spatie Activity Log | Automatic change logging for all entities |
| **State Machine** | Spatie Model States | Lifecycle management (Draft → Active → Completed) |
| **Media** | Spatie Media Library | File upload management with collections |
| **Tags** | Spatie Tags | Polymorphic tagging/categorization |
| **Search** | Laravel Scout (database) | Full-text search indexing |

### Infrastructure

| Component | Technology | Role in AICL |
|-----------|-----------|--------------|
| **Cache** | Redis (database 0) | Application cache, rate limiting |
| **Sessions** | Redis (database 1) | Session storage for Octane compatibility |
| **Queues** | Redis (database 2) | Job queue backend |
| **WebSockets** | Laravel Reverb | Real-time dashboard updates, broadcast events |
| **PDF** | DomPDF | Server-side PDF generation from Blade templates |
| **CSV/XLSX** | Filament Native Export (OpenSpout) | Queue-based, chunked CSV/XLSX export |

### Development & Quality

| Component | Technology | Role in AICL |
|-----------|-----------|--------------|
| **Environment** | DDEV + PHP 8.3 + PostgreSQL 17 | Containerized local dev |
| **Testing** | PHPUnit + Dusk | Feature tests + browser tests |
| **Code Style** | Laravel Pint | PSR-12 + Laravel conventions |
| **Static Analysis** | PHPStan | Type checking |
| **Deployment** | Laravel Envoy | Remote task runner |

---

## What Makes AICL Different

### vs. a Starter Kit (Jetstream, Breeze)

Starter kits give you scaffolding once. AICL gives you a living package that generates entities on demand. The entity system, component library, and RLM validation are not scaffolding — they're runtime traits and tooling that every generated entity uses.

### vs. a CMS (WordPress, Drupal, Statamic)

AICL is not a CMS. There is no content type UI, no plugin marketplace, no runtime entity registry. Entities are code — concrete PHP classes generated by AI. The extension model borrows Drupal's philosophy but uses Laravel's native mechanisms.

### vs. an Admin Panel (Nova, Filament alone)

Filament is one layer of AICL, not the whole thing. AICL adds entity traits, contracts, events, observers, state machines, audit trails, search, notifications, export, PDF, WebSockets, and an AI generation pipeline on top of Filament. The component library provides dashboard-specific UI beyond what Filament offers.

### vs. Hand-Written Laravel

AICL IS hand-written Laravel — the generated code is standard Laravel. The difference is consistency. Hand-written code drifts over time. AICL's traits, contracts, and RLM validation ensure every entity follows the same patterns, forever.

---

## Architecture Documents

Each framework component has its own architecture document:

| Document | What It Covers |
|----------|---------------|
| [Entity System](entity-system.md) | Traits, contracts, events, observers, state machines, policies |
| [Auth & RBAC](auth-rbac.md) | Session auth, OAuth2, MFA, social login, roles, permissions |
| [Filament UI](filament-ui.md) | Resources, forms, tables, pages, widgets, plugin, v4 patterns |
| [Component Library](component-library.md) | 21 dashboard components, hierarchy, AI decision rules |
| [Queue, Jobs & Scheduler](queue-jobs-scheduler.md) | Queue backend, failed jobs, scheduler, workers |
| [Cache, Sessions & Redis](cache-sessions-redis.md) | Redis databases, cache strategy, session management |
| [Notifications](notifications.md) | Dispatcher, channels, logging, broadcast bell |
| [Search & Real-time](search-realtime.md) | Scout search, Reverb WebSockets, broadcast events |
| [Export & PDF](export-pdf.md) | CSV export actions, PDF generator, DomPDF templates |
| [AI Generation Pipeline](ai-generation-pipeline.md) | Scaffolding, RLM validation, 40 patterns, quality pipeline |
| [Testing & Quality](testing-quality.md) | PHPUnit, Dusk, Pint, PHPStan, test strategy |

---

## Quick Reference

| Item | Value |
|------|-------|
| **Package** | `aicl/aicl` |
| **Namespace** | `Aicl\` |
| **Component prefix** | `<x-aicl-*>` |
| **Filament plugin** | `AiclPlugin` |
| **Golden entity** | Project (demonstrates every pattern) |
| **Install command** | `php artisan aicl:install` |
| **Scaffold command** | `php artisan aicl:make-entity {Name}` |
| **Validate command** | `php artisan aicl:validate {Name}` |
| **Test count** | 4,404+ PHPUnit + 16 Dusk |
| **RLM patterns** | 40 (base patterns, target: 100%) |
| **Components** | 21 (20 Blade + 1 Livewire) |
