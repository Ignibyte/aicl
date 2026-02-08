<?php

namespace Aicl\Filament\Pages\Styleguide;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class MetricComponents extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|UnitEnum|null $navigationGroup = 'Styleguide';

    protected static ?int $navigationSort = 102;

    protected static ?string $navigationLabel = 'Metrics';

    protected static ?string $title = 'Metric Components';

    protected string $view = 'aicl::filament.pages.styleguide.metric-components';
}
