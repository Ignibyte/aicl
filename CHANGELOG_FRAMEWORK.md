# AICL Framework Changelog

All notable changes to the AICL framework package (`packages/aicl/`) are documented here.

## Versioning

This project uses **Semantic Versioning (SemVer)** — `MAJOR.MINOR.PATCH`:

- **MAJOR** — Breaking changes to package contracts, traits, base classes, or public API
- **MINOR** — New package features, commands, components, or non-breaking additions
- **PATCH** — Bug fixes, test improvements, documentation updates

Current version: `1.1.1`

---

## [1.1.1] - 2026-03-02

### Added

- **Queued Jobs tab** — New tab on Queue Manager showing pending jobs from the `jobs` database table via embedded Livewire TableWidget. Driver-aware empty state explains visibility when using Redis queue driver.
- **QueuedJob model** — Eloquent model for Laravel's built-in `jobs` table with accessors for job name, timestamps, and reserved status.

### Changed

- **Version badge** — Replaced `Cache::rememberForever` with `AiclServiceProvider::VERSION` constant. Version is now read directly from the constant at runtime — no Redis caching, no stale values after releases.
- **Release process** — Updated `/release` Phase 4 to bump the VERSION constant alongside the changelog.

---

## [1.1.0] - 2026-03-02

### Summary

**Navigation Consolidation Sprint** — Admin navigation reduced from 6 sidebar groups / 29 items to 3 groups / 17 items. Related pages consolidated into tabbed interfaces. Development-only Styleguide removed entirely.

### Added

- **Queue Manager** — Tabbed page combining Queue Dashboard + Failed Jobs Resource into one page with Overview (stats) and Failed Jobs (table with retry/delete actions) tabs
- **Activity Log** — Tabbed page combining Log Viewer, Audit Log, Domain Events, and Notification Log into one page with 4 tabs and 3 embedded Livewire table widgets
- **Tools dashboard** — Card-grid landing page linking to AI Assistant and Architecture Docs (renamed from Document Browser)
- **Sidebar collapse persistence** — Collapse state saved to localStorage, restored across page loads and topbar/sidebar mode switches
- **Register page** — Custom registration page class for auth flow

### Changed

- Settings, API Tokens, and Backups moved from Settings group into System group
- AI Assistant hidden from nav (accessible via Tools dashboard)
- Document Browser renamed to Architecture Docs, hidden from nav
- Navigation sort orders reorganized for System group (Settings=1, API Tokens=2, Backups=3, Ops Panel=5, Queue Manager=6, Activity Log=7, Tools=8, Changelog=9)
- `AiclServiceProvider` registers 3 new Livewire components (`aicl::audit-table`, `aicl::domain-event-table`, `aicl::notification-log-table`)

### Removed

- **Styleguide** — 7 pages, 7 Blade views, 3 test files (development-only, not production content)
- **FailedJobResource** — Resource + 2 sub-pages replaced by Queue Manager
- **QueueDashboard** — Standalone page replaced by Queue Manager
- **LogViewer, AuditLog, DomainEventViewer, NotificationLogPage** — 4 standalone pages replaced by Activity Log
- **Navigation groups** — Settings, Tools, RLM Hub, Styleguide groups removed from AdminPanelProvider

---

## [1.0.3] - 2026-02-26

### Summary

**Three framework-level bug fixes:** Favicon config override, MFA profile page conflict, and orphaned registration toggle. All three fixes move responsibility into `AiclPlugin` so shipped projects get correct behavior automatically.

### Fixed

- **Favicon meta tags ignore config** — `favicon-meta.blade.php` had hardcoded `vendor/aicl/images/` paths. Size-specific `<link rel="icon" sizes="...">` tags overrode Filament's `->favicon()` because browsers prefer size-specific variants. Now derives paths from `config('aicl.theme.favicon')` directory.
- **MFA profile page inaccessible** — Two competing profile pages registered: Filament's built-in `/admin/profile` (no 2FA tab) and Breezy's `/admin/my-profile` (has 2FA). Users clicking the wrong profile link had no MFA option. `AiclPlugin` now registers BreezyCore internally with `myProfile` + `enableTwoFactorAuthentication` + `MustTwoFactor` middleware, eliminating the need for projects to configure Breezy directly.
- **Registration Settings toggle had no effect** — The Settings page "User Registration" toggle saved to database but was never consulted. Only the env flag (`AICL_ALLOW_REGISTRATION`) worked. `AiclPlugin::isRegistrationEnabled()` now checks BOTH config and database setting, with try/catch for pre-migration graceful degradation.

### Changed

- **`AiclPlugin::register()`** — Now registers BreezyCore as a sub-plugin (with `hasPlugin` guard against double-registration) and conditionally enables `->registration()` via `isRegistrationEnabled()`
- **`AiclPlugin::isRegistrationEnabled()`** — New public static method checking config then database setting
- **`SettingsSeeder`** — `enable_registration` default changed from `true` to `false` (matches config default)
- **`ManageSettings`** — Registration toggle helper text updated to mention `AICL_ALLOW_REGISTRATION` env var

### Added

- **`AiclPluginIntegrationTest`** — 11 feature tests: Breezy plugin registration, profile route, no duplicate profile, 2FA route, registration disabled by default, `isRegistrationEnabled()` with config/database/fallback scenarios
- **`AiclPluginTest`** — 8 new unit tests: Breezy source assertions, MustTwoFactor middleware, hasPlugin guard, registration method signatures, try/catch verification

### Upgrade Guide

**Projects using `AdminPanelProvider`:** Remove `BreezyCore::make()` from `->plugins([])` and remove `->profile(isSimple: false)` — `AiclPlugin` now handles both. The `->when(config('aicl.features.allow_registration'), ...)` call can also be removed.

---

## [1.0.2] - 2026-02-23

### Fixed

- **Dead `aicl:rlm` references** — Replaced all references to removed `aicl:rlm` commands with Forge MCP tool calls across pipeline-variant agent prompts
- **Stub sync** — Synced all 16 agent stubs with their pipeline variants after Forge MCP migration

---

## [1.0.1] - 2026-02-23

### Added

- **Architecture docs shipped in package** — `packages/aicl/docs/architecture/` now ships with the Composer package so shipped projects have access to `auth-rbac.md`, `filament-ui.md`, and other architecture reference docs

---

## [1.0.0] - 2026-02-23

### Summary

**Stable Foundation Release with Forge MCP Integration.** Version reset from internal 3.0.4 — all prior development consolidated into this release. The core AICL framework is production-ready with Forge MCP as the knowledge oracle and task management system.

This release represents the culmination of 20+ development sprints:

- **Sprints A–J:** Swoole foundations, concurrent services, approval workflows, presence tracking, AI WebSocket streaming, developer experience (nginx proxy, Dusk, version badge, changelog viewer, doc browser, nav layout switcher)
- **Sprints L–N:** RLM distillation MVP, 20-entity stress test validation (569 files, 401 PHPUnit, 111 Dusk across 4 batches)
- **Sprint O:** Alpine Component Factory + SDC architecture (33→55 components, ComponentRegistry, field signal engine)
- **Sprints T–U:** DX enhancements, entity page UX overhaul (infolist schemas, View↔Edit sub-navigation)
- **Sprints V–X:** Styleguide completeness, RLM hardening (structured reflection, learning guardrails, waiver system, GC scheduler), retrieval optimization (effectiveness-weighted ranking, EntitySignature)
- **Sprint Y:** RLM package extraction (`packages/rlm/` + `packages/rlm-laravel/`)
- **Sprint F0:** RLM removal from AICL (code moved to Forge project)
- **v3.1.0:** Media manager removed from core (dependency footprint reduction)

### Key Features

- **Filament v4 Admin Panel** — Declarative PHP admin with custom Ignibyte theme, split-layout login, 35+ CSS custom properties, navigation switcher (sidebar/topbar)
- **Entity Generation Pipeline** — `aicl:make-entity` with smart scaffolding (`--fields`, `--states`, `--relationships`, `--widgets`, `--notifications`, `--pdf`, `--all`)
- **55 SDC Components** — Schema-driven ComponentRegistry with field signal engine, AI decision rules, production cache
- **Auth Stack** — Filament auth + Passport OAuth2 + Breezy MFA + SAML SSO + Social login (Google/GitHub)
- **RBAC** — `spatie/laravel-permission` + `filament-shield` with dual web/api guard seeding
- **Real-time** — Laravel Reverb WebSockets, presence tracking, broadcast entity events
- **Swoole/Octane** — nginx proxy, concurrent services, SwooleCache, timer-based workflows
- **Forge MCP Integration** — Remote MCP for knowledge base, ticket system, architecture decisions. Local projects validate; Forge provides institutional memory.
- **RlmBridge** — Graceful degradation when RLM is not installed (all methods return null)

### Agent Architecture

Two agents ship with the package (via `aicl:upgrade`):
- `forge-connect` — Self-service Forge project registration
- `init_help` — Bootstrap and first-run guidance

All other agents (generate, pm, architect, tester, etc.) are delivered dynamically via Forge MCP.

### Test Coverage

~4,384 tests across four suites (Project, Package, Framework, CmsPackage)

---

## Pre-1.0 Development History

> Versions 0.0.1 through 0.9.1 document the initial development phase (Feb 5–8, 2026).
> For detailed history of internal versions 1.0.0–3.0.4 (Feb 8–22, 2026), see git history prior to commit `575bc15`.

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
