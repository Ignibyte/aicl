<?php

namespace Aicl\Filament\Pages;

use Filament\Pages\Page;
use UnitEnum;

class AiAssistant extends Page
{
    protected static string|UnitEnum|null $navigationGroup = 'Tools';

    protected static ?int $navigationSort = 5;

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
