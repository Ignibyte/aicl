<?php

declare(strict_types=1);

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

/**
 * AiAgentResource.
 */
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
        // @codeCoverageIgnoreStart — Filament Livewire rendering
        return AiAgentForm::configure($schema);
        // @codeCoverageIgnoreEnd
    }

    public static function infolist(Schema $schema): Schema
    {
        // @codeCoverageIgnoreStart — Filament Livewire rendering
        return AiAgentInfolist::configure($schema);
        // @codeCoverageIgnoreEnd
    }

    public static function table(Table $table): Table
    {
        // @codeCoverageIgnoreStart — Filament Livewire rendering
        return AiAgentsTable::configure($table);
        // @codeCoverageIgnoreEnd
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
