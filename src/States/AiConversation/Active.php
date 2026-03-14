<?php

namespace Aicl\States\AiConversation;

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
