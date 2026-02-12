<?php

namespace Aicl\Enums;

enum KnowledgeLinkRelationship: string
{
    case ViolatedBy = 'violated_by';
    case Prevents = 'prevents';
    case LearnedFrom = 'learned_from';
    case DerivedFrom = 'derived_from';
    case RelatedTo = 'related_to';

    public function label(): string
    {
        return match ($this) {
            self::ViolatedBy => 'Violated By',
            self::Prevents => 'Prevents',
            self::LearnedFrom => 'Learned From',
            self::DerivedFrom => 'Derived From',
            self::RelatedTo => 'Related To',
        };
    }

    public function inverse(): self
    {
        return match ($this) {
            self::ViolatedBy => self::Prevents,
            self::Prevents => self::ViolatedBy,
            self::LearnedFrom => self::DerivedFrom,
            self::DerivedFrom => self::LearnedFrom,
            self::RelatedTo => self::RelatedTo,
        };
    }
}
