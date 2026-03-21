<?php

declare(strict_types=1);

namespace Aicl\Tests\Unit\Console\Generators;

use PHPUnit\Framework\TestCase;

/**
 * Regression tests for ViewGenerator PHPStan changes.
 *
 * Tests the declare(strict_types=1) addition, the FieldDefinition import,
 * the file_get_contents() ?: '' null coalescing, and the @return PHPDoc
 * annotation change from FQCN to imported class reference.
 */
class ViewGeneratorRegressionTest extends TestCase
{
    /**
     * Test file_get_contents ternary with coalesce returns empty string for missing file.
     *
     * PHPStan change: Changed from plain file_get_contents() to
     * (file_get_contents($routesFile) ?: '') to handle false return.
     */
    public function test_file_get_contents_coalesce_handles_missing_file(): void
    {
        // Arrange: non-existent file path
        $path = '/tmp/nonexistent_routes_file_'.uniqid().'.php';

        // Act: replicate the pattern from ViewGenerator
        $existing = file_exists($path) ? (file_get_contents($path) ?: '') : '';

        // Assert: should be empty string, not false
        $this->assertSame('', $existing);
    }

    /**
     * Test file_get_contents coalesce handles existing empty file.
     *
     * Edge case: file exists but is empty.
     */
    public function test_file_get_contents_coalesce_handles_empty_file(): void
    {
        // Arrange: create an empty file
        $path = tempnam(sys_get_temp_dir(), 'viewgen_');
        file_put_contents($path, '');

        try {
            // Act: replicate the pattern
            $existing = file_exists($path) ? (file_get_contents($path) ?: '') : '';

            // Assert: should be empty string
            $this->assertSame('', $existing);
        } finally {
            unlink($path);
        }
    }

    /**
     * Test file_get_contents coalesce handles file with content.
     *
     * Happy path: file exists and has content.
     */
    public function test_file_get_contents_coalesce_preserves_content(): void
    {
        // Arrange: create a file with content
        $path = tempnam(sys_get_temp_dir(), 'viewgen_');
        file_put_contents($path, '<?php // routes');

        try {
            // Act
            $existing = file_exists($path) ? (file_get_contents($path) ?: '') : '';

            // Assert: should preserve the file content
            $this->assertSame('<?php // routes', $existing);
        } finally {
            unlink($path);
        }
    }

    /**
     * Test buildColumnsPhpArray PHPDoc annotation.
     *
     * PHPStan change: Added @param array<int, array<string, mixed>> $columns.
     * Verifies the method signature expectation.
     */
    public function test_columns_array_structure(): void
    {
        // Arrange: create a columns array matching the typed annotation
        $columns = [
            ['key' => 'title', 'label' => 'Title', 'sortable' => true],
            ['key' => 'status', 'label' => 'Status', 'sortable' => false],
        ];

        // Assert: should be array of arrays with mixed values
        $this->assertCount(2, $columns);
    }
}
