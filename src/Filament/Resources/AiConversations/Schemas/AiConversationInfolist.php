<?php

namespace Aicl\Filament\Resources\AiConversations\Schemas;

use Aicl\States\AiConversation\AiConversationState;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AiConversationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Conversation')
                ->columnSpanFull()
                ->columns(2)
                ->schema([
                    TextEntry::make('title')
                        ->default('New Conversation'),
                    TextEntry::make('user.name')
                        ->label('User'),
                    TextEntry::make('agent.name')
                        ->label('Agent'),
                    TextEntry::make('state')
                        ->badge()
                        ->formatStateUsing(fn ($state): string => $state instanceof AiConversationState ? $state->label() : (string) $state)
                        ->color(fn ($state): string => $state instanceof AiConversationState ? $state->color() : 'gray'),
                    IconEntry::make('is_pinned')
                        ->label('Pinned')
                        ->boolean(),
                    TextEntry::make('context_page')
                        ->default('—'),
                ]),

            Section::make('Usage')
                ->columnSpanFull()
                ->columns(2)
                ->schema([
                    TextEntry::make('message_count')
                        ->label('Messages'),
                    TextEntry::make('token_count')
                        ->label('Tokens'),
                    TextEntry::make('last_message_at')
                        ->label('Last Message')
                        ->dateTime()
                        ->default('—'),
                ]),

            Section::make('Compaction')
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('summary')
                        ->columnSpanFull()
                        ->default('No summary yet'),
                ]),

            Section::make('Timestamps')
                ->columnSpanFull()
                ->columns(2)
                ->schema([
                    TextEntry::make('created_at')
                        ->dateTime(),
                    TextEntry::make('updated_at')
                        ->dateTime(),
                ]),
        ]);
    }
}
