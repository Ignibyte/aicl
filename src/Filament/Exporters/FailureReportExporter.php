<?php

namespace Aicl\Filament\Exporters;

use Aicl\Models\FailureReport;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class FailureReportExporter extends Exporter
{
    protected static ?string $model = FailureReport::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')->label('ID'),
            ExportColumn::make('failure.failure_code')->label('Failure Code'),
            ExportColumn::make('failure.title')->label('Failure Title'),
            ExportColumn::make('project_hash'),
            ExportColumn::make('entity_name'),
            ExportColumn::make('phase'),
            ExportColumn::make('agent'),
            ExportColumn::make('resolved'),
            ExportColumn::make('resolution_method'),
            ExportColumn::make('time_to_resolve'),
            ExportColumn::make('reported_at'),
            ExportColumn::make('resolved_at'),
            ExportColumn::make('owner.name')->label('Owner'),
            ExportColumn::make('created_at'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        return 'Your failure report export with '.number_format($export->successful_rows).' rows is ready.';
    }
}
