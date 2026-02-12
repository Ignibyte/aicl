<?php

namespace Aicl\Filament\Resources\RlmPatterns\Tables;

use Aicl\Filament\Exporters\RlmPatternExporter;
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

class RlmPatternsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('target')
                    ->badge()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('severity')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'error' => 'danger',
                        'warning' => 'warning',
                        'info' => 'info',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('category')
                    ->badge()
                    ->color('gray')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('weight')
                    ->numeric(2)
                    ->sortable(),
                TextColumn::make('source')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_active')
                    ->boolean(),
                TextColumn::make('pass_count')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('fail_count')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('owner.name')
                    ->label('Owner')
                    ->sortable(),
                TextColumn::make('last_evaluated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_active'),
                SelectFilter::make('target')
                    ->options([
                        'model' => 'Model',
                        'factory' => 'Factory',
                        'migration' => 'Migration',
                        'seeder' => 'Seeder',
                        'policy' => 'Policy',
                        'observer' => 'Observer',
                        'filament' => 'Filament Resource',
                        'form' => 'Form',
                        'table' => 'Table',
                        'controller' => 'Controller',
                        'test' => 'Test',
                        'exporter' => 'Exporter',
                    ]),
                SelectFilter::make('severity')
                    ->options([
                        'error' => 'Error',
                        'warning' => 'Warning',
                        'info' => 'Info',
                    ]),
                SelectFilter::make('category')
                    ->options([
                        'structural' => 'Structural',
                        'naming' => 'Naming',
                        'security' => 'Security',
                        'performance' => 'Performance',
                        'convention' => 'Convention',
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->headerActions([
                ExportAction::make()
                    ->exporter(RlmPatternExporter::class),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    ExportBulkAction::make()
                        ->exporter(RlmPatternExporter::class),
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('name', 'asc');
    }
}
