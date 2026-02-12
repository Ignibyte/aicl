<?php

namespace Aicl\Filament\Exporters;

use Aicl\Models\GenerationTrace;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class GenerationTraceExporter extends Exporter
{
    protected static ?string $model = GenerationTrace::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')->label('ID'),
            ExportColumn::make('entity_name'),
            ExportColumn::make('project_hash'),
            ExportColumn::make('scaffolder_args'),
            ExportColumn::make('structural_score'),
            ExportColumn::make('semantic_score'),
            ExportColumn::make('test_results'),
            ExportColumn::make('fix_iterations'),
            ExportColumn::make('pipeline_duration'),
            ExportColumn::make('is_processed'),
            ExportColumn::make('aicl_version'),
            ExportColumn::make('laravel_version'),
            ExportColumn::make('owner.name')->label('Owner'),
            ExportColumn::make('created_at'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        return 'Your GenerationTrace export with '.number_format($export->successful_rows).' rows is ready.';
    }
}
