<?php

declare(strict_types=1);

namespace Aicl\States\AiAgent;

/**
 * Draft.
 */
class Draft extends AiAgentState
{
    public function label(): string
    {
        return 'Draft';
    }

    public function color(): string
    {
        return 'gray';
    }

    public function icon(): string
    {
        return 'heroicon-o-pencil-square';
    }
}
