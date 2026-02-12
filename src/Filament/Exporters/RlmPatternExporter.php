<?php

namespace Aicl\Filament\Exporters;

use Aicl\Models\RlmPattern;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class RlmPatternExporter extends Exporter
{
    protected static ?string $model = RlmPattern::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')->label('ID'),
            ExportColumn::make('name'),
            ExportColumn::make('description'),
            ExportColumn::make('target'),
            ExportColumn::make('check_regex'),
            ExportColumn::make('severity'),
            ExportColumn::make('weight'),
            ExportColumn::make('category'),
            ExportColumn::make('source'),
            ExportColumn::make('is_active'),
            ExportColumn::make('pass_count'),
            ExportColumn::make('fail_count'),
            ExportColumn::make('last_evaluated_at'),
            ExportColumn::make('owner.name')->label('Owner'),
            ExportColumn::make('created_at'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        return 'Your RlmPattern export with '.number_format($export->successful_rows).' rows is ready.';
    }
}
