<?php

declare(strict_types=1);

namespace Aicl\Tests\Unit\Console\Support;

use Aicl\Console\Support\ToolSpecParser;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Regression tests for ToolSpecParser PHPStan changes.
 *
 * Tests the declare(strict_types=1) addition and the PHPDoc
 * type annotation improvements for parameter and return types.
 * Uses parseContent() since parse() takes a file path.
 * The ## Tool table expects Field | Value columns (not Key | Value).
 */
class ToolSpecParserRegressionTest extends TestCase
{
    /**
     * Test parseContent creates ToolSpec from valid markdown.
     *
     * Verifies parsing works correctly after strict_types addition.
     */
    public function test_parse_content_creates_tool_spec(): void
    {
        // Arrange: valid tool spec markdown with Field | Value columns
        $content = <<<'MD'
# SearchRecords

Search through entity records.

## Tool
| Field | Value |
|-------|-------|
| Name | search_records |
| Description | Search through records |

## Parameters
| Name | Type | Required | Description |
|------|------|----------|-------------|
| query | string | yes | Search query |

## Returns
| Name | Type | Description |
|------|------|-------------|
| results | array | Matching records |

MD;

        $parser = new ToolSpecParser;

        // Act
        $spec = $parser->parseContent($content);

        // Assert: should return valid ToolSpec
        $this->assertSame('search_records', $spec->name);
    }

    /**
     * Test parse throws for nonexistent file.
     *
     * Verifies file existence check under strict_types.
     */
    public function test_parse_throws_for_nonexistent_file(): void
    {
        // Arrange
        $parser = new ToolSpecParser;

        // Assert + Act
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('not found');

        $parser->parse('/tmp/nonexistent_tool_spec_'.uniqid().'.md');
    }

    /**
     * Test parseContent handles minimal tool spec.
     */
    public function test_parse_content_handles_minimal_spec(): void
    {
        // Arrange: minimal tool spec (no parameters or returns)
        $content = <<<'MD'
# GetStats

Get system statistics.

## Tool
| Field | Value |
|-------|-------|
| Name | get_stats |
| Description | Retrieves system stats |

MD;

        $parser = new ToolSpecParser;

        // Act
        $spec = $parser->parseContent($content);

        // Assert: should parse without error
        $this->assertSame('get_stats', $spec->name);
    }
}
