<?php

namespace Aicl\States\AiConversation;

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
