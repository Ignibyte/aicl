<?php

declare(strict_types=1);

namespace Aicl\Tests\Unit\Filament\Actions;

use Aicl\Filament\Actions\PdfAction;
use PHPUnit\Framework\TestCase;

/**
 * Regression tests for PdfAction PHPStan changes.
 *
 * Covers the added docblocks on properties (pdfView, pdfFilename,
 * dataCallback, paper, orientation) and methods (getDefaultName,
 * setUp, pdfView, filename, pdfData, paper, landscape, portrait).
 * Changes are PHPDoc-only -- tests verify the builder API contracts.
 */
class PdfActionRegressionTest extends TestCase
{
    // -- getDefaultName --

    /**
     * Test getDefaultName returns 'download_pdf'.
     *
     * PHPStan change: Added docblock, no behavioral change.
     */
    public function test_get_default_name_returns_download_pdf(): void
    {
        // Act
        $name = PdfAction::getDefaultName();

        // Assert
        $this->assertSame('download_pdf', $name);
    }

    // -- Builder API --

    /**
     * Test pdfView builder method returns self for chaining.
     *
     * PHPStan change: Added @return static annotation.
     */
    public function test_pdf_view_returns_self(): void
    {
        // Arrange
        $action = PdfAction::make();

        // Act
        $result = $action->pdfView('pdf.test');

        // Assert: returns same instance for chaining
        $this->assertSame($action, $result);
    }

    /**
     * Test paper builder method returns self for chaining.
     *
     * PHPStan change: Added @return static annotation.
     */
    public function test_paper_returns_self(): void
    {
        // Arrange
        $action = PdfAction::make();

        // Act
        $result = $action->paper('letter');

        // Assert: returns same instance for chaining
        $this->assertSame($action, $result);
    }

    /**
     * Test landscape builder method returns self for chaining.
     *
     * PHPStan change: Added @return static annotation.
     */
    public function test_landscape_returns_self(): void
    {
        // Arrange
        $action = PdfAction::make();

        // Act
        $result = $action->landscape();

        // Assert: returns same instance for chaining
        $this->assertSame($action, $result);
    }

    /**
     * Test orientation builder method returns self for chaining.
     *
     * PHPStan change: Added @return static annotation.
     */
    public function test_orientation_returns_self(): void
    {
        // Arrange
        $action = PdfAction::make();

        // Act: set explicit portrait orientation
        $result = $action->orientation('portrait');

        // Assert: returns same instance for chaining
        $this->assertSame($action, $result);
    }

    /**
     * Test filename builder method accepts a string.
     *
     * PHPStan change: Added property type annotation string|\Closure|null.
     */
    public function test_filename_accepts_string(): void
    {
        // Arrange
        $action = PdfAction::make();

        // Act
        $result = $action->filename('test-report.pdf');

        // Assert: returns same instance for chaining
        $this->assertSame($action, $result);
    }

    /**
     * Test filename builder method accepts a closure.
     *
     * PHPStan change: Added property type annotation string|\Closure|null.
     */
    public function test_filename_accepts_closure(): void
    {
        // Arrange
        $action = PdfAction::make();

        // Act: pass a closure that would receive the record
        $result = $action->filename(fn () => 'dynamic-report.pdf');

        // Assert: returns same instance for chaining
        $this->assertSame($action, $result);
    }
}
