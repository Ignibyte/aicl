<?php

declare(strict_types=1);

namespace Aicl\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * Client-side data table with sorting, filtering, pagination, and row selection.
 *
 * AI Decision Rules:
 * - Use for static/pre-loaded data display outside Filament admin context
 * - For Filament admin, use the declarative Table builder instead
 * - Use sortable=true for data users need to compare/rank
 * - Use selectable=true when bulk actions are available
 * - Keep column count reasonable (4-8) for readability
 */
class DataTable extends Component
{
    public function __construct(
        public array $columns = [],
        public array $data = [],
        public bool $sortable = true,
        public bool $filterable = true,
        public bool $paginated = true,
        public int $perPage = 10,
        public array $perPageOptions = [5, 10, 25, 50],
        public bool $selectable = false,
        public string $emptyMessage = 'No data available',
        public string $emptyIcon = 'heroicon-o-table-cells',
    ) {}

    public function render(): View
    {
        return view('aicl::components.data-table');
    }
}
