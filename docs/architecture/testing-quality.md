# Testing & Quality

**Version:** 1.1
**Last Updated:** 2026-02-15
**Owner:** `/pipeline-validate`

---

## Overview

AICL maintains high code quality through a multi-layer testing and analysis pipeline. Every generated entity must pass all layers before acceptance.

---

## Quality Pipeline

```
Code Change
    │
    ▼
┌─────────────────────────────────────────────────────┐
│  1. PINT              vendor/bin/pint --dirty        │
│     Code formatting (PSR-12 + Laravel conventions)   │
├─────────────────────────────────────────────────────┤
│  2. PHPSTAN           vendor/bin/phpstan analyse     │
│     Static type checking (level 1)                   │
├─────────────────────────────────────────────────────┤
│  3. RLM VALIDATE      php artisan aicl:validate      │
│     40 base patterns, target 100% (entities only)                        │
├─────────────────────────────────────────────────────┤
│  4. PHPUNIT           php artisan test --compact     │
│     4,404 tests across 3 suites                      │
├─────────────────────────────────────────────────────┤
│  5. DUSK              ddev dusk                      │
│     16 browser tests (4 test files)                  │
└─────────────────────────────────────────────────────┘
    │
    ▼
All Pass = Accepted
```

---

## PHPUnit Tests

### Configuration

**File:** `phpunit.xml`

Key test environment settings:
```xml
<env name="APP_ENV" value="testing"/>
<env name="QUEUE_CONNECTION" value="sync"/>
<env name="BROADCAST_CONNECTION" value="log"/>
<env name="CACHE_STORE" value="array"/>
<env name="SESSION_DRIVER" value="array"/>
<env name="SCOUT_DRIVER" value="database"/>
```

### Test Structure

```
tests/
├── Feature/              # Integration tests (HTTP, Livewire, database)
│   ├── AdminPageAccessTest.php
│   ├── AuditLogTest.php
│   ├── ComponentEdgeCaseTest.php
│   ├── EntityNotificationTest.php
│   ├── EntityValidatorEdgeCaseTest.php
│   ├── ExportActionTest.php
│   ├── LoginPageSocialTest.php
│   ├── LogParserTest.php
│   ├── NotificationDispatcherTest.php
│   ├── PdfGeneratorTest.php
│   ├── PolicyEdgeCaseTest.php
│   ├── ProjectTest.php
│   ├── SocialAuthControllerTest.php
│   └── WidgetTest.php
├── Browser/              # Dusk browser tests
│   ├── LoginTest.php
│   ├── DashboardTest.php
│   ├── ProjectCrudTest.php
│   ├── NavigationTest.php
│   └── ResponsiveTest.php
├── DuskTestCase.php      # Dusk base class
└── TestCase.php          # PHPUnit base class
```

### Standard Test setUp()

Every AICL test needs permission cache reset and seeding:

```php
protected function setUp(): void
{
    parent::setUp();

    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    $this->seed(RoleSeeder::class);
    $this->seed(SettingsSeeder::class);
}
```

**Why:** `RefreshDatabase` wipes all data including roles, permissions, and settings. The permission cache from a previous test would return stale data.

### Test Categories per Entity

Every generated entity must have tests covering:

| Category | What to Test |
|----------|-------------|
| **Model** | Creation, soft delete, fillable fields, casts, relationships |
| **Owner** | BelongsTo User, owner_id assignment |
| **Policy** | Owner access, admin access, viewer denied, each CRUD action |
| **Scopes** | active(), inactive(), recent(), search(), byUser() |
| **Events** | EntityCreated/Updated/Deleted dispatched |
| **Audit** | Activity log entries created on CRUD |
| **Filament** | List page renders, create form works, edit form works |
| **Factory** | Definition produces valid model, state methods work |
| **API** | Index, show, store, update, destroy (if API routes exist) |

**Minimum:** 15 tests per entity.

### Running Tests

```bash
# Single test by name
ddev exec php artisan test --compact --filter=testCanCreateProject

# Single test file
ddev exec php artisan test --compact tests/Feature/Entities/ProjectTest.php

# Full suite
ddev exec php artisan test --compact

# With verbose output
ddev exec php artisan test
```

---

## Laravel Dusk (Browser Tests)

### Environment

- **Selenium container:** `seleniarm/standalone-chromium:latest` (ARM64 for Apple Silicon)
- **WebDriver URL:** `http://selenium:4444/wd/hub`
- **APP_URL:** `http://web` (Docker internal hostname, nginx-fpm on port 80)
- **Env file:** `.env.dusk.local` (complete env copy with test overrides)

### Running Dusk

```bash
# Run all Dusk tests
ddev dusk

# Run specific Dusk test file
ddev dusk tests/Browser/LoginTest.php

# Run specific test method
ddev dusk --filter=testAdminCanLogin
```

The `ddev dusk` command seeds the database before running and re-seeds after.

### Dusk Base Class

**Location:** `tests/DuskTestCase.php`

Helpers:
- `loginAsAdmin()` — logs in as `admin@aicl.test`
- `ensureLoggedOut()` — clears auth state between tests

**Key learning:** Browser sessions persist across tests in the same class. Always manage auth state explicitly.

### Dusk Gotchas

- `deleteCookies()` before navigation causes "Timed out receiving message from renderer" — navigate first
- Filament form field IDs use `form.` prefix (`id="form.email"`), NOT `data.`
- Filament "Sign out" is a `<button>` (use `press()`), not an `<a>` (don't use `clickLink()`)
- `.env.dusk.local` must include `APP_KEY` — Dusk swaps the entire `.env` file

---

## Laravel Pint

**Config:** `pint.json` (if customized, otherwise Laravel defaults)

```bash
# Fix formatting for changed files only
vendor/bin/pint --dirty

# Fix all files
vendor/bin/pint

# Check without fixing (CI)
vendor/bin/pint --test
```

AICL convention: always run `pint --dirty` before committing. The `--format agent` flag is used in CI for machine-readable output.

---

## PHPStan

```bash
# Run static analysis
vendor/bin/phpstan analyse --level=1
```

AICL uses level 1 (basic checks: unknown classes, unknown functions, wrong argument counts). Higher levels can be configured per-project.

---

## RLM Validation

```bash
# Validate a specific entity against 40 base patterns
php artisan aicl:validate Project

# Output: pattern-by-pattern pass/fail with total score
# Target: 100% (40/40)
```

See [AI Generation Pipeline](ai-generation-pipeline.md) for the full pattern list.

---

## Test Coverage Summary

| Suite | Tests | Status | Notes |
|-------|-------|--------|-------|
| Package | ~4,352 | PASS (22 pre-existing failures) | `Aicl\` namespace code in `packages/aicl/tests/` |
| Framework | ~46 | PASS | Scaffolder tests in `tests/Framework/` |
| Project | ~6 | PASS | `tests/Unit/` + `tests/Feature/` |
| Dusk | 16 | PASS | 4 test files (Login, Dashboard, Navigation, Responsive) |
| **Total** | **~4,420** | | |

---

## Security Scanning

Additional quality tools available via the `/scan` command:

| Scanner | What It Does |
|---------|-------------|
| PHPStan | Static analysis (level 6+) |
| Semgrep | SAST security analysis |
| PHPMD | Mess detection (complexity, unused code) |
| Deptrac | Architecture layer enforcement |
| PHPCPD | Copy-paste detection |
| composer-unused | Unused dependency detection |

---

## Related Documents

- [AI Generation Pipeline](ai-generation-pipeline.md) — Structural validation patterns
- [Entity System](entity-system.md) — What to test per entity
- [Foundation](foundation.md) — Quality philosophy
