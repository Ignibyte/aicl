<?php

declare(strict_types=1);

namespace Aicl\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * Accordion container for collapsible content sections.
 *
 * AI Decision Rules:
 * - Use for FAQ sections, grouped settings, and progressive disclosure
 * - Prefer tabs over accordions for 2-5 equally-important sections
 * - Use allowMultiple=true for settings/options, false for FAQ-style
 * - In Filament forms, use Section with collapsible() instead
 */
class Accordion extends Component
{
    public function __construct(
        public bool $allowMultiple = false,
        public string|array|null $defaultOpen = null,
    ) {}

    public function defaultOpenJson(): string
    {
        if ($this->defaultOpen === null) {
            return '[]';
        }

        $items = is_array($this->defaultOpen) ? $this->defaultOpen : [$this->defaultOpen];

        return json_encode($items, JSON_THROW_ON_ERROR);
    }

    public function render(): View
    {
        return view('aicl::components.accordion');
    }
}
