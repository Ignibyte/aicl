<?php

namespace Aicl\Filament\Resources\AiAgents\Tables;

use Aicl\Enums\AiProvider;
use Aicl\Filament\Exporters\AiAgentExporter;
use Aicl\States\AiAgent\Active;
use Aicl\States\AiAgent\AiAgentState;
use Aicl\States\AiAgent\Archived;
use Aicl\States\AiAgent\Draft;
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

class AiAgentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('provider')
                    ->badge()
                    ->sortable(),
                TextColumn::make('model')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('state')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state instanceof AiAgentState ? $state->label() : (string) $state)
                    ->color(fn ($state): string => $state instanceof AiAgentState ? $state->color() : 'gray')
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('gray'),
                TextColumn::make('sort_order')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('sort_order')
            ->filters([
                SelectFilter::make('provider')
                    ->options(AiProvider::class),
                SelectFilter::make('state')
                    ->options([
                        Draft::class => 'Draft',
                        Active::class => 'Active',
                        Archived::class => 'Archived',
                    ]),
                TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->headerActions([
                CreateAction::make(),
                ExportAction::make()
                    ->exporter(AiAgentExporter::class),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    ExportBulkAction::make()
                        ->exporter(AiAgentExporter::class),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
