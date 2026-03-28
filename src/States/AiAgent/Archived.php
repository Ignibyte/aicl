<?php

declare(strict_types=1);

namespace Aicl\States\AiAgent;

/**
 * Archived.
 */
class Archived extends AiAgentState
{
    public function label(): string
    {
        return 'Archived';
    }

    public function color(): string
    {
        return 'warning';
    }

    public function icon(): string
    {
        return 'heroicon-o-archive-box';
    }
}
