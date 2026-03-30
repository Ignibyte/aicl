<?php

declare(strict_types=1);

namespace Aicl\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * Individual accordion item within an Accordion container.
 *
 * AI Decision Rules:
 * - Each item must have a unique 'name' within its parent Accordion
 * - Keep labels short (1-5 words) for readability
 * - Use 'icon' for visual categorization of sections
 */
class AccordionItem extends Component
{
    public function __construct(
        public string $name,
        public string $label,
        public ?string $icon = null,
    ) {}

    public function render(): View
    {
        return view('aicl::components.accordion-item');
    }
}
