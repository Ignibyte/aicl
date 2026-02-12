<?php

namespace Aicl\Filament\Resources\FailureReports\Tables;

use Aicl\Filament\Exporters\FailureReportExporter;
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

class FailureReportsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('failure.failure_code')
                    ->label('Failure Code')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('entity_name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('project_hash')
                    ->limit(12)
                    ->tooltip(fn ($record): string => $record->project_hash)
                    ->sortable(),
                TextColumn::make('phase')
                    ->sortable()
                    ->badge(),
                TextColumn::make('agent')
                    ->sortable()
                    ->badge()
                    ->color('gray'),
                IconColumn::make('resolved')
                    ->boolean(),
                TextColumn::make('resolution_method')
                    ->badge()
                    ->formatStateUsing(fn ($state): ?string => $state?->label())
                    ->color(fn ($state): ?string => $state?->color())
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('reported_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('owner.name')
                    ->label('Owner')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('rlm_failure_id')
                    ->relationship('failure', 'title')
                    ->label('Failure'),
                TernaryFilter::make('resolved'),
                TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->headerActions([
                ExportAction::make()
                    ->exporter(FailureReportExporter::class),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    ExportBulkAction::make()
                        ->exporter(FailureReportExporter::class),
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('reported_at', 'desc');
    }
}
