# Base Class Flag (`--base=`)

Extend a custom base class when scaffolding entities with `aicl:make-entity`.

**Namespace:** `Aicl\Console\Support\BaseSchemaInspector`, `Aicl\Contracts\DeclaresBaseSchema`
**Location:** `packages/aicl/src/Console/Support/BaseSchemaInspector.php`

## Overview

The `--base=` flag lets you scaffold child entities that extend a shared base model. The scaffolder inspects the base class via the `DeclaresBaseSchema` contract, deduplicates columns/traits/contracts, and generates:

- A model that `extends BaseClass` instead of `Model`
- A migration containing only the child-specific columns (base columns excluded)
- A Filament resource with an "Inherited Fields" section for base fields plus a child details section

---

## Creating a Base Class

Base classes must implement `DeclaresBaseSchema` and return a schema array:

```php
namespace App\Models\Base;

use Aicl\Contracts\DeclaresBaseSchema;
use Illuminate\Database\Eloquent\Model;

class BaseNetworkDevice extends Model implements DeclaresBaseSchema
{
    public static function baseSchema(): array
    {
        return [
            'columns' => [
                ['name' => 'hostname', 'type' => 'string'],
                ['name' => 'ip_address', 'type' => 'string', 'modifiers' => ['nullable']],
                ['name' => 'mac_address', 'type' => 'string', 'modifiers' => ['unique']],
                ['name' => 'location_id', 'type' => 'foreignId'],
                ['name' => 'is_active', 'type' => 'boolean', 'modifiers' => ['default(true)']],
            ],
            'traits' => [
                \Aicl\Traits\HasStandardScopes::class,
                \Aicl\Traits\HasAuditTrail::class,
            ],
            'contracts' => [
                \Aicl\Contracts\HasEntityLifecycle::class,
            ],
            'fillable' => ['hostname', 'ip_address', 'mac_address', 'location_id', 'is_active'],
            'casts' => [
                'is_active' => 'boolean',
            ],
            'relationships' => [
                ['name' => 'location', 'type' => 'belongsTo', 'model' => 'App\\Models\\Location'],
            ],
        ];
    }
}
```

### Schema Array Shape

| Key | Type | Description |
|-----|------|-------------|
| `columns` | `array<array{name, type, modifiers?, argument?}>` | Database columns the base class provides |
| `traits` | `array<string>` | Trait FQCNs the base class uses (not re-declared on child) |
| `contracts` | `array<string>` | Interface FQCNs the base class implements |
| `fillable` | `array<string>` | Fillable fields from the base class |
| `casts` | `array<string, string>` | Cast definitions from the base class |
| `relationships` | `array<array{name, type, model, foreignKey?}>` | Relationships defined on the base class |

### Column Modifiers

The `modifiers` array supports: `nullable`, `unique`, `index`, `default(value)`.

---

## Scaffolding a Child Entity

```bash
ddev exec php artisan aicl:make-entity CableModem \
  --base="App\\Models\\Base\\BaseNetworkDevice" \
  --fields="firmware_version:string,snr:decimal,customer_id:foreignId" \
  --all
```

### What Gets Generated

**Model:** Extends the base class, only declares child-specific traits/contracts:

```php
class CableModem extends BaseNetworkDevice
{
    // Only child-specific fillable, casts, relationships
    // Base class traits/contracts are inherited, not re-declared
}
```

**Migration:** Contains only child-specific columns. Base class columns are excluded to avoid duplication (the base class migration provides those):

```php
Schema::create('cable_modems', function (Blueprint $table) {
    $table->uuid('id')->primary();
    // Base columns NOT repeated here — they come from the base migration
    $table->string('firmware_version');
    $table->decimal('snr')->nullable();
    $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
    $table->timestamps();
    $table->softDeletes();
});
```

**Filament Resource:** Includes an "Inherited Fields" section showing base class fields (read-only context) plus a child details section:

```php
public static function form(Form $form): Form
{
    return $form->schema([
        Section::make('Inherited Fields')
            ->description('Fields from BaseNetworkDevice')
            ->schema([
                // Base class field components
            ]),
        Section::make('Cable Modem Details')
            ->schema([
                // Child-specific field components
            ]),
    ]);
}
```

---

## Without `--base`

When `--base` is not provided, behavior is completely unchanged — the entity extends `Model` directly with all standard AICL entity scaffolding.

---

## Validation

The `BaseSchemaInspector` validates three requirements at parse time:

1. The base class exists and is autoloadable
2. The base class extends `Illuminate\Database\Eloquent\Model`
3. The base class implements `Aicl\Contracts\DeclaresBaseSchema`

If any check fails, the command exits with a descriptive error before generating any files.

---

## Files

| File | Purpose |
|------|---------|
| `packages/aicl/src/Contracts/DeclaresBaseSchema.php` | Contract that base classes must implement |
| `packages/aicl/src/Console/Support/BaseSchemaInspector.php` | Validates and inspects base class schema |
| `packages/aicl/src/Console/Support/FieldDefinition.php` | `fromBaseSchema()` parses base columns into field definitions |
| `packages/aicl/src/Console/Commands/MakeEntityCommand.php` | `--base` option handling and stub merging |
| `tests/Framework/BaseSchemaFlagTest.php` | Framework suite tests (10 tests) |
