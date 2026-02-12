<?php

namespace Aicl\Filament\Exporters;

use Aicl\Models\PreventionRule;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class PreventionRuleExporter extends Exporter
{
    protected static ?string $model = PreventionRule::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')->label('ID'),
            ExportColumn::make('failure.failure_code')->label('Failure Code'),
            ExportColumn::make('failure.title')->label('Failure Title'),
            ExportColumn::make('rule_text'),
            ExportColumn::make('confidence'),
            ExportColumn::make('priority'),
            ExportColumn::make('is_active'),
            ExportColumn::make('applied_count'),
            ExportColumn::make('last_applied_at'),
            ExportColumn::make('owner.name')->label('Owner'),
            ExportColumn::make('created_at'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        return 'Your PreventionRule export with '.number_format($export->successful_rows).' rows is ready.';
    }
}
