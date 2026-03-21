<?php

namespace Aicl\Tests\Unit\Filament\Pages;

use Aicl\Livewire\DomainEventTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Widgets\TableWidget;
use PHPUnit\Framework\TestCase;

class DomainEventViewerTest extends TestCase
{
    public function test_extends_table_widget(): void
    {
        $this->assertTrue((new \ReflectionClass(DomainEventTable::class))->isSubclassOf(TableWidget::class));
    }

    public function test_implements_has_table(): void
    {
        $this->assertTrue((new \ReflectionClass(DomainEventTable::class))->isSubclassOf(HasTable::class));
    }

    public function test_has_table_method(): void
    {
        $this->assertTrue((new \ReflectionClass(DomainEventTable::class))->hasMethod('table'));
    }

    public function test_is_not_auto_discovered(): void
    {
        $reflection = new \ReflectionClass(DomainEventTable::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertFalse($defaults['isDiscovered']);
    }
}
