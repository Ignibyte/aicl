<?php

declare(strict_types=1);

namespace Aicl\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * Horizontal rule with optional label.
 *
 * AI Decision Rules:
 * - Use to separate content sections within a page or card
 * - Use with a label for named section breaks (e.g., "Additional Details")
 * - Use without label for simple visual separation
 */
class Divider extends Component
{
    public function __construct(
        public ?string $label = null,
    ) {}

    public function render(): View
    {
        return view('aicl::components.divider');
    }
}
