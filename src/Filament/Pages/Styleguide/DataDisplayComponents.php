<?php

namespace Aicl\Filament\Pages\Styleguide;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class DataDisplayComponents extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTableCells;

    protected static string|UnitEnum|null $navigationGroup = 'Styleguide';

    protected static ?int $navigationSort = 103;

    protected static ?string $navigationLabel = 'Data Display';

    protected static ?string $title = 'Data Display Components';

    protected string $view = 'aicl::filament.pages.styleguide.data-display-components';
}
