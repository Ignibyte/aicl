<?php

namespace Aicl\Filament\Exporters;

use Aicl\Models\RlmLesson;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class RlmLessonExporter extends Exporter
{
    protected static ?string $model = RlmLesson::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')->label('ID'),
            ExportColumn::make('topic'),
            ExportColumn::make('subtopic'),
            ExportColumn::make('summary'),
            ExportColumn::make('detail'),
            ExportColumn::make('tags'),
            ExportColumn::make('source'),
            ExportColumn::make('confidence'),
            ExportColumn::make('is_verified'),
            ExportColumn::make('view_count'),
            ExportColumn::make('owner.name')->label('Owner'),
            ExportColumn::make('created_at'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        return 'Your RlmLesson export with '.number_format($export->successful_rows).' rows is ready.';
    }
}
