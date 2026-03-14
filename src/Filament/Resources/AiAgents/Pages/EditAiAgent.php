<?php

namespace Aicl\Filament\Resources\AiAgents\Pages;

use Aicl\Filament\Resources\AiAgents\AiAgentResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditAiAgent extends EditRecord
{
    protected static string $resource = AiAgentResource::class;

    protected static ?string $navigationLabel = 'Edit';

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
