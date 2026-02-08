<?php

namespace Aicl\Filament\Pages\Styleguide;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class StyleguideOverview extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSwatch;

    protected static string|UnitEnum|null $navigationGroup = 'Styleguide';

    protected static ?int $navigationSort = 100;

    protected static ?string $navigationLabel = 'Overview';

    protected static ?string $title = 'Component Styleguide';

    protected string $view = 'aicl::filament.pages.styleguide.overview';
}
