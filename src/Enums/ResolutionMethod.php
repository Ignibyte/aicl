<?php

namespace Aicl\Enums;

enum ResolutionMethod: string
{
    case ScaffoldingFix = 'scaffolding_fix';
    case ManualFix = 'manual_fix';
    case Workaround = 'workaround';
    case WontFix = 'wont_fix';
    case Duplicate = 'duplicate';

    public function label(): string
    {
        return match ($this) {
            self::ScaffoldingFix => 'Scaffolding Fix',
            self::ManualFix => 'Manual Fix',
            self::Workaround => 'Workaround',
            self::WontFix => "Won't Fix",
            self::Duplicate => 'Duplicate',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::ScaffoldingFix => 'success',
            self::ManualFix => 'info',
            self::Workaround => 'warning',
            self::WontFix => 'danger',
            self::Duplicate => 'gray',
        };
    }
}
