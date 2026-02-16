<?php

namespace Aicl\Filament\Pages\Styleguide;

use BackedEnum;
use Filament\Pages\Page;
use UnitEnum;

class MetricComponents extends Page
{
    protected static string|BackedEnum|null $navigationIcon = null;

    protected static string|UnitEnum|null $navigationGroup = 'Styleguide';

    protected static ?int $navigationSort = 102;

    protected static ?string $navigationLabel = 'Metrics';

    protected static ?string $title = 'Metric Components';

    protected string $view = 'aicl::filament.pages.styleguide.metric-components';
}
