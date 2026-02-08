<?php

namespace Aicl\Filament\Pages\Styleguide;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class ActionComponents extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCursorArrowRays;

    protected static string|UnitEnum|null $navigationGroup = 'Styleguide';

    protected static ?int $navigationSort = 104;

    protected static ?string $navigationLabel = 'Actions & Utility';

    protected static ?string $title = 'Action & Utility Components';

    protected string $view = 'aicl::filament.pages.styleguide.action-components';
}
