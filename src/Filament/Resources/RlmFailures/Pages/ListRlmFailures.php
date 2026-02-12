<?php

namespace Aicl\Filament\Resources\RlmFailures\Pages;

use Aicl\Filament\Resources\RlmFailures\RlmFailureResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRlmFailures extends ListRecords
{
    protected static string $resource = RlmFailureResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
