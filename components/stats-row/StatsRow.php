<?php

namespace Aicl\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * Horizontal row of stat cards.
 *
 * AI Decision Rules:
 * - Use at the top of dashboards or detail pages for key metrics
 * - Always use 3-4 stats per row (fewer looks sparse, more looks cramped)
 * - Pair with charts below for a complete dashboard section
 *
 * @example <x-aicl-stats-row>
 *     <x-aicl-stat-card label="Total" value="42" />
 *     <x-aicl-stat-card label="Active" value="28" />
 * </x-aicl-stats-row>
 */
class StatsRow extends Component
{
    public function render(): View
    {
        return view('aicl::components.stats-row');
    }
}
