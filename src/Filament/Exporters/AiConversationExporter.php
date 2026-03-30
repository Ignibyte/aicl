<?php

declare(strict_types=1);

namespace Aicl\Filament\Exporters;

use Aicl\Models\AiConversation;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

/**
 * @codeCoverageIgnore Filament Livewire rendering
 */
class AiConversationExporter extends Exporter
{
    protected static ?string $model = AiConversation::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id'),
            ExportColumn::make('title'),
            ExportColumn::make('user.name')->label('User'),
            ExportColumn::make('agent.name')->label('Agent'),
            ExportColumn::make('message_count'),
            ExportColumn::make('token_count'),
            ExportColumn::make('state'),
            ExportColumn::make('is_pinned'),
            ExportColumn::make('last_message_at'),
            ExportColumn::make('created_at'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your AI conversation export has completed and '.number_format($export->successful_rows).' '.str('row')->plural($export->successful_rows).' exported.';

        $failedRowsCount = $export->getFailedRowsCount();

        if ($failedRowsCount) {
            $body .= ' '.number_format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to export.';
        }

        return $body;
    }
}
