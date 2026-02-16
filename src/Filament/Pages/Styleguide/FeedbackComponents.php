<?php

namespace Aicl\Filament\Pages\Styleguide;

use BackedEnum;
use Filament\Pages\Page;
use UnitEnum;

class FeedbackComponents extends Page
{
    protected static string|BackedEnum|null $navigationIcon = null;

    protected static string|UnitEnum|null $navigationGroup = 'Styleguide';

    protected static ?int $navigationSort = 106;

    protected static ?string $navigationLabel = 'Feedback';

    protected static ?string $title = 'Feedback Components';

    protected string $view = 'aicl::filament.pages.styleguide.feedback-components';
}
