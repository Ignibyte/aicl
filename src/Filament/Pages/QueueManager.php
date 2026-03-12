<?php

namespace Aicl\Filament\Pages;

use Aicl\Horizon\Contracts\JobRepository;
use Aicl\Horizon\Contracts\MetricsRepository;
use Aicl\Horizon\Contracts\SupervisorRepository;
use Aicl\Horizon\Contracts\WorkloadRepository;
use Aicl\Models\FailedJob;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\Action as TableRowAction;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use UnitEnum;

class QueueManager extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = null;

    protected static string|UnitEnum|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 6;

    protected static ?string $navigationLabel = 'Queue Manager';

    protected static ?string $title = 'Queue Manager';

    protected static ?string $slug = 'queue-manager';

    protected string $view = 'aicl::filament.pages.queue-manager';

    public string $activeTab = 'overview';

    /**
     * Check if Horizon is enabled and its repositories are available.
     */
    public function isHorizonAvailable(): bool
    {
        return config('aicl.features.horizon', true)
            && app()->bound(JobRepository::class);
    }

    /**
     * Get the configured queue connection driver name.
     */
    public function getQueueDriver(): string
    {
        $connection = config('queue.default', 'sync');

        return config("queue.connections.{$connection}.driver", $connection);
    }

    /**
     * Get available tabs based on current queue configuration.
     *
     * @return array<string, string>
     */
    public function getAvailableTabs(): array
    {
        $tabs = ['overview' => 'Overview'];

        if ($this->isHorizonAvailable()) {
            $tabs['recent'] = 'Recent Jobs';
            $tabs['pending'] = 'Pending';
            $tabs['completed'] = 'Completed';
        }

        $tabs['failed-jobs'] = 'Failed Jobs';
        $tabs['batches'] = 'Batches';

        if ($this->isHorizonAvailable()) {
            $tabs['metrics'] = 'Metrics';
            $tabs['workload'] = 'Workload';
            $tabs['supervisors'] = 'Supervisors';
            $tabs['monitoring'] = 'Monitoring';
        }

        return $tabs;
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return $user->hasRole(['super_admin', 'admin']);
    }

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
                })
                ->visible(fn (): bool => $this->activeTab === 'failed-jobs'),
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
                })
                ->visible(fn (): bool => $this->activeTab === 'failed-jobs'),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(FailedJob::query())
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
                ViewAction::make()
                    ->modalHeading(fn (FailedJob $record): string => "Failed Job: {$record->job_name}")
                    ->infolist([
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
                    ]),
                TableRowAction::make('retry')
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
                TableRowAction::make('delete')
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

    /**
     * Get queue stats for the Overview tab using Horizon repositories when available.
     *
     * @return array{pending: int, pending_high: int, pending_low: int, failed: int, last_failed: ?FailedJob, jobs_per_minute: float, total_processes: int, workload: array}
     */
    public function getQueueStats(): array
    {
        $failedCount = FailedJob::count();
        $lastFailed = FailedJob::latest('failed_at')->first();

        $stats = [
            'pending' => 0,
            'pending_high' => 0,
            'pending_low' => 0,
            'failed' => $failedCount,
            'last_failed' => $lastFailed,
            'jobs_per_minute' => 0.0,
            'total_processes' => 0,
            'workload' => [],
        ];

        if (config('aicl.features.horizon', true) && app()->bound(JobRepository::class)) {
            $stats['pending'] = app(JobRepository::class)->countPending();
            $stats['failed'] = max($failedCount, app(JobRepository::class)->countFailed());

            if (app()->bound(WorkloadRepository::class)) {
                $stats['workload'] = app(WorkloadRepository::class)->get();
                $stats['total_processes'] = collect($stats['workload'])->sum('processes');
            }

            if (app()->bound(MetricsRepository::class)) {
                $snapshots = app(MetricsRepository::class)->snapshotsForQueue('default');
                if (! empty($snapshots)) {
                    $latest = end($snapshots);
                    $stats['jobs_per_minute'] = (float) ($latest->throughput ?? 0);
                }
            }
        } else {
            $stats['pending'] = $this->getQueueSize('default') + $this->getQueueSize('high') + $this->getQueueSize('low');
            $stats['pending_high'] = $this->getQueueSize('high');
            $stats['pending_low'] = $this->getQueueSize('low');
        }

        return $stats;
    }

    /**
     * Get supervisor status for the Supervisors tab.
     */
    public function getSupervisors(): array
    {
        if (! config('aicl.features.horizon', true) || ! app()->bound(SupervisorRepository::class)) {
            return [];
        }

        return app(SupervisorRepository::class)->all();
    }

    protected function getQueueSize(string $queue): int
    {
        try {
            return Queue::size($queue);
        } catch (\Throwable) {
            return 0;
        }
    }
}
