# AICL Framework Changelog

All notable changes to the AICL framework package (`packages/aicl/`) are documented here.

## Versioning

This project uses **Semantic Versioning (SemVer)** — `MAJOR.MINOR.PATCH`:

- **MAJOR** — Breaking changes to package contracts, traits, base classes, or public API
- **MINOR** — New package features, commands, components, or non-breaking additions
- **PATCH** — Bug fixes, test improvements, documentation updates

While in `0.x.x`, the package API is not yet stable. Bump MINOR for features, PATCH for fixes.

Current version: `3.0.0`

---

## [3.0.0] - 2026-02-19

### Summary

**AICL Framework v3.0.0 — Stable Foundation Release.** This major version marks the completion of the core AICL framework. The RLM knowledge system is fully hardened (Sprint W) and retrieval-optimized (Sprint X), the entity generation pipeline is battle-tested across 20+ entities, and the full stack — from scaffolding through validation — is production-ready. This release consolidates v2.5.0 (RLM Hardening) and v2.6.0 (Retrieval Optimization) into a single milestone. Framework development is now stable; future work focuses on the CMS package.

---

## [2.6.0] - 2026-02-19

### Summary

**Sprint X: DSPy-Inspired Retrieval Optimization.** Five improvements that close the RLM optimization loop: (1) effectiveness-weighted recall ranking using `impact_score * confidence`, (2) surfaced count tracking with prevention rate KPI, (3) inline L1 assertions via `--target` and `--quick` flags on `aicl:validate`, (4) outcome-driven optimization via `aicl:rlm optimize` command, (5) typed `EntitySignature` value object for contract-based generation and validation. 5 phases, 39 new tests.

### Added

- **Effectiveness-weighted ranking** — `DistillationService::getTopLessons()` and `generateWhenThenRules()` now order by `impact_score * GREATEST(confidence, 0.1) DESC`; confidence floor (0.1) prevents new lessons from vanishing while decaying lessons naturally fall off cheat sheets
- **Surfaced count tracking** — New `surfaced_count` column on `distilled_lessons` (migration); bulk-incremented in recall path; stale detection flags lessons surfaced 50+ times with 0 interactions as `needs_review`
- **Prevention rate KPI** — `KpiCalculator::lessonRelevanceRates()` computes `prevented_count / max(surfaced_count, 1)`; surfaced stats visible in `aicl:rlm stats` output
- **Inline L1 assertions** — `aicl:validate --target=model --target=factory --quick` runs targeted pattern checks mid-generation; `--target` filters patterns by file type, `--quick` outputs single-line pass/fail with exit code
- **`target` property on `EntityPattern`** — Each pattern explicitly declares its target file type (model, migration, factory, filament, policy, observer, test, api); all 42+ patterns backfilled
- **`aicl:rlm optimize` command** — Analyzes `GenerationTrace` outcomes (GOOD/FAIR/POOR classification), adjusts lesson `impact_score` with +/- 20% clamp per run; `--dry-run` (default), `--apply` to persist, `--reset` to restore `base_impact_score`; minimum 5 traces guard rail
- **`base_impact_score` column** on `distilled_lessons` (migration) — Preserves original score for reset capability
- **`EntitySignature` value object** — Typed contract with `entityName`, `fields`, `states`, `relationships`, `features`; methods: `expectedFiles()`, `expectedPatternCount()`, `toContext()`, `hash()` (SHA-256), `toArray()`/`fromArray()` roundtrip
- **`signature_hash` column** on `generation_traces` (migration) — Stores `EntitySignature::hash()` for outcome-by-signature analysis
- **`MakeEntityCommand` signature integration** — Builds `EntitySignature` from parsed `--fields`/`--states`/`--relationships` options and stores on trace creation
- **`EntityValidator` signature completeness** — When signature provided, validates all `expectedFiles()` exist and reports missing files as warnings
- **Agent prompt inline checkpoints** — `/generate` and `/architect` prompts now include 2 mid-generation `aicl:validate --target --quick` checkpoints: (1) after model+migration+factory, (2) after Filament resources

### Changed

- **`DistillationService` auto-deactivation** — Now also flags lessons with `surfaced_count >= 50` and zero interactions as stale
- **`ValidateEntityCommand`** — New `--target` (repeatable) and `--quick` options; version-aware pattern filtering delegated to `EntityValidator`

### Fixed

- **`KpiCalculator` division guard** — Prevention rate uses `max(surfaced_count, 1)` to avoid divide-by-zero on new lessons

---

## [2.5.0] - 2026-02-19

### Summary

**Sprint W: RLM Hardening — Structured Reflection + Learning Guardrails.** Three workstreams harden the RLM knowledge base against entropy, sludge, and governance gridlock: (1) structured reflection schema for higher-fidelity failure data with 3 ingestion modes, (2) learning system guardrails with observation/instruction split, proof-backed promotion, auto-deactivation, and pattern versioning, (3) automated garbage collection with weekly/daily schedules. Plus a mutation test suite that validates the full learning loop end-to-end. 8 phases, 123 new tests.

### Added

- **Structured reflection schema** — 10 new columns on `rlm_failures`: 4 reflection fields (`attempt`, `feedback`, `fix`, `preventive_rule`), `rule_norm`/`rule_hash` for deterministic clustering, 4 identity fields (`validator_layer`, `validator_id`, `entity_name`, `phase`), plus `file_path` and `trace_id`
- **`RuleNormalizer` helper** — Canonical text normalization + deterministic SHA-1 hashing for preventive rules; ensures minor wording variations cluster together
- **3 ingestion modes for `aicl:rlm learn`** — Free-text (backward compatible), structured flags (`--attempt`, `--feedback`, `--fix`, `--rule`, `--entity`, `--phase`, etc.), and JSON mode (`--json` inline or `@filepath`); auto-generates description summary from structured fields
- **Interactive learn mode** — `aicl:rlm learn` with no arguments prompts for each field sequentially
- **`LessonType` enum** — `observation`/`instruction`/`prevention_rule` with `isSurfaceable()` and `requiresProof()` methods; observations excluded from default recall
- **`KnowledgeLinkType` enum** — `golden_entity_file`/`test_case`/`commit_sha`/`doc_anchor` with proof strength ranking for recall prioritization
- **Auto-deactivation** — Lessons with confidence `< 0.2` automatically deactivated; stale lessons (no positive signal in N generations) flagged `needs_review`
- **Proof-backed promotion** — `promoteObservation()` requires at least one `KnowledgeLink` proof hook to promote observation → instruction; stores `promotion_reason` with cluster stats
- **`ProofLinkIntegrityJob`** — Scheduled job validates proof links still resolve; broken links demote instructions to observations or flag `needs_review`
- **Pattern versioning** — `PatternRegistry::VERSION` constant, `getPatternSet($version)` filtering, `EntityPattern` `introducedIn`/`removedIn` properties, `EntityValidator` version-aware validation with unpinned warning banner
- **Waiver system** — `EntityWaiver` model with budget math (cost = pattern weight), auto-expiry, `aicl:rlm waiver add/list/remove` CLI subcommands, configurable budget via `aicl.rlm.waiver_budget`
- **GC scheduler** — Weekly pattern discovery + cleanup + proof integrity, daily health stats, monthly log rotation; all guarded with `onOneServer` + `withoutOverlapping`
- **`RlmMaintenanceComplete` event** — Dispatched after GC cycle with task summaries and duration
- **Mutation suite** — 6 mutator classes (`MutatorWrongNamespace`, `MutatorMissingTrait`, `MutatorPolicyGap`, `MutatorFactoryTypeMismatch`, `MutatorMissingSearchableColumns`, `MutatorConflictingFix`) implementing `Mutator` interface; 7-step protocol test validates detect → record → cluster → recall → promote end-to-end
- **Enriched `aicl:validate` score card** — Pattern set version header, L1 badge with waiver count/budget, frontend pattern sub-score, file count, learning summary (active lessons + entity failures), version warning for unpinned entities
- **Navigation switcher CSS shipped as package asset** — `data-nav-mode` CSS rules now registered via `FilamentAsset::register([Css::make(...)])` so shipped projects get them automatically after `artisan filament:assets`, eliminating dependency on project theme.css having the rules

### Changed

- **`DistillationService` clustering** — Now uses `rule_hash` as primary clustering key (deterministic); falls back to pattern_id and fuzzy text only for legacy records without `rule_hash`
- **`DistillationService` guidance** — Derives WHEN/THEN rules from structured `feedback`/`fix` fields; falls back to RULE-only format for legacy lessons
- **`RecallService` filtering** — Enforces `surfaceable()` + `verified()` scopes; only `instruction` and `prevention_rule` types with `is_verified = true` appear in recall
- **`KnowledgeWriter::addLesson()`** — Accepts `LessonType` parameter with completeness enforcement (warns if instruction/prevention_rule lacks rule/fix)
- **Agent prompts** — `/rlm`, `/architect`, `/tester` updated with structured learn format as preferred ingestion method; legacy free-text kept as fallback

### Fixed

- **`RlmCommand` learn — undefined array key** — Null-coalescing for `validator_layer` when only `validator_id` is set
- **`AiclServiceProvider::pruneRlmLogs()` — negative `diffInDays()`** — Carbon 3.x returns negative for past dates; added `absolute: true` parameter
- **Version badge shows "unknown" in shipped projects** — `VersionService::parseComposerVersion()` regex now handles `v` prefix (`/^v?(\d+\.\d+\.\d+)/`); added vendor path fallback (`vendor/aicl/aicl/CHANGELOG_FRAMEWORK.md`); `CHANGELOG_FRAMEWORK.md` now ships inside the package
- **Navigation switcher icon flash** — Added `x-cloak` to `bars-3` icon in `navigation-switcher-toggle.blade.php` (matching the `view-columns` icon)

---

## [2.4.1] - 2026-02-19

### Fixed

- **Navigation layout default changed to `switchable`** — all projects now ship with the sidebar/topbar toggle enabled by default (`AICL_NAV_LAYOUT` env var still overrides)
- **Font Awesome dependency eliminated** — navigation switcher toggle icons replaced with Heroicons (`x-heroicon-o-view-columns`, `x-heroicon-o-bars-3`), removing the `owenvoke/blade-fontawesome` requirement from the package
- **Framework version resolution in shipped projects** — `VersionService` now falls back to `Composer\InstalledVersions` when `CHANGELOG_FRAMEWORK.md` is not at the project root (shipped projects get version from Composer metadata instead of showing "vunknown")
- **Changelog page vendor path fallback** — `Changelog` Filament page checks `vendor/aicl/aicl/CHANGELOG_FRAMEWORK.md` as fallback when the dev-only project root copy is absent

---

## [2.4.0] - 2026-02-18

### Summary

**Sprint V: Styleguide Completeness.** Two new utility components, comprehensive code snippets and auto-generated reference blocks across all 7 styleguide pages, a dynamic overview page powered by ComponentRegistry, and 36 new tests validating component rendering and registry consistency.

### Added

- **`<x-aicl-code-block>` component** — Syntax-highlighted code snippet display with Alpine.js show/hide toggle and clipboard copy button; new SDC component (PHP + Blade + component.json)
- **`<x-aicl-component-reference>` component** — Auto-generated documentation panel that reads from `ComponentRegistry::get()` and renders props table, AI decision rule, context tags, excluded contexts, Filament equivalent, and composable-in info inside a collapsible accordion
- **Code snippets on all 6 styleguide sub-pages** — Every component showcase now includes a `<x-aicl-code-block>` with copy-paste Blade markup
- **Component reference blocks on all 6 styleguide sub-pages** — Every component showcase now includes a `<x-aicl-component-reference>` with full prop documentation
- **Missing component showcases** — Added IgnibyteLogo (all size variants + iconOnly) and AuthSplitLayout (scaled preview) to Layout Components page
- **Dynamic overview page** — `StyleguideOverview::getViewData()` pulls live counts from `ComponentRegistry`; stats row shows total components, categories, and JS module count; category cards list all components with descriptions and links to sub-pages
- **36 new tests** — `CodeBlockTest` (4), `ComponentReferenceTest` (11), `StyleguideOverviewTest` + `StyleguideSubpagesTest` (13), `ComponentRegistryConsistencyTest` (8)

### Fixed

- **Array default values in component-reference** — Added `is_array()` check with `json_encode()` rendering for component.json props that have array defaults (e.g., combobox, data-table, command-palette)

---

## [2.3.0] - 2026-02-17

### Summary

**Sprint U: Entity Page UX Overhaul.** Four enhancements to the scaffolder that produce polished, dashboard-grade View and Edit pages out of the box: full-width form layouts, dedicated infolist schemas, View↔Edit tab navigation, and Filament admin layout rules in the world model.

### Added

- **Infolist schema generation** — Scaffolder now generates a dedicated `{Name}Infolist` class (parallel to `{Name}Form`) using `TextEntry`, `IconEntry`, `KeyValueEntry` components for card-based View pages instead of disabled form fields
- **View↔Edit sub-navigation** — Resource class generates `getRecordSubNavigation()` with `SubNavigationPosition::Top` for horizontal tab switching between Details and Edit pages
- **View page header actions** — View page generates `EditAction::make()` header action; Edit page generates `ViewAction::make()` + `DeleteAction::make()`
- **Filament Admin Layout Rules** — New world model section with Section layout rules, full-width field list, section naming conventions, field-to-infolist entry mapping, and sub-navigation rules
- **Golden example `filament-infolist.php`** — New annotated reference file demonstrating infolist patterns

### Changed

- **Form layout defaults** — All generated Sections now include `->columnSpanFull()` (Filament v4 requirement) and `->columns(2)` for side-by-side short field layout
- **View page stubs** — `$navigationLabel = 'Details'` for sub-navigation tab
- **Edit page stubs** — `$navigationLabel = 'Edit'` for sub-navigation tab
- **Resource stubs** — Now include `infolist()` method, `$subNavigationPosition` property, and `getRecordSubNavigation()` method
- **World model file manifest** — Updated from 17 to 18 entries (added `infolist` target)
- **Golden example updates** — Form, View, Edit, and Resource files updated to demonstrate Sprint U patterns
- **`ValidateEntityCommand`** — Now detects form and infolist schema files in addition to existing file checks
- **Component patterns** — 2 new RLM patterns for component validation (42 total)

### Fixed

- **`AdminPanelAuthTest`** — Updated for registration-disabled default; tests now assert redirect when disabled instead of expecting registration link
- **100% test coverage** — Added `UserModelTest` (22 tests) closing the last coverage gap; 6,550 total passing tests across all three suites

---

## [2.2.0] - 2026-02-17

### Summary

**Sprint T: Developer Experience & Process Enhancements.** Six improvements targeting DX, knowledge distribution, documentation persistence, auth fixes, and process taxonomy.

### Added

- **Combined Changelog Viewer** — Changelog page now shows Framework and Project tabs; `VersionService` exposes `frameworkVersion()` and `projectVersion()`
- **`aicl:rlm sync-knowledge` command** — Re-runs all 6 RLM seeders with diff reporting (Total/New/Updated per seeder); supports `--backfill` for embeddings and `--all` for ES re-index
- **Architecture documentation persistence** — `docs/architecture/` directory with `services/`, `middleware/`, `integrations/` subdirectories for persistent project-level architectural docs
- **Process taxonomy reference** — `.claude/planning/framework/reference/process-taxonomy.md` defining Pipeline, Workflow, Task, and Sprint with decision tree and agent routing

### Changed

- **Registration disabled by default** — `aicl.features.allow_registration` config key (default: `false`); enable via `AICL_ALLOW_REGISTRATION=true` in `.env`

### Fixed

- **Filament authorization 500 errors** — Custom middlewares (SecurityHeaders, TrackPresence, ApiRequestLog) now guard against Livewire Redirector objects; custom `MustTwoFactor` middleware without strict return type; `assertFilamentAccessDenied()` test helper replaces 22 workaround assertions
- **MCP config** — `.mcp.json` now uses `ddev exec php` wrapper so Laravel Boost MCP server runs inside the DDEV container

---

## [2.1.0] - 2026-02-16

### Summary

**Sprint O: Alpine Component Factory + SDC Architecture.** Introduces a Single Directory Component (SDC) architecture for the AICL component system, migrates all 21 existing components, adds 12 new Alpine.js + Blade interactive components, implements a schema-driven `ComponentRegistry` with field signal engine, adds 18 new RLM validation patterns, and adds a `--views` scaffolder mode for public-facing Blade views. Component count grows from 21 to 33.

### Added

#### SDC Foundation (Phase 0)
- **`component-v1.json` schema** at `packages/aicl/components/schema/component-v1.json` — JSON Schema for validating component manifests (typed props, slots, variants, decision rules, Filament crosswalk)
- **`ComponentDefinition` value object** — Readonly DTO for parsed component.json data
- **`ComponentRecommendation` value object** — Readonly DTO for AI recommendations (tag, reason, confidence, suggested props, Filament alternative)
- **`ComponentDiscoveryService`** — Auto-discovers components from `packages/aicl/components/` and `app/Components/` (client override by tag name)
- **`ComponentRegistry` singleton** — Query API: `all()`, `get()`, `forCategory()`, `forContext()`, `recommend()`, `recommendForEntity()`, `schema()`, `validateProps()`, `composableChildren()`
- **Field signal engine** — Pattern matching: `starts_at+ends_at` → calendar, `status:enum` → status-badge, `budget:float` → stat-card, `target+actual` → kpi-card, `progress:integer` → progress-card
- **`artisan aicl:components` command** — Subcommands: `list`, `show {tag}`, `validate`, `recommend {fields}`, `tree`, `cache`, `clear`
- **Production cache** — `aicl:components cache` writes to `bootstrap/cache/component-registry.php`
- **Vite plugin** — Auto-bundles `packages/aicl/components/*/*.js` Alpine modules

#### New Interactive Components (Phase 1 — 12 components)
- **`<x-aicl-data-table>`** — Client-side sortable, filterable, paginated data table via Alpine x-data
- **`<x-aicl-command-palette>`** — Cmd+K spotlight search overlay with fuzzy search and keyboard navigation
- **`<x-aicl-modal>`** — Dialog overlay with size variants (sm/md/lg/xl/full), focus trap, backdrop close
- **`<x-aicl-drawer>`** — Slide-out panel from left/right edge with x-transition animation
- **`<x-aicl-dropdown>`** — Trigger + floating menu with Floating UI positioning and keyboard nav
- **`<x-aicl-combobox>`** — Searchable select with typeahead filtering and async option support
- **`<x-aicl-accordion>`** + **`<x-aicl-accordion-item>`** — Collapsible content sections with single/multiple expand modes
- **`<x-aicl-tooltip>`** — Hover/focus tooltip with Floating UI positioning
- **`<x-aicl-avatar>`** — Image with fallback initials, size variants (xs-xl), rounded options
- **`<x-aicl-badge>`** — Extended variant system (outline, solid, soft), dot indicator, removable
- **`<x-aicl-toast>`** — Stacking notification system via Alpine.store('toasts'), auto-dismiss, position variants
- **`<x-aicl-tabs>`** — Enhanced tab switcher with URL hash persistence, keyboard nav, variant styles (underline/boxed/pill/vertical)

#### Component Decision Tree (Phase 2)
- **`aicl:components tree`** — Auto-generates decision tree from registry (7 levels: Metric, Data, Collection, Action, Status, Timeline, Layout, Feedback)
- **`composable_in` fields** — Composition rules enforced via component.json (Layout → Content → Inline hierarchy)
- **World model updated** — Component categories, context rules, and Filament crosswalk table added to `.claude/planning/rlm/world-model.md`
- **RLM recall integration** — `aicl:rlm recall` now renders component recommendations in both full and cheatsheet formats
- **Pipeline context integration** — `aicl:pipeline-context {Entity} --components` returns field-specific recommendations

#### RLM Component Patterns (Phase 3 — 10 patterns C01-C10)
- C01: `component-exists-for-pattern` — Registered component used (not raw HTML)
- C02: `correct-component-for-data` — Field signals match component used
- C03: `valid-component-nesting` — Layout → Content → Inline hierarchy respected
- C04: `statsrow-children` — StatsRow only contains metric category components
- C05: `splitlayout-slot-usage` — Main slot = primary content, sidebar = metadata
- C06: `no-raw-html-for-known-patterns` — Library component used when available
- C07: `component-props-complete` — Required props provided, types match schema
- C08: `responsive-grid-cols` — CardGrid cols 1-4, DataTable responsive classes present
- C09: `empty-state-has-cta` — EmptyState includes actionLabel + actionUrl
- C10: `dark-mode-support` — Components use `dark:` variants

#### Scaffolder `--views` Mode (Phase 4)
- **`aicl:make-entity {Name} --views`** — Generates public-facing Blade views composed from `<x-aicl-*>` library components
- Generates: `index.blade.php`, `show.blade.php`, 3 sub-components (`{entity}-card`, `{entity}-filters`, `{entity}-status`), Alpine JS module, `{Entity}ViewController`, web routes
- Registry-driven component selection — calendar auto-detected for datetime range fields
- Filament widget adapter auto-generated when calendar/chart pattern detected

#### RLM View Patterns (Phase 5 — 8 patterns V01-V08)
- V01: `blade-view-structure` — Extends layout, uses sections correctly
- V02: `alpine-component-structure` — x-data function, init(), destroy() lifecycle
- V03: `component-composition` — Uses `<x-aicl-*>` library components
- V04: `tailwind-token-usage` — Design tokens, not arbitrary values
- V05: `accessibility-markup` — ARIA labels, semantic HTML, keyboard nav
- V06: `echo-binding` — Correct channel names for real-time
- V07: `view-controller-pair` — Controller exists and returns correct view
- V08: `responsive-layout` — Mobile-first responsive classes present

#### Styleguide Pages
- **Interactive Components** page — Modal, Drawer, Dropdown, Accordion, Tabs, Combobox, DataTable, CommandPalette showcases
- **Feedback Components** page — Toast, Tooltip, Avatar, Badge showcases

#### Agent Updates
- `/architect` and `/designer` agent commands updated with Component Registry tool references
- `/designer` gains **Operation 6: React→Alpine Component Conversion** — systematic process for converting React components (shadcn/ui, TailAdmin, Radix) into AICL SDC components
- RLM recall CLI renders component recommendations in both full and cheatsheet formats

### Fixed
- **Navigation icons on grouped pages** — All 19 pages/resources that belong to a `$navigationGroup` now set `$navigationIcon = null`. Icons belong on the NavigationGroup in AdminPanelProvider, not on child items. This fix enables proper top navigation layout support (previously, child-level icons caused display issues in top nav mode).

### Changed
- All 21 existing components migrated from scattered directories (`src/View/Components/`, `resources/views/components/`, `resources/js/components/`) into co-located SDC structure at `packages/aicl/components/{name}/`
- Each component now has a `component.json` manifest with typed props, slots, variants, decision rules, and Filament crosswalk
- `AiclServiceProvider` uses `ComponentDiscoveryService` for Blade component registration
- `AiclPlugin` registers InteractiveComponents and FeedbackComponents styleguide pages
- `aicl:validate` output shows separate "Frontend patterns" score line for entities with `--views`
- Component Library table in `/designer` agent updated from 21 components / 6 categories to 33 components / 7 categories

### Tests
- ~200 new tests across PHPUnit and Dusk covering:
  - `ComponentDiscoveryService`, `ComponentRegistry`, field signal engine
  - `artisan aicl:components` command (all subcommands)
  - All 12 new component Blade classes (render + props + slots)
  - All 33 component.json manifest schema validation
  - Dusk interactive tests for 8 components (DataTable, CommandPalette, Modal, Drawer, Dropdown, Combobox, Accordion, Tabs)
  - 10 component RLM patterns + 8 view RLM patterns
  - Scaffolder `--views` mode in Framework suite
  - Styleguide page registration and accessibility
- Full package suite: 6482 passed, 4 pre-existing infrastructure failures (PolicyCoverageTest, RlmCoverageTest)

### Upgrade Instructions
Existing projects: `composer update aicl/aicl && ddev artisan aicl:upgrade --force`

## [2.0.3] - 2026-02-16

### Fixed
- **Agent distribution bugfix (BUGFIX-001):** upgrade-manifest was actively deleting 7 agents that should ship to projects (`remove-entity`, `scan-all`, `scan-phpstan`, `scan-semgrep`, `scan-snyk`, `test-dusk`, `project-setup`)
- Synced 8 core pipeline agent stubs from `-pipeline.md` variants (adds RLM recall integration, Pre-Compaction Flush)
- Added 6 missing stub files for utility agents
- All three distribution systems (stubs, build-skeleton, upgrade-manifest) now agree on exactly 16 project agents

### Changed
- Simplified `build-skeleton.sh` rsync excludes to single `--exclude='.claude/commands/*.md'` wildcard
- Added pipeline-variant leak check and agent count validation (expected: 16) to build-skeleton.sh
- Rewrote release agent Phase 1d with complete variant architecture documentation and sync procedure

### Upgrade Instructions
Existing projects: `composer update aicl/aicl && ddev artisan aicl:upgrade --force`

## [2.0.2] - 2026-02-16

### Added
- `/project-setup` agent for guided new-project setup and troubleshooting
- HTTPS URL generation via `URL::forceScheme('https')` when `APP_URL` uses https (fixes mixed-content blocking behind nginx proxy)
- Auto-configure `APP_URL` from `DDEV_HOSTNAME` in skeleton post-start hooks
- CMS package sprint plans (Sprints O through S roadmap)
- Published config sections for notifications, docs, health, AI, RLM, and KPI thresholds
- AI tool call display in chat widget JS

### Changed
- Move `aicl:install` out of DDEV hooks into `/project-setup` agent (install no longer runs on every `ddev start`)
- Add `ensureMigrated()` to non-force `InstallCommand` path (runs pending migrations even when already installed)
- Remove DB-stored score/failure markdown files (now managed in PostgreSQL)

### Fixed
- Fix fresh install crash caused by duplicate `event` column on `activity_log` table — removed duplicate migration publishing from `InstallCommand`
- Fix cascading `super_admin` role-not-found error in `AdminUserSeeder`
- Fix `NotificationChannelSeeder` crash on APP_KEY change — use truncate+create instead of `updateOrCreate`
- Fix skeleton DDEV hook backslash escaping for `AdminUserSeeder` class name
- Auto-configure `VITE_REVERB_HOST` from `DDEV_HOSTNAME` in skeleton hooks (fixes WebSocket `wss://localhost` failure)

---

## [2.0.1] - 2026-02-15 (superseded by 2.0.2)

### Fixed
- Fix fresh install crash caused by duplicate `event` column on `activity_log` table — `InstallCommand` was publishing Spatie activitylog/medialibrary migrations that conflicted with the consolidated migrations already shipped in the skeleton
- Fix cascading `super_admin` role-not-found error in `AdminUserSeeder` (caused by the migration failure preventing Shield from seeding roles)
- Remove redundant `php artisan migrate` from skeleton DDEV post-start hooks (already handled by `aicl:install`)
- Fix `NotificationChannelSeeder` crash on APP_KEY change — use truncate+create instead of `updateOrCreate` to avoid decrypting rows with a stale key
- Fix skeleton DDEV hook backslash escaping for `AdminUserSeeder` class name
- Auto-configure `VITE_REVERB_HOST` from `DDEV_HOSTNAME` in skeleton post-start hooks (fixes WebSocket `wss://localhost` failure)

---

## [2.0.0] - 2026-02-15

### Summary

Major release consolidating 13 sprints of work since v1.2.0. This release transforms AICL from a basic code generation framework into a full-featured AI-first application platform with real-time capabilities, intelligent knowledge management, and spec-driven entity generation.

### Highlights

- **Swoole Foundations** (Sprint A) — Concurrent task execution, SwooleCache, ApprovalWorkflow, SwooleTimer
- **External Notifications** (Sprint G) — Health cache, broadcast events, DeclaresBaseSchema, scaffolder events, presence indicator, live log widget
- **Presence & Ops Panel** (Sprint H) — PresenceRegistry, TrackPresenceMiddleware, session management, ToolbarPresence widget
- **AI WebSocket Streaming** (Sprint I) — AiStreamJob, Reverb broadcast events, Alpine.js WebSocket chat UI, concurrent stream limiting
- **Developer Experience** (Sprint J) — nginx→Swoole proxy, Dusk via Swoole, favicons, version badge, changelog viewer, doc browser, nav layout switcher
- **AI Tool Calling + Entity Specs** (Sprint K) — 5 built-in AI tools, SpecFileParser, ValidateSpecCommand, Markdown entity spec files
- **RLM Distillation MVP** (Sprint L) — DistillationService, DistilledLesson model, curated seeders, cheat sheet recall (~30 lines vs 229)
- **RLM Feedback Loop + KPIs** (Sprint M) — KpiCalculator, auto-retirement, confidence growth/decay, computed verdict system
- **Validation** (Sprint N) — 20 entities stress-tested across 4 domain batches (569 files, 401 PHPUnit, 111 Dusk)
- **Spec-Driven Generation** (Sprint O) — Structured widget/notification/observer/report specs, standalone tool and permission spec types
- **Seeker Audit** — PHPStan zero errors, KnowledgeService decomposed (987→275 lines), three-tier error handling
- **Coverage Push** — ~1500 new tests across 25 test files
- **RLM Consolidation** — PG + ES two-layer architecture (SQLite eliminated)

### Stats

- **581 files changed**, 93,200 insertions, 7,153 deletions
- **~4,690 PHPUnit tests** passing (Package + Framework + Project)
- **16 Dusk browser tests** across 4 test files
- **42/42 RLM patterns** (100% coverage)
- **10 hub entities** in the RLM knowledge system
- **PHPStan:** 0 errors (40-entry justified baseline)

### Breaking Changes

- `KnowledgeService` decomposed into `KnowledgeWriter`, `KnowledgeSearchEngine`, and `RecallService`
- SQLite knowledge layer removed (PG + ES only)
- SSE streaming removed (use Reverb/WebSocket instead)
- `RlmFailureSeeder` removed (failures now seeded via `InstallCommand`)

See individual sprint entries below for detailed changelogs (v1.3.0 through v1.16.1).

---

## [1.16.1] - 2026-02-15

### Summary

Seeker Audit: Full codebase quality sweep — 17 findings across security, static analysis, testing, architecture, and code hygiene. All resolved in three batches. Key outcomes: PHPStan zero errors, KnowledgeService decomposed from 987 to 275 lines, three-tier error handling convention established, 94 new tests added.

### Added
- `KnowledgeWriter` — extracted write operations from KnowledgeService (addLesson, recordFailure, recordScore, recordTrace)
- `KnowledgeSearchEngine` — extracted ES hybrid search (kNN + BM25 + RRF) with deterministic fallback
- `RecallService` — extracted agent-facing recall orchestration, risk briefing, topic mapping
- `SocialAuthService` — extracted 6 public methods from SocialAuthController (handleOAuthCallback, handleSamlCallback, findExistingSocialAccount, findOrCreateUser, linkSocialAccount, syncSamlRoles)
- `SocialAuthException` — auth flow errors with missingEmail() and autoCreateDisabled() factories
- `RlmFailureRepository` — upsertByCode() with incrementReportCount toggle, findByCode()
- `UpsertRlmFailureRequest` — form request with 15 validation rules extracted from controller
- `RlmException`, `RlmInvalidArgumentException`, `EmbeddingException`, `SearchUnavailableException` — domain exception hierarchy
- `EntityGeneratorContext` DTO — immutable shared state for generator classes
- `EntityGenerator` interface + `BaseGenerator` base class — generator pattern infrastructure
- `PolicyGenerator`, `EnumGenerator`, `BroadcastEventGenerator`, `StateMachineGenerator`, `MigrationGenerator` — first 5 extracted generators from MakeEntityCommand
- `ParsesCommaSeparatedDefinitions` trait — shared parsing logic for FieldParser and RelationshipParser
- `.claude/architecture/error-handling.md` — three-tier error handling convention documentation
- 94 new tests covering factories, EntityRegistry, API controllers, and service coverage gaps

### Changed
- `KnowledgeService` — reduced from 987 to 275 lines; now thin delegator to KnowledgeWriter, KnowledgeSearchEngine, RecallService
- `AiclServiceProvider` — registered 3 new singletons (KnowledgeSearchEngine, RecallService, KnowledgeWriter)
- `EmbeddingService` — added try/catch on embed()/embedBatch() with Log::warning on failure
- `DistillationService` — added DB::transaction(), tryFrom() on severity, Log::info at start/end
- `HubClient` — added Log::warning on all 4 ConnectionException catches
- `SocialAuthController` — reduced to thin delegation via SocialAuthService
- `RlmFailureController` — reduced to thin delegation via RlmFailureRepository + UpsertRlmFailureRequest
- `MakeEntityCommand` — uses extracted generator classes where available with fallback for remaining 10

### Fixed
- PHPStan: 188 errors reduced to 0 (40-entry justified baseline)
- PagerDutyAdapter: `$rendered['entity_type']` corrected to `$context['entity_type']`
- DomainEventViewer: inlined scope query logic (PHPStan type mismatch)
- RlmKnowledgeController: added method_exists() guards for optional methods
- PHPCPD: 303→168 duplicated lines (0.39%), all non-policy clones eliminated

### Stats
- **New files:** ~28
- **Modified files:** ~20
- **New tests:** 94 (TASK-005) + targeted verification across all tasks
- **PHPStan errors:** 188 → 0
- **Code duplication:** 303 → 168 lines (0.39%)
- **KnowledgeService:** 987 → 275 lines
- **MakeEntityCommand generators extracted:** 5 of 15 (pattern established)

---

## [1.16.0] - 2026-02-15

### Summary

Sprint O: Expanded Spec-Driven Generation — Enriches the entity spec format with structured sections for Widgets, Notifications, Observer Rules, and Report Layouts. Replaces natural language hints with parseable tables that generate complete, working code instead of TODO stubs. Also adds standalone spec types for AI Tools and RBAC Permissions. Reduces post-scaffold customization from ~20% to ~5%.

### Added

#### O.5 — Infrastructure
- `MarkdownTableParser` static utility — `parseMarkdownTable()`, `splitSections()`, `parseBulletList()` — shared by all section parsers
- `SpecValidation` shared trait — 9 validation helpers (`isPascalCase()`, `isSnakeCase()`, `isCamelCase()`, `isValidColumnType()`, `isReservedColumn()`, `isAutoColumn()`, `isKnownTrait()`, `isKnownOption()`, `isValidRelationshipType()`)
- `--cleanup` flag on `MakeEntityCommand` for post-generation file cleanup

#### O.1 — Structured Widget Specs
- `WidgetSpec`, `MetricDefinition`, `ColumnDefinition` value objects for parsed widget definitions
- `WidgetQueryParser` — deterministic mini-DSL parser converting query strings to Eloquent code (`count(*)`, `sum(amount)`, `where` clauses, `order by`, `limit`)
- `parseWidgets()` in `SpecFileParser` — parses structured `## Widgets` section with `### StatsOverview`, `### Chart`, `### Table` subsections
- `generateStructuredWidgets()` in `MakeEntityCommand` — generates complete widget code (real queries, colors, columns) from structured specs
- Widget validation in `ValidateSpecCommand` — validates metrics, chart groupBy, table columns, color names

#### O.2 — Structured Notification Specs
- `NotificationSpec` value object with trigger parsing (`on_create`, `on_update:field`, `on_state:TargetState`)
- `NotificationTemplateResolver` — resolves `{model.field}`, `{actor.field}`, `{old.field}`, `{new.field}` template variables; handles dynamic color expressions and recipient resolution (owner/field)
- `parseNotifications()` in `SpecFileParser` — parses structured `## Notifications` key-value tables
- `generateStructuredNotifications()` + `generateStructuredObserver()` in `MakeEntityCommand` — generates complete notification classes AND observer dispatch logic from specs
- Notification validation in `ValidateSpecCommand` — validates triggers, recipients, channels, colors, template variables

#### O.3 — Observer Rules
- `ObserverRuleSpec` value object with `isLog()`, `isNotify()`, `parseNotifyDetails()` action classification
- `parseObserverRules()` in `SpecFileParser` — parses `## Observer Rules` with `### On Create/Update/Delete` subsections
- Three-priority observer generation chain: Observer Rules → Notification Specs → Legacy stubs
- `buildObserverMethodBody()`, `resolveLogTemplate()`, `resolveRuleCondition()` helpers in `MakeEntityCommand`
- Observer rule validation in `ValidateSpecCommand` — validates events, actions, watch fields, notify details format

#### O.4 — Report Layout Spec
- `ReportLayoutSpec`, `ReportSectionSpec`, `ReportFieldSpec`, `ReportColumnSpec` value objects
- 5 section types for single reports: title, badges, info-grid, card, timeline
- 6 column formats for list reports: text, text:bold, date, currency, percent, badge
- `parseReportLayout()` in `SpecFileParser` — parses `## Report Layout` with `### Single Report` and `### List Report` subsections
- `generateStructuredPdfTemplates()` with per-section renderers in `MakeEntityCommand`
- Report layout validation in `ValidateSpecCommand` — validates section types, field references, column formats

#### O.6 — Standalone Spec Types
- `ToolSpec`, `ToolParameterSpec`, `ToolReturnFieldSpec` value objects with NeuronAI type mapping (`neuronAiType()`, `phpType()`)
- `ToolSpecParser` for `*.tool.md` — parses `## Tool`, `## Parameters`, `## Returns` sections
- `PermissionSpec`, `RoleSpec`, `CustomPermissionSpec` value objects with `*` wildcard expansion to 7 CRUD actions
- `PermissionSpecParser` for `*.permissions.md` — parses `## Roles`, `## Permissions` matrix, `## Custom Permissions`
- Note: Parsers only — generation commands deferred to a future sprint

### Changed
- `EntitySpec` — added `widgetSpecs`, `notificationSpecs`, `observerRules`, `reportLayout` properties with `has*()` convenience methods
- `MakeEntityCommand` — structured generation branches for widgets, notifications, observers, and PDF reports with full backward compatibility
- `ValidateSpecCommand` — validation for all four structured sections plus report layout

### Stats
- **New files:** 23 (value objects, parsers, test files)
- **Modified files:** 4 (SpecFileParser, EntitySpec, MakeEntityCommand, ValidateSpecCommand)
- **New tests:** 192 (41 + 49 + 39 + 20 + 21 + 22)
- **Total Console/Support tests:** 244 (up from 76 baseline), 742 assertions
- **Backward compatibility:** 100% — all legacy hint-based specs work unchanged

---

## [1.15.1] - 2026-02-15

### Summary

Sprint N Validation: Complete — 20 throwaway entities generated across 4 domain batches (Professional Services, HR, Inventory/Supply Chain, Support/Ticketing) to stress-test the scaffolder and evaluate whether Sprint N Hub Integration is justified. Verdict: MARGINAL. Hub Integration: SKIP. RLM L1 validation: KEEP as-is.

### Validation Results
- **20 entities** across 4 batches of 5, each torn down cleanly after data collection
- **569 total files** generated, **401 PHPUnit tests**, **111 Dusk browser tests**
- **Average RLM score: 98.6%** across all entities
- **14 advanced patterns** stress-tested: self-referential FKs, dual same-model FKs, triple FKs, composite FKs, 4-level FK chains, 8-state machines, dual enums, 15-field entities, currency/decimal, JSON structured data, rich text, PDF, Spatie ModelStates with getMorphClass(), duration/time fields
- Pipeline velocity: 0.6 fix iterations (STABLE)
- Zero novel failures, zero lessons created, zero prevention rules activated

### Decision
- **Sprint N Hub Integration: SKIP** — feedback loop solving a problem that barely exists
- **Sprint Q RLM Wiring: SKIP** — existed solely to unblock Sprint N
- **RLM L1-L3: KEEP** — pattern validation and trace recording are valuable as-is
- **Distillation layer: KEEP AS-IS** — works correctly, just has nothing to distill

### Removed
- Sprint N planning docs (`SPRINT-N-hub-integration.md`)
- Sprint M planning docs (`SPRINT-M-rlm-feedback-kpis.md`)
- Sprint Q planning docs (`SPRINT-Q-rlm-wiring.md`, `design-rlm-wiring-improvements.md`)
- All 20 entity spec files from `specs/`
- Pipeline validation project docs and exports

---

## [1.15.0] - 2026-02-15

### Summary

Sprint M: RLM Feedback Loop + Pipeline KPIs — Closes the RLM feedback loop so the knowledge system becomes self-improving. Lessons that prevent failures grow in confidence; lessons that get ignored decay and auto-retire. Three pipeline-level KPIs objectively measure whether the distillation system earns its keep, with a computed verdict (EARNING_ITS_KEEP / MARGINAL / OVERHEAD) that recommends its own deprecation if it can't prove value.

### Added

#### M.1 — Feedback Loop (Layer 4)
- `feedback` action on `aicl:rlm` command — accepts `--entity`, `--phase`, `--surfaced` (comma-separated DL codes), `--failures` (comma-separated BF codes). Compares surfaced lessons against actual failures.
- Prevention/ignore counting — if a surfaced lesson's source failure did NOT recur, `prevented_count++`; if it DID recur, `ignored_count++`. Uncovered failures flagged for new lesson creation.
- Confidence growth/decay — `confidence + (prevented × 0.02) - (ignored × 0.05)`, clamped to [0.0, 1.0]. Effective lessons rise in cheat sheet rankings.
- `RlmFailureDistillObserver` — Eloquent observer on `RlmFailure::created` that checks if new failures cluster with existing distilled lesson sources and dispatches `RedistillJob`.
- `RedistillJob` — Queued job that runs partial re-distillation via `DistillationService::distillCluster()` for affected failure clusters only.
- `DistillationService::recalculateConfidence()` — Confidence formula implementation with [0.0, 1.0] clamping.
- `DistillationService::distillCluster()` — Partial re-distillation for a specific set of failure codes, preserving existing confidence on upsert.
- Pipeline template updated — Phase 3 records cheat sheet delivery (DL codes), Phase 4/6 records feedback command + results.
- Agent command files updated — `/rlm` and `/tester` docs include mandatory feedback step after Phase 4/6 validation.

#### M.2 — Pipeline KPIs + Value Measurement (Layer 6)
- `KpiCalculator` service class with 5 public methods:
  - `fixIterationTrend()` — KPI 1: rolling average of last 5 vs last 20 GenerationTrace fix_iterations. Returns IMPROVING/STABLE/DECLINING/INSUFFICIENT_DATA.
  - `failureRatio()` — KPI 2: known vs novel failure counts over last 10 pipeline runs. Returns HEALTHY/MODERATE/HIGH_RECURRENCE/NO_FAILURES/INSUFFICIENT_DATA.
  - `lessonEffectiveness()` — KPI 3: per-lesson `prevented / (prevented + ignored)` with top 5 and bottom 5 performers.
  - `autoRetireLessons()` — Deactivates lessons with effectiveness < 30% after 5+ applications (`is_active = false`).
  - `computeVerdict()` — Evaluates all 3 KPIs against thresholds. Returns EARNING_ITS_KEEP/MARGINAL/OVERHEAD/INSUFFICIENT_DATA.
- `health` action on `aicl:rlm` command — displays all 3 KPIs with formatted output. `--verdict` flag adds system verdict with per-metric pass/fail indicators.
- Migration adding 4 KPI fields to `generation_traces`: `known_failure_count`, `novel_failure_count`, `surfaced_lesson_codes` (JSONB), `failure_codes_hit` (JSONB).
- `handleFeedback()` populates GenerationTrace KPI fields — classifies each actual failure as "known" (covered by active DistilledLesson) or "novel" (uncovered).

### Fixed
- `handleAar()` in RlmCommand was accessing `$report->failure_code` on `FailureReport` which doesn't have that column (uses `rlm_failure_id` FK). Fixed by eager-loading the `failure` relationship. (Found by PHPStan.)
- Cleaned up redundant `instanceof BackedEnum` checks throughout RlmCommand — Larastan already knows enum types from model casts.

### Tests
- 72 new tests, 184 assertions across 6 test files:
  - `FeedbackCalculationTest` (7) — prevention/ignore counting, confidence math
  - `FeedbackCommandTest` (8) — full feedback CLI integration
  - `RedistillJobTest` (12) — job structure, dispatch, observer behavior
  - `KpiCalculatorTest` (24) — all 3 KPIs, auto-retirement, verdict computation
  - `LessonAutoRetirementTest` (9) — boundary conditions, field preservation
  - `HealthCommandTest` (12) — health CLI output, verdict flag, data states

---

## [1.14.0] - 2026-02-14

### Summary

Sprint K: AI Tool Calling + Entity Spec Files — Two independent tracks that enhance AICL's AI capabilities and developer experience. Track K.1 adds an extensible function-calling framework to the AI Assistant (5 built-in tools, client-extensible). Track K.2 introduces Markdown-based entity spec files as the version-controlled source of truth for entity generation, eliminating manual CLI flag translation.

### Added

#### K.1 — AI Tool Calling Framework
- `AiTool` contract interface extending NeuronAI's `ToolInterface` — adds `category()` and `requiresAuth()` methods
- `BaseTool` abstract class extending NeuronAI `Tool` — authenticated user injection, AICL contract implementation
- `AiToolRegistry` singleton — `register()`, `registerMany()`, `resolve()` with per-user auth injection
- 5 built-in tools:
  - `WhosOnlineTool` — queries `PresenceRegistry` for online user sessions
  - `CurrentUserTool` — returns authenticated user info (id, name, email, roles)
  - `QueryEntityTool` — queries entities via `EntityRegistry` with `field:value` filters and policy checks
  - `EntityCountTool` — counts records with optional status/date grouping
  - `HealthStatusTool` — returns health check results from `HealthCheckRegistry`
- `AiToolCallEvent` broadcast event — `ShouldBroadcastNow` on `private-ai.stream.{streamId}`, event name `ai.tool_call`
- `AiStreamJob::buildAgent()` wires tools from registry when `aicl.ai.tools_enabled = true`
- Stream loop detects `ToolCallMessage` chunks and broadcasts tool invocation events via WebSocket
- Frontend Alpine.js handler for `ai.tool_call` event — pushes tool data into `messages[].tools[]` array
- Tool call pill chips in AI Assistant UI — wrench icon, humanized tool names, dark mode support
- Config keys: `aicl.ai.tools_enabled` (boolean), `aicl.ai.tools` (array of custom tool classes)

#### K.2 — Entity Spec Files
- `EntitySpec` value object (`Aicl\Console\Support\EntitySpec`) — structured representation of a parsed `.entity.md` file with fields, states, transitions, relationships, enums (rich case data), traits, options, business rules, widget/notification hints
- `SpecFileParser` (`Aicl\Console\Support\SpecFileParser`) — full Markdown parser supporting Fields table, Enum ### subsections (case/label/color/icon), States fenced blocks (→/-> arrow notation), Relationships table, Traits/Options bullet lists, Business Rules, Widget/Notification Hints
- `ValidateSpecCommand` (`aicl:validate-spec`) — pre-generation validation: reserved columns (ERROR), auto-columns (WARN), enum definition cross-check, state transition validation, relationship naming, trait/option recognition, searchable column verification
- `--from-spec` boolean flag + `--spec-path=` option on `MakeEntityCommand` — generate from spec file instead of CLI flags
- Rich enum generation from spec data — produces complete enum classes with label(), color(), icon() methods (no placeholder customization needed)
- `specs/Invoice.entity.md` — golden example spec file with all section types
- RLM patterns P-043 through P-046: `spec.file_exists`, `spec.matches_code`, `spec.has_business_rules`, `spec.has_description` (all severity=warning, additive)
- Pipeline template updated — Phase 1 references spec file + `aicl:validate-spec`, Phase 3 uses `--from-spec` scaffolder command

### Tests
- K.1: 55 tests (5 tool tests, registry test, integration stream test)
- K.2: 76 tests (parser unit tests, validate-spec command tests, make-entity --from-spec tests)
- Total: 131 new Sprint K tests, all passing

---

## [1.13.0] - 2026-02-14

### Summary

Sprint L: RLM Failure Distillation MVP — Transforms the RLM knowledge system from a write-only data store into agent-actionable intelligence. Failures are clustered, scored by impact, and distilled into agent-targeted lessons. The default `aicl:rlm recall` output drops from ~229 lines to a focused ~30-line cheat sheet with the top 5 lessons and When-Then rules per agent/phase.

### Added

#### L.1 — Clean (Data Quality)
- Rewrote `RlmLessonSeeder` with 15 curated real lessons covering scaffolder, Filament, testing, auth, Tailwind, and process topics
- Rewrote `PreventionRuleSeeder` with 15 curated rules linked to actual BF-* base failures with deterministic confidence scores
- Added `cleanup` action to `aicl:rlm` command — removes faker data (`F-\d+` failure codes, orphaned rules) from dev environments
- Added both seeders to `InstallCommand` chain (they were never registered before)

#### L.2 — Distill (Clustering Engine)
- New `DistilledLesson` model — UUID PK, lesson_code, title, guidance, target_agent, target_phase, trigger_context (JSONB), source_failure_codes (JSONB), impact_score, confidence, applied/prevented/ignored counts, soft deletes
- New `DistillationService` — deterministic failure clustering (pattern_id + category/root_cause similarity), impact scoring (`severity_weight × report_count × scaffolding_fixed_factor`), agent-perspective lesson generation for 6 agents (architect, tester, rlm, designer, solutions, pm)
- Deterministic lesson codes: `DL-{BF_NUM}-{AGENT_INITIAL}{PHASE}` (e.g., `DL-001-A3`)
- Added `distill` action to `aicl:rlm` command with `--dry-run`, `--agent`, `--stats` options
- `DistilledLesson` Scout-indexed in Elasticsearch (`aicl_distilled_lessons`) with embeddings
- `DistilledLessonSeeder` runs distillation on seeded base failures during install
- `IndexMappings` updated with 6th ES index definition

#### L.3 — Deliver (Focused Cheat Sheets)
- Rewrote `handleRecall()` in `RlmCommand` — defaults to ~30-line cheat sheet format
- Three recall formats: `cheatsheet` (default), `full` (legacy verbose), `json` (programmatic)
- Cheat sheet shows: TOP 5 lessons by impact_score, When-Then rules from trigger_context, recent validation outcomes
- Graceful fallback: if no distilled lessons exist yet, falls back to full recall format
- `--entity` option filters lessons by entity context (trigger_context JSONB overlap)
- `--limit` option controls number of lessons shown (default 5 for cheatsheet)

### Deleted
- `RlmFailureSeeder` — was never in the install chain, only produced faker data

### Changed
- `KnowledgeService` — added `distilled_lesson` type to search/index mappings
- `InstallCommand` — added RlmLessonSeeder, PreventionRuleSeeder, DistilledLessonSeeder to seeder chain

---

## [1.12.0] - 2026-02-14

### Summary

Sprint J: Developer Experience — Infrastructure and UI enhancements: nginx reverse-proxies all traffic through Swoole/Octane (eliminating the `:8443` port), ignibyte favicons, version badge with changelog viewer, markdown document browser, and a runtime navigation layout switcher (sidebar ↔ topbar).

### Added

#### J.1 — Nginx → Swoole Reverse Proxy
- Custom `.ddev/nginx_full/nginx-site.conf` — all dynamic requests proxy to Octane via `upstream octane { server 127.0.0.1:8000; }`
- Static assets (CSS, JS, images, fonts) served directly by nginx for performance; dynamic asset routes (e.g. `/livewire/livewire.js`) fall through to Swoole via `try_files $uri @octane`
- `map $host $forwarded_proto` detects `ddev.site` in Host header to set `X-Forwarded-Proto: https` and `X-Forwarded-Port: 443` (DDEV router terminates TLS)
- `OCTANE_HTTPS` env var NOT needed — trusted proxy middleware reads `X-Forwarded-Proto` header

#### J.2 — Dusk Tests Through Swoole
- `.env.dusk.local` updated `DB_DATABASE=db` to match running Swoole daemon's database
- 5/5 non-auth Dusk tests pass through nginx→Swoole path; 11 auth tests fail due to pre-existing MustTwoFactor Breezy bug (not proxy-related)

#### J.3 — Ignibyte Favicons
- Copied favicon set from `docs/assets/` to `public/` and `public/vendor/aicl/images/`
- `favicon-meta.blade.php` render hook (HEAD_END) adds apple-touch-icon, android-chrome, and sized favicon `<link>` tags
- `AdminPanelProvider` uses `->favicon(asset('vendor/aicl/images/favicon.png'))`

#### J.4 — Version Badge in Topbar
- `VersionService` — parses first `## [x.y.z]` from `CHANGELOG_FRAMEWORK.md`, caches result forever
- `version-badge.blade.php` — small badge rendered via `PanelsRenderHook::USER_MENU_BEFORE`, links to changelog page

#### J.5 — Changelog Viewer Page
- `Changelog` Filament page — renders `CHANGELOG_FRAMEWORK.md` as HTML via `Str::markdown()` with Tailwind prose styling
- Accessible from version badge click

#### J.6 — Markdown Document Browser
- `DocumentBrowser` Filament page — lists and renders `.md` files from configurable directories
- Config key: `aicl.docs.paths` — array of `['label' => ..., 'path' => ...]`
- Ships with `.claude/architecture/` as default path

#### J.7 — Navigation Layout Switcher
- Config key: `aicl.theme.navigation_layout` — `sidebar` (default), `topbar`, or `switchable`
- `switchable` mode: toggle button in topbar (FontAwesome icons via `owenvoke/blade-fontawesome`), Alpine.js `navigationSwitcher()` component, `localStorage` persistence
- Early-init `<script>` in `<head>` prevents flash-of-wrong-layout (FOWL)
- CSS overrides in `theme.css` — `[data-nav-mode]` selectors control sidebar/topbar visibility; JS does NOT toggle `fi-body-has-top-navigation` class

### Dependencies
- Added `owenvoke/blade-fontawesome` v2.9.1 — FontAwesome 6 Blade components

### Tests
- 58 new Sprint J tests (navigation switcher, favicon meta, version service, changelog page, document browser, page access)
- Full suite: 4197 pass / 22 pre-existing failures (MustTwoFactor/Breezy + ExampleTest)

---

## [1.11.0] - 2026-02-13

### Summary

Sprint I: AI WebSocket Streaming — Restores real-time AI assistant streaming that was removed in Sprint H (SSE worker exhaustion). The new architecture dispatches a queued job that broadcasts tokens via Reverb WebSocket, freeing Swoole workers immediately (~5ms hold time vs 10-60s with SSE). Uses native WebSocket API in Alpine.js — no Echo or Pusher dependencies.

### Added

#### I.1 — AiStreamJob (Queued LLM Streaming)
- `AiStreamJob` — Queued job that builds a NeuronAI agent, streams the response, and broadcasts each token via Reverb. Single try, 120s timeout, generic error messages to client
- Protected `buildAgent()` and `extractUsage()` for testability
- Usage logging per stream for cost tracking

#### I.2 — Broadcast Events (AI Stream Lifecycle)
- `AiStreamStarted` — Broadcast on stream begin (`ai.started`)
- `AiTokenEvent` — Broadcast per token with index (`ai.token`)
- `AiStreamCompleted` — Broadcast on finish with total tokens and usage (`ai.completed`)
- `AiStreamFailed` — Broadcast on error with sanitized message (`ai.failed`)
- All events use `ShouldBroadcastNow` (job is already queued), broadcast on `private-ai.stream.{streamId}`

#### I.3 — Controller + Channel Authorization
- `AiAssistantController::ask()` updated — dispatches `AiStreamJob`, returns `{stream_id, channel}` JSON
- Concurrent stream limit: max 2 per user via `Cache::increment()`, returns 429 if exceeded
- Cache-based channel authorization in `routes/channels.php` — stores user ID per stream, 5-minute TTL

#### I.4 — Alpine.js WebSocket Chat UI
- `aiChat()` Alpine component in `aicl-widgets.js` — minimal Pusher-protocol WebSocket client
- Native `WebSocket` API (no Echo/Pusher dependency) with full handshake: connect → auth → subscribe → listen
- Auto-scroll, loading indicator (dots until first token), error display, Enter-to-send
- Config-driven Reverb connection (host, port, scheme, app key server-rendered into Blade)

#### I.5 — Streaming Configuration
- `aicl.ai.streaming.queue` — Queue name for AI jobs (default: `default`)
- `aicl.ai.streaming.timeout` — Job timeout in seconds (default: 120)
- `aicl.ai.streaming.max_concurrent_per_user` — Concurrent stream limit (default: 2)
- `aicl.ai.streaming.reverb.*` — Reverb host/port/scheme for browser-side WebSocket connection

### Fixed
- **Redis type coercion in channel auth** — `Cache::get()` returns strings from Redis; added `(int)` casts on both sides of channel authorization comparison
- **Filament asset publishing** — `Js::make()` serves from `public/js/`, not source; must run `artisan filament:assets` after modifying source JS
- **Octane port mismatch with `url()`/`route()`** — `APP_URL` generates port-443 URLs but Octane runs on `:8443`; switched to relative paths for same-origin AJAX

### Test Coverage

45 new tests added across 7 test files. Total Sprint I coverage:

| Component | Tests | Assertions | Type |
|-----------|-------|------------|------|
| AiStreamJob | 13 | 34 | Unit |
| AiStreamStarted | 5 | 10 | Unit |
| AiTokenEvent | 6 | 10 | Unit |
| AiStreamCompleted | 5 | 10 | Unit |
| AiStreamFailed | 5 | 8 | Unit |
| AiAssistantController | 11 | 28 | Feature |
| **Total** | **45** | **100** | |

### Architecture

```
Browser ──POST /ai/ask──► Swoole Worker ──dispatch──► Queue Worker ──broadcast──► Reverb ──► Browser
         (~5ms hold)                                  (streams tokens)            (WebSocket)
```

- **Zero worker occupation** — Swoole worker returns immediately after dispatching job
- **Unlimited concurrent streams** — Limited only by queue workers, not Swoole worker count
- **No frontend dependencies** — Native WebSocket API speaks Pusher protocol directly to Reverb

---

## [1.10.0] - 2026-02-13

### Summary

Sprint H: Presence & Ops Panel Enhancements — Upgrades the presence system from a standalone "who's online" page into two actionable features: a Redis-backed PresenceRegistry with middleware tracking for the Ops Panel, and a WebSocket-powered toolbar widget showing who else is viewing the same page. Consolidates the OnlineUsers page into the Ops Panel as a single admin operations hub.

### Added

#### H.1 — PresenceRegistry Service + TrackPresenceMiddleware
- `PresenceRegistry` — Redis-backed service tracking active admin sessions with `touch()`, `allSessions()`, `sessionsForUser()`, `forget()`, `terminateSession()`, and `maskSessionId()` (first4...last4 display)
- `TrackPresenceMiddleware` — Captures user/URL/IP on admin panel requests, throttled to one Redis write per 30 seconds per session. TTL-based auto-cleanup
- `SessionTerminated` DomainEvent — Dispatched on force-logout for audit trail
- Middleware registered as `track-presence` alias and wired into Filament panel via `AiclPlugin::register()` authMiddleware

#### H.2 — Ops Panel Active Sessions Section
- Connected Sessions table in Ops Panel — shows user, masked session ID, current page, last seen time
- Online/idle status indicators with animated ping for active sessions
- Kill Session button with Filament confirmation modal (super_admin only, self-termination prevented)
- `wire:poll.5s` for near-real-time session refresh

#### H.3 — Toolbar Page Presence Widget
- `ToolbarPresence` Livewire component — compact topbar indicator showing who else is viewing the same page
- Per-page WebSocket presence channels (`presence-page.{md5_hash}`) with Alpine.js navigation handling
- Max 3 user badges with +N overflow, tooltips, and initials display
- Feature-gated on `config('aicl.features.websockets')`, registered via `PanelsRenderHook::GLOBAL_SEARCH_AFTER`
- Echo JS injected via `@vite` render hook (SCRIPTS_AFTER) for same-origin loading

### Removed

#### H.4 — OnlineUsers Page Consolidated
- `OnlineUsers` page and view deleted — functionality merged into Ops Panel (H.2) and toolbar widget (H.3)
- Tests referencing OnlineUsers updated/removed

### Changed
- `config('aicl.features.websockets')` default changed from `false` to `true`

### Test Coverage

8 new tests added (5 in AiclPluginTest, 3 in OpsPanelPageTest). Total Sprint H coverage: 8 test files, ~70 test methods.

| Component | Tests | Notes |
|-----------|-------|-------|
| PresenceRegistry | 20 | touch, allSessions, sessionsForUser, forget, terminateSession, maskSessionId, singleton |
| TrackPresenceMiddleware | 6 | Authenticated tracking, unauthenticated skip, throttle, passthrough, alias |
| OpsPanel Sessions | 8 | getActiveSessions, terminateSession auth/self-check, killSessionAction modal |
| ToolbarPresence | 7 | Component, alias, channel auth (admin/viewer/null) |
| Broadcasting Channels | 6 | Admin panel channel, page channel, HasPresenceChannel trait |
| AiclPlugin (Sprint H) | 5 | OpsPanel in pages, OnlineUsers removed, PresenceIndicator in widgets, middleware wiring |

---

## [1.9.0] - 2026-02-13

### Summary

Sprint G: Remaining Wiring & Gap Closure — Closes all remaining wiring gaps from Sprints A–E. Activates the external notification pipeline with seeded channels and a reference implementation, wires health check cache into OpsPanel, adds broadcast events to entity lifecycle domain events, adds DeclaresBaseSchema reference implementation on RlmPattern, mounts PresenceIndicator on OpsPanel, and updates the scaffolder to generate broadcast events extending BaseBroadcastEvent.

### Added

#### G.1 — External Notification Pipeline Activation
- `NotificationChannelSeeder` — Seeds 3 demo channels (Email active, Slack inactive, Webhook inactive) via `updateOrCreate` for idempotency. Wired into `aicl:install`
- `RlmFailureAssignedNotification` implements `HasExternalChannels` — Reference implementation for external dispatch. Returns all active channels

#### G.4 — DeclaresBaseSchema → Hub Model Example
- `RlmPattern` implements `DeclaresBaseSchema` — Full `baseSchema()` with column/trait/contract/cast/relationship mapping. Proves `--base=RlmPattern` works end-to-end with `BaseSchemaInspector`

#### G.5 — PresenceIndicator → OpsPanel
- OpsPanel footer widget with default channel `presence-admin-panel`. Channel auth restricted to admin/super_admin roles

#### G.7 — BaseBroadcastEvent → Scaffolder Convention
- `MakeEntityCommand` generates 3 broadcast events (Created/Updated with model, Deleted with scalar storage) extending `BaseBroadcastEvent`
- `RemoveEntityCommand` cleans up generated broadcast events

### Changed

#### G.2 — ServiceHealthCacheManager → OpsPanel + Health Checks
- `HealthCheckRegistry::runAllCached()` — Redis `Cache::remember` (30s TTL) with graceful fallback. `forceRefresh()` clears and repopulates
- OpsPanel `getServiceChecks()` now uses `runAllCached()` with "Force Refresh" button action

#### G.3 — BroadcastsDomainEvent → Entity Lifecycle Events
- `EntityCreated`, `EntityUpdated`, `EntityDeleted` now use `BroadcastsDomainEvent` trait for real-time UI updates via Reverb
- `EntityDeleted` overrides `broadcastOn`/`getEntityType`/`getEntityId` for deleted model safety (scalar storage)
- `DomainEvent::$entity` changed to public, `$eventId`/`$occurredAt` changed from readonly for broadcast serialization
- Pre-action events (`Creating`, `Updating`, `Deleting`) do NOT broadcast

### Removed

#### G.6 — SSE Widget (REMOVED — Sprint H)
- SSE infrastructure deleted due to Swoole worker exhaustion (each SSE connection holds an entire Swoole worker). `SseWidget`, `LiveLogWidget`, `SseStream`, `LogStream` — all deleted. Live features will use Reverb/WebSockets

### Test Coverage

57 new tests across Package and Framework suites. Package: 2500 pass + 26 pre-existing failures. Framework: 46 pass. Project: 6 pass + 1 pre-existing (ExampleTest).

| Component | Tests | Notes |
|-----------|-------|-------|
| External Notification Pipeline | 9 | Seeder, idempotency, templates, rate limits, HasExternalChannels |
| Health Cache (OpsPanel) | 6 | Cache, force refresh, graceful fallback |
| Broadcast Domain Events | 13 | Payload, channels, pre-action exclusion, scalar storage |
| DeclaresBaseSchema (RlmPattern) | 9 | Schema keys, columns, fillable, casts, relationships, inspector |
| PresenceIndicator (OpsPanel) | 3 | Default channel, columnSpan, channel auth |
| Scaffolder Broadcast Events | 4 | Events generated, extend base, deleted scalar, remove cleanup |

---

## [1.8.0] - 2026-02-13

### Summary

Sprint E: Swoole Cache Wiring — Activates SwooleCache (built in Sprint A) as L1 in-process cache across 5 hot data paths: permissions/roles, dashboard widget statistics, RLM knowledge stats, notification badges, and Elasticsearch availability. Total memory overhead ~10.4 MB shared across all workers. All paths degrade gracefully to Redis/PG when Swoole is unavailable.

### Added

#### E.1 — Permission & Role Cache
- `PermissionCacheManager` — Caches Spatie permission/role data in Swoole Tables. Gate `before()` interceptor checks SwooleCache before Redis/PG. 2000 rows, 5-minute TTL
- Warm-on-boot for recently active users (last 24h login). Full table flush on role/permission changes

#### E.2 — Dashboard Widget Statistics Cache
- `WidgetStatsCacheManager` — Caches aggregation queries for 10 widget stat groups. 60s TTL matches Livewire polling interval
- 10 widgets updated with `getOrCompute()` read-through pattern

#### E.3 — RLM Knowledge Stats Cache
- `KnowledgeStatsCacheManager` — Caches `KnowledgeService::stats()` aggregation (10+ COUNT queries across 6 tables). 5-minute TTL
- `KnowledgeService::stats()` modified with SwooleCache-first lookup

#### E.4 — Notification Badge Cache
- `NotificationBadgeCacheManager` — Caches unread notification counts per user. Lazy population, 60s TTL
- `NotificationCenter::getNavigationBadge()` reads from SwooleCache before DB query

#### E.5 — Elasticsearch Availability Cache
- `ServiceHealthCacheManager` — 3-tier cache (L0 per-instance → L1 SwooleCache → L2 HTTP). 30s TTL for ES health checks
- `KnowledgeService::isElasticsearchAvailable()` no longer fires HTTP health checks on every search

### Changed

- `AiclServiceProvider::boot()` — Registers all 5 SwooleCache tables with invalidation events and warm callbacks
- Architecture doc `.claude/architecture/swoole-foundations.md` updated to v2.0 with Cache Wiring Layer section

### Test Coverage

227 tests, 549 assertions across Sprint E components.

| Component | Tests | Assertions |
|-----------|-------|------------|
| Permission & Role Cache | 25 | 92 |
| Dashboard Widget Stats Cache | 33 | 120 |
| RLM Knowledge Stats Cache | 24 | 58 |
| Notification Badge Cache | 25 | 51 |
| ES Availability Cache | 17 | 32 |

### Swoole Table Budget

| Table | Rows | Value Size | Est. Memory | TTL |
|-------|------|-----------|-------------|-----|
| `permissions` | 2,000 | 5 KB | ~10 MB | 300s |
| `widget_stats` | 100 | 2 KB | ~200 KB | 60s |
| `rlm_stats` | 10 | 5 KB | ~50 KB | 300s |
| `notification_badges` | 1,000 | 100 B | ~100 KB | 60s |
| `service_health` | 10 | 200 B | ~2 KB | 30s |

---

## [1.7.0] - 2026-02-13

### Summary

Sprint D: Scaffolding & AI — Evolves the scaffolder with `--base=` flag for custom base class support, adds EntityRegistry for cross-entity discovery and queries, integrates NeuronAI as the AI/agent framework with embedding driver migration, and adds first-class LLM token streaming via SSE.

### Added

#### 4.1 — Scaffolding: `--base=` Flag
- `DeclaresBaseSchema` interface — Contract for models that declare their field schema for scaffolder inheritance
- `BaseSchemaInspector` — Introspects models implementing `DeclaresBaseSchema` and returns structured schema data
- `FieldDefinition::fromBaseSchema()` — Converts base schema definitions to field definitions for migration/form merging
- `MakeEntityCommand --base=` flag — Generated model extends specified base class, migration includes only delta columns, Filament resource includes "Inherited Fields" section

#### 4.2 — EntityRegistry Service
- `EntityRegistry` singleton — Auto-discovers entity classes from `app/Models/` using `HasEntityLifecycle` marker trait
- Methods: `allTypes()`, `search()`, `atLocation()`, `countsByStatus()`, `resolveType()`, `isEntity()`
- Redis tagged cache, cleared on `aicl:make-entity` and `aicl:remove-entity`
- Column-aware cross-entity queries (no raw UNION, graceful skip for missing columns)

#### 4.3 — NeuronAI Integration + AI Token Streaming
- `neuron-core/neuron-ai ^2.12` + `neuron-core/neuron-laravel ^0.4` added as package dependencies
- `NeuronAiEmbeddingAdapter` — Bridges NeuronAI `EmbeddingProvider` to AICL's `EmbeddingDriver` interface
- `EmbeddingService` refactored to delegate to NeuronAI when available (legacy `OpenAiDriver`/`OllamaDriver` deleted)
- `AiStream` — Extends `SseStream`, bridges NeuronAI streaming to SSE. Yields token/done/error events with usage extraction
- `HasAiContext` trait — Structured context for entities: type, label, attributes, relationships, meta. Defaults to fillable fields
- `AiclServiceProvider::configureNeuronAi()` — Maps AICL config to NeuronAI config keys (no new .env vars needed)

#### Architecture & Documentation
- `.claude/architecture/scaffolding-ai.md` — Architecture reference for all Sprint D deliverables
- `packages/aicl/docs/base-flag.md` — Base flag usage guide
- `packages/aicl/docs/entity-registry.md` — EntityRegistry usage guide

### Changed

- `MakeEntityCommand` — Accepts `--base=` flag with `DeclaresBaseSchema` introspection
- `RemoveEntityCommand` — `EntityRegistry::flush()` called on entity removal
- `AiclServiceProvider` — Registers NeuronAI configuration, `EntityRegistry` singleton

### Removed

- `packages/aicl/src/Rlm/Embeddings/OpenAiDriver.php` — Replaced by NeuronAI embedding provider
- `packages/aicl/src/Rlm/Embeddings/OllamaDriver.php` — Replaced by NeuronAI embedding provider

### Test Coverage

100 tests across Sprint D components. Existing RLM tests: zero regressions.

| Component | Tests | Notes |
|-----------|-------|-------|
| BaseSchemaInspector | 10 | Framework suite |
| EntityRegistry | 22 | Discovery, search, caching, invalidation |
| NeuronAI Embedding Adapter | 12 | Provider delegation, batch, dimensions |
| EmbeddingService (migrated) | 8 | NeuronAI delegation, null fallback |
| AiStream | 27 | Streaming, errors, context, usage |
| HasAiContext | 21 | Structured context, defaults, relationships |

---

## [1.6.0] - 2026-02-13

### Summary

Sprint F: Wiring & Integration — Connects Sprint A–D base classes into production use. Every item takes something that existed and was tested and wires it to a real feature, route, or scaffolder output. No new architecture — just plumbing. Includes the AI Assistant (LLM-powered chat page with SSE streaming), concurrent entity search, approval event persistence, scaffolder AI context flag, widget visibility pausing, and default Swoole timers.

### Added

#### F.3 — AI Agent Helper (Backend Endpoint + Filament Page)
- `AiProviderFactory` — Static factory resolving NeuronAI providers from config. Supports OpenAI, Anthropic, Ollama with `isConfigured()` check
- `AiAssistantController` — POST `/ai/ask` validates prompt, resolves optional entity context via `HasAiContext::toAiContext()`, caches payload in Redis with UUID token, returns SSE stream URL. Rate limited via configurable `ai_assistant` throttle
- `AiAssistantRequest` — Form request with admin role authorization and validation rules (prompt required, max length, entity_type/entity_id required_with)
- `AiAssistantStream` — SSE stream subclass. Reads cached payload by token (`Cache::pull` — single-use), resolves provider via `AiProviderFactory`, delegates to `AiStream` for NeuronAI streaming. Auth: `super_admin`/`admin`
- `AiAssistant` Filament page — `/admin/ai-assistant` with Alpine.js chat UI. Streaming token display, typing indicator, auto-scroll, error banner, auto-resizing textarea. Navigation: Tools group, Sparkles icon
- `ai-assistant.blade.php` — Alpine.js component with `fetch` POST + `EventSource` SSE pattern
- `aicl.php` config — Added `ai` section: provider selection (`openai`/`anthropic`/`ollama`), API keys, model names, system prompt, max prompt length, rate limit settings
- `AiclServiceProvider` — Registered `ai_assistant` rate limiter
- `AiclPlugin` — Registered `AiAssistant` page

#### F.4 — Approval Events → DomainEvent
- `ApprovalRequested`, `ApprovalGranted`, `ApprovalRejected`, `ApprovalRevoked` now extend `DomainEvent` — auto-persist to `domain_events` table with actor tracking, entity association, and typed payloads
- Events registered in `DomainEventRegistry` via `AiclServiceProvider`

#### F.7 — SwooleTimer Default Timers
- `RefreshHealthChecksJob` — Swoole timer job refreshing health check cache every 5 minutes
- `CleanStaleDeliveriesJob` — Swoole timer job cleaning stuck notification delivery logs every hour
- Both registered in `AiclServiceProvider::boot()` guarded by `SwooleTimer::isAvailable()` + `!runningUnitTests()`

### Changed

#### F.1 — Concurrent → EntityRegistry.search()
- `EntityRegistry::search()`, `atLocation()`, `countsByStatus()` now use `Concurrent::map()` for parallel cross-entity queries under Swoole with sequential fallback elsewhere

#### F.2 — HasAiContext → Scaffolder Flag
- `MakeEntityCommand` — Added `--ai-context` flag. Generated model includes `use HasAiContext` and overrides `aiContextFields()` with declared fields. Included in `--all`

#### F.5 — ChannelAuth → routes/channels.php
- `routes/channels.php` — Replaced inline user channel auth with `ChannelAuth::userChannel()` helper

#### F.6 — PausesWhenHidden Trait → Existing Widgets
- `PausesWhenHidden` trait — Injects Page Visibility API auto-pause into any Filament widget via Livewire `$this->js()` boot hook. Pauses polling when browser tab is hidden, catch-up poll on return
- Applied to all 17 polling widgets (stats, charts, tables)

### Fixed

- **SSE StreamedResponse under Octane/Swoole** — Removed `ob_end_flush()` loop from `SseStream::toResponse()` that destroyed Octane's output buffer. Swoole wraps `sendContent()` in `ob_start(fn, 1)` which forwards each echo to `$swooleResponse->write()` incrementally. `flush()` after echo is sufficient for both Octane and php-fpm

### Test Coverage

118+ new tests across 3 suites. Pre-existing failures unchanged.

| Component | Tests | Notes |
|-----------|-------|-------|
| AI Provider Factory | 10 | All providers, null returns, isConfigured |
| AI Assistant Stream | 8 | Auth, token handling, expired tokens, error events |
| AI Assistant Controller | 8 | Config check, stream URL, auth, validation, entity context |
| Admin Page Access (AI) | 5 | Role-based access (pre-existing Breezy failure on viewer) |
| EntityRegistry (Concurrent) | 22 | Existing tests pass with Concurrent::map() wiring |
| BaseSchema Flag (Scaffolder) | 10 | --ai-context on/off, --all includes, Framework suite |
| Approval Domain Events | 28 | Persistence, actor tracking, existing workflow tests |
| PausesWhenHidden | 7 | Trait behavior, polling intervals |
| SwooleTimer Jobs | 10 | RefreshHealthChecks (4), CleanStaleDeliveries (6) |

---

## [1.5.1] - 2026-02-13

### Summary

Sprint B Integration — Wires Sprint B infrastructure into three production features: a filterable Domain Event Viewer page (Who/What/When/Where), real-time log tailing via SSE on the existing LogViewer, and a presence-based Online Users dashboard showing connected admin panel users.

### Added

#### Domain Event Viewer
- `DomainEventViewer` Filament page — Page + HasTable backed by `DomainEventRecord::query()` with Who/What/When/Where columns
- Columns: occurred_at (since + tooltip), actor_type (badge), event_type (badge), entity_type + entity_id (class_basename), payload (JSON, hidden by default)
- Filters: actor_type (SelectFilter), actor_id (user lookup), entity_type, event_type (TextInput with wildcard via `ofType()` scope), date_range (two DatePickers)
- Navigation: System group, sort 13, slug `domain-events`, icon `Heroicon::OutlinedBolt`. Access: `super_admin` only
- `resources/views/filament/pages/domain-event-viewer.blade.php`

#### LogStream SSE
- `LogStream` SSE endpoint — Extends `SseStream` for real-time log file tailing. File-position tracking via `ftell`/`fseek`, multi-line buffering (64KB max) for stack traces, server-side level + search filtering
- Event types: `log.entry`, `log.error`. Heartbeat: 5s. Auth: `super_admin`/`admin` role
- `Last-Event-ID` resume support via byte offsets. Path validation: `.log` extension within `storage/logs` only
- `Route::sse('logs/stream', LogStream::class)` registered in `routes/web.php` with `web` middleware

#### Online Users Dashboard
- `OnlineUsers` Filament page — Feature-flagged on `aicl.features.websockets`. Shows "WebSockets Disabled" empty state when flag is off
- Alpine.js `Echo.join('presence-admin-panel')` with live user table: name (green dot), email, current page, IP address, connected duration
- Navigation: System group, sort 11, slug `online-users`, icon `Heroicon::OutlinedUsers`. Access: `super_admin`/`admin`
- `presence-admin-panel` broadcast channel in `routes/channels.php` — Returns `{id, name, email, current_url, ip_address, joined_at}`

### Changed

- `LogViewer` — Added `liveMode` property, `getSseUrl()` method, Live Stream toggle (disables auto-refresh when active), `getPollingInterval()` returns null in live mode
- `log-viewer.blade.php` — Full rewrite with Alpine.js EventSource for live mode, green pulsing LIVE indicator, max 200 entries in memory
- `AiclPlugin` — Registered `DomainEventViewer` and `OnlineUsers` pages
- `routes/channels.php` — Added `presence-admin-panel` presence channel

### Test Coverage

45 new tests, 55+ assertions. Package suite total: 2497 tests passing (pre-existing failures unchanged).

| Component | Tests | Assertions |
|-----------|-------|------------|
| Domain Event Viewer | 18 | ~26 |
| LogStream SSE | 15 | ~19 |
| Online Users + Presence | 17 | ~23 |

---

## [1.5.0] - 2026-02-13

### Summary

Sprint C: Notification & Observability — Production-grade notification delivery with 6 channel drivers, delivery tracking with retry/rate limiting, a safe template rendering engine, and a live ops panel with pluggable health checks. All components are fully wired as singletons in AiclServiceProvider — external channel delivery activates when notifications implement `HasExternalChannels` or custom resolvers are configured. The ops panel is a ready-to-use Filament page gated behind admin RBAC.

### Added

#### 3.1 — Notification Channel Drivers + Delivery Tracking
- `NotificationChannel` model — Stores channel config (encrypted JSONB), type, slug, rate limit settings, message templates. `ChannelType` enum: Email, Slack, Teams, PagerDuty, Webhook, SMS
- `NotificationDeliveryLog` model — Per-channel delivery tracking with status lifecycle (Pending → Sent → Delivered → Failed → RateLimited). Attempt counting, payload/response JSONB, retry timestamps
- `DeliveryStatus` enum — Pending, Sent, Delivered, Failed, RateLimited with color/icon/label
- `DriverRegistry` singleton — Central registry for channel drivers. Ships with 6 built-in drivers, extensible via `register()`
- `ChannelRateLimiter` — Per-channel rate limiting using Redis. Queues excess rather than dropping. Configurable `{max, period}` per channel
- `RetryNotificationDelivery` queued job — Exponential backoff with jitter (1s, 2s, 4s, 8s, 16s), max 5 attempts. Cascade-safe (handles deleted channels gracefully)
- **6 Built-in Drivers:** `EmailDriver` (Laravel Mail), `SlackDriver` (webhook), `TeamsDriver` (webhook connector), `PagerDutyDriver` (Events API v2), `WebhookDriver` (configurable), `SmsDriver` (Twilio-compatible)
- `DriverResult` value object — Uniform driver response with success/failure, response data, error message
- `NotificationChannelDriver` interface — `send()` and `validateConfig()` contracts for custom drivers
- `HasExternalChannels` contract — Notifications declare which external channels they target
- `NotificationChannelResolver` contract — Injectable resolver for dynamic channel routing
- `NotificationRecipientResolver` contract — Injectable resolver for dynamic recipient resolution
- `NotificationSending` event — Fired before dispatch, cancellable
- `NotificationDispatched` event — Fired after dispatch with log reference
- `create_notification_channels_table` migration — Encrypted config, rate limit, message templates JSONB
- `create_notification_delivery_logs_table` migration — Cascade delete on channel removal

#### 3.2 — Message Template Rendering Engine
- `MessageTemplateRenderer` — Safe string interpolation engine using `{{ variable | filter }}` Mustache-style syntax. No PHP eval, no code execution. Constructor-injected HTML escaping for XSS prevention
- **11 Built-in Filters:** `upper`, `lower`, `capitalize`, `truncate`, `date`, `relative`, `number`, `currency`, `default`, `join`, `json`
- **5 Built-in Resolvers:** `ModelVariableResolver`, `UserVariableResolver`, `AppVariableResolver`, `DateVariableResolver`, `ConfigVariableResolver` — each with a prefix (`model.`, `user.`, `app.`, `date.`, `config.`)
- **6 Format Adapters:** `PlainTextAdapter`, `SlackBlockAdapter`, `EmailHtmlAdapter`, `TeamsCardAdapter`, `PagerDutyAdapter`, `WebhookJsonAdapter` — same template data, different output format per channel type
- `FilterRegistry` singleton — Extensible filter registration with `register()` and `registerMany()`
- `FormatAdapterRegistry` singleton — Maps `ChannelType` enum to format adapter classes
- `TemplateVariableResolver` contract — Interface for custom variable resolvers with prefix
- `FormatAdapter` contract — Interface for per-channel output formatting
- `TemplateFilter` contract — Interface for custom template filters
- Templates stored in `notification_channels.message_templates` JSONB — per channel, per notification type
- `add_message_templates_to_notification_channels_table` migration

#### 3.5 — Live Ops Panel
- `OpsPanel` Filament page — Service health dashboard with Livewire polling (30s). RBAC-gated: admin/super_admin only
- `HealthCheckRegistry` singleton — Pluggable health check registration. Ships with 6 checks, extensible via `register()`/`registerMany()`
- `ServiceHealthCheck` interface — `name()`, `check()` contract for custom health checks
- `ServiceStatus` enum — Healthy, Degraded, Down with color coding
- `ServiceCheckResult` value object — Status, details array, optional error message
- **6 Built-in Health Checks:** `SwooleHealthCheck` (workers, connections, memory, coroutines), `PostgresHealthCheck` (status, version, connections, DB size), `RedisHealthCheck` (status, version, memory, key count), `ElasticsearchHealthCheck` (cluster health, nodes, indices, docs), `QueueHealthCheck` (Redis LLEN per queue), `ApplicationHealthCheck` (PHP/Laravel/Octane versions, driver config)
- `resources/views/filament/pages/ops-panel.blade.php` — Grid layout with status indicators, detail key-value pairs, error display

#### Architecture & Documentation
- `.claude/architecture/notification-observability.md` — Architecture reference for all Sprint C deliverables
- `packages/aicl/docs/notification-drivers.md` — Channel driver usage guide and custom driver authoring
- `packages/aicl/docs/message-templates.md` — Template syntax, filters, resolvers, format adapters
- `packages/aicl/docs/ops-panel.md` — Ops panel usage guide with custom health check examples

### Changed

- `NotificationDispatcher` — Enhanced to resolve external channels via `HasExternalChannels` or injectable `NotificationChannelResolver`. Dispatches to external channels with template rendering, rate limiting, and retry via `RetryNotificationDelivery` job
- `AiclServiceProvider` — Registered 9 new singletons (DriverRegistry, ChannelRateLimiter, NotificationDispatcher, HealthCheckRegistry, FilterRegistry, FormatAdapterRegistry, MessageTemplateRenderer) plus 6 drivers and 6 health checks
- `AiclPlugin` — Added `OpsPanel` to registered pages
- `aicl.php` config — Added `notifications` section (default_channels, resolvers, retry, queue, templates) and `health` section (queues, failed_jobs_threshold)

### Test Coverage

613 tests passing, 1 skipped (runtime-dependent Swoole/ES check). Package suite total: 1536+ tests passing (pre-existing failures unchanged).

| Component | Tests | Assertions |
|-----------|-------|------------|
| Channel Drivers + Delivery | ~280 | ~560 |
| Message Templates | ~180 | ~360 |
| Live Ops Panel | ~153 | ~306 |

---

## [1.4.0] - 2026-02-13

### Summary

Sprint B: Event & Real-Time Layer — Domain event bus with persistent audit trail, SSE streaming as a first-class response type, and base classes for real-time UI (broadcasting, polling, channel auth, presence). All components are infrastructure — base classes, traits, and helpers ready for consumption by future features and entity generation.

### Added

#### Domain Event Bus
- `DomainEvent` abstract base class — UUID eventId, Carbon occurredAt, auto-resolved actor (User/System/Agent/Automation), entity association, payload/metadata, replay flag
- `DomainEventSubscriber` — Wildcard listener auto-persists all `DomainEvent` subclasses to `domain_events` table (append-only)
- `DomainEventRecord` model — Query scopes: `forEntity()`, `ofType()` (with `*` wildcard), `since()`, `between()`, `byActor()`, `timeline()`. Plus `prune()` and `replay()` with constructor-bypass reconstruction
- `DomainEventRegistry` — Event type → class mapping for replay reconstruction
- `ActorType` enum — User, System, Agent, Automation with auto-resolution from auth context
- `BroadcastsDomainEvent` trait — Opt-in broadcasting for DomainEvent subclasses implementing ShouldBroadcast
- `UnresolvableEventException` — Thrown when replay cannot reconstruct an unregistered event type
- `create_domain_events_table` migration — UUID PK, event_type, actor_type/id, entity morphs, JSONB payload/metadata, 5 indexes

#### SSE Streaming
- `SseEvent` value object — Wire protocol formatting via `__toString()`. Handles multiline data, JSON encoding, comments. Named constructors: `make()`, `comment()`
- `SseStream` abstract base class — Generator-based streaming (`stream()` yields `SseEvent`). Heartbeat management (default 15s), `connection_aborted()` monitoring, `authorize()` hook. Response headers: `text/event-stream`, `no-cache`, `keep-alive`, `X-Accel-Buffering: no`
- `Route::sse()` macro — Registers GET route with container resolution, authorization, auto-generated route name
- `SseWidget` abstract Filament widget — Alpine.js `EventSource` wiring with named event handlers, connection state tracking, auto-reconnect, Livewire navigation cleanup

#### Real-Time UI Base Classes
- `BaseBroadcastEvent` abstract class — Standard broadcast event with guaranteed envelope (eventId, eventType, occurredAt). Consistent `broadcastOn()` (dashboard + entity channels), `broadcastAs()`, `broadcastWith()` merging payload with metadata
- `PollingWidget` abstract Filament widget — Configurable polling interval with auto-pause via Page Visibility API when browser tab is hidden. Alpine.js `setInterval` + `$wire.poll()` instead of Livewire's `wire:poll`
- `ChannelAuth` static helpers — `entityChannel()` (entity exists + ViewAny permission), `userChannel()` (key comparison with string coercion), `presenceChannel()` (authorization + user data return)
- `HasPresenceChannel` model trait — `presenceChannelName()` and `presencePermission()` for "who's viewing" presence channels
- `PresenceIndicator` Filament widget — Echo presence channel viewer display with join/leaving callbacks, gracefully inert when Echo not configured

#### Blade Views
- `resources/views/widgets/sse-widget.blade.php` — Alpine.js EventSource component
- `resources/views/widgets/polling-widget.blade.php` — Alpine.js visibility-aware polling component
- `resources/views/widgets/presence-indicator.blade.php` — Echo presence channel viewer badges

#### Architecture & Documentation
- `.claude/architecture/event-realtime-layer.md` — Architecture reference for all Sprint B deliverables
- `packages/aicl/docs/domain-event-bus.md` — Domain event bus usage guide
- `packages/aicl/docs/sse-streaming.md` — SSE streaming usage guide with transport decision matrix
- `packages/aicl/docs/realtime-ui.md` — Broadcasting, polling, channel auth, and presence guide

### Changed

- `AiclServiceProvider` — Added `DomainEventSubscriber` registration (wildcard listener), `Route::sse()` macro, imports for `SseStream`, `Request`, `Route`, `Str`

### Test Coverage

160 new tests, 319 assertions. Package suite total: 1496+ tests passing (pre-existing failures unchanged).

| Component | Tests | Assertions |
|-----------|-------|------------|
| Domain Event Bus | 57 | 111 |
| SSE Streaming | 72 | 156 |
| Real-Time UI | 31 | 52 |

---

## [1.3.0] - 2026-02-12

### Summary

Sprint A: Swoole Foundations — Low-level Swoole primitives and workflow engine for Laravel Octane. Adds coroutine concurrency helpers, shared-memory hot caches, managed timers with Redis persistence, and a trait-based approval workflow engine. All components degrade gracefully outside Swoole.

### Added

#### Swoole Primitives
- `Concurrent` — Parallel execution via Swoole coroutines with WaitGroup/Channel synchronization. Methods: `run()` (named parallel), `map()` (fan-out with concurrency limit), `race()` (first-to-finish). Sequential fallback when Swoole unavailable.
- `ConcurrentException` — Aggregate exception with partial results (`getResults()`, `getExceptions()`, `hasResult()`, `hasException()`)
- `ConcurrentTimeoutException` — Timeout variant with static `after()` factory
- `SwooleCache` — Cross-worker shared memory cache built on Octane's Swoole Table lifecycle. Lazy TTL expiration, event-driven invalidation, JSON serialization, warm-on-boot callbacks. Silent no-op fallback.
- `SwooleTimer` — Managed Swoole timers with Redis persistence at `aicl:timers:{key}`. Named keys, job-only dispatch, worker 0 coordination. Methods: `every()`, `after()`, `cancel()`, `list()`, `exists()`, `restore()`.
- `WarmSwooleCaches` listener — Warms registered cache tables on `WorkerStarting` event
- `RestoreSwooleTimers` listener — Restores persisted timers from Redis on worker 0 boot

#### Approval Workflow Engine
- `RequiresApproval` trait — Trait-based approval workflow for any Eloquent model. Methods: `requestApproval()`, `approve()`, `reject()`, `revokeApproval()`, status checks, scopes
- `Approvable` contract — Interface for models using the approval workflow
- `ApprovalStatus` enum — Draft, Pending, Approved, Rejected with color/icon/label
- `ApprovalLog` model — Polymorphic audit trail (actor, action, from/to status, comment)
- `ApprovalException` — State transition validation errors
- `ApprovalRequested`, `ApprovalGranted`, `ApprovalRejected`, `ApprovalRevoked` events
- `ApprovalRequestedNotification`, `ApprovalDecisionNotification` — BaseNotification-based notifications
- `create_approval_logs_table` migration — Polymorphic approval audit trail

#### Architecture & Documentation
- `.claude/architecture/swoole-foundations.md` — Architecture reference for all Sprint A deliverables
- `packages/aicl/docs/swoole-concurrent.md` — Concurrent usage guide
- `packages/aicl/docs/swoole-cache.md` — SwooleCache usage guide
- `packages/aicl/docs/swoole-timer.md` — SwooleTimer usage guide
- `packages/aicl/docs/approval-workflow.md` — Approval workflow guide

### Test Coverage

1536 tests passing, 18 pre-existing failures (0 regressions). 118 new tests, 246 assertions.

---

## [1.2.0] - 2026-02-12

### Summary

RLM Knowledge System Consolidation — Replaced the three-layer storage architecture (markdown files + SQLite FTS5 + PostgreSQL hub entities) with a streamlined two-layer system: PostgreSQL (source of truth) + Elasticsearch (hybrid kNN + BM25 search with OpenAI embeddings). Eliminated ~1,750 lines of SQLite code, added KnowledgeService as the central query interface, EmbeddingService with pluggable drivers, and vector search across all hub models.

### Added

#### Knowledge Service Layer
- `KnowledgeService` — Central query interface replacing SQLite KnowledgeBase. Methods: `search()` (ES hybrid), `recall()` (risk briefings), `learn()`, `failures()`, `scores()`, `stats()`
- `EmbeddingService` — Pluggable driver system with auto-selection logic per config
- `EmbeddingDriver` contract — Interface for embedding providers (embed, embedBatch, dimension)
- `OpenAiDriver` — OpenAI text-embedding-3-small (1536 dimensions) via Laravel Http
- `OllamaDriver` — Local Ollama nomic-embed-text (768→1536 zero-padded)
- `NullDriver` — Graceful no-op for environments without embedding API access
- `GenerateEmbeddingJob` — Queued job: generates embedding, caches on model, re-indexes in ES
- `HasEmbeddings` trait — Shared embedding storage/retrieval logic for Eloquent models
- `IndexMappings` — ES index definitions for 5 models with dense_vector fields (1536 dims, cosine, int8_hnsw)

#### New Models
- `RlmScore` — Score tracking with entity, phase, structural/semantic breakdowns, UUID PKs
- `GoldenAnnotation` — 209 annotated patterns from golden example files, category enum, searchable
- `KnowledgeLink` — Cross-entity relationship graph (relates patterns, failures, lessons, rules)
- `RlmSemanticCache` — PostgreSQL-backed semantic validation cache (replaces SQLite cache)
- `RlmScoreFactory`, `GoldenAnnotationFactory` — Test factories for new models

#### Enums
- `ScoreType` — Score categorization (Structural, Semantic, Combined)
- `AnnotationCategory` — Golden annotation categories (Model, Migration, Resource, etc.)
- `KnowledgeLinkRelationship` — Link types (Prevents, Teaches, Requires, etc.)

#### Seeders
- `PatternRegistrySeeder` — Seeds 42 patterns from PatternRegistry into RlmPattern table
- `BaseFailureSeeder` — Seeds 15+ common failures into RlmFailure table
- `GoldenAnnotationSeeder` — Seeds 209 annotations extracted from golden example files

#### Infrastructure
- `matchish/laravel-scout-elasticsearch` v7.12 — Scout driver for Elasticsearch with ES 8.x support
- `RlmKnowledgeController` — API endpoint for knowledge search/recall
- `RlmScoreController` — CRUD API for scores with form requests and resource
- `RlmScorePolicy` — Role-based access control for scores
- `GoldenAnnotationObserver` — Activity logging for annotation changes
- ES index management commands: `aicl:rlm index`, `aicl:rlm embed --backfill`
- `aicl:install` now seeds PatternRegistry, BaseFailures, and GoldenAnnotations

#### Migrations
- `create_rlm_scores_table` — UUID PK, entity/phase/score columns, indexes
- `create_rlm_semantic_cache_table` — PG-backed cache replacing SQLite
- `create_golden_annotations_table` — Annotation storage with FTS support
- `create_knowledge_links_table` — Cross-entity relationship graph
- `alter_activity_log_uuid_columns` — subject_id/causer_id bigint → string for UUID PKs

### Changed

- `RlmCommand` — Rewritten: SQLite actions replaced with KnowledgeService/Eloquent equivalents. Added `index`, `embed`, `aar` subcommands. ~1,039 line diff
- `HubClient` — `push()` and `pull()` now use Eloquent queries instead of SQLite KnowledgeBase
- `SemanticCache` — Backed by `RlmSemanticCache` Eloquent model instead of SQLite table
- `PatternDiscovery` — Uses Eloquent `GenerationTrace`/`RlmFailure` queries instead of SQLite
- `DiscoverPatternsCommand` — Updated for Eloquent-backed PatternDiscovery
- `ScoutImportCommand` — Fixed argument name for matchish compatibility (`searchable` not `model`)
- `AiclServiceProvider` — Registers `EmbeddingService`, `KnowledgeService` singletons; explicit `ElasticSearchServiceProvider` registration for deferred binding
- `InstallCommand` — Added PatternRegistrySeeder, BaseFailureSeeder, GoldenAnnotationSeeder execution
- Hub model observers (RlmPattern, RlmFailure, RlmLesson, PreventionRule) — Dispatch `GenerateEmbeddingJob` on create/update
- Hub models (RlmPattern, RlmFailure, RlmLesson, PreventionRule) — Added `HasEmbeddings` trait, `toSearchableArray()` includes embedding vector, `shouldBeSearchable()` respects feature flags
- Hub API routes — Added knowledge search and score endpoints
- All 17 agent commands — Updated references from SQLite to KnowledgeService
- `aicl.php` config — Added `rlm.embeddings`, `rlm.semantic`, `search.elasticsearch` sections

### Removed

- `KnowledgeBase.php` — SQLite FTS5 storage layer (~1,468 lines)
- `MarkdownParser.php` — Markdown-to-SQLite parser (~286 lines)
- `KnowledgeBaseTest.php`, `KnowledgeBaseMergeTest.php`, `KnowledgeBaseSyncTest.php`, `MarkdownParserTest.php` — ~1,233 lines of SQLite tests
- `ValidateCommandSemanticTest.php` — Replaced by updated RlmCommandTest
- `RlmCommandSyncTest.php`, `RlmCommandSyncPullTest.php` — Sync tests rewritten
- `failures.md`, `base-failures.md` — Markdown files replaced by database seeders

### Test Coverage

1418 tests passing, 18 pre-existing failures (Breezy MFA middleware — unchanged)

---

## [1.1.0] - 2026-02-11

### Added

#### Phase 3A — Hub Entities (6 Eloquent models)
- `RlmPattern` — Pattern definitions with structural regex, severity, weight, category
- `RlmFailure` — Failure tracking with 6-state machine (Reported → Investigating → Confirmed → Resolved/WontFix/Deprecated), report counting, project counting, promotion pipeline
- `FailureReport` — Per-project failure occurrence reports with 4-state machine, project hash anonymization
- `RlmLesson` — Lessons learned with topic categorization, tags, entity context
- `GenerationTrace` — Full generation audit trail with structural/semantic scores, duration, phase data
- `PreventionRule` — Contextual prevention rules linked to failures and entities
- Full CRUD API controllers, form requests, and Eloquent API resources for all 6 entities
- Filament admin resources with tables, forms, view/edit/create pages for all 6 entities
- Stats overview widgets, deadline widgets, and charts per entity (16 new widgets)
- CSV exporters for all 6 entities
- Factories and seeders for all 6 entities
- Observers for all 6 entities (activity logging, counter maintenance)
- Policies for all 6 entities (role-based with admin/super_admin gates)
- Hub API routes (`hub-api.php`) with versioned endpoints

#### Phase 3B — Cross-Entity Intelligence Layer
- `ProjectIdentity` service — SHA-256 project hashing and data anonymization for hub sync
- Hub-specific API endpoints: failure upsert, top-reported failures, FTS lesson search, contextual prevention rules
- `aicl:rlm sync --push` — Push local SQLite data to hub PostgreSQL via `HubClient` with anonymization
- `aicl:rlm sync --pull` — Pull patterns, base failures, and prevention rules from hub with merge logic
- Graceful offline mode — `sync_queue` table for queuing failed requests with automatic drain on next push
- Promotion pipeline — Auto-detect failures meeting promotion criteria (3+ reports, 2+ projects), dispatches `CheckPromotionCandidatesJob`
- Regression detection — Alerts when a previously-fixed (`scaffolding_fixed`) failure reappears, sends `FailureRegressionNotification`
- Elasticsearch indexing — Scout/HasSearchableFields integration on RlmFailure, RlmLesson, RlmPattern with `hub_search` feature flag
- RLM Dashboard — `RlmDashboard` Filament page with 4 cross-entity analytics widgets (FailureTrendChart, CategoryBreakdownChart, PromotionQueueWidget, ProjectHealthWidget)
- `aicl:hub-seed` command — Seeds hub entities from PatternRegistry + base-failures SQL (idempotent, `--force` option)
- `hub-env.stub` — `.env` template for hub deployment
- `hub_search` and `hub_admin` feature flags in `aicl.config`

#### Infrastructure
- `ScoutImportCommand` extended to discover models in `packages/aicl/src/Models/`
- Enums: `FailureCategory`, `FailureSeverity`, `ResolutionMethod`
- State machines: `RlmFailureState` (6 states), FailureReport (4 states)
- `HubClient` — HTTP client with pool support, retry logic, token auth
- `CheckPromotionCandidatesJob` — Queued job for promotion detection
- 3 new notifications: `FailurePromotionCandidateNotification`, `FailureRegressionNotification`, `FailureReportAssignedNotification`

### Changed

- Consolidated Breezy sessions migration (removed separate alter migration)
- Consolidated activity log migrations (removed separate column-add migrations)
- Replaced Meilisearch with Elasticsearch 8.17.0 (DDEV addon)
- Updated all 17 agent commands with L2.5B dynamic context filtering support
- Moved package tests from `tests/Unit/` and `tests/Feature/` to `packages/aicl/tests/` (three-suite model)
- Test suite: 1510 pass (up from 1353), 25 pre-existing failures (unchanged)

---

## [1.0.6] - 2026-02-10

### Added

- `aicl:upgrade` command — manifest-driven project file synchronization (dry-run by default, `--force` to apply, `--section` for targeted upgrades, `--diff` for inline diffs, `--fresh` to ignore state)
- `packages/aicl/stubs/` directory — single source of truth for all managed project-level files (agent prompts, RLM patterns, pipeline templates, test infrastructure, CLAUDE.md, Dusk env)
- `packages/aicl/config/upgrade-manifest.php` — declares 7 sections with 47 entries using 3 strategies: `overwrite`, `ensure_absent`, `ensure_present`
- `.aicl-state.json` state file tracking — records package version, file checksums, and removed files for modification detection
- `aicl:install` now writes initial `.aicl-state.json` after successful install

### Changed

- `build-skeleton.sh` refactored to copy agent prompts and Dusk env from `stubs/` instead of inline heredocs and pipeline variant files — stubs are now the single source of truth for both skeleton builds and upgrades

---

## [1.0.5] - 2026-02-10

### Fixed

- Pipeline PM now delegates to specialized agents (`/solutions`, `/architect`, `/designer`, `/tester`, `/rlm`, `/docs`) matching framework PM architecture — was incorrectly doing all phases inline as single-agent
- Fixed 5 broken references to framework-internal files in pipeline agents (`designer-pipeline.md`, `generate-pipeline.md`, `pm-pipeline.md`) — these files don't ship with the skeleton
- Added Package Boundary (NON-NEGOTIABLE) section to `remove-entity.md`
- Skeleton now ships all 14 agent commands (was missing `architect`, `solutions`, `tester`, `docs` in some builds)

---

## [1.0.4] - 2026-02-09

### Fixed

- Fixed Reverb WebSocket broadcasting SSL timeout on DDEV projects — server-side PHP broadcaster was connecting via HTTPS to localhost:8080 (which only serves plain HTTP inside the container), causing `cURL error 28: SSL connection timeout`
- Split `REVERB_*` (server-side, plain HTTP, localhost) from `VITE_REVERB_*` (browser-side, HTTPS, DDEV hostname) in skeleton `.env.example` — these have fundamentally different connection requirements in DDEV environments

---

## [1.0.3] - 2026-02-09

### Fixed

- Added missing `shuvroroy/filament-spatie-laravel-backup` and `tomatophp/filament-media-manager` to package `composer.json` — skeleton `AdminPanelProvider` references both plugins but they were only in the dev repo's root dependencies, causing class-not-found on fresh `create-project`

---

## [1.0.2] - 2026-02-09

### Fixed

- Fixed `$pollingInterval` declared as `static` in 3 widget templates (StatsOverview, Chart, Table) — Filament v4 `CanPoll::$pollingInterval` is non-static
- Fixed `RecentFailedJobsWidget` heading property conflict — removed `$heading` property, use `->heading()` method on Table instead (TableWidget::$heading is static)
- Fixed `ScoutImportCommand::handle()` — `$this->components->task()` returns void; refactored to use reference variable for failure tracking
- Fixed escaped quote syntax (`\\"`) in 5 generated notification files causing PHPStan parse errors
- Regenerated PHPStan baseline: 63 errors → 0 (36 accepted vendor/interface edge cases baselined)

### Added

- 45 new tests (62 assertions) covering previously untested code:
  - `EntityEventNotificationListenerTest` (12 tests) — created/updated/deleted event handling, actor exclusion, notification data validation
  - `NotificationSentLoggerTest` (7 tests) — log creation, BaseNotification skip, non-Model skip, channel recording
  - `BaseNotificationTest` (12 tests) — abstract class, ShouldQueue, channel routing, onlyVia, toMail, toBroadcast
  - `PdfActionTest` (11 tests) — builder methods, default name/paper/orientation, property types
  - `HasNotificationLoggingTest` (3 tests) — trait existence, MorphMany relationship
- Test suite now at 1091 passing (up from 1046), 2316 assertions

### Removed

- Cleaned orphaned entity files from previous pipeline runs (widgets, migrations, enums, notifications)

## [1.0.1] - 2026-02-08

### Summary

Post-v1.0 solution completions — entity removal command and branded README confirmed implemented and tested. Backlog cleared.

### Confirmed Complete

- **`aicl:remove-entity` command** — Inverse of `aicl:make-entity`. Scans for all entity files (20+ patterns), previews with `--dry-run`, cleans shared registration files (AppServiceProvider, routes/api.php, routes/channels.php, DatabaseSeeder). 18 tests, 67 assertions passing.
- **`/remove-entity` agent** — Claude Code agent that runs dry-run preview, confirms, executes removal, runs Pint, and verifies tests.
- **Branded README** — `dist/README.md` rewritten with centered hero section (Ignibyte logo + shields.io badges), "What You Get" feature grid, agent toolchain table, testing/quality command reference, ASCII architecture diagram, and full 18-row stack table. Logo asset at `docs/assets/ignibyte-logo.png`.

### Solutions Closed

- `entity-removal-process.md` — Complete (command + agent + tests)
- `branded-readme.md` — Complete
- `private-distribution.md` — Complete (TASK-005)
- `claude-split-framework-pipeline.md` — Complete (TASK-005)

---

## [1.0.0] - 2026-02-08

### Summary

V1.0 Distribution (TASK-005) — Full repo split into two distributable private repositories (`aicl/project` skeleton + `aicl/aicl` package). PostgreSQL 17 as default database, idempotent install command, skeleton build system, pipeline-variant agent prompts, and release process documentation. Repos split, tagged, and validated.

### Added

- **PostgreSQL 17 default** — Switched from MariaDB 10.11. Fixed `notifications.data` column (`text` → `json` for `->>` operator) and `HasStandardScopes::scopeSearch()` (`LIKE` → `LOWER() LIKE` for case-sensitivity).
- **Idempotent `aicl:install`** — `isAlreadyInstalled()` checks roles table. Skips with info message unless `--force`.
- **Dual `@source` theme CSS paths** — Both `packages/aicl/` (dev) and `vendor/aicl/aicl/` (dist) paths. Tailwind v4 ignores non-existent paths.
- **Pipeline-variant agent prompts** — `generate-pipeline.md`, `pm-pipeline.md`, `rlm-pipeline.md` with vendor/ boundary rules.
- **Skeleton distribution files** — `dist/` directory: CLAUDE.md, .env.example, composer.json, .gitignore, .gitattributes, README.md.
- **`build-skeleton.sh`** — Assembles clean distribution from dev repo. Produces 8 agents, 56 files, no framework leaks.
- **Release process doc** — `.claude/planning/framework/reference/release-process.md`.
- **Repo split** — `Ignibyte/aicl` (package), `Ignibyte/project` (skeleton), `Ignibyte/aicl_dev` (monorepo).

### Test Coverage

1095 tests passing, 19 pre-existing Breezy middleware failures unchanged.

---

## [0.9.1] - 2026-02-08

### Summary

Dedicated Test Database (TASK-005) — Tests now run against a separate `aicl_testing` database, leaving the development database (`db`) completely untouched. Eliminates the need for post-test reseeding.

### Changed

- **`.ddev/config.yaml`** — Added `post-start` hook to create `aicl_testing` database and grant `db` user access on every `ddev start/restart`.
- **`phpunit.xml`** — Added `<env name="DB_DATABASE" value="aicl_testing"/>`. Removed `<extensions>` block (ReseedAfterTestSuite).
- **`.env.dusk.local`** — Changed `DB_DATABASE` from `db` to `aicl_testing`.
- **`.ddev/commands/web/dusk`** — Removed pre/post database seeding. Dusk tests use `aicl_testing` via `.env.dusk.local`.

### Removed

- **`tests/ReseedAfterTestSuite.php`** — No longer needed since dev database is never touched by tests.

### Test Coverage

1026 tests passing, 19 pre-existing Breezy middleware failures unchanged.

---

## [0.9.0] - 2026-02-08

### Summary

SAML SSO Integration (TASK-005) — Full SAML 2.0 Single Sign-On support alongside existing social login (Google/GitHub OAuth). Includes SP-initiated authentication flow, configurable 3-layer attribute mapping (package defaults → config overrides → custom mapper class), role mapping with sync/additive modes, CSRF-exempt ACS callback, and dual feature gate (env config + admin settings toggle). 41 new tests.

### Added

- **`SamlAttributeMapper`** — `Aicl\Auth\SamlAttributeMapper` with 3-layer attribute resolution: built-in defaults for standard SAML/WS-Fed/OID URIs, config-based overrides via `aicl.saml.attribute_map`, and DI-swappable custom mapper class via `aicl.saml.mapper_class`.
- **SAML routes** — `packages/aicl/routes/saml.php` with three endpoints: `GET /auth/saml2/metadata` (SP metadata XML), `GET /auth/saml2/redirect` (SP-initiated AuthnRequest), `POST /auth/saml2/callback` (ACS endpoint, CSRF-exempt).
- **SAML controller methods** — `samlMetadata()`, `samlRedirect()`, `samlCallback()`, `samlDriver()`, `syncSamlRoles()` on `SocialAuthController`. The `samlDriver()` helper configures Guzzle SSL verification since the saml2 provider ignores the guzzle constructor param.
- **SSO button on login page** — Conditional rendering based on dual gate: `AICL_SAML` config flag + `enable_saml` settings toggle. Independent of social login buttons.
- **Role sync modes** — `sync` (replace all roles) and `additive` (only add new roles) via `config('aicl.saml.role_sync_mode')`. Maps IdP group attributes to Laravel roles.
- **`aicl.saml` config section** — `idp_name`, `auto_create_users`, `default_role`, `role_sync_mode`, `mapper_class`, `attribute_map`, `role_map` with `source_attribute` and `map`.
- **`config/services.php` saml2 block** — Full `socialiteproviders/saml2` service config with `SAML_IDP_METADATA_URL`, `SAML_SP_ACS_URL`, `SAML_VERIFY_SSL` env var support.
- **Settings migration** — `enable_saml` added to `FeatureSettings` with seeder and admin toggle.
- **41 new tests** — `SamlAttributeMapperTest` (22 unit), `SamlAuthTest` (19 feature) covering attribute resolution, role mapping, user creation/linking, name sync, CSRF exemption, feature flags, and login page methods.

### Changed

- **`SocialAuthController`** — Added SAML-specific methods and `samlDriver()` helper with `GuzzleHttp\Client` import.
- **`Login` page** — `hasSamlLogin()` and `hasSocialLogin()` now check both config flags AND `FeatureSettings` database toggle (dual gate pattern).
- **`FeatureSettings`** — Added `enable_saml` property.
- **`ManageSettings`** — Added SAML SSO toggle in Features section.
- **`SettingsSeeder`** — Seeds `enable_saml` default `false`.
- **`AiclServiceProvider`** — Conditional SAML route loading, Socialite event listener, `SamlAttributeMapper` singleton binding.
- **`GeneralSettings`** — `$site_description` changed from `string` to `?string` to allow null values.
- **Published `config/aicl.php`** — Feature flags now use `env()` calls instead of hard-coded values.

### Dependencies

- Added `socialiteproviders/saml2: ^4.8` to `packages/aicl/composer.json`

### Test Coverage

987 tests — all passing (18 pre-existing Breezy middleware failures unchanged)

---

## [0.8.0] - 2026-02-07

### Summary

API Security Hardening (TASK-007) — OWASP Top 10 compliance for the API layer. Three-tier rate limiting with Redis (Swoole-compatible), pagination cap trait preventing resource exhaustion, CORS lockdown with env-driven origins, Content-Security-Policy middleware with dual Filament/API profiles and report-only mode, environment-aware proxy trust, social auth route throttling, and dedicated API request logging channel.

### Added

- **Three-tier rate limiting** — `api` (60/min), `api-public` (30/min), `api-heavy` (10/min) rate limiters registered in `AppServiceProvider`. Uses `throttleWithRedis()` for Swoole/Octane compatibility. Applied to all API routes via `throttle:api` middleware.
- **`PaginatesApiRequests` trait** — `Aicl\Traits\PaginatesApiRequests` enforces min 1, max 100 pagination cap with configurable defaults. Prevents resource exhaustion (OWASP API4). Applied to `ProjectController` and scaffolded into new entities via `MakeEntityCommand`.
- **CORS configuration** — Published `config/cors.php` with restricted methods (`GET`, `POST`, `PUT`, `PATCH`, `DELETE`), explicit allowed headers, env-driven origins via `CORS_ALLOWED_ORIGINS`, 24h preflight cache, and rate limit header exposure.
- **`SecurityHeadersMiddleware`** — `Aicl\Http\Middleware\SecurityHeadersMiddleware` registered as global middleware. Applies `X-Content-Type-Options`, `X-Frame-Options`, `X-XSS-Protection`, `Referrer-Policy`, `Permissions-Policy`, and HSTS headers. Configurable CSP with dual profiles: Filament admin (allows `unsafe-inline`/`unsafe-eval` for Livewire/Alpine) and API (strict `default-src 'none'`). CSP starts in report-only mode.
- **Environment-aware proxy trust** — `TRUSTED_PROXIES` env var in `bootstrap/app.php`. Supports `*` (trust all), comma-separated IPs, or DDEV default.
- **Social auth throttling** — Socialite routes wrapped with `throttle:10,1` to prevent OAuth abuse.
- **API request logging** — `Aicl\Http\Middleware\ApiRequestLogMiddleware` logs method, path, status, user_id, IP, duration_ms, and user_agent to dedicated `api-requests` daily log channel (30-day retention). Toggled via `config('aicl.security.api_logging')`.
- **`aicl.security` config section** — New config keys for headers, CSP, API logging, and trusted proxies with env variable support.
- **40 new tests** — `SecurityHeadersTest` (13), `ApiRateLimitingTest` (5), `ApiPaginationTest` (4), `ApiRequestLoggingTest` (3), `CorsConfigurationTest` (8), `PaginatesApiRequestsTest` (7).

### Changed

- **`ProjectController`** — Uses `PaginatesApiRequests` trait instead of inline pagination.
- **`MakeEntityCommand`** — Scaffolded API controllers now include `PaginatesApiRequests` trait and `$this->getPerPage($request)` call.
- **`bootstrap/app.php`** — Added `throttleWithRedis()` and `TRUSTED_PROXIES` env-driven proxy trust.
- **`routes/api.php`** — Added `throttle:api` middleware to all API route groups.
- **`.env.example`** — Added security env vars: `TRUSTED_PROXIES`, `CORS_ALLOWED_ORIGINS`, `AICL_API_LOGGING`, `AICL_SECURITY_HEADERS`, `AICL_CSP_ENABLED`, `AICL_CSP_REPORT_ONLY`.

### Test Coverage

884 tests — all passing (17 pre-existing Breezy middleware failures unchanged)

---

## [0.7.0] - 2026-02-07

### Summary

Ignibyte Base Theme (TASK-006) — Established the Ignibyte brand identity as the default theme for every AICL install. Orange primary color, Outfit/Orbitron/JetBrains Mono fonts, dark collapsible sidebar with active accent bars, Ignibyte logo in sidebar header, enhanced login page with glassmorphic right panel, and 35+ CSS custom properties (`--aicl-*`) for per-project theming.

### Added

- **Brand logo assets** — Ignibyte logo (`logo.png`), favicon (`favicon.png`), and OG image (`opengraph.jpg`) shipped in `packages/aicl/resources/assets/images/`, published to `public/vendor/aicl/images/` via `aicl-assets` tag.
- **`<x-aicl-ignibyte-logo>` component** — Reusable Blade component with `size` (sm/md/lg/xl) and `iconOnly` props. Logo image with orange glow drop-shadow, gradient "IGNIBYTE" text in Orbitron font.
- **Custom brand logo view** — `resources/views/filament/admin/logo.blade.php` renders the Ignibyte logo + brand name in sidebar header and topbar via Filament's `brandLogo()`.
- **CSS custom properties** — 35+ `--aicl-*` design tokens in `:root` (light) and `.dark` (dark mode) for background, foreground, card, primary, secondary, muted, accent, destructive, border, sidebar, and chart colors.
- **Tailwind v4 `@theme inline`** — All `--aicl-*` tokens exposed as `--color-aicl-*` aliases consumable by Tailwind utility classes.
- **Google Fonts** — Outfit (body/sans), Orbitron (display/headings), JetBrains Mono (code/mono) loaded via CSS `@import`.
- **Sidebar dark styling** — Dark background, tighter nav group spacing, uppercase group labels, item hover states, active item with orange tint + left accent bar.
- **Login page enhancements** — Ignibyte logo top-left, gradient right panel (`from-primary via-orange-600 to-red-600`), glassmorphic logo circle, grid texture overlay, translucent inputs, glow submit button.
- **Login animations** — `aicl-slide-in-left` and `aicl-slide-in-right` keyframes with `prefers-reduced-motion` support.
- **`aicl.theme` config** — `brand_name`, `logo`, `favicon` keys with env variable support (`AICL_BRAND_NAME`, `AICL_LOGO_PATH`, `AICL_FAVICON_PATH`) for per-project override.

### Changed

- **AdminPanelProvider** — Primary color from Indigo to Orange, added `sidebarCollapsibleOnDesktop()`, `brandLogo()`, `brandLogoHeight()`, `brandName()`, `favicon()`. Navigation groups now use `NavigationGroup::make()` with icons and `collapsible()`.
- **Topbar** — Removed shadow/ring for cleaner dark aesthetic matching sidebar.

### Extensibility

Per-project customization without modifying `packages/aicl/`:
1. Override CSS variables in app-level `theme.css`
2. Replace logo via `config('aicl.theme.logo')` or publish and overwrite `public/vendor/aicl/images/logo.png`
3. Change brand name via `AICL_BRAND_NAME` env or `config('aicl.theme.brand_name')`
4. Override colors via `AdminPanelProvider::colors()` in `app/`
5. Override fonts via `--font-sans`, `--font-display`, `--font-mono` in app-level theme.css

---

## [0.6.0] - 2026-02-07

### Summary

Smart Scaffolder — `aicl:make-entity` now accepts `--fields`, `--states`, `--relationships`, `--widgets`, `--notifications`, and `--pdf` flags. Scaffolded output is ~80% complete with entity-specific columns, casts, form fields, table columns, faker data, validation rules, and tests — up from ~30% generic placeholders. Fully backward compatible: omitting `--fields` produces identical output to the previous version.

### Added

- **`--fields` option** — Define entity fields with `name:type[:modifier]` format (e.g., `--fields="title:string,priority:enum:TaskPriority,due_date:date:nullable"`). Supports 10 types (string, text, integer, float, boolean, date, datetime, enum, json, foreignId) and 4 modifiers (nullable, unique, default(value), index).
- **`--states` option** — Generate Spatie ModelStates state machine with abstract base + concrete state classes (e.g., `--states="draft,in_progress,completed"`). Auto-adds `HasStates` trait, `registerStates()`, and linear transition defaults.
- **`--relationships` option** — Define non-FK relationships with `name:type:Model[:fk]` format (e.g., `--relationships="comments:hasMany:Comment"`). Supports hasMany, hasOne, belongsToMany, morphMany.
- **`--widgets` option** — Generate 3 widget stubs: StatsOverview, ChartWidget (doughnut, only if enum/states), TableWidget (deadlines if date field, recent records otherwise).
- **`--notifications` option** — Generate 2 notification stubs: Assignment notification (always) + StatusChanged notification (only if `--states`).
- **`--pdf` option** — Generate 2 Blade PDF templates: single report and list report, pre-filled with field-specific data rows.
- **`--all` option** — Shorthand for `--widgets --notifications --pdf`.
- **`--traits` option** — Override default trait selection (e.g., `--traits=HasEntityEvents --traits=HasAuditTrail`).
- **Smart migration** — Type-specific columns with nullable, unique, index, default modifiers.
- **Smart model** — Populated `$fillable`, `casts()`, relationships, `searchableColumns()` from field spec.
- **Smart factory** — Faker calls matched to field types (sentence, paragraph, randomNumber, enum cases, FK factories).
- **Smart Filament form** — Correct form component per type (TextInput, RichEditor, Toggle, DatePicker, Select, KeyValue).
- **Smart Filament table** — Correct column per type with filters (TextColumn, IconColumn, badges, money, date).
- **Smart form requests** — Store + Update validation rules derived from types with nullable/unique modifiers.
- **Smart API resource** — All fields in `toArray()` with date formatting, enum values, `whenLoaded` for relationships.
- **Smart exporter** — Export columns per type with enum formatting and FK relationship labels.
- **Smart observer** — `updating()` stub for status transition logging (when `--states`), `updated()` stub for ownership change notifications (when FK fields contain "assigned" or "owner").
- **Smart test** — Field-aware tests: foreignId relationship tests, default state test, state transition test, enum value tests, searchableColumns test with actual field names.
- **`FieldParser` class** — `Aicl\Console\Support\FieldParser` parses and validates `--fields` input with fail-fast error handling.
- **`FieldDefinition` value object** — `Aicl\Console\Support\FieldDefinition` represents a parsed field with type, modifiers, and helper methods.
- **`RelationshipParser` class** — `Aicl\Console\Support\RelationshipParser` parses and validates `--relationships` input.
- **`RelationshipDefinition` value object** — `Aicl\Console\Support\RelationshipDefinition` represents a parsed relationship.

### Backward Compatibility

Fully backward compatible. Running `aicl:make-entity` without `--fields` produces identical output to v0.5.1 (legacy mode). All 11 existing MakeEntity tests pass unchanged.

### Test Coverage

844 tests, 1,754 assertions — all passing (17 pre-existing Breezy middleware failures)

---

## [0.5.1] - 2026-02-07

### Summary

User avatar management with three-source priority chain: uploaded avatar (Breezy profile) > SSO provider avatar > ui-avatars.com default. Users can upload avatars from their profile page with circular crop. SSO avatars are automatically captured from Google/GitHub on login and refreshed on each subsequent login.

### Added

- **User avatar upload** — Enabled Breezy `hasAvatars: true` with circular crop upload on My Profile page. Stores file path in `users.avatar_url` column.
- **SSO avatar capture** — `SocialAuthController` now stores `$socialUser->getAvatar()` in `social_accounts.avatar_url` on new account creation and updates it on subsequent logins (guards against null overwrite).
- **Avatar priority chain** — User model implements Filament `HasAvatar` contract with `getFilamentAvatarUrl()`: uploaded avatar (via Storage public disk) > SSO avatar URL > null (Filament falls back to ui-avatars.com).
- **`getSocialAvatarUrl()` method** — New method on `HasSocialAccounts` trait queries the most recently updated non-null SSO avatar URL across all linked providers.
- **`linkSocialAccount()` updated** — New optional `$avatarUrl` parameter (backward-compatible).
- **2 migrations** — `avatar_url` nullable string on `users` and `social_accounts` tables.
- **13 new tests** — 9 in `UserAvatarTest` (priority chain, fallback, contract) + 4 in `SocialAuthControllerTest` (SSO avatar store/update/null guard).

### Test Coverage

853 tests, 1,847 assertions — all passing (17 pre-existing Breezy middleware failures)

---

## [0.5.0] - 2026-02-07

### Summary

Centralized media gallery with auth-protected private storage. Integrates `tomatophp/filament-media-manager` v4.0.3 for a sidebar media gallery on top of Spatie MediaLibrary. Media files stored on a private `media` disk (`storage/app/media/`) and served through an authenticated route — no public symlinks, fully compatible with Octane. Includes a media widget on Project view/edit pages, updated entity scaffolding, and 2 new RLM patterns.

### Added

- **Centralized media gallery** — `FilamentMediaManagerPlugin` registered in AdminPanelProvider with sub-folders and `Content` navigation group. Browse, upload, and organize media from `/admin/media`.
- **Private `media` filesystem disk** — New `media` disk in `config/filesystems.php` storing files at `storage/app/media/` (outside webroot). No storage symlink needed.
- **Auth-protected media serving** — `GET /media/{path}` route in `routes/web.php` with `auth()->check()` guard. Returns 403 for unauthenticated requests, 404 for missing files. Works natively with Octane.
- **Project media collections** — Project model implements `HasMedia`, uses `InteractsWithMedia` + `InteractsWithMediaManager`. Registers `documents` and `images` collections.
- **ProjectMediaWidget** — Footer widget on View/Edit project pages showing attached media grouped by collection with thumbnail grid, file names, and sizes.
- **MediaManagerPicker form fields** — Project form includes collapsible Media section with pickers for documents and images.
- **`HasMediaCollections` trait updated** — Now includes `InteractsWithMediaManager` alongside `InteractsWithMedia` for automatic gallery integration.
- **`aicl:make-entity` media scaffolding** — When `HasMediaCollections` trait is selected, scaffolds `HasMedia` interface, media traits, and `MediaManagerPicker` form fields.
- **2 new RLM patterns** — `media.gallery_integration` and `media.has_media_interface` (warning severity, 0.5 weight). Total: 42 patterns (40 base + 2 media).
- **Migration guard** — Published Spatie `create_media_table` migration now has `Schema::hasTable('media')` guard to prevent duplicate table conflicts.
- **20 new tests** — `MediaGalleryIntegrationTest` (16 tests) covering plugin registration, routes, model interface/traits, media collections, auth enforcement, media serving, disk config, and widget.

### Test Coverage

844 tests, 1,754 assertions — all passing (17 pre-existing Breezy middleware failures)

### RLM

42/42 patterns (100%) — 40 base + 2 media patterns

---

## [0.4.2] - 2026-02-07

### Summary

Filament error pages within the panel layout and root URL redirect. HTTP errors (404, 403, 500, 503) inside `/admin` now render as Filament pages with sidebar navigation instead of blank Laravel error views. Root URL (`/`) redirects to admin dashboard or login.

### Added

- **Filament error pages** — 4 new Filament pages (`NotFound`, `Forbidden`, `ServerError`, `ServiceUnavailable`) in `Aicl\Filament\Pages\Errors\`. Render within the panel layout with sidebar visible, centered error message, "Go to Dashboard" and "Go Back" buttons. Hidden from sidebar navigation.
- **Exception renderer** — `bootstrap/app.php` `withExceptions()` handler redirects `HttpExceptionInterface` under `/admin/*` to the corresponding Filament error page. API and non-admin requests pass through to default Laravel handling.
- **Root URL redirect** — `/` now redirects to `/admin` (authenticated) or `/admin/login` (guest) since the framework is admin-panel-only.
- **14 new tests** — `FilamentErrorPagesTest` covering page properties, navigation visibility, redirect behavior, page rendering, API passthrough, and non-admin passthrough.
- **`aicl:make-entity` global search by default** — Generated Filament resources now include `$recordTitleAttribute = 'name'`, making all scaffolded entities globally searchable out of the box. Completes TASK-002.

### Test Coverage

840 tests, 1,747 assertions — all passing (PHPUnit, 17 pre-existing Breezy middleware failures)

---

## [0.4.1] - 2026-02-07

### Summary

Global search improvements and developer experience fixes. Users and Projects now appear in Filament global search; Shield Roles excluded. Error notifications added to admin panel. PHPUnit ReseedAfterTestSuite extension fixed to restore database after test runs.

### Added

- **Filament global search on Users and Projects** — Added `$recordTitleAttribute = 'name'` to `UserResource` and `ProjectResource`. Searching by name now returns matching users and projects in the global search bar.
- **Admin panel error notifications** — `registerErrorNotification()` for generic errors (500), not found (404), and access denied (403) in `AdminPanelProvider`.

### Changed

- **Shield RoleResource excluded from global search** — Added `->globallySearchable(false)` to `FilamentShieldPlugin` in `AdminPanelProvider`. Roles no longer pollute global search results.

### Fixed

- **ReseedAfterTestSuite PHPUnit extension** — Suite name condition fixed (was checking for empty string, now uses `str_ends_with('phpunit.xml')`). Uses `aicl:install` via `exec()` to restore Shield permissions + `db:seed` for admin user. Database is now automatically restored after test suite runs.

### Test Coverage

818 tests, 1,645 assertions — all passing (PHPUnit)

---

## [0.4.0] - 2026-02-07

### Summary

Scout search engine integration with Elasticsearch support. Optional upgrade path from database driver to Elasticsearch via `AICL_SCOUT_DRIVER` feature flag. Zero application code changes required — pure driver swap. Includes DDEV Elasticsearch service, `aicl:scout-import` command, and full test coverage.

### Added

- **Scout driver feature flag** — `AICL_SCOUT_DRIVER` env var in `aicl.features.scout_driver` config. Supports `false` (default, database driver) or `'elasticsearch'`.
- **Search config section** — `aicl.search` with `elasticsearch` subsection (host, port, scheme).
- **Conditional Scout driver swap** — `AiclServiceProvider::configureScoutDriver()` calls `configureElasticsearch()` when the flag is set.
- **`aicl:scout-import` command** — Discovers all models using `HasSearchableFields` trait and bulk-imports into Scout index. Supports `--flush` option.
- **DDEV Elasticsearch service** — Elasticsearch 8 via DDEV addon with ARM64 support.
- **`suggest` dependencies** — `matchish/laravel-scout-elasticsearch` and `elasticsearch/elasticsearch` in package `composer.json`.
- **12 new tests** — `ElasticsearchIntegrationTest` covering config, feature flag behavior, command registration, and convention compliance.

### Test Coverage

818 tests, 1,645 assertions — all passing (PHPUnit)

---

## [0.3.2] - 2026-02-07

### Summary

Scaffolding fixes (F-003), documentation audit, and cleanup. `MakeEntityCommand` now generates List page with CreateAction, table with Export/record/bulk actions. Category entity pipeline backed out and cleaned up. Documentation audit confirmed all 45+ docs current and consistent.

### Fixed

- **[F-003] `MakeEntityCommand` scaffolding gaps** — List page now generates `getHeaderActions()` with `CreateAction::make()`. Table now generates `recordActions` (View/Edit), `headerActions` (ExportAction), and `toolbarActions` (BulkActionGroup with ExportBulkAction + DeleteBulkAction). Retrofitted Projects and Users tables.

### Changed

- **Documentation audit** — Cleaned stale PIPELINE-Category.md from `pipeline/active/`. Updated MEMORY.md (removed stale `aic-to-laravel-migration-guide.md` reference, updated planning structure to match two-tree architecture, added 6 new agent commands). Moved detailed phase component descriptions to `phase-components.md` topic file to keep MEMORY.md under 200 lines.

### Test Coverage

802 tests, 1,620 assertions — all passing (PHPUnit)

---

## [0.3.1] - 2026-02-07

### Summary

Entity generation pipeline validation. Wired up the generated Project entity with full registrations (Policy, Observer, API routes, Filament resource discovery) and validated the complete entity stack against all 40 RLM patterns (100% score).

### Added

- **Entity Registration in `AppServiceProvider`** — `Gate::policy()` for ProjectPolicy and `Project::observe()` for ProjectObserver
- **Project API Routes** — `routes/api.php` now includes `v1/projects` resource routes with `auth:api` middleware
- **Filament Auto-Discovery** — `AdminPanelProvider` now uses `discoverResources()` for `app/Filament/Resources/`, so future generated entities are auto-registered
- **`Content` Navigation Group** — Added to AdminPanelProvider for generated entity resources

### Test Coverage

802 tests, 1,620 assertions — all passing (PHPUnit)
`aicl:validate Project` — 40/40 patterns pass (100%)

---

## [0.3.0] - 2026-02-07

### Summary

Golden example extraction and Claude skill updates. Project entity extracted as annotated reference into `.claude/golden-example/`, all Project-specific code removed from `packages/aicl/`, and new `/generate-entity` skill created. The package is now a clean framework — no demo entity ships with it.

### Added

- **[TASK-005] Golden Example** — `.claude/golden-example/` with 24 annotated files (model, migration, factory, seeder, policy, observer, Filament resource + form + table + pages, API controller + requests + resource, exporter, widgets, notifications, PDF templates, test). Each file has `// PATTERN:` comments explaining WHY each piece exists.
- **[TASK-006] `/generate-entity` Skill** — New Claude skill (`.claude/commands/generate-entity.md`) that reads the golden example, takes entity name + field list, and generates a full entity stack following the reference patterns.

### Changed

- **[TASK-006] `/architect` Skill** — Added "Golden Example Reference" section with instructions to read `.claude/golden-example/README.md` before entity work.
- **[TASK-006] `/rlm` Skill** — Updated to reference golden example as the canonical validation baseline. Pattern library now points to golden example files. Validation workflow starts with golden example read.

### Removed

- **[TASK-005] Project Entity** — ALL Project-specific code removed from `packages/aicl/`:
  - Model, States (6 files), Enum, Migration, Factory, Seeder
  - Filament Resource (7 files), 3 Widgets, Exporter
  - API Controller, 2 Form Requests, API Resource
  - Observer, Policy, 2 Notifications, 2 PDF Templates
  - Registrations removed from AiclPlugin, AiclServiceProvider, routes/api.php
- **29 Project-dependent test files** — Will be regenerated when Project entity is re-created via `aicl:make-entity`

### Test Coverage

793 tests, 1,609 assertions — all passing (PHPUnit)

---

## [0.2.0] - 2026-02-06

### Summary

V1 Architect tasks 1–4 and 7 complete. Passport upgraded to v13, Laravel upgraded to v12, custom CSV export replaced with Filament native export, all infrastructure code consolidated into the package, and `app/` cleaned to skeleton state. Test suite at 1331+ tests passing.

### Changed

- **[TASK-1] Passport 12 → 13 Upgrade** — `laravel/passport` v12.4.2 → v13.4.3. PassportSeeder updated for name-based client lookup. 5 old migrations replaced with v13 schema. Resolves known medium security vulnerability (Snyk).
- **[TASK-2] Laravel 11 → 12 Upgrade** — `laravel/framework` v11.48.0 → v12.50.0. Carbon 3.11.1, Boost v2.1.1. Zero code changes needed — dependency bumps only.
- **[TASK-3] Filament Native Export** — Custom `ExportAction`/`BulkExportAction` replaced with Filament's native export system. Created `ProjectExporter` with 9 columns. `league/csv` removed from direct dependencies. Published exports/imports/failed_import_rows migrations.
- **[TASK-4] Package Consolidation** — ~40 files moved from `app/` to `packages/aicl/`: UserResource (6 files), Styleguide pages (5 files + views), policies (2 files), seeders (4 files), Project resource, widgets, API controllers. All namespaces updated from `App\` to `Aicl\`.
- **[TASK-7] Clean Up `app/`** — `app/` now contains only User.php, Controller.php, AppServiceProvider, AdminPanelProvider, and dashboard widgets. Discovery paths removed from AdminPanelProvider.

### Removed

- `app/Filament/Resources/Users/` — moved to package
- `app/Filament/Pages/Styleguide/` — moved to package
- `app/Policies/UserPolicy.php`, `app/Policies/RolePolicy.php` — moved to package
- `database/seeders/RoleSeeder.php`, `AdminUserSeeder.php`, `SettingsSeeder.php`, `PassportSeeder.php` — moved to package
- `packages/aicl/src/Filament/Actions/ExportAction.php` — replaced by Filament native
- `packages/aicl/src/Filament/Actions/BulkExportAction.php` — replaced by Filament native
- `league/csv` direct dependency

### Test Coverage

1331+ tests passing (PHPUnit)

---

## [0.1.0] - 2026-02-06

### Summary

Post-V1 completion: Centralized notification API, audit log viewer, real-time WebSocket hookup, deployment automation, and comprehensive test coverage expansion. Test suite grew from 243 to **979 tests** (1,921 assertions).

### Added

#### Notification API
- `NotificationLog` model — UUID PK, tracks type, notifiable (morph), sender (morph), channels (JSON), per-channel delivery status (JSON), data, read state
- `NotificationDispatcher` service — singleton, mandatory entry point for all notifications; creates log, dispatches per-channel via cloned notification with `onlyVia()`, tracks per-channel status (sent/failed)
- `NotificationLogPage` — admin-only Filament page showing ALL notification logs system-wide (filters: type, recipient, channel, status, read)
- `HasNotificationLogging` trait — `notificationLogs()` morphMany relationship for User model
- `BaseNotification` broadcast support — `toBroadcast()` method, `onlyVia()` channel restriction, `via()` now returns `['database', 'mail', 'broadcast']`

#### Real-time WebSocket Hookup
- `EntityEventNotificationListener` — queued listener for `EntityCreated`, `EntityUpdated`, `EntityDeleted`; creates `DatabaseNotification` + `NotificationLog` for entity owner + super_admins
- Frontend Echo listeners in `resources/js/echo.js` — `private-dashboard` channel for `.entity.created`, `.entity.updated`, `.entity.deleted` events
- `ActivityFeed` upgraded with `#[On('entity-changed')]` Livewire event listener
- Dashboard widgets (`ProjectStatsOverview`, `ProjectsByStatusChart`, `UpcomingDeadlines`) — `#[On('entity-changed')]` + 60s polling fallback

#### Audit & System
- `AuditLog` Filament page — admin-only, queries `Spatie\Activitylog\Models\Activity`, columns: timestamp, user, action (color-coded badge), entity type (basename), entity ID, description; filters by event, entity type, user
- `PassportSeeder` — auto-creates Passport personal access client on fresh installs
- Queue worker daemon added to DDEV config (`web_extra_daemons`)

#### Deployment
- `laravel/envoy` installed as dev dependency
- `Envoy.blade.php` with 5 tasks: `deploy`, `fresh`, `cache-clear`, `status`, `octane-reload`
- `DEPLOY_SERVER` and `DEPLOY_PATH` env vars added to `.env.example`

#### Architecture Documentation
- 6 comprehensive architecture docs in `.claude/architecture/`: arch-overview, entity-system, auth-guide, filament-patterns, components-reference, ai-generation-rules
- Feature test map (`.claude/architecture/feature-test-map.md`) — manual smoke test checklist for all features
- Not-implemented tracker (`.claude/architecture/not-implemented.md`) — status of all decided stack items

#### Test Coverage (11 new test files, +736 tests)
- `NotificationApiTest` (28 tests) — NotificationLog model, scopes, dispatcher, BaseNotification broadcast
- `EntityNotificationTest` (9 tests) — entity CRUD notification generation, listener registration
- `AuditLogTest` (9 tests) — page access, activity display, filters
- `AdminPageAccessTest` — admin page authorization
- `ComponentEdgeCaseTest` — component library edge cases
- `EntityValidatorEdgeCaseTest` — RLM validator edge cases
- `ExportActionTest` — CSV export action
- `LogParserTest` — log parser service
- `LoginPageSocialTest` — social login page rendering
- `NotificationDispatcherTest` — dispatcher service
- `PdfGeneratorTest` — PDF generation
- `PolicyEdgeCaseTest` — policy authorization edge cases
- `SocialAuthControllerTest` — OAuth controller flows
- `WidgetTest` — dashboard widget rendering

### Changed

- Bell notification polling reduced from 30s to 300s (broadcast channel handles instant delivery)
- `ProjectObserver` uses `NotificationDispatcher` instead of direct `$user->notify()`
- `EntityEventNotificationListener` creates `NotificationLog` alongside `DatabaseNotification`

### Fixed

- `FailedJob` ViewPage incorrect import: `Filament\Schemas\Components\TextEntry` → `Filament\Infolists\Components\TextEntry`
- `ApiTokens` page removed non-functional `getHeaderActions()` "Create Token" popup (inline form is sufficient)

### Removed

- `spatie/laravel-data` dependency — redundant with Filament/Livewire TALL stack
- `laravel/precognition` from decided stack — redundant with Filament live form validation

### Test Coverage

979 tests, 1,921 assertions — all passing (PHPUnit) + 24 Dusk browser tests (37 assertions)

---

## [0.0.1] - 2026-02-05

### Summary

Initial development release of AICL — an AI-first Laravel application framework delivered as a Composer package. All 10 implementation phases complete with 243 tests and 434 assertions passing.

### Added

#### Core Package (Phase 0)
- `aicl/aicl` Composer package at `packages/aicl/`
- Service provider with config publishing
- Path repository setup for local development

#### Authentication & RBAC (Phase 1)
- Filament v4.7.0 admin panel with custom theme
- Spatie Permission + Filament Shield for role-based access control
- Filament Breezy v3 for multi-factor authentication
- Policy-based authorization for all resources

#### Entity Trait System (Phases 2-3)
- `HasEntityEvents` — typed lifecycle events
- `HasAuditTrail` — activity logging via Spatie Activity Log
- `HasStandardScopes` — common query scopes (active, recent, etc.)
- `HasMediaCollections` — Spatie Media Library integration
- `HasSearchableFields` — Laravel Scout integration
- `HasTagging` — Spatie Tags support
- 5 contracts defining entity behaviors
- 6 lifecycle events (Creating, Created, Updating, Updated, Deleting, Deleted)
- BaseObserver for cross-cutting entity concerns

#### Golden Entity: Project (Phase 4)
- Project model with Spatie Model States
- ProjectPriority enum with labels/colors
- Full Filament resource (CRUD, filters, search)
- REST API v1 endpoints with Eloquent Resources
- Dashboard widgets (ProjectStatsWidget, ProjectsByStatusWidget)

#### Component Library (Phase 5)
- 16 Blade components + 1 Livewire component
- Layout: SplitLayout, CardGrid, StatsRow, EmptyState
- Metrics: StatCard, KpiCard, TrendCard, ProgressCard
- Data: MetadataList, InfoCard, StatusBadge, Timeline
- Actions: ActionBar, QuickAction, AlertBanner, Divider
- Livewire: ActivityFeed (real-time with polling)
- Styleguide pages in Filament admin (`/admin/styleguide-*`)

#### RLM/AI Pipeline (Phase 6)
- `aicl:make-entity {Name}` — scaffolds full entity stack
- `aicl:validate {Name}` — scores entity against 40 patterns
- Generates: model, migration, factory, seeder, policy, observer, Filament resource, API, tests

#### System Utilities (Phase 7)
- Failed Jobs resource with retry/delete actions
- Queue Dashboard with stats and recent failures widgets
- Log Viewer with level filtering and search
- Settings management (General, Mail, Features via Spatie Settings)
- Notification Center with read/unread management

#### Search & Real-time (Phase 8)
- Laravel Scout with database driver
- Filament global search integration
- GlobalSearchWidget for dashboards
- Laravel Reverb WebSocket configuration
- Broadcastable entity events (EntityCreated, EntityUpdated, EntityDeleted)

#### Export & PDF (Phase 9)
- ExportAction — CSV export with column selection
- BulkExportAction — export selected table records
- PdfGenerator — DomPDF service wrapper
- PDF templates (layout, styles, project-report, projects-list)
- PdfAction — single-record PDF download

#### Social/OAuth (Phase 10)
- SocialAccount model with encrypted token storage
- HasSocialAccounts trait for User model
- SocialAuthController for OAuth redirect/callback flows
- Extended Filament Login page with social buttons
- ApiTokens page for Passport personal access token management
- Feature flags: `AICL_SOCIAL_LOGIN`, `AICL_SAML`, `AICL_WEBSOCKETS`

#### Development Environment
- DDEV with PHP 8.3, MariaDB 10.11
- Swoole 6.0.0 via PECL
- Laravel Octane daemon on port 8443
- Tailwind CSS v4.1.18 with custom Filament theme
- Laravel Boost MCP integration

### Artisan Commands

| Command | Description |
|---------|-------------|
| `aicl:install` | Full package installation (config, migrations, Shield, roles) |
| `aicl:make-entity {Name}` | Scaffold complete entity stack |
| `aicl:validate {Name}` | Score entity against 40 RLM patterns |

### Architecture Decisions

- ADR-001: Package structure with `Aicl\` namespace
- ADR-002: Extension patterns (Observers, Events, Service Container)
- ADR-003: Entity trait inventory and composition
- ADR-004: Filament panel architecture
- ADR-005: Octane safety rules (no singletons, stateless services)

### Test Coverage

243 tests, 434 assertions covering:
- Admin panel authentication flows
- RBAC permission enforcement
- Entity lifecycle events
- Component library rendering
- Export/PDF generation
- Search and real-time features
- Social OAuth flows
- RLM validation patterns

---

## Future Roadmap

- ~~SAML SSO with Okta/Azure AD support~~ — Done (v0.9.0)
- Advanced reporting with scheduled delivery
- Multi-tenancy (team/organization scoping)
- ~~Audit dashboard with visual timeline~~ — Done (v0.1.0 AuditLog page, v1.5.1 DomainEventViewer)
- ~~Entity relationships (polymorphic, pivot tables)~~ — Done (v0.6.0 `--relationships` scaffolder flag)
- Import system (CSV/Excel with validation)
- WebSocket migration for AI streaming (Sprint I — replacing removed SSE)
- Toolbar presence indicator and session management (Sprint H — in design)
