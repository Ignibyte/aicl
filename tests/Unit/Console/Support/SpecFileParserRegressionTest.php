<?php

declare(strict_types=1);

namespace Aicl\Tests\Unit\Console\Support;

use Aicl\Console\Support\SpecFileParser;
use PHPUnit\Framework\TestCase;

/**
 * Regression tests for SpecFileParser PHPStan changes.
 *
 * Tests the declare(strict_types=1) addition and the PHPDoc type
 * annotation improvements to array parameters and return types.
 * Uses parseContent() since parse() takes a file path.
 */
class SpecFileParserRegressionTest extends TestCase
{
    /**
     * Test parseContent handles well-formed spec file content.
     *
     * Verifies parsing works correctly after strict_types addition.
     */
    public function test_parse_content_returns_entity_spec(): void
    {
        // Arrange: create a minimal spec file content
        $content = <<<'SPEC'
# TestEntity

## Fields
| Name | Type | Modifiers |
|------|------|-----------|
| title | string | |
| description | text | nullable |

## States
- draft
- active

SPEC;

        // Act
        $parser = new SpecFileParser;
        $spec = $parser->parseContent($content);

        // Assert: should return a valid EntitySpec without type errors
        $this->assertSame('TestEntity', $spec->name);
        $this->assertNotEmpty($spec->fields);
    }

    /**
     * Test parseContent handles spec without optional sections.
     *
     * Many spec sections (notifications, observer rules, widgets)
     * are optional and their arrays may be null.
     */
    public function test_parse_content_handles_minimal_spec(): void
    {
        // Arrange: spec with only name and fields (minimum required)
        $content = <<<'SPEC'
# MinimalEntity

## Fields
| Name | Type | Modifiers |
|------|------|-----------|
| name | string | |

SPEC;

        // Act
        $parser = new SpecFileParser;
        $spec = $parser->parseContent($content);

        // Assert: optional sections should be empty/null, not cause errors
        $this->assertSame('MinimalEntity', $spec->name);
        $this->assertNotEmpty($spec->fields);
    }
}
