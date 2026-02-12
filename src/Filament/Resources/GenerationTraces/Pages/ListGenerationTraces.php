<?php

namespace Aicl\Filament\Resources\GenerationTraces\Pages;

use Aicl\Filament\Resources\GenerationTraces\GenerationTraceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListGenerationTraces extends ListRecords
{
    protected static string $resource = GenerationTraceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
