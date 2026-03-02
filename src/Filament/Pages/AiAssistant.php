<?php

namespace Aicl\Filament\Pages;

use Filament\Pages\Page;
use UnitEnum;

class AiAssistant extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static string|UnitEnum|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 8;

    protected static ?string $navigationLabel = 'AI Assistant';

    protected static ?string $title = 'AI Assistant';

    protected static ?string $slug = 'ai-assistant';

    protected string $view = 'aicl::filament.pages.ai-assistant';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return $user->hasRole(['super_admin', 'admin']);
    }
}
