<?php

namespace Aicl\Filament\Resources\AiAgents;

use Aicl\Filament\Resources\AiAgents\Schemas\AiAgentForm;
use Aicl\Filament\Resources\AiAgents\Schemas\AiAgentInfolist;
use Aicl\Filament\Resources\AiAgents\Tables\AiAgentsTable;
use Aicl\Models\AiAgent;
use BackedEnum;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

class AiAgentResource extends Resource
{
    protected static ?string $model = AiAgent::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|BackedEnum|null $navigationIcon = null;

    protected static string|UnitEnum|null $navigationGroup = 'AI';

    protected static ?int $navigationSort = 1;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    public static function form(Schema $schema): Schema
    {
        return AiAgentForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return AiAgentInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AiAgentsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAiAgents::route('/'),
            'create' => Pages\CreateAiAgent::route('/create'),
            'view' => Pages\ViewAiAgent::route('/{record}'),
            'edit' => Pages\EditAiAgent::route('/{record}/edit'),
        ];
    }
}
