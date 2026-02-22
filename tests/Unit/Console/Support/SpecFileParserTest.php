<?php

namespace Aicl\Tests\Unit\Console\Support;

use Aicl\Console\Support\EntitySpec;
use Aicl\Console\Support\RelationshipDefinition;
use Aicl\Console\Support\SpecFileParser;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class SpecFileParserTest extends TestCase
{
    protected SpecFileParser $parser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->parser = new SpecFileParser;
    }

    // ========================================================================
    // Helper: write spec content to a temp file and return its path
    // ========================================================================

    protected function writeTempSpec(string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'spec_').'.entity.md';
        file_put_contents($path, $content);

        return $path;
    }

    protected function completeSpec(): string
    {
        return <<<'MD'
# Invoice

An invoice tracks billable work for a project. Invoices progress through
a draft, sent, paid lifecycle and can be exported to PDF.

---

## Fields

| Name | Type | Modifiers | Description |
|------|------|-----------|-------------|
| invoice_number | string | unique | Auto-generated sequential number |
| title | string | | Human-readable invoice title |
| description | text | | Detailed line-item breakdown |
| amount | float | | Total invoice amount in USD |
| tax_rate | float | nullable | Optional tax percentage |
| due_date | date | | Payment deadline |
| paid_at | datetime | nullable | When payment was received |
| priority | enum:InvoicePriority | | Billing priority level |
| project_id | foreignId:projects | | Parent project reference |
| assigned_to | foreignId:users | nullable | Assigned AR rep |
| is_billable | boolean | default(false) | Whether this invoice is billable |
| metadata | json | nullable | Arbitrary key-value data |

## Enums

### InvoicePriority

| Case | Label | Color | Icon |
|------|-------|-------|------|
| low | Low | gray | heroicon-o-minus |
| normal | Normal | info | heroicon-o-bars-3 |
| high | High | warning | heroicon-o-arrow-up |
| urgent | Urgent | danger | heroicon-o-fire |

## States

```states
draft → sent → paid
draft → cancelled
sent → cancelled
sent → overdue
overdue → paid
overdue → cancelled
```

Default: `draft`

## Relationships

| Method | Type | Related Model | Foreign Key | Description |
|--------|------|---------------|-------------|-------------|
| lineItems | hasMany | InvoiceLineItem | | Individual line items |
| payments | hasMany | Payment | | Payment records |
| tags | belongsToMany | Tag | | Categorization tags |
| comments | morphMany | Comment | | Threaded comments |

## Traits

- HasEntityEvents
- HasAuditTrail
- HasStandardScopes
- HasAiContext

## Options

- widgets: true
- notifications: true
- pdf: true

## Business Rules

- Invoice numbers are auto-generated as `INV-{YYYY}-{sequential}`
- `amount` must be > 0 for non-draft invoices

## Widget Hints

- StatsOverview: Total count, Active count, Overdue count
- Chart: Doughnut by status

## Notification Hints

- InvoiceAssigned: When `assigned_to` changes
- InvoiceStatusChanged: On any state transition
MD;
    }

    protected function minimalSpec(): string
    {
        return <<<'MD'
# Task

## Fields

| Name | Type | Modifiers | Description |
|------|------|-----------|-------------|
| title | string | | The task title |
| priority | integer | default(0) | Priority level |
MD;
    }

    // ========================================================================
    // 1. Complete spec produces valid EntitySpec
    // ========================================================================

    public function test_parse_complete_spec_produces_valid_entity_spec(): void
    {
        $spec = $this->parser->parseContent($this->completeSpec());

        $this->assertInstanceOf(EntitySpec::class, $spec);
        $this->assertSame('Invoice', $spec->name);
        $this->assertStringContainsString('invoice tracks billable work', $spec->description);
        $this->assertCount(12, $spec->fields);
        $this->assertNotEmpty($spec->states);
        $this->assertNotEmpty($spec->stateTransitions);
        $this->assertNotEmpty($spec->relationships);
        $this->assertNotEmpty($spec->enums);
        $this->assertNotEmpty($spec->traits);
        $this->assertNotEmpty($spec->options);
        $this->assertNotEmpty($spec->businessRules);
        $this->assertNotEmpty($spec->widgetHints);
        $this->assertNotEmpty($spec->notificationHints);
    }

    // ========================================================================
    // 2. Minimal spec (just Name + Fields) produces valid EntitySpec
    // ========================================================================

    public function test_parse_minimal_spec_produces_valid_entity_spec(): void
    {
        $spec = $this->parser->parseContent($this->minimalSpec());

        $this->assertInstanceOf(EntitySpec::class, $spec);
        $this->assertSame('Task', $spec->name);
        $this->assertCount(2, $spec->fields);
        $this->assertEmpty($spec->states);
        $this->assertEmpty($spec->stateTransitions);
        $this->assertEmpty($spec->relationships);
        $this->assertEmpty($spec->enums);
        // Default traits applied when ## Traits is absent
        $this->assertSame(['HasEntityEvents', 'HasAuditTrail', 'HasStandardScopes'], $spec->traits);
        $this->assertEmpty($spec->options);
        $this->assertEmpty($spec->businessRules);
    }

    // ========================================================================
    // 3. Missing # Name header throws InvalidArgumentException
    // ========================================================================

    public function test_missing_name_header_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must start with a # Name header');

        $this->parser->parseContent(<<<'MD'
## Fields

| Name | Type | Modifiers | Description |
|------|------|-----------|-------------|
| title | string | | The title |
MD);
    }

    // ========================================================================
    // 4. Missing ## Fields section throws InvalidArgumentException
    // ========================================================================

    public function test_missing_fields_section_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must have a ## Fields section');

        $this->parser->parseContent(<<<'MD'
# Task

Just a description, no fields section.
MD);
    }

    // ========================================================================
    // 5. Empty fields table throws InvalidArgumentException
    // ========================================================================

    public function test_empty_fields_table_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must contain at least one field');

        $this->parser->parseContent(<<<'MD'
# Task

## Fields

| Name | Type | Modifiers | Description |
|------|------|-----------|-------------|
MD);
    }

    // ========================================================================
    // 6. Invalid field type throws InvalidArgumentException
    // ========================================================================

    public function test_invalid_field_type_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Unknown field type: 'uuid'");

        $this->parser->parseContent(<<<'MD'
# Task

## Fields

| Name | Type | Modifiers | Description |
|------|------|-----------|-------------|
| code | uuid | | Some code |
MD);
    }

    // ========================================================================
    // 7. Duplicate field name throws InvalidArgumentException
    // ========================================================================

    public function test_duplicate_field_name_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Duplicate field name: 'title'");

        $this->parser->parseContent(<<<'MD'
# Task

## Fields

| Name | Type | Modifiers | Description |
|------|------|-----------|-------------|
| title | string | | First title |
| title | text | | Duplicate title |
MD);
    }

    // ========================================================================
    // 8. Invalid field name (not snake_case) throws InvalidArgumentException
    // ========================================================================

    public function test_invalid_field_name_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Field name 'myTitle' must be snake_case");

        $this->parser->parseContent(<<<'MD'
# Task

## Fields

| Name | Type | Modifiers | Description |
|------|------|-----------|-------------|
| myTitle | string | | Bad field name |
MD);
    }

    // ========================================================================
    // 9. Enum field without type argument throws InvalidArgumentException
    // ========================================================================

    public function test_enum_field_without_type_argument_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Enum field 'status' requires an argument");

        $this->parser->parseContent(<<<'MD'
# Task

## Fields

| Name | Type | Modifiers | Description |
|------|------|-----------|-------------|
| status | enum | | Missing class name |
MD);
    }

    // ========================================================================
    // 10. ForeignId field without table argument throws InvalidArgumentException
    // ========================================================================

    public function test_foreign_id_field_without_table_argument_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("ForeignId field 'user_id' requires an argument");

        $this->parser->parseContent(<<<'MD'
# Task

## Fields

| Name | Type | Modifiers | Description |
|------|------|-----------|-------------|
| user_id | foreignId | | Missing table name |
MD);
    }

    // ========================================================================
    // 11. Parse pipe-separated modifiers
    // ========================================================================

    public function test_parse_single_modifier(): void
    {
        $spec = $this->parser->parseContent(<<<'MD'
# Task

## Fields

| Name | Type | Modifiers | Description |
|------|------|-----------|-------------|
| code | string | nullable | Nullable modifier |
| slug | string | unique | Unique modifier |
MD);

        $this->assertCount(2, $spec->fields);

        $this->assertTrue($spec->fields[0]->nullable);
        $this->assertFalse($spec->fields[0]->unique);

        $this->assertTrue($spec->fields[1]->unique);
        $this->assertFalse($spec->fields[1]->nullable);
    }

    public function test_parse_modifier_with_default_value(): void
    {
        $spec = $this->parser->parseContent(<<<'MD'
# Task

## Fields

| Name | Type | Modifiers | Description |
|------|------|-----------|-------------|
| priority | integer | default(0) | Default value |
MD);

        $this->assertSame('0', $spec->fields[0]->default);
    }

    // ========================================================================
    // 12. Type-specific defaults apply
    // ========================================================================

    public function test_text_fields_are_auto_nullable(): void
    {
        $spec = $this->parser->parseContent(<<<'MD'
# Task

## Fields

| Name | Type | Modifiers | Description |
|------|------|-----------|-------------|
| notes | text | | Should be auto-nullable |
MD);

        $this->assertTrue($spec->fields[0]->nullable);
    }

    public function test_date_fields_are_auto_nullable(): void
    {
        $spec = $this->parser->parseContent(<<<'MD'
# Task

## Fields

| Name | Type | Modifiers | Description |
|------|------|-----------|-------------|
| due_date | date | | Should be auto-nullable |
MD);

        $this->assertTrue($spec->fields[0]->nullable);
    }

    public function test_datetime_fields_are_auto_nullable(): void
    {
        $spec = $this->parser->parseContent(<<<'MD'
# Task

## Fields

| Name | Type | Modifiers | Description |
|------|------|-----------|-------------|
| completed_at | datetime | | Should be auto-nullable |
MD);

        $this->assertTrue($spec->fields[0]->nullable);
    }

    public function test_json_fields_are_auto_nullable(): void
    {
        $spec = $this->parser->parseContent(<<<'MD'
# Task

## Fields

| Name | Type | Modifiers | Description |
|------|------|-----------|-------------|
| metadata | json | | Should be auto-nullable |
MD);

        $this->assertTrue($spec->fields[0]->nullable);
    }

    public function test_boolean_fields_default_to_true(): void
    {
        $spec = $this->parser->parseContent(<<<'MD'
# Task

## Fields

| Name | Type | Modifiers | Description |
|------|------|-----------|-------------|
| is_active | boolean | | Should default to true |
MD);

        $this->assertSame('true', $spec->fields[0]->default);
    }

    public function test_boolean_field_respects_explicit_default(): void
    {
        $spec = $this->parser->parseContent(<<<'MD'
# Task

## Fields

| Name | Type | Modifiers | Description |
|------|------|-----------|-------------|
| is_billable | boolean | default(false) | Explicit false default |
MD);

        $this->assertSame('false', $spec->fields[0]->default);
    }

    // ========================================================================
    // 13. Parse ## Enums section with rich case data
    // ========================================================================

    public function test_parse_enums_section_with_rich_case_data(): void
    {
        $spec = $this->parser->parseContent($this->completeSpec());

        $this->assertArrayHasKey('InvoicePriority', $spec->enums);

        $cases = $spec->enums['InvoicePriority'];
        $this->assertCount(4, $cases);

        $this->assertSame('low', $cases[0]['case']);
        $this->assertSame('Low', $cases[0]['label']);
        $this->assertSame('gray', $cases[0]['color']);
        $this->assertSame('heroicon-o-minus', $cases[0]['icon']);

        $this->assertSame('urgent', $cases[3]['case']);
        $this->assertSame('Urgent', $cases[3]['label']);
        $this->assertSame('danger', $cases[3]['color']);
        $this->assertSame('heroicon-o-fire', $cases[3]['icon']);
    }

    // ========================================================================
    // 14. Parse ## States section with arrow transitions
    // ========================================================================

    public function test_parse_states_with_unicode_arrows(): void
    {
        $spec = $this->parser->parseContent($this->completeSpec());

        // All states are present (from single-arrow lines)
        $this->assertContains('draft', $spec->states);
        $this->assertContains('sent', $spec->states);
        $this->assertContains('paid', $spec->states);
        $this->assertContains('cancelled', $spec->states);
        $this->assertContains('overdue', $spec->states);

        // Check transitions from single-arrow lines
        // Note: chained arrows "draft → sent → paid" are skipped by parser (>2 parts)
        // Only single-arrow transitions are captured
        $this->assertArrayHasKey('draft', $spec->stateTransitions);
        $this->assertContains('cancelled', $spec->stateTransitions['draft']);

        $this->assertArrayHasKey('sent', $spec->stateTransitions);
        $this->assertContains('cancelled', $spec->stateTransitions['sent']);
        $this->assertContains('overdue', $spec->stateTransitions['sent']);

        $this->assertArrayHasKey('overdue', $spec->stateTransitions);
        $this->assertContains('paid', $spec->stateTransitions['overdue']);
        $this->assertContains('cancelled', $spec->stateTransitions['overdue']);
    }

    public function test_parse_states_with_ascii_arrows(): void
    {
        $spec = $this->parser->parseContent(<<<'MD'
# Task

## Fields

| Name | Type | Modifiers | Description |
|------|------|-----------|-------------|
| title | string | | The title |

## States

```states
open -> in_progress
in_progress -> done
open -> cancelled
```
MD);

        $this->assertContains('open', $spec->states);
        $this->assertContains('in_progress', $spec->states);
        $this->assertContains('done', $spec->states);
        $this->assertContains('cancelled', $spec->states);

        $this->assertContains('in_progress', $spec->stateTransitions['open']);
        $this->assertContains('cancelled', $spec->stateTransitions['open']);
        $this->assertContains('done', $spec->stateTransitions['in_progress']);
    }

    // ========================================================================
    // 15. Parse ## States section with explicit Default: line
    // ========================================================================

    public function test_parse_states_with_explicit_default(): void
    {
        $spec = $this->parser->parseContent($this->completeSpec());

        $this->assertSame('draft', $spec->defaultState);
    }

    public function test_parse_states_without_explicit_default_uses_first_state(): void
    {
        $spec = $this->parser->parseContent(<<<'MD'
# Task

## Fields

| Name | Type | Modifiers | Description |
|------|------|-----------|-------------|
| title | string | | The title |

## States

```states
open -> in_progress
in_progress -> done
```
MD);

        $this->assertSame('open', $spec->defaultState);
    }

    // ========================================================================
    // 16. Parse ## Relationships section into RelationshipDefinition objects
    // ========================================================================

    public function test_parse_relationships_section(): void
    {
        $spec = $this->parser->parseContent($this->completeSpec());

        $this->assertCount(4, $spec->relationships);

        $lineItems = $spec->relationships[0];
        $this->assertInstanceOf(RelationshipDefinition::class, $lineItems);
        $this->assertSame('lineItems', $lineItems->name);
        $this->assertSame('hasMany', $lineItems->type);
        $this->assertSame('InvoiceLineItem', $lineItems->relatedModel);
        $this->assertNull($lineItems->foreignKey);

        $tags = $spec->relationships[2];
        $this->assertSame('tags', $tags->name);
        $this->assertSame('belongsToMany', $tags->type);
        $this->assertSame('Tag', $tags->relatedModel);

        $comments = $spec->relationships[3];
        $this->assertSame('comments', $comments->name);
        $this->assertSame('morphMany', $comments->type);
        $this->assertSame('Comment', $comments->relatedModel);
    }

    public function test_parse_relationships_with_foreign_key(): void
    {
        $spec = $this->parser->parseContent(<<<'MD'
# Task

## Fields

| Name | Type | Modifiers | Description |
|------|------|-----------|-------------|
| title | string | | The title |

## Relationships

| Method | Type | Related Model | Foreign Key |
|--------|------|---------------|-------------|
| tasks | hasMany | Task | project_id |
MD);

        $this->assertCount(1, $spec->relationships);
        $this->assertSame('project_id', $spec->relationships[0]->foreignKey);
    }

    // ========================================================================
    // 17. Parse ## Traits section (with defaults when absent)
    // ========================================================================

    public function test_parse_traits_section(): void
    {
        $spec = $this->parser->parseContent($this->completeSpec());

        $this->assertContains('HasEntityEvents', $spec->traits);
        $this->assertContains('HasAuditTrail', $spec->traits);
        $this->assertContains('HasStandardScopes', $spec->traits);
        $this->assertContains('HasAiContext', $spec->traits);
        $this->assertCount(4, $spec->traits);
    }

    public function test_traits_default_when_section_absent(): void
    {
        $spec = $this->parser->parseContent($this->minimalSpec());

        $this->assertSame(
            ['HasEntityEvents', 'HasAuditTrail', 'HasStandardScopes'],
            $spec->traits
        );
    }

    // ========================================================================
    // 18. Parse ## Options section with type casting
    // ========================================================================

    public function test_parse_options_with_type_casting(): void
    {
        $spec = $this->parser->parseContent(<<<'MD'
# Task

## Fields

| Name | Type | Modifiers | Description |
|------|------|-----------|-------------|
| title | string | | The title |

## Options

- widgets: true
- notifications: false
- pdf: null
- batch_size: 100
- threshold: 0.75
- label: Custom Label
MD);

        $this->assertTrue($spec->options['widgets']);
        $this->assertFalse($spec->options['notifications']);
        $this->assertNull($spec->options['pdf']);
        $this->assertSame(100, $spec->options['batch_size']);
        $this->assertSame(0.75, $spec->options['threshold']);
        $this->assertSame('Custom Label', $spec->options['label']);
    }

    // ========================================================================
    // 19. Parse ## Business Rules, Widget Hints, Notification Hints
    // ========================================================================

    public function test_parse_business_rules(): void
    {
        $spec = $this->parser->parseContent($this->completeSpec());

        $this->assertCount(2, $spec->businessRules);
        $this->assertStringContainsString('auto-generated', $spec->businessRules[0]);
        $this->assertStringContainsString('amount', $spec->businessRules[1]);
    }

    public function test_parse_widget_hints(): void
    {
        $spec = $this->parser->parseContent($this->completeSpec());

        $this->assertCount(2, $spec->widgetHints);
        $this->assertStringContainsString('StatsOverview', $spec->widgetHints[0]);
        $this->assertStringContainsString('Chart', $spec->widgetHints[1]);
    }

    public function test_parse_notification_hints(): void
    {
        $spec = $this->parser->parseContent($this->completeSpec());

        $this->assertCount(2, $spec->notificationHints);
        $this->assertStringContainsString('InvoiceAssigned', $spec->notificationHints[0]);
        $this->assertStringContainsString('InvoiceStatusChanged', $spec->notificationHints[1]);
    }

    // ========================================================================
    // 20. Parse ## Enums with missing color/icon (optional columns)
    // ========================================================================

    public function test_parse_enums_with_missing_color_and_icon(): void
    {
        $spec = $this->parser->parseContent(<<<'MD'
# Task

## Fields

| Name | Type | Modifiers | Description |
|------|------|-----------|-------------|
| priority | enum:TaskPriority | | The priority |

## Enums

### TaskPriority

| Case | Label |
|------|-------|
| low | Low |
| medium | Medium |
| high | High |
MD);

        $this->assertArrayHasKey('TaskPriority', $spec->enums);

        $cases = $spec->enums['TaskPriority'];
        $this->assertCount(3, $cases);

        $this->assertSame('low', $cases[0]['case']);
        $this->assertSame('Low', $cases[0]['label']);
        $this->assertArrayNotHasKey('color', $cases[0]);
        $this->assertArrayNotHasKey('icon', $cases[0]);
    }

    // ========================================================================
    // Additional edge case: parse() with file path
    // ========================================================================

    public function test_parse_from_file_path(): void
    {
        $path = $this->writeTempSpec($this->minimalSpec());

        try {
            $spec = $this->parser->parse($path);

            $this->assertInstanceOf(EntitySpec::class, $spec);
            $this->assertSame('Task', $spec->name);
        } finally {
            @unlink($path);
        }
    }

    public function test_parse_nonexistent_file_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Spec file not found');

        $this->parser->parse('/tmp/nonexistent_spec_file.entity.md');
    }

    public function test_parse_empty_file_throws_exception(): void
    {
        $path = $this->writeTempSpec('');

        try {
            $this->expectException(InvalidArgumentException::class);
            $this->expectExceptionMessage('Spec file is empty');

            $this->parser->parse($path);
        } finally {
            @unlink($path);
        }
    }

    // ========================================================================
    // Edge case: PascalCase validation on entity name
    // ========================================================================

    public function test_non_pascal_case_name_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must be PascalCase');

        $this->parser->parseContent(<<<'MD'
# task

## Fields

| Name | Type | Modifiers | Description |
|------|------|-----------|-------------|
| title | string | | The title |
MD);
    }

    // ========================================================================
    // Edge case: enum class name not PascalCase
    // ========================================================================

    public function test_enum_class_name_must_be_pascal_case(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must be PascalCase');

        $this->parser->parseContent(<<<'MD'
# Task

## Fields

| Name | Type | Modifiers | Description |
|------|------|-----------|-------------|
| status | enum:bad_name | | Invalid enum class |
MD);
    }

    // ========================================================================
    // Edge case: foreignId table name not snake_case
    // ========================================================================

    public function test_foreign_id_table_name_must_be_snake_case(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must be snake_case');

        $this->parser->parseContent(<<<'MD'
# Task

## Fields

| Name | Type | Modifiers | Description |
|------|------|-----------|-------------|
| user_id | foreignId:Users | | Invalid table name |
MD);
    }

    // ========================================================================
    // Edge case: index modifier parses correctly
    // ========================================================================

    public function test_parse_index_modifier(): void
    {
        $spec = $this->parser->parseContent(<<<'MD'
# Task

## Fields

| Name | Type | Modifiers | Description |
|------|------|-----------|-------------|
| email | string | index | Indexed field |
MD);

        $this->assertTrue($spec->fields[0]->indexed);
        $this->assertFalse($spec->fields[0]->unique);
    }

    // ========================================================================
    // EntitySpec convenience methods
    // ========================================================================

    public function test_entity_spec_has_state_machine(): void
    {
        $specWithStates = $this->parser->parseContent($this->completeSpec());
        $specWithoutStates = $this->parser->parseContent($this->minimalSpec());

        $this->assertTrue($specWithStates->hasStateMachine());
        $this->assertFalse($specWithoutStates->hasStateMachine());
    }

    public function test_entity_spec_wants_widgets(): void
    {
        $spec = $this->parser->parseContent($this->completeSpec());

        $this->assertTrue($spec->wantsWidgets());
    }

    public function test_entity_spec_wants_notifications(): void
    {
        $spec = $this->parser->parseContent($this->completeSpec());

        $this->assertTrue($spec->wantsNotifications());
    }

    public function test_entity_spec_wants_pdf(): void
    {
        $spec = $this->parser->parseContent($this->completeSpec());

        $this->assertTrue($spec->wantsPdf());
    }

    public function test_entity_spec_wants_ai_context_via_trait(): void
    {
        $spec = $this->parser->parseContent($this->completeSpec());

        $this->assertTrue($spec->wantsAiContext());
    }

    public function test_entity_spec_to_fields_string(): void
    {
        $spec = $this->parser->parseContent($this->minimalSpec());
        $fieldsString = $spec->toFieldsString();

        $this->assertStringContainsString('title:string', $fieldsString);
        $this->assertStringContainsString('priority:integer', $fieldsString);
    }

    public function test_entity_spec_to_states_string(): void
    {
        $spec = $this->parser->parseContent($this->completeSpec());
        $statesString = $spec->toStatesString();

        $this->assertStringContainsString('draft', $statesString);
        $this->assertStringContainsString('sent', $statesString);
        $this->assertStringContainsString('paid', $statesString);
    }

    public function test_entity_spec_to_relationships_string(): void
    {
        $spec = $this->parser->parseContent($this->completeSpec());
        $relString = $spec->toRelationshipsString();

        $this->assertStringContainsString('lineItems:hasMany:InvoiceLineItem', $relString);
        $this->assertStringContainsString('tags:belongsToMany:Tag', $relString);
    }

    // ========================================================================
    // Edge case: states without fenced code block
    // ========================================================================

    public function test_parse_states_without_fenced_block(): void
    {
        $spec = $this->parser->parseContent(<<<'MD'
# Task

## Fields

| Name | Type | Modifiers | Description |
|------|------|-----------|-------------|
| title | string | | The title |

## States

open → in_progress
in_progress → done
MD);

        $this->assertContains('open', $spec->states);
        $this->assertContains('in_progress', $spec->states);
        $this->assertContains('done', $spec->states);
    }

    // ========================================================================
    // Edge case: empty optional sections don't cause errors
    // ========================================================================

    public function test_missing_optional_sections_produce_empty_arrays(): void
    {
        $spec = $this->parser->parseContent($this->minimalSpec());

        $this->assertEmpty($spec->enums);
        $this->assertEmpty($spec->relationships);
        $this->assertEmpty($spec->options);
        $this->assertEmpty($spec->businessRules);
        $this->assertEmpty($spec->widgetHints);
        $this->assertEmpty($spec->notificationHints);
        $this->assertSame('', $spec->defaultState);
        $this->assertNull($spec->baseClass);
    }

    // ========================================================================
    // Edge case: base class from options
    // ========================================================================

    public function test_base_class_extracted_from_options(): void
    {
        $spec = $this->parser->parseContent(<<<'MD'
# Task

## Fields

| Name | Type | Modifiers | Description |
|------|------|-----------|-------------|
| title | string | | The title |

## Options

- base: App\Models\Base\BaseTicket
- widgets: true
MD);

        $this->assertSame('App\Models\Base\BaseTicket', $spec->baseClass);
        // base should be removed from options array
        $this->assertArrayNotHasKey('base', $spec->options);
        $this->assertTrue($spec->options['widgets']);
    }

    // ========================================================================
    // Edge case: description is empty when only a separator line exists
    // ========================================================================

    public function test_description_is_empty_when_no_paragraph(): void
    {
        $spec = $this->parser->parseContent(<<<'MD'
# Task

---

## Fields

| Name | Type | Modifiers | Description |
|------|------|-----------|-------------|
| title | string | | The title |
MD);

        $this->assertSame('', $spec->description);
    }

    // ========================================================================
    // Edge case: multiple enum subsections
    // ========================================================================

    public function test_parse_multiple_enum_subsections(): void
    {
        $spec = $this->parser->parseContent(<<<'MD'
# Task

## Fields

| Name | Type | Modifiers | Description |
|------|------|-----------|-------------|
| priority | enum:TaskPriority | | Priority |
| category | enum:TaskCategory | | Category |

## Enums

### TaskPriority

| Case | Label | Color |
|------|-------|-------|
| low | Low | gray |
| high | High | danger |

### TaskCategory

| Case | Label |
|------|-------|
| bug | Bug |
| feature | Feature |
MD);

        $this->assertCount(2, $spec->enums);
        $this->assertArrayHasKey('TaskPriority', $spec->enums);
        $this->assertArrayHasKey('TaskCategory', $spec->enums);
        $this->assertCount(2, $spec->enums['TaskPriority']);
        $this->assertCount(2, $spec->enums['TaskCategory']);

        // TaskPriority has color, TaskCategory does not
        $this->assertArrayHasKey('color', $spec->enums['TaskPriority'][0]);
        $this->assertArrayNotHasKey('color', $spec->enums['TaskCategory'][0]);
    }
}
