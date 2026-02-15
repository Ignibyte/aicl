<?php

namespace Aicl\Tests\Unit\Console\Support;

use Aicl\Console\Support\ColumnDefinition;
use Aicl\Console\Support\MetricDefinition;
use Aicl\Console\Support\WidgetSpec;
use PHPUnit\Framework\TestCase;

class WidgetSpecTest extends TestCase
{
    // ========================================================================
    // Value Object Construction
    // ========================================================================

    public function test_widget_spec_stats_type(): void
    {
        $metric = new MetricDefinition(
            label: 'Total Invoices',
            query: 'count(*)',
            color: 'primary',
        );

        $widget = new WidgetSpec(
            type: 'stats',
            name: 'StatsOverview',
            metrics: [$metric],
        );

        $this->assertSame('stats', $widget->type);
        $this->assertSame('StatsOverview', $widget->name);
        $this->assertCount(1, $widget->metrics);
        $this->assertSame('Total Invoices', $widget->metrics[0]->label);
    }

    public function test_widget_spec_chart_type(): void
    {
        $widget = new WidgetSpec(
            type: 'chart',
            name: 'Chart',
            chartType: 'doughnut',
            groupBy: 'status',
            colors: ['draft' => 'gray', 'active' => 'success'],
        );

        $this->assertSame('chart', $widget->type);
        $this->assertSame('doughnut', $widget->chartType);
        $this->assertSame('status', $widget->groupBy);
        $this->assertCount(2, $widget->colors);
        $this->assertSame('gray', $widget->colors['draft']);
    }

    public function test_widget_spec_table_type(): void
    {
        $col1 = new ColumnDefinition(name: 'name', format: 'bold');
        $col2 = new ColumnDefinition(name: 'due_date', format: 'date');

        $widget = new WidgetSpec(
            type: 'table',
            name: 'Upcoming Deadlines',
            query: 'where status = active, order by due_date, limit 5',
            columns: [$col1, $col2],
        );

        $this->assertSame('table', $widget->type);
        $this->assertSame('Upcoming Deadlines', $widget->name);
        $this->assertSame('where status = active, order by due_date, limit 5', $widget->query);
        $this->assertCount(2, $widget->columns);
        $this->assertSame('name', $widget->columns[0]->name);
        $this->assertSame('bold', $widget->columns[0]->format);
    }

    // ========================================================================
    // MetricDefinition
    // ========================================================================

    public function test_metric_with_condition_color(): void
    {
        $metric = new MetricDefinition(
            label: 'Overdue',
            query: 'count(*) where status = overdue',
            color: 'danger',
            conditionColor: '> 0: danger, else: success',
        );

        $this->assertSame('Overdue', $metric->label);
        $this->assertSame('danger', $metric->color);
        $this->assertSame('> 0: danger, else: success', $metric->conditionColor);
    }

    public function test_metric_without_condition_color(): void
    {
        $metric = new MetricDefinition(
            label: 'Total',
            query: 'count(*)',
            color: 'primary',
        );

        $this->assertNull($metric->conditionColor);
    }

    // ========================================================================
    // ColumnDefinition
    // ========================================================================

    public function test_column_with_format(): void
    {
        $col = new ColumnDefinition(name: 'priority', format: 'badge');

        $this->assertSame('priority', $col->name);
        $this->assertSame('badge', $col->format);
    }

    public function test_column_without_format(): void
    {
        $col = new ColumnDefinition(name: 'owner.name');

        $this->assertSame('owner.name', $col->name);
        $this->assertSame('', $col->format);
    }

    // ========================================================================
    // Default Values
    // ========================================================================

    public function test_widget_spec_defaults(): void
    {
        $widget = new WidgetSpec(type: 'stats', name: 'Test');

        $this->assertSame([], $widget->metrics);
        $this->assertNull($widget->chartType);
        $this->assertNull($widget->groupBy);
        $this->assertSame([], $widget->colors);
        $this->assertNull($widget->query);
        $this->assertSame([], $widget->columns);
    }
}
