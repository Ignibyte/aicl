<?php

declare(strict_types=1);

namespace Aicl\Livewire;

use Aicl\Notifications\Enums\DeliveryStatus;
use Aicl\Notifications\Models\NotificationDeliveryLog;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/** Livewire table widget displaying failed notification deliveries with retry actions. */
class FailedDeliveriesTable extends TableWidget
{
    protected static bool $isDiscovered = false;

    protected string $view = 'aicl::livewire.failed-deliveries-table';

    /** @codeCoverageIgnore Reason: filament-closure -- Filament table format closures require Livewire rendering */
    public function table(Table $table): Table
    {
        return $table
            ->query(NotificationDeliveryLog::query()->failed())
            ->columns([
                TextColumn::make('notificationLog.type')
                    ->label('Notification')
                    ->formatStateUsing(function (?string $state): string {
                        // @codeCoverageIgnoreStart — Filament Livewire rendering
                        if (! $state) {
                            return 'Unknown';
                        }

                        return str(class_basename($state))
                            ->replaceLast('Notification', '')
                            ->headline()
                            ->toString();
                        // @codeCoverageIgnoreEnd
                    })
                    ->limit(30),
                TextColumn::make('channel.name')
                    ->label('Channel')
                    ->placeholder('Unknown'),
                TextColumn::make('channel.type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state instanceof \BackedEnum ? (string) $state->value : (string) $state)
                    ->color('gray'),
                TextColumn::make('error_message')
                    ->label('Error')
                    ->limit(50)
                    ->tooltip(fn (NotificationDeliveryLog $record): ?string => $record->error_message)
                    ->wrap(),
                TextColumn::make('attempt_count')
                    ->label('Attempts')
                    ->alignCenter(),
                TextColumn::make('failed_at')
                    ->label('Failed')
                    ->since()
                    ->sortable()
                    ->tooltip(fn (NotificationDeliveryLog $record): ?string => $record->failed_at?->format('Y-m-d H:i:s')),
                TextColumn::make('next_retry_at')
                    ->label('Next Retry')
                    ->since()
                    ->placeholder('No retry')
                    ->sortable(),
            ])
            ->defaultSort('failed_at', 'desc')
            ->recordActions([
                Action::make('retry')
                    ->label('Retry')
                    ->icon('heroicon-o-arrow-path')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (NotificationDeliveryLog $record): void {
                        // @codeCoverageIgnoreStart — Filament Livewire rendering
                        $record->update([
                            'status' => DeliveryStatus::Pending,
                            'failed_at' => null,
                            'next_retry_at' => null,
                        ]);

                        Notification::make()
                            ->success()
                            ->title('Delivery Queued for Retry')
                            ->body('The notification delivery has been reset to pending.')
                            ->send();
                        // @codeCoverageIgnoreEnd
                    }),
            ])
            ->emptyStateHeading('No failed deliveries')
            ->emptyStateDescription('All notification deliveries have succeeded.')
            ->emptyStateIcon('heroicon-o-check-circle')
            ->paginated([10, 25, 50, 100]);
    }
}
