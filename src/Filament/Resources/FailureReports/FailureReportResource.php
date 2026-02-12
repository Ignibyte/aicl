<?php

namespace Aicl\Filament\Resources\FailureReports;

use Aicl\Filament\Resources\FailureReports\Pages\CreateFailureReport;
use Aicl\Filament\Resources\FailureReports\Pages\EditFailureReport;
use Aicl\Filament\Resources\FailureReports\Pages\ListFailureReports;
use Aicl\Filament\Resources\FailureReports\Pages\ViewFailureReport;
use Aicl\Filament\Resources\FailureReports\Schemas\FailureReportForm;
use Aicl\Filament\Resources\FailureReports\Tables\FailureReportsTable;
use Aicl\Models\FailureReport;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class FailureReportResource extends Resource
{
    protected static ?string $model = FailureReport::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|UnitEnum|null $navigationGroup = 'RLM Hub';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'entity_name';

    public static function form(Schema $schema): Schema
    {
        return FailureReportForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FailureReportsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFailureReports::route('/'),
            'create' => CreateFailureReport::route('/create'),
            'view' => ViewFailureReport::route('/{record}'),
            'edit' => EditFailureReport::route('/{record}/edit'),
        ];
    }
}
