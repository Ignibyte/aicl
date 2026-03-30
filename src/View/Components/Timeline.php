<?php

declare(strict_types=1);

namespace Aicl\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * Vertical timeline with entries.
 *
 * AI Decision Rules:
 * - Use for audit logs, activity history, and change tracking
 * - Use in detail pages to show entity lifecycle
 * - Each entry should have a timestamp and description
 * - Use icon and color to differentiate entry types
 *
 * @example <x-aicl-timeline :entries="[
 *     ['date' => '2026-01-15', 'title' => 'Project created', 'description' => 'By Admin', 'color' => 'green'],
 *     ['date' => '2026-01-20', 'title' => 'Status changed', 'description' => 'Draft → Active', 'color' => 'blue'],
 * ]" />
 *
 * @codeCoverageIgnore Blade view component
 */
class Timeline extends Component
{
    /**
     * @param array<int, array{date: string, title: string, description?: string, color?: string, icon?: string}> $entries
     */
    public function __construct(
        public array $entries = [],
    ) {}

    public function render(): View
    {
        return view('aicl::components.timeline');
    }
}
