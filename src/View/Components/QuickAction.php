<?php

declare(strict_types=1);

namespace Aicl\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * Icon button with tooltip for quick actions.
 *
 * AI Decision Rules:
 * - Use inside ActionBar for compact icon-only actions
 * - Always include a tooltip label for accessibility
 * - Use for secondary actions (copy, share, pin, bookmark)
 *
 * @codeCoverageIgnore Blade view component
 */
class QuickAction extends Component
{
    public function __construct(
        public string $icon,
        public string $label,
        public ?string $href = null,
        public string $color = 'gray',
    ) {}

    public function render(): View
    {
        return view('aicl::components.quick-action');
    }
}
