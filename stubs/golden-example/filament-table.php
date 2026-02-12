<?php

// PATTERN: Table schema in its own class for separation of concerns.
// PATTERN: Includes columns, filters, record actions, header actions, and toolbar actions.
// PATTERN: Uses PdfAction for single-record PDF download.
// PATTERN: Uses ExportAction with Exporter for CSV export.

namespace Aicl\Filament\Resources\Projects\Tables;

use Aicl\Enums\ProjectPriority;
use Aicl\Filament\Actions\PdfAction;
use Aicl\Filament\Exporters\ProjectExporter;
use Aicl\States\Active;
use Aicl\States\Archived;
use Aicl\States\Completed;
use Aicl\States\Draft;
use Aicl\States\OnHold;
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

class ProjectsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                // PATTERN: State columns use formatStateUsing + color for badges.
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state->label())
                    ->color(fn ($state): string => $state->color())
                    ->sortable(),
                // PATTERN: Enum columns cast automatically; display via label().
                TextColumn::make('priority')
                    ->badge()
                    ->formatStateUsing(fn (ProjectPriority $state): string => $state->label())
                    ->color(fn (ProjectPriority $state): string => $state->color())
                    ->sortable(),
                // PATTERN: Relationship columns use dot notation.
                TextColumn::make('owner.name')
                    ->label('Owner')
                    ->sortable(),
                TextColumn::make('start_date')
                    ->date()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('end_date')
                    ->date()
                    ->sortable()
                    ->toggleable(),
                // PATTERN: Money columns use money() format.
                TextColumn::make('budget')
                    ->money('USD')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_active')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                // PATTERN: Aggregate columns use ->counts() or ->sum().
                TextColumn::make('members_count')
                    ->counts('members')
                    ->label('Members')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                // PATTERN: State filters use getMorphClass() as values.
                SelectFilter::make('status')
                    ->options([
                        Draft::getMorphClass() => 'Draft',
                        Active::getMorphClass() => 'Active',
                        OnHold::getMorphClass() => 'On Hold',
                        Completed::getMorphClass() => 'Completed',
                        Archived::getMorphClass() => 'Archived',
                    ]),
                // PATTERN: Enum filters use mapWithKeys.
                SelectFilter::make('priority')
                    ->options(
                        collect(ProjectPriority::cases())
                            ->mapWithKeys(fn (ProjectPriority $p): array => [$p->value => $p->label()])
                            ->all()
                    ),
                // PATTERN: Relationship filters with searchable + preload.
                SelectFilter::make('owner')
                    ->relationship('owner', 'name')
                    ->searchable()
                    ->preload(),
                TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            // PATTERN: Record actions = per-row actions.
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                // PATTERN: PdfAction uses pdfView() and pdfData() (not view()/data()).
                PdfAction::make()
                    ->pdfView('aicl::pdf.project-report')
                    ->pdfData(fn ($record) => [
                        'project' => $record->load(['owner', 'tags']),
                        'title' => $record->name.' Report',
                        'activities' => $record->activities()->latest()->take(10)->get(),
                    ]),
            ])
            // PATTERN: Header actions = top-of-table actions.
            ->headerActions([
                ExportAction::make()
                    ->exporter(ProjectExporter::class),
            ])
            // PATTERN: Toolbar actions = bulk actions.
            ->toolbarActions([
                BulkActionGroup::make([
                    ExportBulkAction::make()
                        ->exporter(ProjectExporter::class),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
