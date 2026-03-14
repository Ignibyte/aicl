<?php

namespace Aicl\States\AiConversation;

class Summarized extends AiConversationState
{
    public function label(): string
    {
        return 'Summarized';
    }

    public function color(): string
    {
        return 'info';
    }

    public function icon(): string
    {
        return 'heroicon-o-document-text';
    }
}
