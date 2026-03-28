<?php

declare(strict_types=1);

namespace Aicl\States\AiAgent;

/**
 * Active.
 */
class Active extends AiAgentState
{
    public function label(): string
    {
        return 'Active';
    }

    public function color(): string
    {
        return 'success';
    }

    public function icon(): string
    {
        return 'heroicon-o-check-circle';
    }
}
