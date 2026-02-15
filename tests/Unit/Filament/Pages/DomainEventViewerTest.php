<?php

namespace Aicl\Tests\Unit\Filament\Pages;

use Aicl\Filament\Pages\DomainEventViewer;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Tables\Contracts\HasTable;
use PHPUnit\Framework\TestCase;

class DomainEventViewerTest extends TestCase
{
    public function test_extends_page(): void
    {
        $this->assertTrue(is_subclass_of(DomainEventViewer::class, Page::class));
    }

    public function test_implements_has_table(): void
    {
        $this->assertTrue(is_subclass_of(DomainEventViewer::class, HasTable::class));
    }

    public function test_implements_has_forms(): void
    {
        $this->assertTrue(is_subclass_of(DomainEventViewer::class, HasForms::class));
    }

    public function test_slug(): void
    {
        $reflection = new \ReflectionClass(DomainEventViewer::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertEquals('domain-events', $defaults['slug']);
    }

    public function test_navigation_group(): void
    {
        $reflection = new \ReflectionClass(DomainEventViewer::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertEquals('System', $defaults['navigationGroup']);
    }

    public function test_navigation_sort(): void
    {
        $reflection = new \ReflectionClass(DomainEventViewer::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertEquals(13, $defaults['navigationSort']);
    }

    public function test_view_is_non_static_instance_property(): void
    {
        $reflection = new \ReflectionClass(DomainEventViewer::class);
        $property = $reflection->getProperty('view');

        $this->assertFalse($property->isStatic());
        $this->assertEquals('aicl::filament.pages.domain-event-viewer', $property->getDefaultValue());
    }
}
