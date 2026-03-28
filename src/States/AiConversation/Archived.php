<?php

declare(strict_types=1);

namespace Aicl\States\AiConversation;

/**
 * Archived.
 */
class Archived extends AiConversationState
{
    public function label(): string
    {
        return 'Archived';
    }

    public function color(): string
    {
        return 'gray';
    }

    public function icon(): string
    {
        return 'heroicon-o-archive-box';
    }
}
