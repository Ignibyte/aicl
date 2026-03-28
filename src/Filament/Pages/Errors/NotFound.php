<?php

declare(strict_types=1);

namespace Aicl\Filament\Pages\Errors;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/**
 * @codeCoverageIgnore Filament Livewire rendering
 */
class NotFound extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentMagnifyingGlass;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Page Not Found';

    protected static ?string $slug = 'errors/404';

    protected string $view = 'aicl::errors.http-error';

    public int $code = 404;

    public string $icon = 'heroicon-o-document-magnifying-glass';

    public string $description = "The page you're looking for doesn't exist or has been moved.";

    public function getHeading(): string
    {
        return '';
    }

    public static function canAccess(): bool
    {
        return true;
    }
}
