<?php

namespace Aicl\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum AiMessageRole: string implements HasColor, HasIcon, HasLabel
{
    case User = 'user';
    case Assistant = 'assistant';
    case System = 'system';

    public function getLabel(): string
    {
        return match ($this) {
            self::User => 'User',
            self::Assistant => 'Assistant',
            self::System => 'System',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::User => 'primary',
            self::Assistant => 'success',
            self::System => 'gray',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::User => 'heroicon-o-user',
            self::Assistant => 'heroicon-o-cpu-chip',
            self::System => 'heroicon-o-cog-6-tooth',
        };
    }
}
