<?php

namespace Aicl\Enums;

enum LessonType: string
{
    case Observation = 'observation';
    case Instruction = 'instruction';
    case PreventionRule = 'prevention_rule';

    public function label(): string
    {
        return match ($this) {
            self::Observation => 'Observation',
            self::Instruction => 'Instruction',
            self::PreventionRule => 'Prevention Rule',
        };
    }

    /**
     * Whether this type is surfaced in default recall.
     */
    public function isSurfaceable(): bool
    {
        return match ($this) {
            self::Observation => false,
            self::Instruction, self::PreventionRule => true,
        };
    }

    /**
     * Whether this type requires proof hooks (rule + fix at minimum).
     */
    public function requiresProof(): bool
    {
        return match ($this) {
            self::Observation => false,
            self::Instruction, self::PreventionRule => true,
        };
    }
}
