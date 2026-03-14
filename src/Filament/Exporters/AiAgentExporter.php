<?php

namespace Aicl\Filament\Exporters;

use Aicl\Models\AiAgent;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class AiAgentExporter extends Exporter
{
    protected static ?string $model = AiAgent::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')->label('ID'),
            ExportColumn::make('name'),
            ExportColumn::make('slug'),
            ExportColumn::make('provider'),
            ExportColumn::make('model'),
            ExportColumn::make('state'),
            ExportColumn::make('is_active')->label('Active'),
            ExportColumn::make('sort_order'),
            ExportColumn::make('max_tokens'),
            ExportColumn::make('temperature'),
            ExportColumn::make('context_window'),
            ExportColumn::make('context_messages'),
            ExportColumn::make('max_requests_per_minute')->label('Rate Limit'),
            ExportColumn::make('created_at'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        return 'Your AI agent export with '.number_format($export->successful_rows).' rows is ready.';
    }
}
