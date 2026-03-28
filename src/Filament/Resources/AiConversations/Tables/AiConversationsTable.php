<?php

declare(strict_types=1);

namespace Aicl\Filament\Resources\AiConversations\Tables;

use Aicl\Filament\Exporters\AiConversationExporter;
use Aicl\States\AiConversation\Active;
use Aicl\States\AiConversation\AiConversationState;
use Aicl\States\AiConversation\Archived;
use Aicl\States\AiConversation\Summarized;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportAction;
use Filament\Actions\ExportBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

/**
 * @codeCoverageIgnore Filament Livewire rendering
 */
class AiConversationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->default('New Conversation')
                    ->searchable()
                    ->sortable()
                    ->limit(50),
                TextColumn::make('user.name')
                    ->label('User')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('agent.name')
                    ->label('Agent')
                    ->sortable(),
                TextColumn::make('message_count')
                    ->label('Messages')
                    ->sortable(),
                TextColumn::make('token_count')
                    ->label('Tokens')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('state')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state instanceof AiConversationState ? $state->label() : (string) $state)
                    ->color(fn ($state): string => $state instanceof AiConversationState ? $state->color() : 'gray')
                    ->sortable(),
                IconColumn::make('is_pinned')
                    ->label('Pinned')
                    ->boolean()
                    ->trueIcon('heroicon-s-star')
                    ->falseIcon('heroicon-o-star')
                    ->trueColor('warning')
                    ->falseColor('gray'),
                TextColumn::make('last_message_at')
                    ->label('Last Message')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('last_message_at', 'desc')
            ->filters([
                SelectFilter::make('state')
                    ->options([
                        Active::class => 'Active',
                        Summarized::class => 'Summarized',
                        Archived::class => 'Archived',
                    ]),
                SelectFilter::make('ai_agent_id')
                    ->label('Agent')
                    ->relationship('agent', 'name'),
                TernaryFilter::make('is_pinned')
                    ->label('Pinned'),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->headerActions([
                CreateAction::make(),
                ExportAction::make()
                    ->exporter(AiConversationExporter::class),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    ExportBulkAction::make()
                        ->exporter(AiConversationExporter::class),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
