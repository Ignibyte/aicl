<?php

namespace Aicl\Filament\Pages\Errors;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class ServiceUnavailable extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Maintenance Mode';

    protected static ?string $slug = 'errors/503';

    protected string $view = 'aicl::errors.http-error';

    public int $code = 503;

    public string $icon = 'heroicon-o-wrench-screwdriver';

    public string $description = 'The application is temporarily unavailable for maintenance. Please try again shortly.';

    public function getHeading(): string
    {
        return '';
    }

    public static function canAccess(): bool
    {
        return true;
    }
}
