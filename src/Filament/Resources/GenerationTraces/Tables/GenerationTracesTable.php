<?php

namespace Aicl\Filament\Resources\GenerationTraces\Tables;

use Aicl\Filament\Exporters\GenerationTraceExporter;
use Filament\Actions\BulkActionGroup;
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

class GenerationTracesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('entity_name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('structural_score')
                    ->label('Structural')
                    ->numeric(2)
                    ->suffix('%')
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('semantic_score')
                    ->label('Semantic')
                    ->numeric(2)
                    ->suffix('%')
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('fix_iterations')
                    ->label('Fixes')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('pipeline_duration')
                    ->label('Duration')
                    ->numeric()
                    ->suffix('s')
                    ->sortable()
                    ->placeholder('—'),
                IconColumn::make('is_processed')
                    ->label('Processed')
                    ->boolean(),
                TextColumn::make('aicl_version')
                    ->label('AICL')
                    ->badge()
                    ->color('gray')
                    ->sortable(),
                TextColumn::make('owner.name')
                    ->label('Owner')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('project_hash')
                    ->limit(12)
                    ->tooltip(fn ($record) => $record->project_hash)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_processed')
                    ->label('Processed'),
                SelectFilter::make('entity_name')
                    ->label('Entity')
                    ->options(fn () => \Aicl\Models\GenerationTrace::query()
                        ->distinct()
                        ->pluck('entity_name', 'entity_name')
                        ->toArray()),
                TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->headerActions([
                ExportAction::make()
                    ->exporter(GenerationTraceExporter::class),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    ExportBulkAction::make()
                        ->exporter(GenerationTraceExporter::class),
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
