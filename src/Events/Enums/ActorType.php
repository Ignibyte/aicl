<?php

namespace Aicl\Events\Enums;

enum ActorType: string
{
    case User = 'user';
    case System = 'system';
    case Agent = 'agent';
    case Automation = 'automation';

    public function label(): string
    {
        return match ($this) {
            self::User => 'User',
            self::System => 'System',
            self::Agent => 'AI Agent',
            self::Automation => 'Automation',
        };
    }
}
