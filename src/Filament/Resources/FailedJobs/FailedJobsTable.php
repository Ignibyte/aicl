<?php

namespace Aicl\Filament\Resources\FailedJobs;

use Aicl\Models\FailedJob;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Artisan;

class FailedJobsTable
{
    public static function configure(Table $table): Table
    {
        return $table
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
                TextColumn::make('connection')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('exception_summary')
                    ->label('Exception')
                    ->limit(60)
                    ->tooltip(fn (FailedJob $record): string => $record->exception_summary)
                    ->wrap(),
                TextColumn::make('failed_at')
                    ->label('Failed At')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('failed_at', 'desc')
            ->filters([
                SelectFilter::make('queue')
                    ->options(fn () => FailedJob::query()
                        ->distinct()
                        ->pluck('queue', 'queue')
                        ->toArray()),
                SelectFilter::make('connection')
                    ->options(fn () => FailedJob::query()
                        ->distinct()
                        ->pluck('connection', 'connection')
                        ->toArray()),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('retry')
                    ->label('Retry')
                    ->icon('heroicon-o-arrow-path')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (FailedJob $record): void {
                        Artisan::call('queue:retry', ['id' => [$record->uuid]]);

                        Notification::make()
                            ->success()
                            ->title('Job Queued for Retry')
                            ->body("Job {$record->uuid} has been queued for retry.")
                            ->send();
                    }),
                Action::make('delete')
                    ->label('Delete')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (FailedJob $record): void {
                        Artisan::call('queue:forget', ['id' => $record->uuid]);

                        Notification::make()
                            ->success()
                            ->title('Job Deleted')
                            ->body("Failed job {$record->uuid} has been deleted.")
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('retry')
                        ->label('Retry Selected')
                        ->icon('heroicon-o-arrow-path')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            $uuids = $records->pluck('uuid')->toArray();
                            Artisan::call('queue:retry', ['id' => $uuids]);

                            Notification::make()
                                ->success()
                                ->title('Jobs Queued for Retry')
                                ->body(count($uuids).' jobs have been queued for retry.')
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make()
                        ->action(function (Collection $records): void {
                            foreach ($records as $record) {
                                Artisan::call('queue:forget', ['id' => $record->uuid]);
                            }

                            Notification::make()
                                ->success()
                                ->title('Jobs Deleted')
                                ->body($records->count().' failed jobs have been deleted.')
                                ->send();
                        }),
                ]),
            ])
            ->emptyStateHeading('No failed jobs')
            ->emptyStateDescription('All jobs have completed successfully.')
            ->emptyStateIcon('heroicon-o-check-circle');
    }
}
