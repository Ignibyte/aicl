<?php

declare(strict_types=1);

namespace Aicl\Filament\Resources\AiConversations\Pages;

use Aicl\Filament\Resources\AiConversations\AiConversationResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

/**
 * @codeCoverageIgnore Filament Livewire rendering
 */
class ViewAiConversation extends ViewRecord
{
    protected static string $resource = AiConversationResource::class;

    protected static ?string $navigationLabel = 'Details';

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
