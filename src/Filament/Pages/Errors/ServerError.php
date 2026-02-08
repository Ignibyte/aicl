<?php

namespace Aicl\Filament\Pages\Errors;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class ServerError extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Something Went Wrong';

    protected static ?string $slug = 'errors/500';

    protected string $view = 'aicl::errors.http-error';

    public int $code = 500;

    public string $icon = 'heroicon-o-exclamation-triangle';

    public string $description = 'An unexpected error occurred. Our team has been notified.';

    public function getHeading(): string
    {
        return '';
    }

    public static function canAccess(): bool
    {
        return true;
    }
}
