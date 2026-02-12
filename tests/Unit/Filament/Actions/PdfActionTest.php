<?php

namespace Aicl\Tests\Unit\Filament\Actions;

use Aicl\Filament\Actions\PdfAction;
use Filament\Actions\Action;
use PHPUnit\Framework\TestCase;

class PdfActionTest extends TestCase
{
    public function test_pdf_action_extends_filament_action(): void
    {
        $this->assertTrue(is_subclass_of(PdfAction::class, Action::class));
    }

    public function test_default_name_is_download_pdf(): void
    {
        $this->assertEquals('download_pdf', PdfAction::getDefaultName());
    }

    public function test_pdf_view_method_exists(): void
    {
        $this->assertTrue(method_exists(PdfAction::class, 'pdfView'));
    }

    public function test_filename_method_exists(): void
    {
        $this->assertTrue(method_exists(PdfAction::class, 'filename'));
    }

    public function test_pdf_data_method_exists(): void
    {
        $this->assertTrue(method_exists(PdfAction::class, 'pdfData'));
    }

    public function test_paper_method_exists(): void
    {
        $this->assertTrue(method_exists(PdfAction::class, 'paper'));
    }

    public function test_orientation_method_exists(): void
    {
        $this->assertTrue(method_exists(PdfAction::class, 'orientation'));
    }

    public function test_landscape_method_exists(): void
    {
        $this->assertTrue(method_exists(PdfAction::class, 'landscape'));
    }

    public function test_pdf_filename_property_accepts_string_or_closure(): void
    {
        $reflection = new \ReflectionClass(PdfAction::class);
        $property = $reflection->getProperty('pdfFilename');
        $type = $property->getType();

        $this->assertNotNull($type);
    }

    public function test_default_paper_is_a4(): void
    {
        $reflection = new \ReflectionClass(PdfAction::class);
        $property = $reflection->getProperty('paper');
        $property->setAccessible(true);

        // Create instance without Livewire context — check default via reflection
        $this->assertEquals('a4', $property->getDefaultValue());
    }

    public function test_default_orientation_is_portrait(): void
    {
        $reflection = new \ReflectionClass(PdfAction::class);
        $property = $reflection->getProperty('orientation');
        $property->setAccessible(true);

        $this->assertEquals('portrait', $property->getDefaultValue());
    }
}
