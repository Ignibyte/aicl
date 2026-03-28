# AICL AI Generation Rules

**Version:** 1.1
**Last Updated:** 2026-02-11
**Owner:** `/pipeline-implement`

---

## Overview

This document defines the structural validation rules (patterns) and scaffolding pipeline that govern AI code generation in AICL. Validation is now managed via Forge MCP — the RLM system was extracted from AICL in Sprint F0.

**Golden Reference:** The Project entity (`packages/aicl/src/Models/Project.php`) is the canonical example. All generated entities should follow its patterns exactly.

---

## Generation Pipeline

```
User Request
    │
    ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                        AI GENERATION PIPELINE                                │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│  1. SCAFFOLD           php artisan aicl:make-entity {Name}                  │
│     └─→ Generates skeleton files for all layers                             │
│                                                                              │
│  2. CUSTOMIZE          AI fills in entity-specific logic                    │
│     └─→ Fields, relationships, validations, UI components                   │
│                                                                              │
│  3. FORMAT             vendor/bin/pint --dirty                              │
│     └─→ Ensures code style consistency                                      │
│                                                                              │
│  4. ANALYZE            vendor/bin/phpstan analyse --level=1                 │
│     └─→ Static type checking                                                │
│                                                                              │
│  5. VALIDATE           php artisan aicl:validate {Name}                     │
│     └─→ Scores entity against 40 base patterns (target: 100%)              │
│                                                                              │
│  6. TEST               php artisan test --filter={Name}Test                 │
│     └─→ All tests must pass                                                 │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
    │
    ▼
Validated Entity (Ready for Use)
```

---

## Scaffolding Command

```bash
php artisan aicl:make-entity {Name}
```

**Generates:**

| Layer | File | Location |
|-------|------|----------|
| Model | `{Name}.php` | `app/Models/` |
| Migration | `*_create_{table}_table.php` | `database/migrations/` |
| Factory | `{Name}Factory.php` | `database/factories/` |
| Seeder | `{Name}Seeder.php` | `database/seeders/` |
| Policy | `{Name}Policy.php` | `app/Policies/` |
| Observer | `{Name}Observer.php` | `app/Observers/` |
| Filament Resource | `{Name}Resource.php` | `app/Filament/Resources/{Plural}/` |
| Form Schema | `{Name}Form.php` | `app/Filament/Resources/{Plural}/Schemas/` |
| Table Schema | `{Plural}Table.php` | `app/Filament/Resources/{Plural}/Tables/` |
| Pages | `List/Create/Edit/View{Name}.php` | `app/Filament/Resources/{Plural}/Pages/` |
| API Controller | `{Name}Controller.php` | `app/Http/Controllers/Api/` |
| API Resource | `{Name}Resource.php` | `app/Http/Resources/` |
| Test | `{Name}Test.php` | `tests/Feature/Entities/` |

> **Note:** All generated files go into client space (`app/`, `database/`, `tests/`). Nothing is written to `packages/aicl/`. The golden entity (Project) lives in the package as a reference — follow its patterns but use the locations above.

---

## Validation Patterns (40 Structural Patterns)

### Pattern Registry

These patterns define the structural rules for well-formed generated code. Validation is managed via Forge MCP.

The validator checks each pattern and calculates a weighted score. Target: **100%**.

---

## Model Patterns (12)

### M1: Namespace & Class Declaration
```php
namespace Aicl\Models;  // or App\Models for client entities

class {Name} extends Model implements Auditable, HasEntityLifecycle
```

**Rule:** Model must extend `Illuminate\Database\Eloquent\Model` and implement at minimum `Auditable` and `HasEntityLifecycle`.

### M2: Required Traits
```php
use HasFactory;
use HasEntityEvents;
use HasAuditTrail;
use HasStandardScopes;
use SoftDeletes;
```

**Rule:** Every entity must use these 5 traits.

### M3: Optional Traits (Conditional)
| Trait | Condition |
|-------|-----------|
| `HasTagging` | Entity has categories/labels |
| `HasSearchableFields` | Entity is user-searchable |
| State machine traits | Entity has lifecycle states |

### M4: $fillable Array
```php
protected $fillable = [
    'name',
    'description',
    // ... all mass-assignable fields
];
```

**Rule:** Always use `$fillable`. Never use `$guarded = []`.

### M5: casts() Method
```php
protected function casts(): array
{
    return [
        'status' => ProjectState::class,
        'priority' => ProjectPriority::class,
        'start_date' => 'date',
        'is_active' => 'boolean',
    ];
}
```

**Rule:** Use `casts()` method (Laravel 11), not `$casts` property.

### M6: newFactory() Method
```php
protected static function newFactory(): ProjectFactory
{
    return ProjectFactory::new();
}
```

**Rule:** Package models must define `newFactory()` to resolve factory correctly.

### M7: Relationship Return Types
```php
public function owner(): BelongsTo
{
    return $this->belongsTo(User::class, 'owner_id');
}
```

**Rule:** All relationship methods must have explicit return type hints.

### M8: Owner Relationship
```php
public function owner(): BelongsTo
{
    return $this->belongsTo(User::class, 'owner_id');
}
```

**Rule:** Every entity must have an `owner()` relationship to User.

### M9: Searchable Columns Override
```php
protected function getSearchableColumns(): array
{
    return ['name', 'description'];
}
```

**Rule:** If using `HasStandardScopes`, override `getSearchableColumns()`.

### M10: Searchable Attributes Override
```php
protected function getSearchableAttributes(): array
{
    return ['name', 'description'];
}
```

**Rule:** If using `HasSearchableFields`, override `getSearchableAttributes()`.

### M11: PHPDoc Property Annotations
```php
/**
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property ProjectState $status
 * @property \Carbon\Carbon $created_at
 */
```

**Rule:** Document all database columns as `@property` annotations.

### M12: No Direct DB Facade Usage
**Rule:** Use Eloquent query builder, not `DB::table()` or `DB::select()`.

---

## Migration Patterns (5)

### MIG1: Anonymous Class
```php
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            // ...
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
```

**Rule:** Use anonymous migration class pattern.

### MIG2: Standard Columns
```php
$table->id();
$table->timestamps();
$table->softDeletes();
```

**Rule:** Always include `id()`, `timestamps()`, `softDeletes()`.

### MIG3: Foreign Key to Owner
```php
$table->foreignId('owner_id')
    ->constrained('users')
    ->cascadeOnDelete();
```

**Rule:** Include owner foreign key with cascade delete.

### MIG4: Appropriate Column Types
| Field Type | Column Type |
|------------|-------------|
| Name/title | `string(255)` |
| Description | `text` |
| Money | `decimal(10, 2)` |
| Count | `unsignedInteger` |
| Boolean | `boolean` |
| Date | `date` |
| DateTime | `timestamp` |

### MIG5: down() Method
```php
public function down(): void
{
    Schema::dropIfExists('projects');
}
```

**Rule:** Always include `down()` with `dropIfExists()`.

---

## Factory Patterns (5)

### FAC1: Model Property
```php
protected $model = Project::class;
```

**Rule:** Set `$model` property explicitly.

### FAC2: definition() Method
```php
public function definition(): array
{
    return [
        'name' => fake()->sentence(3),
        'description' => fake()->paragraph(),
        'owner_id' => User::factory(),
        'is_active' => true,
    ];
}
```

**Rule:** Return array with realistic fake data.

### FAC3: State Methods for Status
```php
public function draft(): static
{
    return $this->state(['status' => 'draft']);
}

public function active(): static
{
    return $this->state(['status' => 'active']);
}
```

**Rule:** Create state method for each status/state value.

### FAC4: inactive() State
```php
public function inactive(): static
{
    return $this->state(['is_active' => false]);
}
```

**Rule:** Include `inactive()` state if entity has `is_active` field.

### FAC5: Relationship Factories
```php
'owner_id' => User::factory(),
```

**Rule:** Use related factories for foreign keys.

---

## Policy Patterns (5)

### POL1: Extends BasePolicy
```php
class ProjectPolicy extends BasePolicy
{
    protected string $permissionPrefix = 'Project';
}
```

**Rule:** Extend `Aicl\Policies\BasePolicy`.

### POL2: Permission Prefix
```php
protected string $permissionPrefix = 'Project';
```

**Rule:** Set `$permissionPrefix` to entity name.

### POL3: view() with Owner Check
```php
public function view(User $user, Project $project): bool
{
    if ($project->owner_id === $user->id) {
        return true;
    }
    return $user->can("View:{$this->permissionPrefix}");
}
```

**Rule:** Owner always has access; fallback to permission check.

### POL4: update() with Owner Check
Same pattern as view().

### POL5: delete() with Owner Check
Same pattern as view().

---

## Observer Patterns (3)

### OBS1: Extends BaseObserver
```php
class ProjectObserver extends BaseObserver
```

**Rule:** Extend `Aicl\Observers\BaseObserver`.

### OBS2: created() Method
```php
public function created(Project $project): void
{
    $this->logActivity($project, 'created');
}
```

**Rule:** Log activity on creation.

### OBS3: Status Change Logging
```php
public function updated(Project $project): void
{
    if ($project->wasChanged('status')) {
        $this->logActivity($project, 'status changed to ' . $project->status->label());
    }
}
```

**Rule:** Log state transitions.

---

## Filament Patterns (6)

### FIL1: Resource Structure
```
app/Filament/Resources/{Plural}/
├── {Name}Resource.php
├── Schemas/
│   └── {Name}Form.php
├── Tables/
│   └── {Plural}Table.php
└── Pages/
    ├── List{Plural}.php
    ├── Create{Name}.php
    ├── Edit{Name}.php
    └── View{Name}.php
```

**Rule:** Follow this directory structure.

### FIL2: Delegated Form/Table
```php
public static function form(Schema $schema): Schema
{
    return ProjectForm::configure($schema);
}

public static function table(Table $table): Table
{
    return ProjectsTable::configure($table);
}
```

**Rule:** Delegate to separate schema classes.

### FIL3: All Four Pages
```php
public static function getPages(): array
{
    return [
        'index' => Pages\ListProjects::route('/'),
        'create' => Pages\CreateProject::route('/create'),
        'view' => Pages\ViewProject::route('/{record}'),
        'edit' => Pages\EditProject::route('/{record}/edit'),
    ];
}
```

**Rule:** Define all four pages.

### FIL4: v4 Namespace for Section/Grid
```php
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
```

**Rule:** Use v4 namespaces, not v3.

### FIL5: Name Column Styling
```php
TextColumn::make('name')
    ->weight('bold')
    ->searchable()
    ->sortable(),
```

**Rule:** Name column is bold, searchable, sortable.

### FIL6: Status Badge
```php
TextColumn::make('status')
    ->badge()
    ->formatStateUsing(fn ($state) => $state->label())
    ->color(fn ($state) => match (class_basename($state)) {
        'Active' => 'success',
        'Draft' => 'gray',
        // ...
    }),
```

**Rule:** Status shown as colored badge.

---

## Test Patterns (4)

### TST1: PHPUnit Class
```php
class ProjectTest extends TestCase
{
    use RefreshDatabase;
}
```

**Rule:** PHPUnit (not Pest), with `RefreshDatabase`.

### TST2: setUp() Method
```php
protected function setUp(): void
{
    parent::setUp();

    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    $this->seed(RoleSeeder::class);
    $this->seed(SettingsSeeder::class);
}
```

**Rule:** Reset permission cache, seed roles and settings.

### TST3: Test Categories
- Model creation and soft delete
- Owner relationship
- Policy methods (owner, admin, viewer)
- Scopes (active, inactive, search)
- Entity events dispatch
- Audit trail logging
- Filament pages (list, create, view)
- Factory states

### TST4: Assertion Coverage
**Rule:** Minimum 15 tests per entity, covering all categories.

---

## AI Decision Rules

### When to Add Optional Traits

| If Entity Has... | Add Trait/Interface |
|------------------|---------------------|
| Categories, labels, tags | `HasTagging`, `Taggable` |
| Full-text search needs | `HasSearchableFields`, `Searchable` |
| Lifecycle states | State machine, `Stateful` |

### When to Add Widgets

| If Entity Has... | Generate Widget |
|------------------|-----------------|
| Status field | StatsOverviewWidget (per-status counts) |
| Status field | StatusChart (doughnut distribution) |
| Date + status | UpcomingDeadlinesWidget |
| Monetary field | KPI widget (budget vs spent) |
| HasMany relationship | Count stat card |
| created_at + volume | TrendCard or line chart |

### Form Field Selection

| Data Type | Form Component |
|-----------|----------------|
| Short text | `TextInput` |
| Long text | `RichEditor` |
| Boolean | `Toggle` |
| Enum | `Select` with `->options(EnumClass::class)` |
| Foreign key | `Select` with `->relationship()` |
| Date | `DatePicker` |
| DateTime | `DateTimePicker` |
| Money | `TextInput` with `->numeric()->prefix('$')` |

### Table Column Selection

| Data Type | Table Column |
|-----------|--------------|
| Name/title | `TextColumn` with `->weight('bold')->searchable()` |
| Status/enum | `TextColumn` with `->badge()` |
| Date | `TextColumn` with `->date()` |
| DateTime | `TextColumn` with `->dateTime()` |
| Boolean | `IconColumn` or `TextColumn` with `->badge()` |
| Relationship | `TextColumn::make('relation.field')` |

---

## Validation Command

```bash
php artisan aicl:validate {Name}
```

**Output:**
```
Validating Entity: Project
═══════════════════════════════════════════════════════════════

Model Patterns (12/12)
────────────────────────────────────────────────────────────────
✓ M1: Namespace & class declaration
✓ M2: Required traits (HasFactory, HasEntityEvents, etc.)
✓ M3: Optional traits match entity needs
✓ M4: Uses $fillable (not $guarded)
✓ M5: Uses casts() method
✓ M6: Has newFactory() method
✓ M7: Relationship return types
✓ M8: Has owner() relationship
✓ M9: Overrides getSearchableColumns()
✓ M10: Overrides getSearchableAttributes()
✓ M11: PHPDoc property annotations
✓ M12: No direct DB facade usage

Migration Patterns (5/5)
────────────────────────────────────────────────────────────────
✓ MIG1: Anonymous migration class
✓ MIG2: Standard columns (id, timestamps, softDeletes)
✓ MIG3: Foreign key to owner
✓ MIG4: Appropriate column types
✓ MIG5: Has down() method

[... continues for all categories ...]

═══════════════════════════════════════════════════════════════
Total Score: 40/40 (100%)
Status: ✓ PASSED
```

---

## Quality Checklist

Before marking an entity complete:

- [ ] `vendor/bin/pint --dirty` passes
- [ ] `vendor/bin/phpstan analyse --level=1` passes
- [ ] `php artisan aicl:validate {Name}` scores 100%
- [ ] `php artisan test --filter={Name}Test` all tests pass
- [ ] `php artisan test --compact` full suite passes
- [ ] Entity appears correctly in Filament admin
- [ ] CRUD operations work (create, read, update, delete)
- [ ] Filters and search work
- [ ] Policies enforce correct access
- [ ] Activity log records changes

---

## Related Documents

- [Foundation](foundation.md)
- [Entity System](entity-system.md)
- [Filament UI](filament-ui.md)
- [Testing & Quality](testing-quality.md)
