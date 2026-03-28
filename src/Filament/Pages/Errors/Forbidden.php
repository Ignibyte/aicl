<?php

declare(strict_types=1);

namespace Aicl\Filament\Pages\Errors;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/**
 * @codeCoverageIgnore Filament Livewire rendering
 */
class Forbidden extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldExclamation;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Access Denied';

    protected static ?string $slug = 'errors/403';

    protected string $view = 'aicl::errors.http-error';

    public int $code = 403;

    public string $icon = 'heroicon-o-shield-exclamation';

    public string $description = "You don't have permission to view this page. Contact your administrator if you think this is a mistake.";

    public function getHeading(): string
    {
        return '';
    }

    public static function canAccess(): bool
    {
        return true;
    }
}
