<?php

namespace Aicl\Livewire;

use Aicl\Models\QueuedJob;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class QueuedJobsTable extends TableWidget
{
    protected static bool $isDiscovered = false;

    protected static ?string $heading = '';

    protected string $view = 'aicl::livewire.queued-jobs-table';

    public function table(Table $table): Table
    {
        return $table
            ->query(QueuedJob::query())
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                TextColumn::make('job_name')
                    ->label('Job')
                    ->searchable(query: function ($query, string $search): void {
                        $query->where('payload', 'like', "%{$search}%");
                    })
                    ->limit(40),
                TextColumn::make('queue')
                    ->badge()
                    ->color('gray')
                    ->sortable(),
                TextColumn::make('attempts')
                    ->sortable()
                    ->alignCenter(),
                IconColumn::make('is_reserved')
                    ->label('Reserved')
                    ->boolean()
                    ->trueIcon('heroicon-o-lock-closed')
                    ->falseIcon('heroicon-o-lock-open')
                    ->trueColor('warning')
                    ->falseColor('gray'),
                TextColumn::make('available_at_date')
                    ->label('Available At')
                    ->dateTime()
                    ->sortable(query: function ($query, string $direction): void {
                        $query->orderBy('available_at', $direction);
                    }),
                TextColumn::make('created_at_date')
                    ->label('Created')
                    ->dateTime()
                    ->sortable(query: function ($query, string $direction): void {
                        $query->orderBy('created_at', $direction);
                    }),
            ])
            ->defaultSort('id', 'asc')
            ->filters([
                SelectFilter::make('queue')
                    ->options(fn () => QueuedJob::query()
                        ->distinct()
                        ->pluck('queue', 'queue')
                        ->toArray()),
            ])
            ->emptyStateHeading('No queued jobs')
            ->emptyStateDescription(
                config('queue.default') !== 'database'
                    ? 'Your queue driver is "'.config('queue.default').'". Queued jobs are only visible here when using the database driver.'
                    : 'No jobs are currently waiting in the queue.'
            )
            ->emptyStateIcon('heroicon-o-queue-list')
            ->paginated([10, 25, 50, 100]);
    }
}
