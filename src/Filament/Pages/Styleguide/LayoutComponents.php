<?php

namespace Aicl\Filament\Pages\Styleguide;

use BackedEnum;
use Filament\Pages\Page;
use UnitEnum;

class LayoutComponents extends Page
{
    protected static string|BackedEnum|null $navigationIcon = null;

    protected static string|UnitEnum|null $navigationGroup = 'Styleguide';

    protected static ?int $navigationSort = 101;

    protected static ?string $navigationLabel = 'Layout';

    protected static ?string $title = 'Layout Components';

    protected string $view = 'aicl::filament.pages.styleguide.layout-components';
}
