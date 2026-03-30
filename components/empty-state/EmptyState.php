<?php

declare(strict_types=1);

namespace Aicl\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * Empty state placeholder with message and optional action.
 *
 * AI Decision Rules:
 * - Use when a list/table/section has no data to display
 * - Always include a heading and description
 * - Include an action button when the user can create the missing item
 * - Use an icon that represents the missing content type
 *
 * @example <x-aicl-empty-state
 *     heading="No projects yet"
 *     description="Get started by creating your first project."
 *     icon="heroicon-o-briefcase"
 *     :action-url="route('projects.create')"
 *     action-label="Create Project"
 * />
 */
class EmptyState extends Component
{
    public function __construct(
        public string $heading,
        public string $description = '',
        public string $icon = 'heroicon-o-inbox',
        public ?string $actionUrl = null,
        public ?string $actionLabel = null,
    ) {}

    public function render(): View
    {
        return view('aicl::components.empty-state');
    }
}
