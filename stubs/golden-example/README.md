# Golden Example: Project Entity

This directory contains the **annotated reference implementation** of a complete AICL entity stack. When generating a new entity, the AI reads these files to understand the patterns, conventions, and architecture decisions that every entity must follow.

## How to Use This Reference

1. **Before generating any entity**, read this README and the relevant golden example files
2. **Replace `Project` with your entity name** throughout (PascalCase for classes, snake_case for tables/columns)
3. **Follow the pattern comments** (prefixed with `// PATTERN:`) — they explain WHY each piece exists
4. **Validate output** with `aicl:validate {EntityName}` to score against the 42 RLM patterns

## Pipeline Context

This golden example serves as the reference for **Phase 3 (Generate)** of the 8-phase entity pipeline:

```
Phase 1 — PLAN        → /pm         → Parse request, classify, produce spec
Phase 2 — DESIGN      → /solutions  → Design blueprint (relationships, states, rules)
Phase 3 — GENERATE    → /architect  → Scaffold + customize code (USE THIS REFERENCE)
Phase 4 — VALIDATE    → /rlm + /tester → Score patterns + run entity tests
Phase 5 — REGISTER    → /architect  → Wire up policy, observer, routes
Phase 6 — RE-VALIDATE → /rlm + /tester → Re-score + re-run after registration
Phase 7 — VERIFY      → /tester     → Full test suite
Phase 8 — COMPLETE    → /docs       → Document and archive
```

The architect reads these files during Phase 3. The RLM validates against the 42 patterns derived from these files during Phase 4 and 6.

## Smart Scaffolder (Phase 3 Workflow)

The `aicl:make-entity` command now supports a **smart mode** that generates ~80% of the entity code from a field specification. The architect's Phase 3 workflow is:

1. **Build the command** from Phase 2 design blueprint:
```bash
ddev artisan aicl:make-entity {Name} \
  --fields="title:string,priority:enum:TaskPriority,due_date:date:nullable,assigned_to:foreignId:users" \
  --states="draft,in_progress,review,completed" \
  --widgets --notifications --pdf \
  --no-interaction
```

2. **Customize the remaining 20%** — the scaffolder generates correct structure, but the architect still needs to:
   - Replace enum placeholder cases (Low/Medium/High) with actual domain values
   - Customize state transitions beyond linear defaults
   - Fill in widget data queries (replace stubs with actual Eloquent)
   - Write notification content (replace placeholder text)
   - Complete observer TODO stubs for notifications and side effects
   - Add domain-specific test cases

### Field Format Reference

| Type | Example | Migration | Filament Form |
|------|---------|-----------|---------------|
| `string` | `title:string` | `->string('title')` | `TextInput` |
| `text` | `body:text:nullable` | `->text('body')->nullable()` | `RichEditor` |
| `integer` | `count:integer` | `->integer('count')` | `TextInput->numeric()` |
| `float` | `price:float:nullable` | `->decimal('price', 12, 2)->nullable()` | `TextInput->numeric()` |
| `boolean` | `active:boolean` | `->boolean('active')->default(true)` | `Toggle` |
| `date` | `due:date` | `->date('due')->nullable()` | `DatePicker` |
| `datetime` | `start:datetime` | `->dateTime('start')->nullable()` | `DateTimePicker` |
| `enum` | `priority:enum:Priority` | `->string('priority')` | `Select->options()` |
| `json` | `meta:json` | `->json('meta')->nullable()` | `KeyValue` |
| `foreignId` | `user_id:foreignId:users` | `->foreignId('user_id')->constrained()` | `Select->relationship()` |

**Modifiers:** `nullable`, `unique`, `default(value)`, `index` (e.g., `slug:string:unique:index`)

## Entity Stack (Full File List)

| File | Purpose | Location |
|------|---------|----------|
| `model.php` | Eloquent model with traits, casts, relationships | `app/Models/` |
| `enum.php` | Backed string enum with label/color helpers | `app/Enums/` |
| `state.php` | Abstract state class (Spatie Model States) | `app/States/` |
| `state-concrete.php` | Concrete state classes (one per state) | Same directory as abstract state |
| `migration.php` | Database migration with indexes & foreign keys | `database/migrations/` |
| `factory.php` | Model factory with states for each status | `database/factories/` |
| `seeder.php` | Seeder creating realistic demo data | `database/seeders/` |
| `policy.php` | Authorization policy extending BasePolicy | `app/Policies/` |
| `observer.php` | Lifecycle observer extending BaseObserver | `app/Observers/` |
| `filament-resource.php` | Filament v4 Resource class | `app/Filament/Resources/{Plural}/` |
| `filament-form.php` | Form schema (separate class) | `app/Filament/Resources/{Plural}/Schemas/` |
| `filament-table.php` | Table schema (separate class) | `app/Filament/Resources/{Plural}/Tables/` |
| `filament-pages/` | List, Create, Edit, View pages | `app/Filament/Resources/{Plural}/Pages/` |
| `api-controller.php` | RESTful API controller | `app/Http/Controllers/Api/` |
| `api-requests.php` | Store & Update Form Requests | `app/Http/Requests/` |
| `api-resource.php` | Eloquent API Resource | `app/Http/Resources/` |
| `exporter.php` | Filament v4 Exporter for CSV export | `app/Filament/Exporters/` |
| `widgets/stats-overview.php` | StatsOverviewWidget (counts, KPIs) | `app/Filament/Widgets/` |
| `widgets/chart.php` | ChartWidget (doughnut/bar/line) | `app/Filament/Widgets/` |
| `widgets/table.php` | TableWidget (upcoming deadlines, etc.) | `app/Filament/Widgets/` |
| `notifications/assigned.php` | Assignment notification | `app/Notifications/` |
| `notifications/status-changed.php` | Status change notification | `app/Notifications/` |
| `pdf/single-report.blade.php` | PDF template for single record | `resources/views/pdf/` |
| `pdf/list-report.blade.php` | PDF template for list/table view | `resources/views/pdf/` |
| `test.php` | Feature test with CRUD, auth, scopes | `tests/Feature/Entities/` |

## Key Patterns

### 1. Model Traits (Pick What You Need)
- `HasEntityEvents` — Dispatches `EntityCreated`, `EntityUpdated`, `EntityDeleted` events
- `HasAuditTrail` — Spatie Activity Log integration for who-changed-what-when
- `HasStandardScopes` — `active()`, `inactive()`, `recent()`, `search()` query scopes
- `HasTagging` — Polymorphic tagging system
- `HasSearchableFields` — Laravel Scout full-text search
- `HasStates` — Spatie Model States for state machines

### 2. Model Contracts (Implement Based on Traits)
- `Auditable` — Required when using `HasAuditTrail`
- `HasEntityLifecycle` — Required when using `HasEntityEvents`
- `Stateful` — Required when using `HasStates`
- `Taggable` — Required when using `HasTagging`

### 3. Filament v4 Conventions
- `Section` → `Filament\Schemas\Components\Section` (NOT Forms)
- `Grid` → `Filament\Schemas\Components\Grid` (NOT Forms)
- Form components → `Filament\Forms\Components\*`
- Actions → `Filament\Actions\*`
- `$navigationGroup` type is `string|UnitEnum|null`
- `ChartWidget::$heading` is non-static instance property

### 4. State Machine Pattern
- Abstract base: `{Entity}State extends Spatie\ModelStates\State`
- Each concrete state: `label()`, `color()`, `icon()`
- Transitions defined in `config()` static method
- Cast via `casts()` method on model

### 5. Observer Pattern
- Extends `Aicl\Observers\BaseObserver`
- Type hint parameter as `Model`, then use `/** @var Entity $model */` docblock
- Use `NotificationDispatcher` for sending notifications (not `$user->notify()`)
- Log activities via `activity()->performedOn($model)->log()`

### 6. Registration (Phase 5 — AFTER Phase 4 Validation)

**IMPORTANT:** Registration is a separate pipeline phase (Phase 5). It MUST happen AFTER validation (Phase 4) passes — never before. See F-002 in `failures.md` for why this matters.

The Architect wires up the entity in Phase 5:

**`app/Providers/AppServiceProvider.php`** — Add to `boot()` method:
```php
Gate::policy(Entity::class, EntityPolicy::class);
Entity::observe(EntityObserver::class);
```

**`routes/api.php`** — Add API routes:
```php
Route::middleware(['api', 'auth:api'])->prefix('v1')->group(function () {
    Route::apiResource('entities', EntityController::class)
        ->parameters(['entities' => 'record']);
});
```

**`AdminPanelProvider`** — Filament resources in `app/Filament/Resources/` are auto-discovered via `->discoverResources()` — no manual registration needed (ensure the `discoverResources()` call exists).

After registration, Phase 6 (RE-VALIDATE) runs RLM + Tester again to confirm nothing broke from the wiring. Then Phase 7 (VERIFY) runs the full test suite.

### 7. Registration Verification Checklist (Phase 5)

After wiring, the Architect must verify:
- [ ] `Gate::policy(Entity::class, EntityPolicy::class)` in AppServiceProvider::boot()
- [ ] `Entity::observe(EntityObserver::class)` in AppServiceProvider::boot()
- [ ] API routes added to `routes/api.php` with `['api', 'auth:api']` middleware
- [ ] Filament resource is auto-discovered (check `discoverResources()` in AdminPanelProvider)

## Namespace Convention

- **Package code** (base infrastructure): `Aicl\` namespace, lives in `packages/aicl/`
- **Generated entities** (client code): `App\` namespace, lives in `app/` and `database/`
- The `aicl:make-entity` command generates ALL files into `app/` (models, policies, observers, Filament resources, API controllers)
- Generated models live in `App\Models\`, policies in `App\Policies\`, observers in `App\Observers\`
- The golden entity (Project) also lives in `app/` to demonstrate the actual generated pattern

## File Placement Rules

| What | Location | NEVER |
|------|----------|-------|
| Models | `app/Models/` | `packages/aicl/` |
| Enums | `app/Enums/` | `packages/aicl/` |
| States | `app/States/` | `packages/aicl/` |
| Policies | `app/Policies/` | `packages/aicl/` |
| Observers | `app/Observers/` | `packages/aicl/` |
| Filament Resources | `app/Filament/Resources/{Plural}/` | `packages/aicl/` |
| API Controllers | `app/Http/Controllers/Api/` | `packages/aicl/` |
| Form Requests | `app/Http/Requests/` | `packages/aicl/` |
| API Resources | `app/Http/Resources/` | `packages/aicl/` |
| Exporters | `app/Filament/Exporters/` | `packages/aicl/` |
| Widgets | `app/Filament/Widgets/` | `packages/aicl/` |
| Notifications | `app/Notifications/` | `packages/aicl/` |
| PDF Templates | `resources/views/pdf/` | `packages/aicl/` |
| Migrations | `database/migrations/` | `packages/aicl/` |
| Factories | `database/factories/` | `packages/aicl/` |
| Seeders | `database/seeders/` | `packages/aicl/` |
| Tests | `tests/Feature/Entities/` | `packages/aicl/` |
