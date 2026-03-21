# AICL Architecture Documentation

**Version:** 3.0
**Last Updated:** 2026-03-16
**Owner:** `/docs` agent

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
| [Entity System](entity-system.md) | Traits, contracts, events, observers, state machines, policies | `/architect`, `/rlm` |
| [Auth & RBAC](auth-rbac.md) | Session auth, OAuth2, MFA, social login, roles, permissions, Shield | `/architect`, `/tester` |
| [Filament UI](filament-ui.md) | Resources, forms, tables, pages, widgets, plugin, v4 gotchas | `/architect`, `/rlm` |
| [Component Library](component-library.md) | 21 dashboard components, hierarchy, props, AI decision rules | `/architect`, `/rlm` |
| [Queue, Jobs & Scheduler](queue-jobs-scheduler.md) | Redis queue, failed jobs, scheduler, Octane context, monitoring | `/architect` |
| [Cache, Sessions & Redis](cache-sessions-redis.md) | Redis DB mapping, cache strategy, sessions, Spatie Settings | `/architect` |
| [Notifications](notifications.md) | Dispatcher, channels, NotificationLog, bell, entity event notifications | `/architect` |
| [Search & Real-time](search-realtime.md) | Scout search, Reverb WebSockets, broadcast events, Echo listeners | `/architect` |
| [Export & PDF](export-pdf.md) | CSV export actions, PdfGenerator, DomPDF templates | `/architect` |
| [SSO & External Auth](sso-external-auth.md) | SAML 2.0, social OAuth, attribute mapping, role sync | `/architect` |
| [Swoole Foundations](swoole-foundations.md) | Concurrent, SwooleCache, SwooleTimer, RequiresApproval — usage guide, API, fallback behavior | `/architect`, `/solutions` |
| [Event & Real-Time Layer](event-realtime-layer.md) | Domain Event Bus, broadcasting, polling, channel auth, presence, transport selection | `/architect`, `/solutions` |
| [Notification & Observability](notification-observability.md) | External notification drivers (Slack, Email, Teams, PagerDuty, Webhook, SMS), message templates, Ops Panel health checks | `/architect` |
| [Scaffolding & AI Layer](scaffolding-ai.md) | `--base=` flag, EntityRegistry, NeuronAI integration, AI tool calling, entity spec files | `/architect`, `/rlm` |
| [Alpine Component System](alpine-component-system.md) | React→Alpine transformation, Blade component tiers, x-data patterns, CMS blocks | `/designer`, `/architect` |
| [MCP Server](mcp-server.md) | Model Context Protocol server, entity auto-exposure, tools/resources/prompts, scope auth, extensibility | `/architect`, `/solutions` |

### AI & Quality

| Document | What It Covers | Primary Audience |
|----------|---------------|-----------------|
| [AI Generation Pipeline](ai-generation-pipeline.md) | Scaffolding command, 40 structural patterns, validation rules, quality checklist | `/architect` |
| [Testing & Quality](testing-quality.md) | PHPUnit, Dusk, Pint, PHPStan, test strategy, security scanning | `/tester` |

### Infrastructure & Operations (v3.0 — March 2026)

**Start here when debugging service issues:** [Service Orchestration](service-orchestration.md)

| Document | What It Covers | Primary Audience |
|----------|---------------|-----------------|
| [Service Orchestration](service-orchestration.md) | **Master reference** — service map, request flows, dependency matrix, port map, startup sequence, health checks, troubleshooting multi-service failures | All agents |
| [AI Assistant](ai-assistant.md) | Complete AI assistant lifecycle — architecture flow, 5 service dependencies, NeuronAI providers, tool system, streaming, compaction, troubleshooting decision tree | All agents |
| [Swoole/Octane (Operations)](swoole-octane.md) | DDEV/supervisor config, nginx proxy, ports, state management, worker lifecycle, troubleshooting, production considerations | `/architect`, `/solutions` |
| [Horizon & Queues](horizon-queues.md) | Custom Horizon process manager, supervisor hierarchy, queue names, scaling, AI streaming relationship, feature flag, troubleshooting | `/architect` |
| [Reverb & WebSockets](reverb-websockets.md) | Reverb server config, broadcasting, channel auth, Pusher protocol, AI streaming events, native WS vs Echo, troubleshooting | `/architect` |
| [Redis](redis.md) | Connection architecture (DB 0/1), cache/session/queue/broadcasting roles, prefix stacking, Horizon connection, troubleshooting | `/architect` |
| [Scheduler](scheduler.md) | schedule:work daemon, registered tasks, monitoring (ScheduleHistory), health check, Swoole timer boundary | `/architect` |
| [Repositories](repositories.md) | 3-repo structure (dev/framework/project), code flow, versioning, release workflow | `/release`, all agents |

### Component Deep-Dives

Detailed usage guides for specific subsystems (consolidated from standalone docs):

| Document | What It Covers | Primary Audience |
|----------|---------------|-----------------|
| [Swoole Concurrent](swoole-concurrent.md) | Concurrent::map() usage, parallel queries, fallback behavior | `/architect` |
| [Swoole Cache](swoole-cache.md) | SwooleCache API, cache wiring layer, testing patterns | `/architect` |
| [Swoole Timer](swoole-timer.md) | SwooleTimer API, Redis persistence, worker coordination | `/architect` |
| [Approval Workflow](approval-workflow.md) | RequiresApproval trait, approval logs, state transitions | `/architect` |
| [Domain Event Bus](domain-event-bus.md) | DomainEvent system, event registry, event persistence | `/architect` |
| [Realtime UI](realtime-ui.md) | Real-time UI patterns, polling, WebSocket integration | `/architect`, `/designer` |
| [Base Flag](base-flag.md) | `--base=` flag system for entity scaffolding | `/architect` |
| [Entity Registry](entity-registry.md) | EntityRegistry service, entity discovery, parallel queries | `/architect` |
| [Notification Drivers](notification-drivers.md) | External drivers (Slack, Email, Teams, PagerDuty, Webhook, SMS) | `/architect` |
| [Message Templates](message-templates.md) | Notification message templating system | `/architect` |
| [Ops Panel](ops-panel.md) | Operations panel, health checks, system monitoring | `/architect` |
| [Horizon (standalone)](horizon.md) | Horizon queue monitoring setup and configuration | `/architect` |
| [NeuronAI Integration](neuronai-integration.md) | NeuronAI provider integration, AI tool calling | `/architect`, `/solutions` |

### Legacy Docs (Superseded)

These older docs contain some useful detail but are partially outdated. The v3.0 Infrastructure docs above are authoritative:

| Document | Superseded By | Notes |
|----------|--------------|-------|
| [Cache, Sessions & Redis](cache-sessions-redis.md) | [Redis](redis.md) | Old doc references `.env` vars and removed Spatie Settings |
| [Queue, Jobs & Scheduler](queue-jobs-scheduler.md) | [Horizon & Queues](horizon-queues.md) + [Scheduler](scheduler.md) | Old doc doesn't mention Horizon or `aicl:horizon` |
| [Search & Real-time](search-realtime.md) | [Reverb & WebSockets](reverb-websockets.md) + [Search](search.md) | Old doc references `.env` vars |

---

## How Agents Use These Docs

### `/architect` — Building Features
1. Read [Foundation](foundation.md) for principles
2. Follow [Entity System](entity-system.md) for models and traits
3. Follow [Filament UI](filament-ui.md) for admin panel integration
4. Use [Component Library](component-library.md) for dashboard composition
5. Use [Swoole Foundations](swoole-foundations.md) for Concurrent, SwooleCache, SwooleTimer, and RequiresApproval
6. **For service issues:** Start with [Service Orchestration](service-orchestration.md), then drill into specific service docs
7. **For AI assistant issues:** Read [AI Assistant](ai-assistant.md) — includes troubleshooting decision tree

### `/rlm` — Validating Generated Code
1. Use [AI Generation Pipeline](ai-generation-pipeline.md) for structural pattern details and scaffolding rules
2. Cross-reference with [Entity System](entity-system.md) for trait/contract patterns
3. Check [Filament UI](filament-ui.md) for resource structure patterns
4. Validation is managed via Forge MCP (RLM extracted from AICL in Sprint F0)

### `/tester` — Writing Tests
1. Read [Testing & Quality](testing-quality.md) for test strategy and conventions
2. Reference [Auth & RBAC](auth-rbac.md) for auth test scenarios
3. Use [Entity System](entity-system.md) for event/observer test patterns

### `/solutions` — Designing Systems
1. Start with [Foundation](foundation.md) for constraints and principles
2. Review component docs to understand what's already built
3. Check `.claude/planning/framework/solutions/` for prior design documents

---

## Quick Links

- **Golden Entity Guide:** [.claude/planning/rlm/golden-entity-guide.md](../planning/rlm/golden-entity-guide.md)
- **Feature Test Map:** [.claude/planning/framework/reference/feature-test-map.md](../planning/framework/reference/feature-test-map.md)
- **Changelog:** [CHANGELOG_FRAMEWORK.md](../../CHANGELOG_FRAMEWORK.md)
