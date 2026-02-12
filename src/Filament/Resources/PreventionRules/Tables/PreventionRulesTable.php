<?php

namespace Aicl\Filament\Resources\PreventionRules\Tables;

use Aicl\Filament\Exporters\PreventionRuleExporter;
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

class PreventionRulesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('rule_text')
                    ->label('Rule')
                    ->limit(80)
                    ->searchable()
                    ->weight('bold'),
                TextColumn::make('failure.failure_code')
                    ->label('Failure')
                    ->badge()
                    ->color('danger')
                    ->placeholder('Standalone')
                    ->sortable(),
                TextColumn::make('confidence')
                    ->numeric(2)
                    ->sortable(),
                TextColumn::make('priority')
                    ->numeric()
                    ->sortable(),
                IconColumn::make('is_active')
                    ->boolean(),
                TextColumn::make('applied_count')
                    ->label('Applied')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('last_applied_at')
                    ->dateTime()
                    ->sortable()
                    ->since()
                    ->placeholder('Never'),
                TextColumn::make('owner.name')
                    ->label('Owner')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('rlm_failure_id')
                    ->relationship('failure', 'title')
                    ->label('Failure'),
                TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->headerActions([
                ExportAction::make()
                    ->exporter(PreventionRuleExporter::class),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    ExportBulkAction::make()
                        ->exporter(PreventionRuleExporter::class),
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('priority', 'desc');
    }
}
