<?php

namespace Aicl\Filament\Widgets;

use Aicl\Filament\Resources\FailedJobs\FailedJobResource;
use Aicl\Models\FailedJob;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Facades\Artisan;

class RecentFailedJobsWidget extends TableWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Recent Failed Jobs')
            ->query(FailedJob::query()->latest('failed_at')->limit(10))
            ->columns([
                TextColumn::make('job_name')
                    ->label('Job')
                    ->limit(30),
                TextColumn::make('queue')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('exception_summary')
                    ->label('Error')
                    ->limit(50),
                TextColumn::make('failed_at')
                    ->label('Failed')
                    ->since(),
            ])
            ->recordActions([
                Action::make('retry')
                    ->label('Retry')
                    ->icon('heroicon-o-arrow-path')
                    ->color('success')
                    ->action(function (FailedJob $record): void {
                        Artisan::call('queue:retry', ['id' => [$record->uuid]]);

                        Notification::make()
                            ->success()
                            ->title('Job Queued for Retry')
                            ->send();
                    }),
                Action::make('view')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->url(fn (FailedJob $record): string => FailedJobResource::getUrl('view', ['record' => $record])),
            ])
            ->paginated(false)
            ->emptyStateHeading('No failed jobs')
            ->emptyStateDescription('All jobs are processing successfully.')
            ->emptyStateIcon('heroicon-o-check-circle');
    }
}
