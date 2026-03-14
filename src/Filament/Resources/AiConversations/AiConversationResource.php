<?php

namespace Aicl\Filament\Resources\AiConversations;

use Aicl\Filament\Resources\AiConversations\Pages\CreateAiConversation;
use Aicl\Filament\Resources\AiConversations\Pages\EditAiConversation;
use Aicl\Filament\Resources\AiConversations\Pages\ListAiConversations;
use Aicl\Filament\Resources\AiConversations\Pages\ViewAiConversation;
use Aicl\Filament\Resources\AiConversations\Schemas\AiConversationForm;
use Aicl\Filament\Resources\AiConversations\Schemas\AiConversationInfolist;
use Aicl\Filament\Resources\AiConversations\Tables\AiConversationsTable;
use Aicl\Models\AiConversation;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

class AiConversationResource extends Resource
{
    protected static ?string $model = AiConversation::class;

    protected static string|UnitEnum|null $navigationGroup = 'AI';

    protected static string|BackedEnum|null $navigationIcon = null;

    protected static ?int $navigationSort = 2;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return AiConversationForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return AiConversationInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AiConversationsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAiConversations::route('/'),
            'create' => CreateAiConversation::route('/create'),
            'view' => ViewAiConversation::route('/{record}'),
            'edit' => EditAiConversation::route('/{record}/edit'),
        ];
    }
}
