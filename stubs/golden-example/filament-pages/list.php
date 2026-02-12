<?php

// PATTERN: List page extends ListRecords and adds a Create header action.

namespace Aicl\Filament\Resources\Projects\Pages;

use Aicl\Filament\Resources\Projects\ProjectResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProjects extends ListRecords
{
    protected static string $resource = ProjectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
