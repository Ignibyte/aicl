<?php

namespace Aicl\View\Components;

use Aicl\Components\ComponentDefinition;
use Aicl\Components\ComponentRegistry;
use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * Renders structured reference documentation for an AICL component.
 *
 * Reads component metadata from the ComponentRegistry and displays
 * a props table, AI decision rule, context tags, Filament equivalent,
 * and composable-in list inside a collapsible accordion.
 *
 * AI Decision Rules:
 * - Use in styleguide pages below each component demo
 * - Always pass the short tag name (e.g., 'status-badge')
 */
class ComponentReference extends Component
{
    public ?ComponentDefinition $definition;

    public function __construct(
        public string $component,
    ) {
        $this->definition = app(ComponentRegistry::class)->get($this->component);
    }

    public function render(): View
    {
        return view('aicl::components.component-reference');
    }
}
