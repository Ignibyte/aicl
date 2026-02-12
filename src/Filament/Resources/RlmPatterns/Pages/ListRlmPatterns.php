<?php

namespace Aicl\Filament\Resources\RlmPatterns\Pages;

use Aicl\Filament\Resources\RlmPatterns\RlmPatternResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRlmPatterns extends ListRecords
{
    protected static string $resource = RlmPatternResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
