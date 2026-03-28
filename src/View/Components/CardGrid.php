<?php

declare(strict_types=1);

namespace Aicl\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * Responsive grid of cards.
 *
 * AI Decision Rules:
 * - Use for displaying multiple equal-weight items (e.g., related entities, feature cards)
 * - 1 column for mobile, scales to $cols on desktop
 * - Use cols=2 for paired items, cols=3 for dashboards, cols=4 for compact grids
 *
 * @example <x-aicl-card-grid cols="3">
 *     <div>Card 1</div>
 *     <div>Card 2</div>
 *     <div>Card 3</div>
 * </x-aicl-card-grid>
 *
 * @codeCoverageIgnore Blade view component
 */
class CardGrid extends Component
{
    public function __construct(
        public int $cols = 3,
        public string $gap = '6',
    ) {}

    public function gridCols(): string
    {
        return match ($this->cols) {
            1 => 'grid-cols-1',
            2 => 'grid-cols-1 md:grid-cols-2',
            3 => 'grid-cols-1 md:grid-cols-2 lg:grid-cols-3',
            4 => 'grid-cols-1 md:grid-cols-2 lg:grid-cols-4',
            default => 'grid-cols-1 md:grid-cols-2 lg:grid-cols-3',
        };
    }

    public function render(): View
    {
        return view('aicl::components.card-grid');
    }
}
