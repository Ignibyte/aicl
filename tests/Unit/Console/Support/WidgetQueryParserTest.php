<?php

namespace Aicl\Tests\Unit\Console\Support;

use Aicl\Console\Support\WidgetQueryParser;
use PHPUnit\Framework\TestCase;

class WidgetQueryParserTest extends TestCase
{
    // ========================================================================
    // Aggregate queries
    // ========================================================================

    public function test_count_all(): void
    {
        $parser = new WidgetQueryParser('Invoice');
        $result = $parser->parseAggregate('count(*)');

        $this->assertSame('Invoice::query()->count()', $result);
    }

    public function test_count_with_where_string_value(): void
    {
        $parser = new WidgetQueryParser('Invoice');
        $result = $parser->parseAggregate('count(*) where status = active');

        $this->assertSame("Invoice::query()->where('status', 'active')->count()", $result);
    }

    public function test_count_with_where_state_value(): void
    {
        $parser = new WidgetQueryParser('Invoice', states: ['draft', 'active', 'completed']);
        $result = $parser->parseAggregate('count(*) where status = active');

        $this->assertSame("Invoice::query()->where('status', Active::getMorphClass())->count()", $result);
    }

    public function test_count_with_not_equal(): void
    {
        $parser = new WidgetQueryParser('Invoice');
        $result = $parser->parseAggregate('count(*) where status != paid');

        $this->assertSame("Invoice::query()->where('status', '!=', 'paid')->count()", $result);
    }

    public function test_sum_field(): void
    {
        $parser = new WidgetQueryParser('Invoice');
        $result = $parser->parseAggregate('sum(amount)');

        $this->assertSame("Invoice::query()->sum('amount')", $result);
    }

    public function test_sum_with_where(): void
    {
        $parser = new WidgetQueryParser('Invoice');
        $result = $parser->parseAggregate('sum(amount) where status != paid');

        $this->assertSame("Invoice::query()->where('status', '!=', 'paid')->sum('amount')", $result);
    }

    public function test_empty_dsl_returns_fallback(): void
    {
        $parser = new WidgetQueryParser('Invoice');
        $result = $parser->parseAggregate('');

        $this->assertStringContainsString('Invoice::query()->count()', $result);
        $this->assertStringContainsString('TODO', $result);
    }

    public function test_unparseable_dsl_returns_todo(): void
    {
        $parser = new WidgetQueryParser('Invoice');
        $result = $parser->parseAggregate('something invalid here');

        $this->assertStringContainsString('TODO', $result);
        $this->assertStringContainsString('Invoice::query()->count()', $result);
    }

    public function test_count_with_boolean_value(): void
    {
        $parser = new WidgetQueryParser('Task');
        $result = $parser->parseAggregate('count(*) where is_active = true');

        $this->assertSame("Task::query()->where('is_active', true)->count()", $result);
    }

    public function test_count_with_numeric_value(): void
    {
        $parser = new WidgetQueryParser('Task');
        $result = $parser->parseAggregate('count(*) where priority = 1');

        $this->assertSame("Task::query()->where('priority', 1)->count()", $result);
    }

    public function test_count_with_enum_value(): void
    {
        $parser = new WidgetQueryParser('Task', enums: [
            'TaskPriority' => [
                ['case' => 'Low', 'label' => 'Low'],
                ['case' => 'High', 'label' => 'High'],
            ],
        ]);
        $result = $parser->parseAggregate('count(*) where priority = High');

        $this->assertSame("Task::query()->where('priority', \\App\\Enums\\TaskPriority::High->value)->count()", $result);
    }

    public function test_count_with_multiple_where_conditions(): void
    {
        $parser = new WidgetQueryParser('Invoice');
        $result = $parser->parseAggregate('count(*) where status = active and is_active = true');

        $this->assertSame("Invoice::query()->where('status', 'active')->where('is_active', true)->count()", $result);
    }

    // ========================================================================
    // Table queries
    // ========================================================================

    public function test_table_query_simple_where(): void
    {
        $parser = new WidgetQueryParser('Task');
        $result = $parser->parseTableQuery('where status = active');

        $this->assertSame("Task::query()->where('status', 'active')", $result);
    }

    public function test_table_query_with_now(): void
    {
        $parser = new WidgetQueryParser('Task');
        $result = $parser->parseTableQuery('where due_date >= now');

        $this->assertSame("Task::query()->where('due_date', '>=', now())", $result);
    }

    public function test_table_query_with_order_and_limit(): void
    {
        $parser = new WidgetQueryParser('Task');
        $result = $parser->parseTableQuery('where status = active, order by due_date, limit 5');

        $this->assertSame("Task::query()->where('status', 'active')->orderBy('due_date')->limit(5)", $result);
    }

    public function test_table_query_with_order_direction(): void
    {
        $parser = new WidgetQueryParser('Task');
        $result = $parser->parseTableQuery('order by created_at desc, limit 10');

        $this->assertSame("Task::query()->orderBy('created_at', 'desc')->limit(10)", $result);
    }

    public function test_table_query_multiple_conditions(): void
    {
        $parser = new WidgetQueryParser('Task', states: ['active', 'draft']);
        $result = $parser->parseTableQuery('where status = active, due_date >= now, order by due_date, limit 5');

        $this->assertSame(
            "Task::query()->where('status', Active::getMorphClass())->where('due_date', '>=', now())->orderBy('due_date')->limit(5)",
            $result
        );
    }

    public function test_table_query_empty_returns_default(): void
    {
        $parser = new WidgetQueryParser('Task');
        $result = $parser->parseTableQuery('');

        $this->assertSame('Task::query()->latest()->limit(5)', $result);
    }

    // ========================================================================
    // Condition color parsing
    // ========================================================================

    public function test_parse_condition_color(): void
    {
        $result = WidgetQueryParser::parseConditionColor('> 0: danger, else: success');

        $this->assertSame("\$value > 0 ? 'danger' : 'success'", $result);
    }

    public function test_parse_condition_color_with_greater_equal(): void
    {
        $result = WidgetQueryParser::parseConditionColor('>= 10: warning, else: gray');

        $this->assertSame("\$value >= 10 ? 'warning' : 'gray'", $result);
    }

    public function test_parse_condition_color_empty(): void
    {
        $this->assertNull(WidgetQueryParser::parseConditionColor(''));
    }

    public function test_parse_condition_color_invalid(): void
    {
        $this->assertNull(WidgetQueryParser::parseConditionColor('not a valid expression'));
    }

    // ========================================================================
    // Column format to Filament
    // ========================================================================

    public function test_column_format_bold(): void
    {
        $this->assertSame("->weight('bold')", WidgetQueryParser::columnFormatToFilament('bold'));
    }

    public function test_column_format_date(): void
    {
        $this->assertSame('->date()', WidgetQueryParser::columnFormatToFilament('date'));
    }

    public function test_column_format_datetime(): void
    {
        $this->assertSame('->dateTime()', WidgetQueryParser::columnFormatToFilament('datetime'));
    }

    public function test_column_format_badge(): void
    {
        $this->assertSame('->badge()', WidgetQueryParser::columnFormatToFilament('badge'));
    }

    public function test_column_format_money(): void
    {
        $this->assertSame("->money('usd')", WidgetQueryParser::columnFormatToFilament('money'));
    }

    public function test_column_format_boolean(): void
    {
        $this->assertSame('->boolean()', WidgetQueryParser::columnFormatToFilament('boolean'));
    }

    public function test_column_format_empty(): void
    {
        $this->assertSame('', WidgetQueryParser::columnFormatToFilament(''));
    }

    public function test_column_format_unknown(): void
    {
        $this->assertStringContainsString('TODO', WidgetQueryParser::columnFormatToFilament('sparkline'));
    }
}
