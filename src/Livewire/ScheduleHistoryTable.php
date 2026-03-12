<?php

namespace Aicl\Livewire;

use Aicl\Models\ScheduleHistory;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class ScheduleHistoryTable extends TableWidget
{
    protected static bool $isDiscovered = false;

    protected string $view = 'aicl::livewire.schedule-history-table';

    public bool $failedOnly = false;

    public function table(Table $table): Table
    {
        $query = ScheduleHistory::query();

        if ($this->failedOnly) {
            $query->failed();
        }

        return $table
            ->query($query)
            ->columns([
                TextColumn::make('command')
                    ->searchable()
                    ->limit(40),
                TextColumn::make('expression')
                    ->label('Schedule'),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'success' => 'success',
                        'failed' => 'danger',
                        'running' => 'info',
                        'skipped' => 'gray',
                        default => 'gray',
                    }),
                TextColumn::make('duration_ms')
                    ->label('Duration')
                    ->formatStateUsing(function (?int $state): string {
                        if ($state === null) {
                            return '—';
                        }

                        if ($state >= 1000) {
                            return number_format($state / 1000, 1).'s';
                        }

                        return "{$state}ms";
                    }),
                TextColumn::make('started_at')
                    ->label('Started')
                    ->since()
                    ->sortable()
                    ->tooltip(fn (ScheduleHistory $record): string => $record->started_at->format('Y-m-d H:i:s')),
            ])
            ->defaultSort('started_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'success' => 'Success',
                        'failed' => 'Failed',
                        'running' => 'Running',
                        'skipped' => 'Skipped',
                    ]),
                SelectFilter::make('command')
                    ->options(fn (): array => ScheduleHistory::query()
                        ->distinct()
                        ->pluck('command', 'command')
                        ->toArray())
                    ->searchable(),
            ])
            ->recordActions([
                ViewAction::make()
                    ->modalHeading(fn (ScheduleHistory $record): string => "Task: {$record->command}")
                    ->infolist([
                        Section::make('Execution Details')
                            ->schema([
                                TextEntry::make('command'),
                                TextEntry::make('description')
                                    ->placeholder('No description'),
                                TextEntry::make('expression')
                                    ->label('Schedule'),
                                TextEntry::make('status')
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'success' => 'success',
                                        'failed' => 'danger',
                                        'running' => 'info',
                                        default => 'gray',
                                    }),
                                TextEntry::make('exit_code')
                                    ->label('Exit Code')
                                    ->placeholder('N/A'),
                                TextEntry::make('duration_ms')
                                    ->label('Duration')
                                    ->formatStateUsing(function (?int $state): string {
                                        if ($state === null) {
                                            return 'N/A';
                                        }

                                        if ($state >= 1000) {
                                            return number_format($state / 1000, 2).'s';
                                        }

                                        return "{$state}ms";
                                    }),
                                TextEntry::make('started_at')
                                    ->label('Started')
                                    ->dateTime(),
                                TextEntry::make('finished_at')
                                    ->label('Finished')
                                    ->dateTime()
                                    ->placeholder('Still running'),
                            ])
                            ->columns(3),
                        Section::make('Output')
                            ->schema([
                                TextEntry::make('output')
                                    ->label('')
                                    ->columnSpanFull()
                                    ->placeholder('No output captured.'),
                            ])
                            ->collapsible()
                            ->collapsed(),
                    ]),
            ])
            ->emptyStateHeading($this->failedOnly ? 'No failed tasks' : 'No schedule history')
            ->emptyStateDescription($this->failedOnly
                ? 'All scheduled tasks have completed successfully.'
                : 'Schedule history will appear here as tasks are executed.')
            ->emptyStateIcon($this->failedOnly ? 'heroicon-o-check-circle' : 'heroicon-o-calendar')
            ->paginated([10, 25, 50, 100]);
    }
}
