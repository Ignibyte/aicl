<?php

// PATTERN: Filament v4 Exporter for CSV export via ExportAction.
// PATTERN: Uses ExportColumn for column definitions.
// PATTERN: Enums/states need formatStateUsing to output plain values.

namespace Aicl\Filament\Exporters;

use App\Models\Project;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class ProjectExporter extends Exporter
{
    protected static ?string $model = Project::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')->label('ID'),
            ExportColumn::make('name'),
            // PATTERN: State/Enum columns need special formatting for CSV output.
            ExportColumn::make('status')
                ->formatStateUsing(fn ($state) => $state instanceof \BackedEnum ? $state->value : $state),
            ExportColumn::make('priority')
                ->formatStateUsing(fn ($state) => $state instanceof \BackedEnum ? $state->value : $state),
            // PATTERN: Relationship columns use dot notation.
            ExportColumn::make('owner.name')->label('Owner'),
            ExportColumn::make('start_date'),
            ExportColumn::make('end_date'),
            ExportColumn::make('budget'),
            ExportColumn::make('created_at'),
        ];
    }

    // PATTERN: Completion notification body.
    public static function getCompletedNotificationBody(Export $export): string
    {
        return 'Your project export with '.number_format($export->successful_rows).' rows is ready.';
    }
}
