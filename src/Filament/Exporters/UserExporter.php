<?php

declare(strict_types=1);

namespace Aicl\Filament\Exporters;

use App\Models\User;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

/**
 * UserExporter.
 */
class UserExporter extends Exporter
{
    protected static ?string $model = User::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')->label('ID'),
            ExportColumn::make('name'),
            ExportColumn::make('email'),
            ExportColumn::make('roles.name')->label('Roles'),
            ExportColumn::make('email_verified_at')->label('Verified'),
            ExportColumn::make('created_at'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        return 'Your user export with '.number_format($export->successful_rows).' rows is ready.';
    }
}
