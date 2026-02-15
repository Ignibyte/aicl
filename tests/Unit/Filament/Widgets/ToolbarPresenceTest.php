<?php

namespace Aicl\Tests\Unit\Filament\Widgets;

use Aicl\Filament\Widgets\ToolbarPresence;
use Livewire\Component;
use Tests\TestCase;

class ToolbarPresenceTest extends TestCase
{
    public function test_extends_livewire_component(): void
    {
        $this->assertTrue(is_subclass_of(ToolbarPresence::class, Component::class));
    }

    public function test_livewire_component_alias_is_registered(): void
    {
        $componentClass = app(\Livewire\Mechanisms\ComponentRegistry::class)
            ->getClass('toolbar-presence');

        $this->assertSame(ToolbarPresence::class, $componentClass);
    }

    public function test_component_renders_view(): void
    {
        $component = new ToolbarPresence;
        $view = $component->render();

        $this->assertSame('aicl::widgets.toolbar-presence', $view->name());
    }
}
