<?php

declare(strict_types=1);

namespace Aicl\States\AiConversation;

/**
 * Summarized.
 */
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
