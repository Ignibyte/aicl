<?php

namespace Aicl\Filament\Resources\AiAgents\Pages;

use Aicl\Filament\Resources\AiAgents\AiAgentResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewAiAgent extends ViewRecord
{
    protected static string $resource = AiAgentResource::class;

    protected static ?string $navigationLabel = 'Details';

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
