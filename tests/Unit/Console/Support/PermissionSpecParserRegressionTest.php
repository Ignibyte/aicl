<?php

declare(strict_types=1);

namespace Aicl\Tests\Unit\Console\Support;

use Aicl\Console\Support\PermissionSpecParser;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Regression tests for PermissionSpecParser PHPStan changes.
 *
 * Tests the declare(strict_types=1) addition and the PHPDoc type
 * annotation improvements. Verifies parsing behavior is preserved.
 * PermissionSpecParser.parse() takes a file path, not a definition string.
 */
class PermissionSpecParserRegressionTest extends TestCase
{
    /**
     * Test parseContent handles well-formed markdown content.
     *
     * Verifies parsing works correctly after strict_types addition.
     */
    public function test_parse_content_with_valid_markdown(): void
    {
        // Arrange: valid permission spec markdown
        $content = <<<'MD'
# Permissions

## Roles
| Role | Description | Guard |
|------|-------------|-------|
| admin | Administrator | web |
| viewer | Read-only user | web |

## Permissions
| Entity | Admin | Viewer |
|--------|-------|--------|
| User | * | view_any,view |

MD;

        $parser = new PermissionSpecParser;

        // Act
        $spec = $parser->parseContent($content);

        // Assert: should return valid PermissionSpec
        $this->assertNotEmpty($spec->roles);
        $this->assertCount(2, $spec->roles);
    }

    /**
     * Test parse throws for nonexistent file.
     *
     * Verifies file existence check under strict_types.
     */
    public function test_parse_throws_for_nonexistent_file(): void
    {
        // Arrange
        $parser = new PermissionSpecParser;

        // Assert + Act
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('not found');

        $parser->parse('/tmp/nonexistent_perm_spec_'.uniqid().'.md');
    }

    /**
     * Test parseContent throws when Roles section missing.
     *
     * Validates error handling under strict_types.
     */
    public function test_parse_content_throws_without_roles_section(): void
    {
        // Arrange: content without ## Roles
        $content = "# Permissions\n\nSome text.\n";
        $parser = new PermissionSpecParser;

        // Assert + Act
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Roles');

        $parser->parseContent($content);
    }
}
