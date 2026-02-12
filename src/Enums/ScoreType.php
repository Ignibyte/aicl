<?php

namespace Aicl\Enums;

enum ScoreType: string
{
    case Structural = 'structural';
    case Semantic = 'semantic';
    case Combined = 'combined';

    public function label(): string
    {
        return match ($this) {
            self::Structural => 'Structural',
            self::Semantic => 'Semantic',
            self::Combined => 'Combined',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Structural => 'primary',
            self::Semantic => 'info',
            self::Combined => 'success',
        };
    }
}
