<?php

declare(strict_types=1);

namespace Aicl\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * Key-value definition list for entity metadata.
 *
 * AI Decision Rules:
 * - Use in detail/view pages to display entity attributes
 * - Use in sidebar of SplitLayout for contextual metadata
 * - Pass items as associative array ['Label' => 'Value']
 * - Use for non-editable data display (dates, IDs, statuses)
 *
 * @example <x-aicl-metadata-list :items="['Created' => $project->created_at, 'Owner' => $project->owner->name]" />
 *
 * @codeCoverageIgnore Blade view component
 */
class MetadataList extends Component
{
    /**
     * @param array<string, string|null> $items
     */
    public function __construct(
        public array $items = [],
    ) {}

    public function render(): View
    {
        return view('aicl::components.metadata-list');
    }
}
