<?php

namespace Aicl\Filament\Resources\FailedJobs\Pages;

use Aicl\Filament\Resources\FailedJobs\FailedJobResource;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Artisan;

class ListFailedJobs extends ListRecords
{
    protected static string $resource = FailedJobResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('retry_all')
                ->label('Retry All')
                ->icon('heroicon-o-arrow-path')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Retry All Failed Jobs')
                ->modalDescription('Are you sure you want to retry all failed jobs? This will queue all failed jobs for processing.')
                ->action(function (): void {
                    Artisan::call('queue:retry', ['id' => ['all']]);

                    Notification::make()
                        ->success()
                        ->title('All Jobs Queued for Retry')
                        ->body('All failed jobs have been queued for retry.')
                        ->send();
                }),
            Action::make('flush')
                ->label('Flush All')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Flush All Failed Jobs')
                ->modalDescription('Are you sure you want to delete all failed jobs? This action cannot be undone.')
                ->action(function (): void {
                    Artisan::call('queue:flush');

                    Notification::make()
                        ->success()
                        ->title('All Failed Jobs Deleted')
                        ->body('All failed jobs have been permanently deleted.')
                        ->send();
                }),
        ];
    }
}
