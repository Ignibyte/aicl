<?php

namespace Aicl\Filament\Resources\RlmPatterns;

use Aicl\Filament\Resources\RlmPatterns\Pages\CreateRlmPattern;
use Aicl\Filament\Resources\RlmPatterns\Pages\EditRlmPattern;
use Aicl\Filament\Resources\RlmPatterns\Pages\ListRlmPatterns;
use Aicl\Filament\Resources\RlmPatterns\Pages\ViewRlmPattern;
use Aicl\Filament\Resources\RlmPatterns\Schemas\RlmPatternForm;
use Aicl\Filament\Resources\RlmPatterns\Tables\RlmPatternsTable;
use Aicl\Models\RlmPattern;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class RlmPatternResource extends Resource
{
    protected static ?string $model = RlmPattern::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|UnitEnum|null $navigationGroup = 'RLM Hub';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return RlmPatternForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RlmPatternsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRlmPatterns::route('/'),
            'create' => CreateRlmPattern::route('/create'),
            'view' => ViewRlmPattern::route('/{record}'),
            'edit' => EditRlmPattern::route('/{record}/edit'),
        ];
    }
}
