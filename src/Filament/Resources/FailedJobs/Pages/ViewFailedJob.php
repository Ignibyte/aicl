<?php

namespace Aicl\Filament\Resources\FailedJobs\Pages;

use Aicl\Filament\Resources\FailedJobs\FailedJobResource;
use Aicl\Models\FailedJob;
use Filament\Actions\Action;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Artisan;

class ViewFailedJob extends ViewRecord
{
    protected static string $resource = FailedJobResource::class;

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Job Details')
                    ->schema([
                        TextEntry::make('id')
                            ->label('ID'),
                        TextEntry::make('uuid')
                            ->label('UUID')
                            ->copyable(),
                        TextEntry::make('job_name')
                            ->label('Job Name'),
                        TextEntry::make('queue')
                            ->badge()
                            ->color('gray'),
                        TextEntry::make('connection'),
                        TextEntry::make('failed_at')
                            ->label('Failed At')
                            ->dateTime(),
                    ])
                    ->columns(3),
                Section::make('Exception')
                    ->schema([
                        TextEntry::make('exception')
                            ->label('')
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),
                Section::make('Payload')
                    ->schema([
                        TextEntry::make('payload')
                            ->label('')
                            ->columnSpanFull()
                            ->formatStateUsing(function ($state): string {
                                if (is_array($state)) {
                                    return json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                                }

                                $decoded = json_decode($state, true);

                                return json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                            }),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('retry')
                ->label('Retry Job')
                ->icon('heroicon-o-arrow-path')
                ->color('success')
                ->requiresConfirmation()
                ->action(function (): void {
                    /** @var FailedJob $record */
                    $record = $this->getRecord();

                    Artisan::call('queue:retry', ['id' => [$record->uuid]]);

                    Notification::make()
                        ->success()
                        ->title('Job Queued for Retry')
                        ->body("Job {$record->uuid} has been queued for retry.")
                        ->send();

                    $this->redirect(FailedJobResource::getUrl('index'));
                }),
            Action::make('delete')
                ->label('Delete Job')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->action(function (): void {
                    /** @var FailedJob $record */
                    $record = $this->getRecord();

                    Artisan::call('queue:forget', ['id' => $record->uuid]);

                    Notification::make()
                        ->success()
                        ->title('Job Deleted')
                        ->body("Failed job {$record->uuid} has been deleted.")
                        ->send();

                    $this->redirect(FailedJobResource::getUrl('index'));
                }),
        ];
    }
}
