<?php

namespace Aicl\Filament\Exporters;

use Aicl\Models\RlmFailure;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class RlmFailureExporter extends Exporter
{
    protected static ?string $model = RlmFailure::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')->label('ID'),
            ExportColumn::make('failure_code'),
            ExportColumn::make('pattern_id'),
            ExportColumn::make('category'),
            ExportColumn::make('subcategory'),
            ExportColumn::make('title'),
            ExportColumn::make('description'),
            ExportColumn::make('root_cause'),
            ExportColumn::make('fix'),
            ExportColumn::make('preventive_rule'),
            ExportColumn::make('severity'),
            ExportColumn::make('scaffolding_fixed'),
            ExportColumn::make('first_seen_at'),
            ExportColumn::make('last_seen_at'),
            ExportColumn::make('report_count'),
            ExportColumn::make('project_count'),
            ExportColumn::make('resolution_count'),
            ExportColumn::make('resolution_rate'),
            ExportColumn::make('promoted_to_base'),
            ExportColumn::make('promoted_at'),
            ExportColumn::make('aicl_version'),
            ExportColumn::make('laravel_version'),
            ExportColumn::make('status')->formatStateUsing(fn ($state) => $state instanceof \Stringable ? (string) $state : $state),
            ExportColumn::make('owner.name')->label('Owner'),
            ExportColumn::make('created_at'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        return 'Your RlmFailure export with '.number_format($export->successful_rows).' rows is ready.';
    }
}
