<?php

declare(strict_types=1);

namespace Aicl\Tests\Unit\Services;

use Aicl\Services\PdfGenerator;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Regression tests for PdfGenerator PHPStan changes.
 *
 * Tests the (bool) type cast added to Storage::put() return value
 * in the save() method. Under strict_types, Storage::put() returns
 * string|bool depending on the driver, and the save() method must
 * return a strict bool.
 */
class PdfGeneratorRegressionTest extends TestCase
{
    /**
     * Test save() returns bool true on successful write to default disk.
     *
     * PHPStan change: Added (bool) cast to Storage::put() return value
     * because put() can return string|bool depending on the storage driver.
     */
    public function test_save_returns_bool_on_default_disk(): void
    {
        // Arrange: fake the storage disk
        Storage::fake('local');

        // Create a simple Blade view for PDF generation
        $viewName = 'test-pdf-regression';
        $viewPath = resource_path("views/{$viewName}.blade.php");
        $viewDir = dirname($viewPath);

        if (! is_dir($viewDir)) {
            mkdir($viewDir, 0755, true);
        }

        file_put_contents($viewPath, '<html><body><h1>Test PDF</h1></body></html>');

        try {
            // Act
            $generator = new PdfGenerator;
            $result = $generator->save($viewName, [], 'test-output.pdf');

            // Assert: should return a strict bool, not string
            $this->assertTrue($result);
        } finally {
            // Cleanup
            if (file_exists($viewPath)) {
                unlink($viewPath);
            }
        }
    }

    /**
     * Test save() returns bool true on successful write to named disk.
     *
     * PHPStan change: Both code paths (with $disk and without) cast to (bool).
     */
    public function test_save_returns_bool_on_named_disk(): void
    {
        // Arrange: fake a named disk
        Storage::fake('reports');

        $viewName = 'test-pdf-disk-regression';
        $viewPath = resource_path("views/{$viewName}.blade.php");
        $viewDir = dirname($viewPath);

        if (! is_dir($viewDir)) {
            mkdir($viewDir, 0755, true);
        }

        file_put_contents($viewPath, '<html><body><h1>Report</h1></body></html>');

        try {
            // Act: save to named disk
            $generator = new PdfGenerator;
            $result = $generator->save($viewName, [], 'reports/test.pdf', 'reports');

            // Assert: should return strict bool
            $this->assertTrue($result);
        } finally {
            if (file_exists($viewPath)) {
                unlink($viewPath);
            }
        }
    }

    /**
     * Test PdfGenerator fluent methods return static under strict_types.
     *
     * Verifies that the fluent builder pattern works correctly with
     * declare(strict_types=1).
     */
    public function test_fluent_methods_return_same_instance(): void
    {
        // Arrange
        $generator = new PdfGenerator;

        // Act: chain fluent methods
        $result = $generator->paper('letter')->landscape();

        // Assert: should return the same instance for chaining
        $this->assertSame($generator, $result);
    }

    /**
     * Test make() static factory returns correct type under strict_types.
     */
    public function test_make_returns_pdf_generator_instance(): void
    {
        // Act
        $generator = PdfGenerator::make();

        // Assert
        $this->assertInstanceOf(PdfGenerator::class, $generator);
    }
}
