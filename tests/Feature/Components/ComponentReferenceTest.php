<?php

namespace Aicl\Tests\Feature\Components;

use Aicl\Components\ComponentDefinition;
use Aicl\View\Components\ComponentReference;
use Tests\TestCase;

class ComponentReferenceTest extends TestCase
{
    public function test_component_reference_can_be_instantiated(): void
    {
        $component = new ComponentReference(component: 'status-badge');

        $this->assertEquals('status-badge', $component->component);
        $this->assertInstanceOf(ComponentDefinition::class, $component->definition);
    }

    public function test_component_reference_returns_null_for_unknown_component(): void
    {
        $component = new ComponentReference(component: 'nonexistent-widget');

        $this->assertNull($component->definition);
    }

    public function test_component_reference_renders_nothing_for_unknown_component(): void
    {
        $view = $this->blade(
            '<x-aicl-component-reference component="nonexistent-widget" />'
        );

        $view->assertDontSee('Component Reference', false);
    }

    public function test_component_reference_renders_decision_rule(): void
    {
        $view = $this->blade(
            '<x-aicl-component-reference component="status-badge" />'
        );

        $view->assertSee('AI Decision Rule', false);
        $view->assertSee('Use for any status, state, or category field', false);
    }

    public function test_component_reference_renders_props_table(): void
    {
        $view = $this->blade(
            '<x-aicl-component-reference component="status-badge" />'
        );

        $view->assertSee('Props', false);
        $view->assertSee('label', false);
        $view->assertSee('color', false);
        $view->assertSee('icon', false);
    }

    public function test_component_reference_renders_context_tags(): void
    {
        $view = $this->blade(
            '<x-aicl-component-reference component="status-badge" />'
        );

        $view->assertSee('Rendering Contexts', false);
        $view->assertSee('blade', false);
        $view->assertSee('livewire', false);
    }

    public function test_component_reference_renders_excluded_contexts(): void
    {
        $view = $this->blade(
            '<x-aicl-component-reference component="status-badge" />'
        );

        // status-badge has not_for: ["filament-form"]
        $view->assertSee('filament-form', false);
    }

    public function test_component_reference_renders_filament_equivalent(): void
    {
        $view = $this->blade(
            '<x-aicl-component-reference component="status-badge" />'
        );

        $view->assertSee('Filament Equivalent', false);
        $view->assertSee('Filament\Tables\Columns\TextColumn', false);
    }

    public function test_component_reference_renders_composable_in(): void
    {
        $view = $this->blade(
            '<x-aicl-component-reference component="status-badge" />'
        );

        $view->assertSee('Composable In', false);
        $view->assertSee('x-aicl-stats-row', false);
        $view->assertSee('x-aicl-card-grid', false);
    }

    public function test_component_reference_uses_accordion(): void
    {
        $view = $this->blade(
            '<x-aicl-component-reference component="status-badge" />'
        );

        $view->assertSee('Component Reference', false);
        $view->assertSee('ref-status-badge', false);
    }

    public function test_component_reference_renders_required_prop_indicator(): void
    {
        $view = $this->blade(
            '<x-aicl-component-reference component="status-badge" />'
        );

        // "label" prop is required=true, should show "Yes" indicator
        $view->assertSee('Yes', false);
    }
}
