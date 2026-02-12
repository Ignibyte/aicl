<?php

namespace Aicl\Filament\Resources\FailureReports\Pages;

use Aicl\Filament\Resources\FailureReports\FailureReportResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFailureReports extends ListRecords
{
    protected static string $resource = FailureReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
