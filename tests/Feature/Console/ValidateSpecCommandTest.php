<?php

namespace Aicl\Tests\Feature\Console;

use Aicl\Console\Commands\ValidateSpecCommand;
use Illuminate\Console\Command;
use Tests\TestCase;

class ValidateSpecCommandTest extends TestCase
{
    /**
     * Temp files created during tests, cleaned up in tearDown.
     *
     * @var array<int, string>
     */
    protected array $tempFiles = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Register the ValidateSpecCommand so it's available via artisan
        $this->app->make(\Illuminate\Contracts\Console\Kernel::class)
            ->registerCommand(new ValidateSpecCommand);
    }

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $path) {
            @unlink($path);
        }

        $this->tempFiles = [];

        parent::tearDown();
    }

    /**
     * Write a spec file to a temp path and track for cleanup.
     */
    protected function writeSpecFile(string $content, ?string $name = null): string
    {
        $dir = sys_get_temp_dir().'/aicl_spec_test_'.getmypid();

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $filename = ($name ?? 'Test').'.entity.md';
        $path = $dir.'/'.$filename;
        file_put_contents($path, $content);
        $this->tempFiles[] = $path;

        return $path;
    }

    protected function validSpec(): string
    {
        return <<<'MD'
# Invoice

An invoice tracks billable work for a project.

---

## Fields

| Name | Type | Modifiers | Description |
|------|------|-----------|-------------|
| invoice_number | string | unique | Auto-generated number |
| title | string | | Human-readable title |
| description | text | | Line-item breakdown |
| amount | float | | Total amount |
| due_date | date | | Payment deadline |
| priority | enum:InvoicePriority | | Priority level |
| project_id | foreignId:projects | | Parent project |

## Enums

### InvoicePriority

| Case | Label | Color |
|------|-------|-------|
| low | Low | gray |
| high | High | danger |

## States

```states
draft -> sent
sent -> paid
draft -> cancelled
```

Default: `draft`

## Relationships

| Method | Type | Related Model | Foreign Key |
|--------|------|---------------|-------------|
| lineItems | hasMany | InvoiceLineItem | |
| tags | belongsToMany | Tag | |

## Traits

- HasEntityEvents
- HasAuditTrail
- HasStandardScopes

## Options

- widgets: true
- notifications: true
MD;
    }

    // ========================================================================
    // 1. Valid spec file passes validation (exit code 0)
    // ========================================================================

    public function test_valid_spec_passes_validation(): void
    {
        $path = $this->writeSpecFile($this->validSpec(), 'Invoice');

        $this->artisan('aicl:validate-spec', ['spec' => $path])
            ->assertExitCode(Command::SUCCESS)
            ->expectsOutputToContain("Spec 'Invoice' is valid!");
    }

    // ========================================================================
    // 2. Missing spec file shows error
    // ========================================================================

    public function test_missing_spec_file_shows_error(): void
    {
        $this->artisan('aicl:validate-spec', ['spec' => '/tmp/nonexistent_entity.entity.md'])
            ->assertExitCode(Command::FAILURE)
            ->expectsOutputToContain('Spec file not found');
    }

    // ========================================================================
    // 3. Reserved column name (id) produces error
    // ========================================================================

    public function test_reserved_column_name_produces_error(): void
    {
        $path = $this->writeSpecFile(<<<'MD'
# Task

A task entity.

## Fields

| Name | Type | Modifiers | Description |
|------|------|-----------|-------------|
| title | string | | The title |
| id | integer | | Reserved column |
MD);

        $this->artisan('aicl:validate-spec', ['spec' => $path])
            ->assertExitCode(Command::FAILURE)
            ->expectsOutputToContain("'id' is a reserved column");
    }

    // ========================================================================
    // 4. Auto-column name (is_active) produces warning (not error)
    // ========================================================================

    public function test_auto_column_name_produces_warning(): void
    {
        $path = $this->writeSpecFile(<<<'MD'
# Task

A task entity.

## Fields

| Name | Type | Modifiers | Description |
|------|------|-----------|-------------|
| title | string | | The title |
| is_active | boolean | | Auto-generated field |
MD);

        $this->artisan('aicl:validate-spec', ['spec' => $path])
            ->assertExitCode(Command::SUCCESS)
            ->expectsOutputToContain("'is_active' is auto-generated");
    }

    // ========================================================================
    // 5. Missing description produces warning
    // ========================================================================

    public function test_missing_description_produces_warning(): void
    {
        $path = $this->writeSpecFile(<<<'MD'
# Task

---

## Fields

| Name | Type | Modifiers | Description |
|------|------|-----------|-------------|
| title | string | | The title |
MD);

        $this->artisan('aicl:validate-spec', ['spec' => $path])
            ->assertExitCode(Command::SUCCESS)
            ->expectsOutputToContain('No description provided');
    }

    // ========================================================================
    // 6. Enum field without corresponding ## Enums definition produces warning
    // ========================================================================

    public function test_enum_field_without_enum_definition_produces_warning(): void
    {
        $path = $this->writeSpecFile(<<<'MD'
# Task

A task entity.

## Fields

| Name | Type | Modifiers | Description |
|------|------|-----------|-------------|
| title | string | | The title |
| status | enum:TaskStatus | | The status |
MD);

        $this->artisan('aicl:validate-spec', ['spec' => $path])
            ->assertExitCode(Command::SUCCESS)
            ->expectsOutputToContain("references 'TaskStatus' but no ## Enums");
    }

    // ========================================================================
    // 7. Invalid state transitions (reference undefined states) produce error
    // ========================================================================

    public function test_invalid_state_transitions_produce_error(): void
    {
        $path = $this->writeSpecFile(<<<'MD'
# Task

A task entity.

## Fields

| Name | Type | Modifiers | Description |
|------|------|-----------|-------------|
| title | string | | The title |

## States

```states
open -> in_progress
in_progress -> done
```

Default: `pending`
MD);

        // "pending" is not in the states list (which only has open, in_progress, done)
        $this->artisan('aicl:validate-spec', ['spec' => $path])
            ->assertExitCode(Command::FAILURE)
            ->expectsOutputToContain("Default state 'pending' is not in the states list");
    }

    // ========================================================================
    // 8. Invalid relationship method name produces error
    // ========================================================================

    public function test_invalid_relationship_method_name_produces_error(): void
    {
        $path = $this->writeSpecFile(<<<'MD'
# Task

A task entity.

## Fields

| Name | Type | Modifiers | Description |
|------|------|-----------|-------------|
| title | string | | The title |

## Relationships

| Method | Type | Related Model | Foreign Key |
|--------|------|---------------|-------------|
| line_items | hasMany | LineItem | |
MD);

        // line_items is snake_case, not camelCase
        $this->artisan('aicl:validate-spec', ['spec' => $path])
            ->assertExitCode(Command::FAILURE)
            ->expectsOutputToContain("'line_items' must be camelCase");
    }

    // ========================================================================
    // 9. Unknown trait produces warning
    // ========================================================================

    public function test_unknown_trait_produces_warning(): void
    {
        $path = $this->writeSpecFile(<<<'MD'
# Task

A task entity.

## Fields

| Name | Type | Modifiers | Description |
|------|------|-----------|-------------|
| title | string | | The title |

## Traits

- HasEntityEvents
- HasCustomBehavior
MD);

        $this->artisan('aicl:validate-spec', ['spec' => $path])
            ->assertExitCode(Command::SUCCESS)
            ->expectsOutputToContain("'HasCustomBehavior' is not a recognized AICL trait");
    }

    // ========================================================================
    // 10. Valid spec shows field/state/relationship/enum counts
    // ========================================================================

    public function test_valid_spec_shows_counts(): void
    {
        $path = $this->writeSpecFile($this->validSpec(), 'Invoice');

        $this->artisan('aicl:validate-spec', ['spec' => $path])
            ->assertExitCode(Command::SUCCESS)
            ->expectsOutputToContain('Fields')
            ->expectsOutputToContain('States')
            ->expectsOutputToContain('Relationships')
            ->expectsOutputToContain('Enums');
    }

    // ========================================================================
    // Additional: parse error (malformed spec) shows error
    // ========================================================================

    public function test_parse_error_shows_error_message(): void
    {
        $path = $this->writeSpecFile(<<<'MD'
Just some text without a header.
MD);

        $this->artisan('aicl:validate-spec', ['spec' => $path])
            ->assertExitCode(Command::FAILURE)
            ->expectsOutputToContain('Parse error');
    }

    // ========================================================================
    // Additional: unsupported relationship type produces error
    // ========================================================================

    public function test_unsupported_relationship_type_produces_error(): void
    {
        $path = $this->writeSpecFile(<<<'MD'
# Task

A task entity.

## Fields

| Name | Type | Modifiers | Description |
|------|------|-----------|-------------|
| title | string | | The title |

## Relationships

| Method | Type | Related Model | Foreign Key |
|--------|------|---------------|-------------|
| parent | belongsTo | Task | |
MD);

        // belongsTo is not in the supported types list
        $this->artisan('aicl:validate-spec', ['spec' => $path])
            ->assertExitCode(Command::FAILURE)
            ->expectsOutputToContain('belongsTo');
    }

    // ========================================================================
    // Additional: unknown option produces warning
    // ========================================================================

    public function test_unknown_option_produces_warning(): void
    {
        $path = $this->writeSpecFile(<<<'MD'
# Task

A task entity.

## Fields

| Name | Type | Modifiers | Description |
|------|------|-----------|-------------|
| title | string | | The title |

## Options

- widgets: true
- custom_option: false
MD);

        $this->artisan('aicl:validate-spec', ['spec' => $path])
            ->assertExitCode(Command::SUCCESS)
            ->expectsOutputToContain("'custom_option' is not a recognized AICL option");
    }

    // ========================================================================
    // Additional: entity name resolution (without .md extension)
    // ========================================================================

    public function test_entity_name_resolves_to_specs_directory(): void
    {
        // When passing just a name like "Invoice", it tries specs/Invoice.entity.md
        // Since that file may or may not exist, we test that it doesn't crash
        // and gives a meaningful error if not found
        $this->artisan('aicl:validate-spec', ['spec' => 'NonexistentEntity123'])
            ->assertExitCode(Command::FAILURE)
            ->expectsOutputToContain('Spec file not found');
    }
}
