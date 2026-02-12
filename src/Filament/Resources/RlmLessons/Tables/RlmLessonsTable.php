<?php

namespace Aicl\Filament\Resources\RlmLessons\Tables;

use Aicl\Filament\Exporters\RlmLessonExporter;
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

class RlmLessonsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('topic')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->badge(),
                TextColumn::make('summary')
                    ->searchable()
                    ->sortable()
                    ->limit(60),
                TextColumn::make('tags')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('source')
                    ->sortable()
                    ->badge()
                    ->color('gray'),
                TextColumn::make('confidence')
                    ->numeric(2)
                    ->sortable(),
                IconColumn::make('is_verified')
                    ->boolean()
                    ->label('Verified'),
                TextColumn::make('view_count')
                    ->numeric()
                    ->sortable()
                    ->label('Views'),
                TextColumn::make('owner.name')
                    ->label('Owner')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('topic')
                    ->options(fn () => \Aicl\Models\RlmLesson::query()
                        ->distinct()
                        ->pluck('topic', 'topic')
                        ->toArray()),
                TernaryFilter::make('is_verified')
                    ->label('Verified'),
                TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->headerActions([
                ExportAction::make()
                    ->exporter(RlmLessonExporter::class),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    ExportBulkAction::make()
                        ->exporter(RlmLessonExporter::class),
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
