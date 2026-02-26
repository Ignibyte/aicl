# AICL World Model — Machine-Readable Pattern Reference

**Last Updated:** 2026-02-06
**Owner:** `/rlm`
**Golden Entity:** Project (100% score)

This document is the **canonical source of truth** for what correct AICL entity code looks like. Every pattern is derived from the golden entity (Project). AI agents MUST reference this document before generating or modifying entity code.

---

## File Manifest

Every AICL entity produces this file set. There are two location contexts:

- **Golden Location** — Where the package reference entity (Project) lives (`Aicl\` namespace). Golden example code is now served via Forge MCP `search-patterns`.
- **Generated Location** — Where the `/generate` pipeline places ALL new entities. **Everything goes into client space (`app/`). Nothing goes into `packages/aicl/`.**

**CRITICAL RULE:** The generation pipeline NEVER modifies `packages/aicl/`. The golden examples (served via Forge MCP `search-patterns`) show patterns; the Generated Location shows where files actually go. See `.claude/planning/framework/reference/code-generation-system.md` for full rationale.

**NOTE:** `aicl:make-entity` generates ALL entity files into `app/` with `App\` namespace. Models go to `app/Models/`, Policies to `app/Policies/`, Observers to `app/Observers/`. Nothing is ever written to `packages/aicl/`.

| # | Target | File | Golden Location (reference only) | Generated Location (target) | Required |
|---|--------|------|----------------------------------|-----------------------------|----------|
| 1 | `model` | `{Name}.php` | `packages/aicl/src/Models/` | `app/Models/` | Yes |
| 2 | `migration` | `*_create_{table}_table.php` | `database/migrations/` | `database/migrations/` | Yes |
| 3 | `factory` | `{Name}Factory.php` | `database/factories/` | `database/factories/` | Yes |
| 4 | `seeder` | `{Name}Seeder.php` | `database/seeders/` | `database/seeders/` | Yes |
| 5 | `policy` | `{Name}Policy.php` | `packages/aicl/src/Policies/` | `app/Policies/` | Yes |
| 6 | `observer` | `{Name}Observer.php` | `packages/aicl/src/Observers/` | `app/Observers/` | Yes |
| 7 | `filament` | `{Name}Resource.php` | `packages/aicl/src/Filament/Resources/{Plural}/` | `app/Filament/Resources/{Plural}/` | Yes |
| 8 | `form` | `{Name}Form.php` | `packages/aicl/src/Filament/Resources/{Plural}/Schemas/` | `app/Filament/Resources/{Plural}/Schemas/` | Yes |
| 9 | `table` | `{Plural}Table.php` | `packages/aicl/src/Filament/Resources/{Plural}/Tables/` | `app/Filament/Resources/{Plural}/Tables/` | Yes |
| 10 | `pages` | `List/Create/Edit/View{Name}.php` | `packages/aicl/src/Filament/Resources/{Plural}/Pages/` | `app/Filament/Resources/{Plural}/Pages/` | Yes |
| 11 | `controller` | `{Name}Controller.php` | `packages/aicl/src/Http/Controllers/Api/` | `app/Http/Controllers/Api/` | If API |
| 12 | `api_resource` | `{Name}Resource.php` | `packages/aicl/src/Http/Resources/` | `app/Http/Resources/` | If API |
| 13 | `store_request` | `Store{Name}Request.php` | `packages/aicl/src/Http/Requests/` | `app/Http/Requests/` | If API |
| 14 | `update_request` | `Update{Name}Request.php` | `packages/aicl/src/Http/Requests/` | `app/Http/Requests/` | If API |
| 15 | `exporter` | `{Name}Exporter.php` | `packages/aicl/src/Filament/Exporters/` | `app/Filament/Exporters/` | If Export |
| 16 | `test` | `{Name}Test.php` | `tests/Feature/Entities/` | `tests/Feature/Entities/` | Yes |
| 17 | `api_test` | `{Name}ApiTest.php` | `tests/Feature/Entities/Api/` | `tests/Feature/Entities/Api/` | If API |

### Namespace Mapping

| Layer | Golden (Package — reference only) | Generated (Client — target) |
|-------|-----------------------------------|-----------------------------|
| Model | `Aicl\Models\` | `App\Models\` |
| Policy | `Aicl\Policies\` | `App\Policies\` |
| Observer | `Aicl\Observers\` | `App\Observers\` |
| Filament Resource | `Aicl\Filament\Resources\` | `App\Filament\Resources\` |
| API Controller | `Aicl\Http\Controllers\Api\` | `App\Http\Controllers\Api\` |
| Form Requests | `Aicl\Http\Requests\` | `App\Http\Requests\` |
| API Resource | `Aicl\Http\Resources\` | `App\Http\Resources\` |
| Exporter | `Aicl\Filament\Exporters\` | `App\Filament\Exporters\` |

---

## Dual-Source Architecture: Golden Examples (Forge MCP) vs Scaffolding

The AICL entity system has **two sources of pattern truth** that must stay in sync:

### 1. Golden Examples (Forge MCP)
- **Purpose:** Annotated teaching reference for AI agents
- **Contains:** Golden example code for each component type (model, migration, factory, policy, observer, resource, test, etc.)
- **Accessed via:** Forge MCP `search-patterns` tool (e.g., `component_type=model`) — NOT local files
- **Includes:** Production-quality patterns with annotations explaining WHY
- **Format:** Served dynamically from the Forge knowledge base

### 2. Scaffolding Command (`MakeEntityCommand`)
- **Purpose:** Code generator that produces actual entity files
- **Contains:** PHP heredoc templates inside the artisan command (~1000 lines)
- **Used by:** `php artisan aicl:make-entity {Name}` — produces 18 files
- **Includes:** Only the minimal common structure (no entity-specific customizations)
- **Format:** String templates with `{Name}`, `{table}`, `{snake}` placeholders

### Relationship
- The golden examples (via Forge MCP) are the **superset** — they show the full, customized, production entity patterns
- The scaffolding is the **subset** — it generates the common skeleton that agents then customize
- Both must pass the same 42 RLM validation patterns (40 base + 2 media), plus up to 8 frontend patterns when applicable
- `aicl:validate` is the **synchronization check** — if either source drifts, validation will catch it

### Frontend Patterns (Conditional — Phase 3.5 Entities)
- **8 additional patterns** validated when entity has widgets, PDF, or custom views
- Defined in `.claude/planning/rlm/patterns/frontend.md`
- Cover: theme tokens, component reuse, responsive layout, dark mode, accessibility, section grouping, PDF branding, widget consistency
- Total possible patterns: up to 50 (42 base/media + 8 frontend)

### Drift Prevention Rules
1. When fixing a scaffolding bug in `MakeEntityCommand`, check if the golden examples in Forge need the same fix
2. When adding a new pattern to the golden examples in Forge, check if `MakeEntityCommand` should generate it
3. The `/rlm` agent is responsible for flagging drift between the two sources
4. Failures are logged to Forge MCP via `report-failure` for future prevention

---

## Model Rules

**Namespace:** `App\Models` (generated entities) or `Aicl\Models` (package reference)
**Extends:** `Illuminate\Database\Eloquent\Model`
**Golden file:** `app/Models/Project.php`

### Required Structure

```php
<?php

namespace App\Models;

// Contract imports (based on traits selected)
use Aicl\Contracts\Auditable;
use Aicl\Contracts\HasEntityLifecycle;
// Trait imports
use Aicl\Traits\HasAuditTrail;
use Aicl\Traits\HasEntityEvents;
use Aicl\Traits\HasStandardScopes;
// Framework imports
use Database\Factories\{Name}Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $name
 * ... (all columns)
 * @property-read User $owner
 */
class {Name} extends Model implements Auditable, HasEntityLifecycle
{
    /** @use HasFactory<{Name}Factory> */
    use HasAuditTrail;
    use HasEntityEvents;
    use HasFactory;
    use HasStandardScopes;
    use SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'name',
        // ... entity-specific columns
        'is_active',
        'owner_id',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            // ... entity-specific casts
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    protected static function newFactory(): {Name}Factory
    {
        return {Name}Factory::new();
    }
}
```

### Mandatory Checklist
- [ ] Namespace is `App\Models` (generated) or `Aicl\Models` (package)
- [ ] Extends `Model`
- [ ] Implements at least `Auditable` and `HasEntityLifecycle` contracts
- [ ] Uses `HasAuditTrail`, `HasEntityEvents`, `HasFactory`, `HasStandardScopes`, `SoftDeletes` traits
- [ ] `$fillable` is `protected` with `@var list<string>` annotation
- [ ] `casts()` is a method (NOT `$casts` property — Laravel 11 convention)
- [ ] `owner()` returns `BelongsTo` with explicit return type + PHPDoc generics
- [ ] `newFactory()` returns concrete factory class (required for package models)
- [ ] All relationship methods have explicit return type declarations
- [ ] PHPDoc block with `@property` for all columns and `@property-read` for relationships

### Optional Traits (AI Decision Rules)
| Condition | Add Trait | Add Contract |
|-----------|-----------|-------------|
| Entity has lifecycle states (draft→active→complete) | `HasStates` (spatie) | `Stateful` |
| Entity is categorized/tagged | `HasTagging` | `Taggable` |
| Entity is frequently searched by users | `HasSearchableFields` | `Searchable` |

---

## Migration Rules

**Pattern:** Anonymous class (Laravel 11)
**Golden file:** `database/migrations/2026_02_05_180728_create_projects_table.php`

### Required Structure

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('{table}', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            // ... entity-specific columns
            $table->boolean('is_active')->default(true);
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // Strategic indexes on frequently filtered columns
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('{table}');
    }
};
```

### Mandatory Checklist
- [ ] Anonymous class (`return new class extends Migration`)
- [ ] `$table->id()` primary key
- [ ] `$table->timestamps()`
- [ ] `$table->softDeletes()`
- [ ] `$table->foreignId('owner_id')->constrained('users')->cascadeOnDelete()`
- [ ] `down()` method with `Schema::dropIfExists()`
- [ ] Indexes on columns used in filters (status, priority, is_active)

### Column Type Mapping
| PHP Type | Migration Column | Cast |
|----------|-----------------|------|
| `string` | `$table->string('name')` | none (default) |
| `?string` | `$table->text('description')->nullable()` | none |
| `bool` | `$table->boolean('is_active')->default(true)` | `'boolean'` |
| `int` (FK) | `$table->foreignId('owner_id')` | none |
| `float` (money) | `$table->decimal('budget', 12, 2)->nullable()` | `'decimal:2'` |
| `Carbon` (date only) | `$table->date('start_date')->nullable()` | `'date'` |
| `Carbon` (datetime) | `$table->dateTime('due_at')->nullable()` | `'datetime'` |
| `BackedEnum` | `$table->string('priority')` | `EnumClass::class` |
| `State` (spatie) | `$table->string('status')->default('draft')` | `StateClass::class` |
| `array` | `$table->json('metadata')->nullable()` | `'array'` |

### Smart Scaffolder Field Types

The `aicl:make-entity --fields` option uses these type names to deterministically generate migration columns, casts, form components, table columns, validation rules, and faker data:

| Scaffolder Type | Migration | Cast | Filament Form | Faker | Validation |
|-----------------|-----------|------|---------------|-------|------------|
| `string` | `->string()` | none | `TextInput` | `fake()->sentence(3)` | `['required', 'string', 'max:255']` |
| `text` | `->text()->nullable()` | none | `RichEditor` | `fake()->paragraph()` | `['nullable', 'string']` |
| `integer` | `->integer()` | none | `TextInput->numeric()` | `fake()->randomNumber(4)` | `['required', 'integer']` |
| `float` | `->decimal(12,2)->nullable()` | `'decimal:2'` | `TextInput->numeric()` | `fake()->randomFloat(2, 100, 50000)` | `['nullable', 'numeric', 'min:0']` |
| `boolean` | `->boolean()->default(true)` | `'boolean'` | `Toggle->default(true)` | `true` | `['boolean']` |
| `date` | `->date()->nullable()` | `'date'` | `DatePicker` | `fake()->dateTimeBetween(...)` | `['nullable', 'date']` |
| `datetime` | `->dateTime()->nullable()` | `'datetime'` | `DateTimePicker` | `fake()->dateTimeBetween(...)` | `['nullable', 'date']` |
| `enum` | `->string()->default(first)` | `EnumClass::class` | `Select->options(Enum)` | `fake()->randomElement(Enum::cases())` | `['required', Rule::enum(Enum)]` |
| `json` | `->json()->nullable()` | `'array'` | `KeyValue` | `['key' => 'val']` | `['nullable', 'array']` |
| `foreignId` | `->foreignId()->constrained()` | none | `Select->relationship()` | `Model::factory()` | `['required', 'exists:table,id']` |

**Modifiers:** `nullable`, `unique`, `default(value)`, `index` — applied as `name:type:modifier1:modifier2`.

---

## Factory Rules

**Namespace:** `Database\Factories`
**Golden file:** `database/factories/ProjectFactory.php`

### Required Structure

```php
<?php

namespace Database\Factories;

use App\Models\{Name};
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<{Name}> */
class {Name}Factory extends Factory
{
    protected $model = {Name}::class;

    public function definition(): array
    {
        return [
            'name' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'is_active' => true,
            'owner_id' => User::factory(),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }

    // Additional states per entity...
}
```

### Mandatory Checklist
- [ ] Namespace is `Database\Factories`
- [ ] `@extends Factory<{Name}>` PHPDoc
- [ ] `protected $model` set to model class
- [ ] `definition()` returns array with `fake()` helper (NOT `$this->faker`)
- [ ] `owner_id` uses `User::factory()` for automatic relationship creation
- [ ] `inactive()` state method for `is_active` toggle

### State Method Rules
| Entity Has... | Required State Methods |
|---------------|----------------------|
| `is_active` boolean | `inactive()` |
| State machine (spatie) | One method per state: `draft()`, `active()`, `completed()`, etc. |
| Priority enum | `highPriority()`, `critical()` |
| Date-based logic | Scenario states: `overdue()`, `upcoming()` |

### Fake Data Selection
| Field Type | Faker Method |
|------------|-------------|
| Name/title | `fake()->sentence(3)` or `fake()->company()` |
| Description | `fake()->paragraph()` or `fake()->paragraph(3)` |
| Email | `fake()->safeEmail()` |
| URL | `fake()->url()` |
| Money | `fake()->optional(0.7)->randomFloat(2, 1000, 500000)` |
| Date range | `$start = fake()->dateTimeBetween('-6 months', '+1 month')` then `fake()->dateTimeBetween($start, '+12 months')` |
| Enum | `fake()->randomElement(EnumClass::cases())` |
| Boolean (default true) | `true` (not randomized) |

---

## Policy Rules

**Namespace:** `App\Policies` (generated entities) or `Aicl\Policies` (package reference)
**Extends:** `Aicl\Policies\BasePolicy`
**Golden file:** `app/Policies/ProjectPolicy.php`

### Required Structure

```php
<?php

namespace App\Policies;

use Aicl\Policies\BasePolicy;
use App\Models\{Name};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User;

class {Name}Policy extends BasePolicy
{
    protected function permissionPrefix(): string
    {
        return '{Name}';
    }

    public function view(User $user, Model $record): bool
    {
        /** @var {Name} $record */
        if ($record->owner_id === $user->getKey()) {
            return true;
        }

        return parent::view($user, $record);
    }

    public function update(User $user, Model $record): bool
    {
        /** @var {Name} $record */
        if ($record->owner_id === $user->getKey()) {
            return true;
        }

        return parent::update($user, $record);
    }

    public function delete(User $user, Model $record): bool
    {
        /** @var {Name} $record */
        if ($record->owner_id === $user->getKey()) {
            return true;
        }

        return parent::delete($user, $record);
    }
}
```

### Mandatory Checklist
- [ ] Extends `BasePolicy` (inherits `viewAny`, `create`, `restore`, `forceDelete`, etc.)
- [ ] Implements `permissionPrefix()` returning entity name string
- [ ] Overrides `view()`, `update()`, `delete()` with owner check
- [ ] Uses `Model $record` parameter type (NOT concrete entity type — for polymorphic compatibility)
- [ ] Uses `$user->getKey()` (NOT `$user->id` — for UUID/int compatibility)
- [ ] Includes `/** @var {Name} $record */` docblock for static analysis
- [ ] Owner check is first (`if ($record->owner_id === $user->getKey()) return true`)
- [ ] Falls back to `parent::method()` for Shield permission check

### Permission Format
```
{Action}:{Prefix}
```
Actions: `ViewAny`, `View`, `Create`, `Update`, `Delete`, `Restore`, `ForceDelete`, `RestoreAny`, `ForceDeleteAny`, `Replicate`, `Reorder`

---

## Observer Rules

**Namespace:** `App\Observers` (generated entities) or `Aicl\Observers` (package reference)
**Extends:** `Aicl\Observers\BaseObserver`
**Golden file:** `app/Observers/ProjectObserver.php`

### Required Structure

```php
<?php

namespace App\Observers;

use Aicl\Observers\BaseObserver;
use App\Models\{Name};
use Illuminate\Database\Eloquent\Model;

class {Name}Observer extends BaseObserver
{
    public function created(Model $model): void
    {
        /** @var {Name} $model */
        activity()
            ->performedOn($model)
            ->log('{Name} "{$model->name}" was created');
    }

    public function deleted(Model $model): void
    {
        /** @var {Name} $model */
        activity()
            ->performedOn($model)
            ->log('{Name} "{$model->name}" was deleted');
    }
}
```

### Mandatory Checklist
- [ ] Extends `BaseObserver`
- [ ] `Model $model` parameter type (NOT concrete type)
- [ ] `/** @var {Name} $model */` docblock in each method
- [ ] `created()` logs activity with `activity()->performedOn($model)->log()`
- [ ] `deleted()` logs activity
- [ ] Log messages include model name attribute: `'{Name} "{$model->name}" was deleted'`

### Optional Observer Methods (AI Decision Rules)
| Entity Has... | Add Method |
|---------------|-----------|
| State machine | `updating()` — log state transitions with old/new properties |
| Owner assignment | `updated()` — notify new owner when `owner_id` changes |
| Status notifications | `updated()` — notify owner on status change |

### Advanced Pattern: Status Transition Logging
```php
public function updating(Model $model): void
{
    /** @var {Name} $model */
    if ($model->isDirty('status')) {
        activity()
            ->performedOn($model)
            ->withProperties([
                'old_status' => $model->getOriginal('status'),
                'new_status' => (string) $model->status,
            ])
            ->log("{Name} status changed to {$model->status->label()}");
    }
}
```

---

## Filament Resource Rules

**Generated Namespace:** `App\Filament\Resources\{Plural}`
**Golden Namespace:** `App\Filament\Resources\Projects`
**Golden file:** `app/Filament/Resources/Projects/ProjectResource.php`

### Required Structure

```php
<?php

// Generated entities use App\ namespace; golden entity uses Aicl\ namespace
namespace App\Filament\Resources\{Plural};

use Aicl\Models\{Name};
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class {Name}Resource extends Resource
{
    protected static ?string $model = {Name}::class;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;
    protected static string|UnitEnum|null $navigationGroup = 'Content';
    protected static ?int $navigationSort = 10;
    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return {Name}Form::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return {Plural}Table::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => List{Plural}::route('/'),
            'create' => Create{Name}::route('/create'),
            'view' => View{Name}::route('/{record}'),
            'edit' => Edit{Name}::route('/{record}/edit'),
        ];
    }
}
```

### Mandatory Checklist
- [ ] `$navigationIcon` type is `string|BackedEnum|null` (Heroicon enum)
- [ ] `$navigationGroup` type is `string|UnitEnum|null`
- [ ] Form delegated to `{Name}Form::configure($schema)`
- [ ] Table delegated to `{Plural}Table::configure($table)`
- [ ] Four pages: List, Create, View, Edit

### Form Schema Rules (Filament v4)
- [ ] Layout imports: `Filament\Schemas\Components\Section`, `Filament\Schemas\Components\Grid`
- [ ] Form imports: `Filament\Forms\Components\TextInput`, `Select`, `Toggle`, etc.
- [ ] Fields grouped in `Section` components
- [ ] `Grid` for multi-column layouts within sections
- [ ] `name` field: `TextInput::make('name')->required()->maxLength(255)`
- [ ] `description`: `RichEditor::make('description')->columnSpanFull()`
- [ ] `owner_id`: `Select::make('owner_id')->relationship('owner', 'name')->required()->searchable()->preload()`
- [ ] `is_active`: `Toggle::make('is_active')->default(true)`

### Table Schema Rules
- [ ] `name` column: `->searchable()->sortable()->weight('bold')`
- [ ] Enum columns: `->badge()->color(fn($state) => $state->color())`
- [ ] Relationship columns: `TextColumn::make('owner.name')`
- [ ] Date columns: `->date()` or `->dateTime()`
- [ ] `defaultSort('created_at', 'desc')`
- [ ] Filters: status, owner, is_active (TernaryFilter)
- [ ] Secondary columns: `->toggleable(isToggledHiddenByDefault: true)`

---

## API Controller Rules

**Generated Namespace:** `App\Http\Controllers\Api`
**Golden Namespace:** `App\Http\Controllers\Api`
**Golden file:** `app/Http/Controllers/Api/ProjectController.php`

### Mandatory Checklist
- [ ] Uses Form Request classes (NOT inline `$request->validate()`)
- [ ] `index()` returns `AnonymousResourceCollection`
- [ ] `index()` eager loads relationships: `->with('owner')`
- [ ] `index()` uses `->when()` for optional filters
- [ ] `store()` uses `Store{Name}Request`, returns `201` status
- [ ] `show()` uses `Gate::authorize('view', $record)`
- [ ] `update()` uses `Update{Name}Request`
- [ ] `destroy()` uses `Gate::authorize('delete', $record)`, returns `200` with message

### Form Request Rules

**Generated Namespace:** `App\Http\Requests`
**Golden Namespace:** `App\Http\Requests`
**Golden files:** `app/Http/Requests/StoreProjectRequest.php`, `app/Http/Requests/UpdateProjectRequest.php`

- [ ] `Store{Name}Request`: `authorize()` checks `create` permission via `$this->user()->can('create', {Name}::class)`
- [ ] `Update{Name}Request`: `authorize()` checks `update` permission via `$this->user()->can('update', $this->route('{snake}'))`
- [ ] Array-style validation rules (NOT string pipe-delimited)
- [ ] `'sometimes'` on update rules for partial updates
- [ ] `Rule::enum()` for enum fields
- [ ] Custom `messages()` for user-facing error text
- [ ] FK validation: `'exists:users,id'`
- [ ] Relationship arrays validated: `'member_ids' => ['nullable', 'array']`, `'member_ids.*' => ['integer', 'exists:users,id']`

---

## Test Rules

**Namespace:** `Tests\Feature\Entities`
**Framework:** PHPUnit (NOT Pest)
**Golden file:** `tests/Feature/Entities/ProjectTest.php`

### Required Structure

```php
<?php

namespace Tests\Feature;

use Aicl\Models\{Name};
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class {Name}Test extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        $this->seedPermissions();
    }

    protected function seedPermissions(): void
    {
        $permissions = [
            'ViewAny:{Name}', 'View:{Name}', 'Create:{Name}',
            'Update:{Name}', 'Delete:{Name}',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin->syncPermissions(Permission::where('guard_name', 'web')->get());
    }

    // ... test methods
}
```

### Minimum Required Tests
| # | Test | Validates |
|---|------|-----------|
| 1 | `test_{snake}_can_be_created` | Factory + database persistence |
| 2 | `test_{snake}_belongs_to_owner` | Owner relationship |
| 3 | `test_{snake}_soft_deletes` | SoftDeletes trait |
| 4 | `test_owner_can_view_own_{snake}` | Policy owner check |
| 5 | `test_admin_can_manage_any_{snake}` | Policy admin permissions |
| 6 | `test_{snake}_creation_is_logged` | HasAuditTrail (if trait used) |
| 7 | `test_entity_events_are_dispatched` | HasEntityEvents (if trait used) |
| 8 | `test_active_scope_filters_correctly` | HasStandardScopes (if trait used) |
| 9 | `test_search_scope_finds_matching_records` | HasStandardScopes search (if used) |

### Test Table Name
Use `Str::snake(Str::pluralStudly($name))` for the table name — NOT a hardcoded `{snake}s` suffix. Irregular plurals (e.g., `Person` → `people`, `Category` → `categories`) must be handled correctly.

---

## Seeder Rules

**Namespace:** `Database\Seeders`
**Golden file:** `database/seeders/ProjectSeeder.php`

### Required Structure
```php
<?php

namespace Database\Seeders;

use Aicl\Models\{Name};
use App\Models\User;
use Illuminate\Database\Seeder;

class {Name}Seeder extends Seeder
{
    public function run(): void
    {
        $owner = User::first() ?? User::factory()->create();

        {Name}::factory()
            ->count(5)
            ->create(['owner_id' => $owner->id]);
    }
}
```

---

## Widget Decision Rules

| Entity Has... | Widget Type | Class |
|---------------|------------|-------|
| Status field (enum/state) | Stats overview (count per status) | `StatsOverviewWidget` |
| Status field (enum/state) | Doughnut chart (distribution) | `ChartWidget` with `getType() → 'doughnut'` |
| Date field + status | Upcoming deadlines table | `TableWidget` |
| Monetary field | KPI card (budget vs spent) | `StatsOverviewWidget` |
| HasMany relationship | Count stat card | `StatsOverviewWidget` |
| `created_at` | Trend line over time | `ChartWidget` with `getType() → 'line'` |

### Widget Pattern Notes
- `ChartWidget::$heading` is a non-static instance property (`protected ?string $heading`)
- Use `getType()` returning `'doughnut'` — `DoughnutChartWidget` is deprecated in Filament v4
- Include `#[On('entity-changed')]` listener for real-time refresh
- Set `pollingInterval = '60s'` for near-real-time updates

---

## Exporter Rules (Filament Native Export)

**Generated Namespace:** `App\Filament\Exporters`
**Golden Namespace:** `App\Filament\Exporters`
**Golden file:** `app/Filament/Exporters/ProjectExporter.php`

> **Important:** AICL uses Filament v4 native export (OpenSpout). Do NOT use `league/csv` — it has been removed as a dependency.

### Required Structure

```php
<?php

namespace App\Filament\Exporters;

use Aicl\Models\{Name};
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class {Name}Exporter extends Exporter
{
    protected static ?string $model = {Name}::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')->label('ID'),
            ExportColumn::make('name'),
            // Enum/state columns must format to string:
            ExportColumn::make('status')
                ->formatStateUsing(fn ($state) => $state instanceof \BackedEnum ? $state->value : $state),
            ExportColumn::make('owner.name')->label('Owner'),
            ExportColumn::make('created_at'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        return 'Your {name} export with ' . number_format($export->successful_rows) . ' rows is ready.';
    }
}
```

### Mandatory Checklist
- [ ] Extends `Filament\Actions\Exports\Exporter`
- [ ] `$model` set to entity model class
- [ ] `getColumns()` returns array of `ExportColumn` instances
- [ ] Enum/state columns use `->formatStateUsing()` to convert to string values
- [ ] Relationship columns use dot notation: `ExportColumn::make('owner.name')`
- [ ] `getCompletedNotificationBody()` returns user-friendly completion message

### Table Integration
- [ ] Header action: `ExportAction::make()->exporter({Name}Exporter::class)` in `headerActions()`
- [ ] Bulk action: `ExportBulkAction::make()->exporter({Name}Exporter::class)` in `toolbarActions()` BulkActionGroup
- [ ] Imports: `Filament\Actions\ExportAction`, `Filament\Actions\ExportBulkAction`

### AI Decision: When to Generate Exporter
| Condition | Generate Exporter? |
|-----------|-------------------|
| Entity has a table/list view | Yes |
| Entity is admin-only config | No |
| Entity has > 5 columns | Yes (column selection is useful) |

---

## Component Registry (SDC Architecture)

The AICL component system uses a **Single Directory Component (SDC)** architecture where each `<x-aicl-*>` Blade component is co-located in a single directory with its PHP class, Blade template, optional JS module, and a `component.json` manifest.

**Registry query:** `app(ComponentRegistry::class)` or `artisan aicl:components list|show|recommend|tree`

### Component Categories (9)

| Category | Components | When to Use |
|----------|-----------|-------------|
| **metric** | stat-card, kpi-card, trend-card, progress-card | Dashboard metrics, KPIs, progress tracking |
| **data** | metadata-list, info-card, avatar | Structured data display, user presence |
| **collection** | data-table | Lists, grids, paginated data sets |
| **action** | action-bar, quick-action, dropdown, command-palette, combobox | User actions, selections, global search |
| **status** | status-badge, badge | Status indicators, labels, tags |
| **timeline** | timeline | Audit logs, activity feeds, histories |
| **layout** | split-layout, card-grid, stats-row, empty-state, auth-split-layout, accordion, accordion-item, tabs, tab-panel, drawer | Page structure, content organization |
| **feedback** | alert-banner, modal, toast | Alerts, dialogs, notifications |
| **utility** | spinner, divider, tooltip, ignibyte-logo | UI utilities, loading states, separators |

### Context Rules (AI Decision Key)

| Context | Meaning | Use Component? |
|---------|---------|---------------|
| `blade` | Standard Blade views (public-facing, CMS) | YES — use `<x-aicl-*>` |
| `livewire` | Livewire component views | YES — use `<x-aicl-*>` |
| `filament-widget` | Filament dashboard widget views | YES — use `<x-aicl-*>` in widget Blade |
| `filament-form` | Filament form schema (PHP) | NO — use Filament form components |
| `filament-table` | Filament table schema (PHP) | NO — use Filament table columns |
| `email` | Email templates | SOME — only static components (no JS) |
| `pdf` | PDF templates | SOME — only static components (no JS) |

### Composition Hierarchy

Components declare valid parents via `composable_in` in their `component.json`:
- **`stats-row`** accepts: stat-card, kpi-card, trend-card, progress-card, status-badge
- **`card-grid`** accepts: info-card, stat-card, kpi-card, trend-card, progress-card, status-badge, metadata-list, timeline, alert-banner, empty-state, action-bar, divider, tabs, badge, avatar, accordion
- **`split-layout`** accepts: Most content components (all except overlays/global)
- **`info-card`** accepts: metadata-list, status-badge, badge, avatar
- **`metadata-list`** accepts: status-badge, badge, avatar
- **`accordion`** accepts: accordion-item
- **`tabs`** accepts: tab-panel
- **`action-bar`** accepts: quick-action

### Filament Crosswalk

When generating code for **Filament admin context**, DO NOT use `<x-aicl-*>` components in form/table schemas. Instead:

| AICL Component | Filament Equivalent | When |
|---------------|--------------------|----- |
| stat-card / kpi-card | `StatsOverviewWidget` | Dashboard widgets |
| status-badge | `TextColumn::make()->badge()` | Table columns |
| data-table | `Filament\Tables\Table` | Admin tables |
| combobox | `Select::make()->searchable()` | Form fields |
| modal | `Filament\Actions\Action::make()->modal()` | Action modals |
| accordion | `Section::make()->collapsible()` | Form sections |
| tabs | `Filament\Schemas\Components\Tabs` | Form/infolist tabs |
| command-palette | Built-in global search | Admin search |
| tooltip | Native `->tooltip()` method | Column/action hints |

### Field Signal Engine

The `FieldSignalEngine` maps entity field patterns to component recommendations:

| Field Pattern | Recommended Component | Confidence |
|--------------|----------------------|-----------|
| `status:enum` or `*_status:enum` | status-badge | 0.95 |
| `progress:integer` | progress-card | 0.95 |
| `starts_at + ends_at` (datetime range) | data-table | 0.95 |
| `target + actual` (numeric pair) | kpi-card | 0.90 |
| `*_count:integer` or `total_*:integer` | stat-card | 0.90 |
| `budget:float`, `amount:float`, etc. | stat-card | 0.80 |
| `is_*:boolean` | status-badge | 0.70 |
| `*_at:datetime` or `*_date:date` | trend-card | 0.60 |

Query: `artisan aicl:components recommend --fields="name:string,status:enum,budget:float"`

---

## Quality Pipeline

After generating any entity, run this sequence:

```bash
# 1. Format code
ddev exec vendor/bin/pint --dirty --format agent

# 2. Validate patterns (target: 100%)
ddev artisan aicl:validate {Name}

# 3. Run entity tests
ddev artisan test --compact --filter={Name}Test

# 4. Run full suite (confirm no regressions)
ddev artisan test --compact
```

All steps must pass before an entity is considered complete.
