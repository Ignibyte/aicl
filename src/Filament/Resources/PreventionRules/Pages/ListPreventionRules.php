<?php

namespace Aicl\Filament\Resources\PreventionRules\Pages;

use Aicl\Filament\Resources\PreventionRules\PreventionRuleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPreventionRules extends ListRecords
{
    protected static string $resource = PreventionRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
