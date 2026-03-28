<?php

declare(strict_types=1);

namespace Aicl\Filament\Resources\AiAgents\Pages;

use Aicl\Filament\Resources\AiAgents\AiAgentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

/**
 * @codeCoverageIgnore Filament Livewire rendering
 */
class ListAiAgents extends ListRecords
{
    protected static string $resource = AiAgentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
