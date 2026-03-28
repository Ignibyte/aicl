<?php

declare(strict_types=1);

namespace Aicl\Filament\Resources\AiConversations\Pages;

use Aicl\Filament\Resources\AiConversations\AiConversationResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

/**
 * @codeCoverageIgnore Filament Livewire rendering
 */
class EditAiConversation extends EditRecord
{
    protected static string $resource = AiConversationResource::class;

    protected static ?string $navigationLabel = 'Edit';

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
