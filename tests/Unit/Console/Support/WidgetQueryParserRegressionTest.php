<?php

declare(strict_types=1);

namespace Aicl\Tests\Unit\Console\Support;

use Aicl\Console\Support\WidgetQueryParser;
use PHPUnit\Framework\TestCase;

/**
 * Regression tests for WidgetQueryParser PHPStan changes.
 *
 * Tests the declare(strict_types=1) addition and PHPDoc type
 * annotation improvements. WidgetQueryParser has parseAggregate(),
 * not parse().
 */
class WidgetQueryParserRegressionTest extends TestCase
{
    /**
     * Test parseAggregate handles count query expression.
     *
     * Verifies parsing works under strict_types.
     */
    public function test_parse_aggregate_count_query(): void
    {
        // Arrange
        $parser = new WidgetQueryParser('TestEntity');

        // Act: parse a count query
        $result = $parser->parseAggregate('count(*)');

        // Assert: should return a valid Eloquent code string
        $this->assertStringContainsString('count()', $result);
    }

    /**
     * Test parseAggregate handles count with where clause.
     *
     * Verifies query parsing with typed returns.
     */
    public function test_parse_aggregate_count_with_where(): void
    {
        // Arrange
        $parser = new WidgetQueryParser('TestEntity');

        // Act
        $result = $parser->parseAggregate('count(*) where status = active');

        // Assert: should contain where clause
        $this->assertStringContainsString('where', $result);
    }

    /**
     * Test parseAggregate handles sum query.
     *
     * Verifies sum aggregate works under strict_types.
     */
    public function test_parse_aggregate_sum_query(): void
    {
        // Arrange
        $parser = new WidgetQueryParser('TestEntity');

        // Act
        $result = $parser->parseAggregate('sum(amount)');

        // Assert
        $this->assertStringContainsString('sum', $result);
    }
}
