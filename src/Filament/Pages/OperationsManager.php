<?php

namespace Aicl\Filament\Pages;

use Aicl\Horizon\Contracts\JobRepository;
use Aicl\Horizon\Contracts\MetricsRepository;
use Aicl\Horizon\Contracts\SupervisorRepository;
use Aicl\Horizon\Contracts\WorkloadRepository;
use Aicl\Models\FailedJob;
use Aicl\Models\ScheduleHistory;
use Aicl\Notifications\Enums\DeliveryStatus;
use Aicl\Notifications\Models\NotificationChannel;
use Aicl\Notifications\Models\NotificationDeliveryLog;
use Aicl\Services\PresenceRegistry;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\Action as TableRowAction;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
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

class OperationsManager extends Page implements HasActions, HasForms, HasTable
{
    use InteractsWithActions;
    use InteractsWithForms;
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = null;

    protected static string|UnitEnum|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 6;

    protected static ?string $navigationLabel = 'Operations Manager';

    protected static ?string $title = 'Operations Manager';

    protected static ?string $slug = 'operations-manager';

    protected string $view = 'aicl::filament.pages.operations-manager';

    public string $activeSection = 'queues';

    public string $activeTab = 'overview';

    // ── Queue Section ────────────────────────────────────────

    public function isHorizonAvailable(): bool
    {
        return config('aicl.features.horizon', true)
            && app()->bound(JobRepository::class);
    }

    public function getQueueDriver(): string
    {
        $connection = config('queue.default', 'sync');

        return config("queue.connections.{$connection}.driver", $connection);
    }

    /**
     * @return array<string, string>
     */
    public function getQueueTabs(): array
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

    /**
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

    public function getSupervisors(): array
    {
        if (! config('aicl.features.horizon', true) || ! app()->bound(SupervisorRepository::class)) {
            return [];
        }

        return app(SupervisorRepository::class)->all();
    }

    // ── Scheduler Section ────────────────────────────────────

    /**
     * @return array<string, string>
     */
    public function getSchedulerTabs(): array
    {
        return [
            'registered' => 'Registered Tasks',
            'history' => 'Execution History',
            'schedule-failures' => 'Failures',
        ];
    }

    /**
     * Get registered scheduled tasks by parsing artisan schedule:list output.
     *
     * @return array<int, array{command: string, expression: string, description: string|null, next_due: string|null, last_status: string|null, last_run: string|null}>
     */
    public function getRegisteredTasks(): array
    {
        try {
            Artisan::call('schedule:list');
            $output = Artisan::output();
        } catch (\Throwable) {
            return [];
        }

        $tasks = [];
        $lines = array_filter(explode("\n", trim($output)));

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, 'NOTE:')) {
                continue;
            }

            // Parse the schedule:list output format: "expression  command  next_due"
            if (preg_match('/^([\*\/\d,\-\s]{9,20})\s+(.+?)\s{2,}(.+)$/', $line, $matches)) {
                $command = trim($matches[2]);
                $expression = trim($matches[1]);
                $nextDue = trim($matches[3]);

                // Look up last execution from history
                $lastRun = ScheduleHistory::query()
                    ->forCommand($command)
                    ->latest('started_at')
                    ->first();

                $tasks[] = [
                    'command' => $command,
                    'expression' => $expression,
                    'description' => null,
                    'next_due' => $nextDue,
                    'last_status' => $lastRun?->status,
                    'last_run' => $lastRun?->started_at?->diffForHumans(),
                ];
            }
        }

        return $tasks;
    }

    /**
     * @return array{total_registered: int, last_run_at: string|null, failed_24h: int, success_rate_24h: float}
     */
    public function getSchedulerStats(): array
    {
        $lastRun = ScheduleHistory::query()->latest('started_at')->first();
        $recent = ScheduleHistory::query()->recent(24);
        $totalRecent = (clone $recent)->count();
        $failedRecent = (clone $recent)->failed()->count();

        return [
            'total_registered' => count($this->getRegisteredTasks()),
            'last_run_at' => $lastRun?->started_at?->diffForHumans(),
            'failed_24h' => $failedRecent,
            'success_rate_24h' => $totalRecent > 0
                ? round((($totalRecent - $failedRecent) / $totalRecent) * 100, 1)
                : 100.0,
        ];
    }

    // ── Notifications Section ────────────────────────────────

    /**
     * @return array<string, string>
     */
    public function getNotificationTabs(): array
    {
        return [
            'delivery-health' => 'Delivery Health',
            'failed-deliveries' => 'Failed Deliveries',
        ];
    }

    /**
     * Get per-channel delivery health stats for the last 24 hours.
     *
     * @return array<int, array{channel_name: string, channel_type: string, total: int, delivered: int, failed: int, pending: int, success_rate: float}>
     */
    public function getNotificationDeliveryHealth(): array
    {
        $channels = NotificationChannel::query()->active()->get();
        $stats = [];

        foreach ($channels as $channel) {
            $logs = $channel->deliveryLogs()
                ->where('created_at', '>=', now()->subHours(24));

            $total = (clone $logs)->count();
            $delivered = (clone $logs)->where('status', DeliveryStatus::Delivered)->count();
            $failed = (clone $logs)->where('status', DeliveryStatus::Failed)->count();
            $pending = (clone $logs)->where('status', DeliveryStatus::Pending)->count();

            $stats[] = [
                'channel_name' => $channel->name,
                'channel_type' => $channel->type->label(),
                'total' => $total,
                'delivered' => $delivered,
                'failed' => $failed,
                'pending' => $pending,
                'success_rate' => $total > 0 ? round(($delivered / $total) * 100, 1) : 100.0,
            ];
        }

        return $stats;
    }

    public function getNotificationQueueDepth(): int
    {
        return $this->getQueueSize('notifications');
    }

    public function getStuckDeliveries(): int
    {
        return NotificationDeliveryLog::query()
            ->where('status', DeliveryStatus::Pending)
            ->where('created_at', '<', now()->subMinutes(30))
            ->count();
    }

    // ── Sessions Section ──────────────────────────────────────

    /**
     * Get all active admin sessions from the PresenceRegistry.
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    public function getActiveSessions(): \Illuminate\Support\Collection
    {
        return app(PresenceRegistry::class)->allSessions();
    }

    /**
     * Terminate a session by its ID (super_admin only).
     */
    public function terminateSession(string $sessionId): void
    {
        $user = auth()->user();

        if (! $user || ! $user->hasRole('super_admin')) {
            Notification::make()
                ->title('Unauthorized')
                ->body('Only super admins can terminate sessions.')
                ->danger()
                ->send();

            return;
        }

        $currentSessionId = request()->hasSession() ? request()->session()->getId() : null;

        if ($currentSessionId !== null && $sessionId === $currentSessionId) {
            Notification::make()
                ->title('Cannot Terminate')
                ->body('You cannot terminate your own current session.')
                ->warning()
                ->send();

            return;
        }

        $result = app(PresenceRegistry::class)->terminateSession($sessionId);

        if ($result) {
            Notification::make()
                ->title('Session Terminated')
                ->body('The session has been forcefully terminated.')
                ->success()
                ->send();
        } else {
            Notification::make()
                ->title('Session Not Found')
                ->body('The session may have already expired.')
                ->warning()
                ->send();
        }
    }

    // ── Shared ───────────────────────────────────────────────

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
                ->visible(fn (): bool => $this->activeSection === 'queues' && $this->activeTab === 'failed-jobs'),
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
                ->visible(fn (): bool => $this->activeSection === 'queues' && $this->activeTab === 'failed-jobs'),
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

    protected function getQueueSize(string $queue): int
    {
        try {
            return Queue::size($queue);
        } catch (\Throwable) {
            return 0;
        }
    }
}
