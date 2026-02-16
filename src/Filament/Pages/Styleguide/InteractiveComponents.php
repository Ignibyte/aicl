<?php

namespace Aicl\Filament\Pages\Styleguide;

use BackedEnum;
use Filament\Pages\Page;
use UnitEnum;

class InteractiveComponents extends Page
{
    protected static string|BackedEnum|null $navigationIcon = null;

    protected static string|UnitEnum|null $navigationGroup = 'Styleguide';

    protected static ?int $navigationSort = 105;

    protected static ?string $navigationLabel = 'Interactive';

    protected static ?string $title = 'Interactive Components';

    protected string $view = 'aicl::filament.pages.styleguide.interactive-components';
}
