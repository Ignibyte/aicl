<?php

namespace Aicl\Filament\Resources\RlmFailures\Tables;

use Aicl\Enums\FailureCategory;
use Aicl\Enums\FailureSeverity;
use Aicl\Filament\Exporters\RlmFailureExporter;
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

class RlmFailuresTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('failure_code')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->limit(40),
                TextColumn::make('category')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state instanceof FailureCategory ? $state->label() : $state)
                    ->color(fn ($state) => $state instanceof FailureCategory ? $state->color() : 'gray')
                    ->sortable(),
                TextColumn::make('severity')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state instanceof FailureSeverity ? $state->label() : $state)
                    ->color(fn ($state) => match (true) {
                        $state instanceof FailureSeverity => $state->color(),
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state->label())
                    ->color(fn ($state) => $state->color()),
                TextColumn::make('report_count')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('resolution_rate')
                    ->formatStateUsing(fn ($state) => $state !== null ? number_format((float) $state * 100, 1).'%' : '—')
                    ->sortable(),
                IconColumn::make('promoted_to_base')
                    ->boolean()
                    ->label('Promoted'),
                IconColumn::make('scaffolding_fixed')
                    ->boolean()
                    ->label('Fixed')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('owner.name')
                    ->label('Owner')
                    ->sortable(),
                TextColumn::make('last_seen_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('category')
                    ->options(collect(FailureCategory::cases())->mapWithKeys(
                        fn (FailureCategory $case) => [$case->value => $case->label()]
                    )),
                SelectFilter::make('severity')
                    ->options(collect(FailureSeverity::cases())->mapWithKeys(
                        fn (FailureSeverity $case) => [$case->value => $case->label()]
                    )),
                SelectFilter::make('status')
                    ->options([
                        'reported' => 'Reported',
                        'confirmed' => 'Confirmed',
                        'investigating' => 'Investigating',
                        'resolved' => 'Resolved',
                        'wont_fix' => 'Won\'t Fix',
                        'deprecated' => 'Deprecated',
                    ]),
                TernaryFilter::make('promoted_to_base')
                    ->label('Promoted'),
                TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->headerActions([
                ExportAction::make()
                    ->exporter(RlmFailureExporter::class),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    ExportBulkAction::make()
                        ->exporter(RlmFailureExporter::class),
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('failure_code', 'asc');
    }
}
