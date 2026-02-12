<?php

namespace Aicl\Filament\Resources\GenerationTraces;

use Aicl\Filament\Resources\GenerationTraces\Pages\CreateGenerationTrace;
use Aicl\Filament\Resources\GenerationTraces\Pages\EditGenerationTrace;
use Aicl\Filament\Resources\GenerationTraces\Pages\ListGenerationTraces;
use Aicl\Filament\Resources\GenerationTraces\Pages\ViewGenerationTrace;
use Aicl\Filament\Resources\GenerationTraces\Schemas\GenerationTraceForm;
use Aicl\Filament\Resources\GenerationTraces\Tables\GenerationTracesTable;
use Aicl\Models\GenerationTrace;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class GenerationTraceResource extends Resource
{
    protected static ?string $model = GenerationTrace::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCommandLine;

    protected static string|UnitEnum|null $navigationGroup = 'RLM Hub';

    protected static ?int $navigationSort = 5;

    protected static ?string $recordTitleAttribute = 'entity_name';

    public static function form(Schema $schema): Schema
    {
        return GenerationTraceForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return GenerationTracesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGenerationTraces::route('/'),
            'create' => CreateGenerationTrace::route('/create'),
            'view' => ViewGenerationTrace::route('/{record}'),
            'edit' => EditGenerationTrace::route('/{record}/edit'),
        ];
    }
}
