<?php

declare(strict_types=1);

namespace Aicl\States\AiConversation;

/**
 * Active.
 */
class Active extends AiConversationState
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
        return 'heroicon-o-chat-bubble-left-right';
    }
}
