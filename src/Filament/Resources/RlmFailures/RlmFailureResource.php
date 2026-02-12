<?php

namespace Aicl\Filament\Resources\RlmFailures;

use Aicl\Filament\Resources\RlmFailures\Pages\CreateRlmFailure;
use Aicl\Filament\Resources\RlmFailures\Pages\EditRlmFailure;
use Aicl\Filament\Resources\RlmFailures\Pages\ListRlmFailures;
use Aicl\Filament\Resources\RlmFailures\Pages\ViewRlmFailure;
use Aicl\Filament\Resources\RlmFailures\Schemas\RlmFailureForm;
use Aicl\Filament\Resources\RlmFailures\Tables\RlmFailuresTable;
use Aicl\Models\RlmFailure;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class RlmFailureResource extends Resource
{
    protected static ?string $model = RlmFailure::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    protected static string|UnitEnum|null $navigationGroup = 'RLM Hub';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return RlmFailureForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RlmFailuresTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRlmFailures::route('/'),
            'create' => CreateRlmFailure::route('/create'),
            'view' => ViewRlmFailure::route('/{record}'),
            'edit' => EditRlmFailure::route('/{record}/edit'),
        ];
    }
}
