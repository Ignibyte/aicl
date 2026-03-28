<?php

declare(strict_types=1);

namespace Aicl\Filament\Resources\AiConversations\Pages;

use Aicl\Filament\Resources\AiConversations\AiConversationResource;
use Filament\Resources\Pages\CreateRecord;

/**
 * CreateAiConversation.
 */
class CreateAiConversation extends CreateRecord
{
    protected static string $resource = AiConversationResource::class;
}
