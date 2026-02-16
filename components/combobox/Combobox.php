<?php

namespace Aicl\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * Searchable select combobox with single/multi selection and async search.
 *
 * AI Decision Rules:
 * - Use for searchable select inputs outside Filament admin context
 * - For Filament forms, use Select::make()->searchable() instead
 * - Use multiple=true for tag-like multi-selection
 * - Use searchEndpoint for large option sets (100+ items)
 */
class Combobox extends Component
{
    public function __construct(
        public array $options = [],
        public mixed $value = null,
        public string $placeholder = 'Select...',
        public bool $searchable = true,
        public bool $multiple = false,
        public bool $clearable = false,
        public bool $disabled = false,
        public ?string $searchEndpoint = null,
        public ?string $name = null,
    ) {}

    public function render(): View
    {
        return view('aicl::components.combobox');
    }
}
