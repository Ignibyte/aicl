# AICL Architecture Documentation

**Version:** 3.0
**Last Updated:** 2026-03-16
**Owner:** Human-maintained

---

## Start Here

**New to AICL?** Read [foundation.md](foundation.md) first — it explains what AICL is, why it exists, and how all the pieces fit together.

---

## Document Map

### Foundation

| Document | What It Covers | Primary Audience |
|----------|---------------|-----------------|
| [Foundation](foundation.md) | Why AICL exists, product model, core principles, technology stack | All agents |

### Framework Components

Each major system has its own document explaining how it works in the AICL context:

| Document | What It Covers | Primary Audience |
|----------|---------------|-----------------|
| [Entity System](entity-system.md) | Traits, contracts, events, observers, state machines, policies | `/pipeline-implement`, `/pipeline-validate` |
| [Auth & RBAC](auth-rbac.md) | Session auth, OAuth2, MFA, social login, roles, permissions, Shield | `/pipeline-implement`, `/pipeline-validate` |
| [Filament UI](filament-ui.md) | Resources, forms, tables, pages, widgets, plugin, v4 gotchas | `/pipeline-implement`, `/pipeline-validate` |
| [Component Library](component-library.md) | 21 dashboard components, hierarchy, props, AI decision rules | `/pipeline-implement`, `/pipeline-validate` |
| [Queue, Jobs & Scheduler](queue-jobs-scheduler.md) | Redis queue, failed jobs, scheduler, Octane context, monitoring | `/pipeline-implement` |
| [Cache, Sessions & Redis](cache-sessions-redis.md) | Redis DB mapping, cache strategy, sessions, Spatie Settings | `/pipeline-implement` |
| [Notifications](notifications.md) | Dispatcher, channels, NotificationLog, bell, entity event notifications | `/pipeline-implement` |
| [Search & Real-time](search-realtime.md) | Scout search, Reverb WebSockets, broadcast events, Echo listeners | `/pipeline-implement` |
| [Export & PDF](export-pdf.md) | CSV export actions, PdfGenerator, DomPDF templates | `/pipeline-implement` |
| [SSO & External Auth](sso-external-auth.md) | SAML 2.0, social OAuth, attribute mapping, role sync | `/pipeline-implement` |
| [Swoole Foundations](swoole-foundations.md) | Concurrent, SwooleCache, SwooleTimer, RequiresApproval — usage guide, API, fallback behavior | `/pipeline-implement`, `/pipeline-design` |
| [Event & Real-Time Layer](event-realtime-layer.md) | Domain Event Bus, broadcasting, polling, channel auth, presence, transport selection | `/pipeline-implement`, `/pipeline-design` |
| [Notification & Observability](notification-observability.md) | External notification drivers (Slack, Email, Teams, PagerDuty, Webhook, SMS), message templates, Ops Panel health checks | `/pipeline-implement` |
| [Scaffolding & AI Layer](scaffolding-ai.md) | `--base=` flag, EntityRegistry, NeuronAI integration, AI tool calling, entity spec files | `/pipeline-implement`, `/pipeline-validate` |
| [Alpine Component System](alpine-component-system.md) | React→Alpine transformation, Blade component tiers, x-data patterns, CMS blocks | `/pipeline-style`, `/pipeline-implement` |
| [MCP Server](mcp-server.md) | Model Context Protocol server, entity auto-exposure, tools/resources/prompts, scope auth, extensibility | `/pipeline-implement`, `/pipeline-design` |

### AI & Quality

| Document | What It Covers | Primary Audience |
|----------|---------------|-----------------|
| [AI Generation Pipeline](ai-generation-pipeline.md) | Scaffolding command, 40 structural patterns, validation rules, quality checklist | `/pipeline-implement` |
| [Testing & Quality](testing-quality.md) | PHPUnit, Dusk, Pint, PHPStan, test strategy, security scanning | `/pipeline-validate` |

### Infrastructure & Operations (v3.0 — March 2026)

**Start here when debugging service issues:** [Service Orchestration](service-orchestration.md)

| Document | What It Covers | Primary Audience |
|----------|---------------|-----------------|
| [Service Orchestration](service-orchestration.md) | **Master reference** — service map, request flows, dependency matrix, port map, startup sequence, health checks, troubleshooting multi-service failures | All agents |
| [AI Assistant](ai-assistant.md) | Complete AI assistant lifecycle — architecture flow, 5 service dependencies, NeuronAI providers, tool system, streaming, compaction, troubleshooting decision tree | All agents |
| [Swoole/Octane (Operations)](swoole-octane.md) | DDEV/supervisor config, nginx proxy, ports, state management, worker lifecycle, troubleshooting, production considerations | `/pipeline-implement`, `/pipeline-design` |
| [Horizon & Queues](horizon-queues.md) | Custom Horizon process manager, supervisor hierarchy, queue names, scaling, AI streaming relationship, feature flag, troubleshooting | `/pipeline-implement` |
| [Reverb & WebSockets](reverb-websockets.md) | Reverb server config, broadcasting, channel auth, Pusher protocol, AI streaming events, native WS vs Echo, troubleshooting | `/pipeline-implement` |
| [Redis](redis.md) | Connection architecture (DB 0/1), cache/session/queue/broadcasting roles, prefix stacking, Horizon connection, troubleshooting | `/pipeline-implement` |
| [Scheduler](scheduler.md) | schedule:work daemon, registered tasks, monitoring (ScheduleHistory), health check, Swoole timer boundary | `/pipeline-implement` |
| [Repositories](repositories.md) | 3-repo structure (dev/framework/project), code flow, versioning, release workflow | All pipeline commands |

### Legacy Docs (Superseded)

These older docs contain some useful detail but are partially outdated. The v3.0 Infrastructure docs above are authoritative:

| Document | Superseded By | Notes |
|----------|--------------|-------|
| [Cache, Sessions & Redis](cache-sessions-redis.md) | [Redis](redis.md) | Old doc references `.env` vars and removed Spatie Settings |
| [Queue, Jobs & Scheduler](queue-jobs-scheduler.md) | [Horizon & Queues](horizon-queues.md) + [Scheduler](scheduler.md) | Old doc doesn't mention Horizon or `aicl:horizon` |
| [Search & Real-time](search-realtime.md) | [Reverb & WebSockets](reverb-websockets.md) + [Search](search.md) | Old doc references `.env` vars |

---

## How Pipeline Commands Use These Docs

### `/pipeline-implement` — Building Features
1. Read [Foundation](foundation.md) for principles
2. Follow [Entity System](entity-system.md) for models and traits
3. Follow [Filament UI](filament-ui.md) for admin panel integration
4. Use [Component Library](component-library.md) for dashboard composition
5. Use [Swoole Foundations](swoole-foundations.md) for Concurrent, SwooleCache, SwooleTimer, and RequiresApproval
6. **For service issues:** Start with [Service Orchestration](service-orchestration.md), then drill into specific service docs
7. **For AI assistant issues:** Read [AI Assistant](ai-assistant.md) — includes troubleshooting decision tree

### `/pipeline-validate` — Reviewing & Testing
1. Use [AI Generation Pipeline](ai-generation-pipeline.md) for structural pattern details and scaffolding rules
2. Cross-reference with [Entity System](entity-system.md) for trait/contract patterns
3. Check [Filament UI](filament-ui.md) for resource structure patterns
4. Read [Testing & Quality](testing-quality.md) for test strategy and conventions
5. Reference [Auth & RBAC](auth-rbac.md) for auth test scenarios

### `/pipeline-design` — Designing Systems
1. Start with [Foundation](foundation.md) for constraints and principles
2. Review component docs to understand what's already built

---

## Quick Links

- **Changelog:** [CHANGELOG_FRAMEWORK.md](../../CHANGELOG_FRAMEWORK.md)
